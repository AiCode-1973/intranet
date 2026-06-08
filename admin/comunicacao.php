<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Buscar Usuários para o seletor individual
$usuarios = $conn->query("SELECT id, nome, email, setor_id FROM usuarios WHERE ativo = 1 ORDER BY nome ASC");
$usuarios_arr = [];
while($u = $usuarios->fetch_assoc()) $usuarios_arr[] = $u;

// Buscar Logs recentes
$logs = $conn->query("SELECT l.*, u.nome as usuario_nome 
                      FROM email_logs l 
                      LEFT JOIN usuarios u ON l.usuario_id = u.id 
                      ORDER BY l.data_envio DESC LIMIT 10");

// ── INICIAR ENVIO EM LOTE (AJAX) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'iniciar_lote') {
    header('Content-Type: application/json; charset=utf-8');

    $assunto = sanitize($_POST['assunto']);
    $corpo   = $_POST['mensagem'];

    // Processar anexos (mesma lógica do enviar normal)
    if (!empty($_FILES['anexos']['name'][0])) {
        $dir_uploads = '../uploads/comunicacao/';
        if (!is_dir($dir_uploads)) mkdir($dir_uploads, 0755, true);
        $ext_permitidas = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','txt','zip','rar'];
        $arquivos_salvos = [];
        foreach ($_FILES['anexos']['name'] as $k => $nome_orig) {
            if ($_FILES['anexos']['error'][$k] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['anexos']['size'][$k] > 10 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($nome_orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_permitidas)) continue;
            $nome_seguro = uniqid('com_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($nome_orig));
            if (move_uploaded_file($_FILES['anexos']['tmp_name'][$k], $dir_uploads . $nome_seguro)) {
                $arquivos_salvos[] = ['nome' => $nome_orig, 'path' => $nome_seguro, 'size' => $_FILES['anexos']['size'][$k]];
            }
        }
        if (!empty($arquivos_salvos)) {
            $corpo .= '<div style="margin-top:20px;padding:14px 16px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;">';
            $corpo .= '<p style="margin:0 0 8px;font-weight:700;font-size:11px;color:#0d9488;text-transform:uppercase;letter-spacing:.05em;">&#128206; Anexos</p>';
            $corpo .= '<ul style="margin:0;padding:0;list-style:none;">';
            foreach ($arquivos_salvos as $arq) {
                $kb = round($arq['size'] / 1024, 1);
                $corpo .= '<li style="margin-bottom:5px;">'
                    . '<a href="uploads/comunicacao/' . htmlspecialchars($arq['path']) . '" style="color:#0d9488;font-weight:600;font-size:12px;">'
                    . htmlspecialchars($arq['nome']) . '</a>'
                    . '<span style="color:#9ca3af;font-size:10px;margin-left:5px;">(' . $kb . ' KB)</span></li>';
            }
            $corpo .= '</ul></div>';
        }
    }

    $res_config = $conn->query("SELECT * FROM email_config LIMIT 1");
    $config = $res_config->fetch_assoc();
    if (!$config) {
        echo json_encode(['ok' => false, 'erro' => 'Configure o servidor SMTP antes de enviar!']);
        exit;
    }

    $job_key = 'ejob_' . bin2hex(random_bytes(8));
    $_SESSION[$job_key] = [
        'assunto'  => $assunto,
        'corpo'    => $corpo,
        'lista'    => $usuarios_arr,
        'enviados' => 0,
        'falhas'   => 0,
    ];

    echo json_encode(['ok' => true, 'job_key' => $job_key, 'total' => count($usuarios_arr)]);
    exit;
}

// ── PROCESSAR LOTE (AJAX) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'processar_lote') {
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(120);
    ignore_user_abort(true);

    $job_key = isset($_POST['job_key']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['job_key']) : '';
    $offset  = intval($_POST['offset'] ?? 0);
    $tam_lote = 5;

    if (empty($job_key) || !isset($_SESSION[$job_key])) {
        echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página e tente novamente.']);
        exit;
    }

    $job   = $_SESSION[$job_key];
    $lista = $job['lista'];
    $total = count($lista);
    $lote  = array_slice($lista, $offset, $tam_lote);

    $res_config = $conn->query("SELECT * FROM email_config LIMIT 1");
    $config = $res_config->fetch_assoc();

    $enviados_lote = 0;
    $falhas_lote   = 0;

    foreach ($lote as $dest) {
        if (enviarEmail($dest['email'], $job['assunto'], $job['corpo'], $config)) {
            $enviados_lote++;
            $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status) VALUES (?, ?, ?, ?, 'Sucesso')");
            $stmt->bind_param("isss", $dest['id'], $dest['email'], $job['assunto'], $job['corpo']);
            $stmt->execute();
            $stmt->close();
        } else {
            $falhas_lote++;
            $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status, erro_mensagem) VALUES (?, ?, ?, ?, 'Falha', 'Erro no servidor de envio')");
            $stmt->bind_param("isss", $dest['id'], $dest['email'], $job['assunto'], $job['corpo']);
            $stmt->execute();
            $stmt->close();
        }
    }

    $_SESSION[$job_key]['enviados'] += $enviados_lote;
    $_SESSION[$job_key]['falhas']   += $falhas_lote;
    $total_enviados = $_SESSION[$job_key]['enviados'];
    $total_falhas   = $_SESSION[$job_key]['falhas'];

    $novo_offset = $offset + count($lote);
    $concluido   = ($novo_offset >= $total);

    if ($concluido) {
        registrarLog($conn, "Enviou e-mail em lote: \"{$job['assunto']}\" ({$total_enviados} enviados, {$total_falhas} falhas)");
        unset($_SESSION[$job_key]);
    }

    echo json_encode([
        'ok'             => true,
        'enviados_lote'  => $enviados_lote,
        'falhas_lote'    => $falhas_lote,
        'total_enviados' => $total_enviados,
        'total_falhas'   => $total_falhas,
        'offset'         => $novo_offset,
        'total'          => $total,
        'concluido'      => $concluido,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'enviar') {
    $assunto = sanitize($_POST['assunto']);
    $corpo = $_POST['mensagem']; // Permitir HTML
    $tipo_destinatario = $_POST['tipo_destinatario']; // 'todos' ou 'individual'

    // Processar anexos
    if (!empty($_FILES['anexos']['name'][0])) {
        $dir_uploads = '../uploads/comunicacao/';
        if (!is_dir($dir_uploads)) mkdir($dir_uploads, 0755, true);
        $ext_permitidas = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','txt','zip','rar'];
        $arquivos_salvos = [];
        foreach ($_FILES['anexos']['name'] as $k => $nome_orig) {
            if ($_FILES['anexos']['error'][$k] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['anexos']['size'][$k] > 10 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($nome_orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_permitidas)) continue;
            $nome_seguro = uniqid('com_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($nome_orig));
            if (move_uploaded_file($_FILES['anexos']['tmp_name'][$k], $dir_uploads . $nome_seguro)) {
                $arquivos_salvos[] = ['nome' => $nome_orig, 'path' => $nome_seguro, 'size' => $_FILES['anexos']['size'][$k]];
            }
        }
        if (!empty($arquivos_salvos)) {
            $corpo .= '<div style="margin-top:20px;padding:14px 16px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;">';
            $corpo .= '<p style="margin:0 0 8px;font-weight:700;font-size:11px;color:#0d9488;text-transform:uppercase;letter-spacing:.05em;">&#128206; Anexos</p>';
            $corpo .= '<ul style="margin:0;padding:0;list-style:none;">';
            foreach ($arquivos_salvos as $arq) {
                $kb = round($arq['size'] / 1024, 1);
                $corpo .= '<li style="margin-bottom:5px;">'
                    . '<a href="uploads/comunicacao/' . htmlspecialchars($arq['path']) . '" '
                    . 'style="color:#0d9488;font-weight:600;font-size:12px;">'
                    . htmlspecialchars($arq['nome']) . '</a>'
                    . '<span style="color:#9ca3af;font-size:10px;margin-left:5px;">(' . $kb . ' KB)</span>'
                    . '</li>';
            }
            $corpo .= '</ul></div>';
        }
    }

    // Obter config de e-mail
    $res_config = $conn->query("SELECT * FROM email_config LIMIT 1");
    $config = $res_config->fetch_assoc();
    
    if (!$config) {
        $mensagem = "Configure o servidor SMTP antes de enviar!";
        $tipo_mensagem = "danger";
    } else {
        $enviados = 0;
        $falhas = 0;
        
        $lista_envio = [];
        if ($tipo_destinatario == 'todos') {
            $lista_envio = $usuarios_arr;
        } else {
            $user_id = intval($_POST['usuario_id']);
            foreach($usuarios_arr as $u) {
                if ($u['id'] == $user_id) {
                    $lista_envio[] = $u;
                    break;
                }
            }
        }
        
        foreach ($lista_envio as $dest) {
            // Aqui chamamos a função de envio (que implementaremos em functions.php)
            // Por enquanto, simulamos e logamos no banco
            if (enviarEmail($dest['email'], $assunto, $corpo, $config)) {
                $enviados++;
                $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status) VALUES (?, ?, ?, ?, 'Sucesso')");
                $stmt->bind_param("isss", $dest['id'], $dest['email'], $assunto, $corpo);
                $stmt->execute();
            } else {
                $falhas++;
                $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status, erro_mensagem) VALUES (?, ?, ?, ?, 'Falha', 'Erro no servidor de envio')");
                $stmt->bind_param("isss", $dest['id'], $dest['email'], $assunto, $corpo);
                $stmt->execute();
            }
        }
        
        $mensagem = "Log de Envio: $enviados sucesso(s), $falhas falha(s).";
        $tipo_mensagem = $falhas == 0 ? "success" : "warning";
        registrarLog($conn, "Enviou e-mail ($tipo_destinatario): $assunto");
    }
}

// Excluir log individual
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_log') {
    $log_id = intval($_POST['log_id']);
    $stmt = $conn->prepare("DELETE FROM email_logs WHERE id = ?");
    $stmt->bind_param("i", $log_id);
    if ($stmt->execute()) {
        $mensagem = "Registro de envio excluído.";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu log de e-mail ID: $log_id");
    } else {
        $mensagem = "Erro ao excluir: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
    
    // Recarregar logs após exclusão
    $logs = $conn->query("SELECT l.*, u.nome as usuario_nome 
                          FROM email_logs l 
                          LEFT JOIN usuarios u ON l.usuario_id = u.id 
                          ORDER BY l.data_envio DESC LIMIT 10");
}

// Excluir todos os logs
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'limpar_historico') {
    if ($conn->query("DELETE FROM email_logs")) {
        $mensagem = "Histórico de envios limpo com sucesso.";
        $tipo_mensagem = "success";
        registrarLog($conn, "Limpou todo o histórico de e-mails");
    } else {
        $mensagem = "Erro ao limpar histórico: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    
    // Recarregar logs (vazio)
    $logs = $conn->query("SELECT l.*, u.nome as usuario_nome 
                          FROM email_logs l 
                          LEFT JOIN usuarios u ON l.usuario_id = u.id 
                          ORDER BY l.data_envio DESC LIMIT 10");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicação por E-mail - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-primary flex items-center gap-3">
                    <i data-lucide="mail" class="w-8 h-8"></i>
                    Central de Comunicação
                </h1>
                <p class="text-text-secondary text-sm mt-1">Envie comunicados e avisos diretamente para o e-mail dos colaboradores.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="config_email.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-primary rounded-xl text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm border-b-2 active:translate-y-0.5">
                    <i data-lucide="settings" class="w-4 h-4"></i> Configurar SMTP
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-xl border mb-6 flex items-center gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : ($tipo_mensagem == 'warning' ? 'bg-amber-50 border-amber-100 text-amber-700' : 'bg-red-50 border-red-100 text-red-700'); ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                <span class="text-sm font-bold tracking-tight"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Composer -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-3xl shadow-xl border border-border overflow-hidden">
                    <div class="bg-primary px-6 py-4 flex items-center gap-3 text-white">
                        <i data-lucide="pen-tool" class="w-5 h-5"></i>
                        <h2 class="text-sm font-black uppercase tracking-widest">Nova Mensagem</h2>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
                        <input type="hidden" name="acao" value="enviar">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Destinatário</label>
                                <select name="tipo_destinatario" id="tipo_destinatario" onchange="toggleDestinatario()" class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                                    <option value="todos">Todos os Usuários Ativos</option>
                                    <option value="individual">Usuário Individual</option>
                                </select>
                            </div>

                            <div id="div_individual" class="hidden">
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Selecionar Usuário</label>
                                <select name="usuario_id" class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                                    <?php foreach($usuarios_arr as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo $u['nome']; ?> (<?php echo $u['email']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Assunto do E-mail</label>
                            <input type="text" name="assunto" required placeholder="Ex: Informativo - Reunião Geral"
                                   class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Conteúdo da Mensagem</label>
                            <textarea name="mensagem" rows="10" required placeholder="Digite sua mensagem aqui... Você pode usar tags HTML básicas."
                                      class="w-full p-4 bg-background border border-border rounded-2xl text-sm font-medium focus:outline-none focus:border-primary transition-all leading-relaxed"></textarea>
                        </div>

                        <!-- Anexos -->
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">
                                Anexos
                                <span class="font-normal normal-case opacity-60 text-[9px]">(PDF, DOC, XLS, JPG, PNG, ZIP &mdash; m&aacute;x. 10MB cada)</span>
                            </label>
                            <div id="dropZone"
                                 onclick="document.getElementById('anexosInput').click()"
                                 ondragover="event.preventDefault();this.classList.add('!border-primary','bg-primary/[0.02]')"
                                 ondragleave="this.classList.remove('!border-primary','bg-primary/[0.02]')"
                                 ondrop="event.preventDefault();this.classList.remove('!border-primary','bg-primary/[0.02]');anexosInputDrop(event)"
                                 class="border-2 border-dashed border-border hover:border-primary rounded-2xl p-6 text-center cursor-pointer transition-all group hover:bg-primary/[0.02]">
                                <i data-lucide="paperclip" class="w-6 h-6 text-text-secondary/40 group-hover:text-primary mx-auto mb-2 transition-colors"></i>
                                <p class="text-[11px] font-bold text-text-secondary group-hover:text-primary transition-colors">Clique para selecionar ou arraste arquivos aqui</p>
                                <p class="text-[9px] text-text-secondary/50 mt-0.5">PDF, DOC, XLS, JPG, PNG, ZIP &mdash; at&eacute; 10MB cada</p>
                                <input type="file" id="anexosInput" name="anexos[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt,.zip,.rar"
                                       class="hidden" onchange="mostrarAnexos(this.files)">
                            </div>
                            <div id="listaAnexos" class="mt-3 space-y-2 hidden"></div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-10 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 active:scale-95 transition-all flex items-center gap-3">
                                <i data-lucide="send" class="w-4 h-4"></i> Enviar Mensagem
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar Info & Logs -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl border border-border p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-primary"></i>
                            Envios Recentes
                        </h3>
                        <?php if ($logs->num_rows > 0): ?>
                            <button onclick="limparHistorico()" class="text-[9px] font-black text-rose-500 hover:text-rose-600 uppercase tracking-tighter flex items-center gap-1 transition-colors" title="Limpar tudo">
                                <i data-lucide="trash" class="w-3 h-3"></i>
                                Limpar Tudo
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while($l = $logs->fetch_assoc()): ?>
                                <div class="p-3 bg-background rounded-2xl border border-border/50 group hover:border-primary transition-colors cursor-default relative">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-[11px] font-bold text-text truncate pr-8"><?php echo $l['assunto']; ?></p>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="text-[8px] font-black px-1.5 py-0.5 rounded uppercase <?php echo $l['status'] == 'Sucesso' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                                <?php echo $l['status']; ?>
                                            </span>
                                            <button onclick="excluirLog(<?php echo $l['id']; ?>)" class="text-text-secondary hover:text-rose-500 transition-colors opacity-0 group-hover:opacity-100 p-1">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-[9px] text-text-secondary font-bold">Para: <?php echo $l['usuario_nome'] ?: 'Todos'; ?></p>
                                        <p class="text-[8px] text-text-secondary opacity-50"><?php echo date('d/m H:i', strtotime($l['data_envio'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-[10px] italic text-text-secondary py-4 text-center">Nenhum envio registrado.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-primary/5 rounded-3xl border border-primary/10 p-6">
                    <h4 class="text-[10px] font-black text-primary uppercase tracking-widest mb-3">Dicas de Envio</h4>
                    <ul class="space-y-3">
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">Você pode usar tags HTML como &lt;b&gt;, &lt;i&gt; e &lt;br&gt; para formatar o texto.</p>
                        </li>
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">Evite usar muitas imagens ou links suspeitos para não cair na caixa de spam.</p>
                        </li>
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">O envio em massa para muitos usuários pode levar alguns segundos para ser processado.</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDestinatario() {
            const tipo = document.getElementById('tipo_destinatario').value;
            const div = document.getElementById('div_individual');
            if (tipo === 'individual') {
                div.classList.remove('hidden');
            } else {
                div.classList.add('hidden');
            }
        }

        function excluirLog(id) {
            if (confirm('Deseja excluir este registro do histórico?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_log">
                    <input type="hidden" name="log_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function limparHistorico() {
            if (confirm('ATENÇÃO: Deseja apagar TODO o histórico de envios? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="limpar_historico">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        const _icones_ext = {
            pdf: 'file-text', doc: 'file-text', docx: 'file-text',
            xls: 'table-2', xlsx: 'table-2',
            jpg: 'image', jpeg: 'image', png: 'image', gif: 'image',
            zip: 'archive', rar: 'archive',
            txt: 'file',
        };

        let _arquivosAtuais = new DataTransfer();

        function mostrarAnexos(files) {
            Array.from(files).forEach(f => _arquivosAtuais.items.add(f));
            document.getElementById('anexosInput').files = _arquivosAtuais.files;
            _renderizarAnexos();
        }

        function _removerAnexo(idx) {
            const novo = new DataTransfer();
            Array.from(_arquivosAtuais.files).forEach((f, i) => { if (i !== idx) novo.items.add(f); });
            _arquivosAtuais = novo;
            document.getElementById('anexosInput').files = _arquivosAtuais.files;
            _renderizarAnexos();
        }

        function _renderizarAnexos() {
            const lista = document.getElementById('listaAnexos');
            lista.innerHTML = '';
            if (_arquivosAtuais.files.length === 0) { lista.classList.add('hidden'); return; }
            lista.classList.remove('hidden');
            Array.from(_arquivosAtuais.files).forEach((file, idx) => {
                const ext = file.name.split('.').pop().toLowerCase();
                const icon = _icones_ext[ext] || 'file';
                const kb = (file.size / 1024).toFixed(1);
                const item = document.createElement('div');
                item.className = 'flex items-center justify-between p-2.5 bg-background border border-border rounded-xl';
                item.innerHTML = `
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="${icon}" class="w-3.5 h-3.5 text-primary"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-text block leading-tight">${file.name}</span>
                            <span class="text-[9px] text-text-secondary">${kb} KB</span>
                        </div>
                    </div>
                    <button type="button" onclick="_removerAnexo(${idx})" class="p-1.5 hover:bg-red-50 rounded-lg text-text-secondary hover:text-red-500 transition-all shrink-0" title="Remover">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                `;
                lista.appendChild(item);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function anexosInputDrop(event) {
            mostrarAnexos(event.dataTransfer.files);
        }

        // ── Envio em lote (intercepta submit quando destinatário = todos) ──────
        document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e) {
            if (document.getElementById('tipo_destinatario').value !== 'todos') return;
            e.preventDefault();

            const modal = document.getElementById('modalProgresso');
            modal.style.display = 'flex';
            document.getElementById('progressoConcluido').classList.add('hidden');
            document.getElementById('progressoBarra').style.width = '0%';
            document.getElementById('progressoContador').textContent = 'Iniciando...';
            document.getElementById('progressoPorcentagem').textContent = '0%';
            document.getElementById('progressoEnviados').textContent = '0';
            document.getElementById('progressoFalhas').textContent = '0';
            document.getElementById('progressoSubtitulo').textContent = 'Aguarde, os e-mails estão sendo enviados em lotes.';

            const fd = new FormData(this);
            fd.set('acao', 'iniciar_lote');

            fetch(location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        modal.style.display = 'none';
                        alert('Erro ao iniciar envio: ' + data.erro);
                        return;
                    }
                    _processarLote(data.job_key, 0, data.total);
                })
                .catch(err => {
                    modal.style.display = 'none';
                    alert('Erro de comunicação: ' + err.message);
                });
        });

        function _processarLote(jobKey, offset, total) {
            const fd = new FormData();
            fd.append('acao', 'processar_lote');
            fd.append('job_key', jobKey);
            fd.append('offset', offset);

            fetch(location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        alert('Erro no processamento: ' + (data.erro || 'Desconhecido'));
                        document.getElementById('modalProgresso').style.display = 'none';
                        return;
                    }
                    const pct = data.total > 0 ? Math.round((data.offset / data.total) * 100) : 100;
                    document.getElementById('progressoBarra').style.width = pct + '%';
                    document.getElementById('progressoContador').textContent = data.offset + ' / ' + data.total;
                    document.getElementById('progressoPorcentagem').textContent = pct + '%';
                    document.getElementById('progressoEnviados').textContent = data.total_enviados;
                    document.getElementById('progressoFalhas').textContent = data.total_falhas;

                    if (data.concluido) {
                        document.getElementById('progressoSubtitulo').textContent = 'Processamento finalizado!';
                        document.getElementById('progressoResumo').textContent =
                            data.total_enviados + ' enviado(s)' +
                            (data.total_falhas > 0 ? ', ' + data.total_falhas + ' com falha' : '') + '.';
                        document.getElementById('progressoConcluido').classList.remove('hidden');
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    } else {
                        _processarLote(jobKey, data.offset, data.total);
                    }
                })
                .catch(err => {
                    alert('Erro: ' + err.message);
                    document.getElementById('modalProgresso').style.display = 'none';
                });
        }
    </script>
    <?php include '../footer.php'; ?>

    <!-- Modal: Progresso de Envio em Lote -->
    <div id="modalProgresso" style="display:none" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-md w-full border border-border">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <i data-lucide="send" class="w-5 h-5 text-primary"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-text uppercase tracking-widest">Enviando mensagens</h3>
                    <p class="text-[10px] text-text-secondary" id="progressoSubtitulo">Aguarde, os e-mails estão sendo enviados em lotes.</p>
                </div>
            </div>

            <div class="w-full bg-gray-100 rounded-full h-2.5 mb-3 overflow-hidden">
                <div id="progressoBarra" class="h-2.5 rounded-full bg-primary transition-all duration-500" style="width:0%"></div>
            </div>
            <div class="flex justify-between text-[10px] font-bold text-text-secondary mb-5">
                <span id="progressoContador">Iniciando...</span>
                <span id="progressoPorcentagem">0%</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-green-50 rounded-xl text-center border border-green-100">
                    <p class="text-2xl font-black text-green-600" id="progressoEnviados">0</p>
                    <p class="text-[9px] font-black text-green-500 uppercase tracking-wider mt-0.5">Enviados</p>
                </div>
                <div class="p-3 bg-red-50 rounded-xl text-center border border-red-100">
                    <p class="text-2xl font-black text-red-500" id="progressoFalhas">0</p>
                    <p class="text-[9px] font-black text-red-400 uppercase tracking-wider mt-0.5">Falhas</p>
                </div>
            </div>

            <div id="progressoConcluido" class="hidden mt-5 p-4 bg-green-50 border border-green-100 rounded-2xl text-center">
                <i data-lucide="check-circle" class="w-8 h-8 text-green-500 mx-auto mb-2"></i>
                <p class="text-sm font-black text-green-700">Envio concluído!</p>
                <p class="text-[10px] text-green-600 mt-1" id="progressoResumo"></p>
                <button onclick="location.reload()" class="mt-4 bg-primary text-white px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-primary-hover transition-all active:scale-95">
                    OK
                </button>
            </div>
        </div>
    </div>
</body>
</html>
