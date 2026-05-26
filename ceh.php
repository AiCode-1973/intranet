<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar abertura de chamado CEH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'abrir_chamado_ceh') {
    $titulo = sanitize($_POST['titulo']);
    $descricao = $_POST['descricao'];
    $prioridade = isset($_POST['prioridade']) ? sanitize($_POST['prioridade']) : 'Média';
    $categoria = sanitize($_POST['categoria']);
    $anexo = '';

    // Processar upload de anexo
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'ceh_main_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destino = 'uploads/ceh/' . $novo_nome;
        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $destino)) {
            $anexo = $novo_nome;
        }
    }

    $stmt = $conn->prepare("INSERT INTO ceh_chamados (titulo, descricao, prioridade, categoria, usuario_id, anexo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $titulo, $descricao, $prioridade, $categoria, $usuario_id, $anexo);

    if ($stmt->execute()) {
        $chamado_id = $stmt->insert_id;
        registrarLog($conn, "Abriu chamado CEH: " . $titulo);
        header("Location: ceh.php?msg=aberto");
        exit;
    } else {
        $mensagem = "Erro ao abrir chamado: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar Novo Comentário do Solicitante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_comentario') {
    $chamado_id = intval($_POST['chamado_id']);
    $usuario_id = $_SESSION['usuario_id'];
    $comentario = sanitize($_POST['comentario']);
    $anexo = '';

    // Processar upload de anexo no comentário
    if (isset($_FILES['anexo_comentario']) && $_FILES['anexo_comentario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anexo_comentario']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'ceh_com_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destino = 'uploads/ceh/' . $novo_nome;
        if (move_uploaded_file($_FILES['anexo_comentario']['tmp_name'], $destino)) {
            $anexo = $novo_nome;
        }
    }

    // Segurança: Garantir que o chamado pertence ao usuário ou ele é admin
    $check = $conn->query("SELECT id FROM ceh_chamados WHERE id = $chamado_id AND (usuario_id = $usuario_id OR 1=" . (isAdmin() ? "1" : "0") . ")");
    
    if ($check->num_rows > 0 && (!empty($comentario) || !empty($anexo))) {
        $stmt = $conn->prepare("INSERT INTO ceh_comentarios (chamado_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario, anexo) VALUES (?, ?, ?, 0, 1, ?)");
        $stmt->bind_param("iiss", $chamado_id, $usuario_id, $comentario, $anexo);
        if ($stmt->execute()) {
            header("Location: ceh.php?msg=comentario_ok&id=$chamado_id");
            exit;
        }
        $stmt->close();
    }
}

// Processar Marcação de Leitura (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'marcar_lido') {
    $chamado_id = intval($_POST['chamado_id']);
    $conn->query("UPDATE ceh_comentarios SET lido_pelo_usuario = 1 WHERE chamado_id = $chamado_id");
    exit;
}

// Poll endpoint para auto-refresh
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    header('Content-Type: application/json');
    $cond = isAdmin() ? '' : 'WHERE c.usuario_id = ' . intval($usuario_id);
    $rows = $conn->query("SELECT c.id, c.status,
        (SELECT COUNT(*) FROM ceh_comentarios cc WHERE cc.chamado_id = c.id AND cc.lido_pelo_usuario = 0) as nao_lidos
        FROM ceh_chamados c $cond ORDER BY c.data_abertura DESC");
    $result = [];
    while ($r = $rows->fetch_assoc()) $result[] = $r;
    echo json_encode(['chamados' => $result]);
    exit;
}

// Mensagens de feedback
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'aberto') {
        $mensagem = "Chamado CEH aberto com sucesso! A equipe técnica foi notificada.";
        $tipo_mensagem = "success";
    } elseif ($_GET['msg'] == 'comentario_ok') {
        $mensagem = "Resposta enviada com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Filtros
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_clauses = [];

if (!isAdmin()) {
    $where_clauses[] = "c.usuario_id = $usuario_id";
}

if ($filtro_status) {
    $where_clauses[] = "c.status = '$filtro_status'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Buscar chamados CEH
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico 
        FROM ceh_chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        $where_sql
        ORDER BY c.data_abertura DESC";
$res = $conn->query($sql);
$chamados = [];
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0, 'Cancelado' => 0];

while($row = $res->fetch_assoc()) {
    $c_id = $row['id'];
    
    // Comentários não lidos
    $unread_res = $conn->query("SELECT COUNT(*) FROM ceh_comentarios WHERE chamado_id = $c_id AND lido_pelo_usuario = 0");
    $row['tem_novidade'] = ($unread_res->fetch_row()[0] > 0);

    // Buscar comentários
    $comentarios_res = $conn->query("SELECT cc.*, u.nome as autor FROM ceh_comentarios cc 
                                     JOIN usuarios u ON cc.usuario_id = u.id 
                                     INNER JOIN ceh_chamados ch ON ch.id = cc.chamado_id 
                                     WHERE cc.chamado_id = $c_id 
                                     ORDER BY cc.data_comentario ASC");
    $row['comentarios'] = [];
    while($coment = $comentarios_res->fetch_assoc()) {
        $coment['data_comentario_fmt'] = $coment['data_comentario']
            ? date('d/m/Y H:i', strtotime($coment['data_comentario']))
            : '-';
        $row['comentarios'][] = $coment;
    }
    $comentarios_res->free();
    $unread_res->free();
    $row['data_abertura_fmt'] = $row['data_abertura']
        ? date('d/m/Y H:i', strtotime($row['data_abertura']))
        : '-';
    
    $chamados[] = $row;
    if (isset($stats[$row['status']])) $stats[$row['status']]++;
}

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'dot' => 'bg-blue-500', 'icon' => 'clock'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'dot' => 'bg-amber-500', 'icon' => 'play-circle'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'dot' => 'bg-purple-500', 'icon' => 'component'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'dot' => 'bg-green-500', 'icon' => 'check-circle'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'dot' => 'bg-red-500', 'icon' => 'x-circle']
];

$prioridade_labels = [
    'Baixa' => ['text' => 'text-gray-400', 'icon' => 'arrow-down'],
    'Média' => ['text' => 'text-blue-500', 'icon' => 'minus'],
    'Alta' => ['text' => 'text-orange-500', 'icon' => 'arrow-up'],
    'Urgente' => ['text' => 'text-red-600 font-bold', 'icon' => 'alert-circle']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Equipamentos (CEH) - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                    Central de Equipamentos (CEH)
                </h1>
                <p class="text-text-secondary text-xs mt-1">Chamados técnicos para equipamentos hospitalares</p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Indicador Ao Vivo -->
                <div class="flex items-center gap-1.5 px-2 py-1 bg-white border border-border rounded-lg shadow-sm">
                    <span id="ceh-poll-status" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Ao Vivo</span>
                </div>
                <?php if (isAdmin()): ?>
                <a href="admin/ceh_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Painel Gestor CEH
                </a>
                <?php endif; ?>
                <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-[11px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-wider">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Novo Chamado
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div id="ceh-msg" class="p-3 rounded-lg border mb-6 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 transition-opacity duration-500">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
            <script>
                setTimeout(function() {
                    var m = document.getElementById('ceh-msg');
                    if (m) { m.style.opacity = '0'; setTimeout(function() { m.remove(); }, 500); }
                }, 4000);
            </script>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <a href="ceh.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_status ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($chamados); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach(['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Resolvido'] as $st): 
                $active = ($filtro_status == $st);
                $style = $status_styles[$st];
                $icon = $style['icon'];
            ?>
            <a href="?status=<?php echo urlencode($st); ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$st]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $st; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Chamados List -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Equipamento</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico CEH</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody id="ceh-tbody" class="divide-y divide-border text-xs">
                        <?php if (count($chamados) > 0): ?>
                            <?php foreach ($chamados as $chamado): 
                                $style = $status_styles[$chamado['status']];
                                $prio = $prioridade_labels[$chamado['prioridade']];
                            ?>
                            <tr data-id="<?php echo $chamado['id']; ?>" data-status="<?php echo htmlspecialchars($chamado['status']); ?>" data-unread="<?php echo $chamado['tem_novidade'] ? '1' : '0'; ?>" onclick='verDetalhes(<?php echo htmlspecialchars(json_encode($chamado, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)' class="hover:bg-background/20 transition-colors group cursor-pointer">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <span class="text-[9px] font-mono font-bold text-text-secondary opacity-50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php if ($chamado['tem_novidade']): ?>
                                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white animate-pulse"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-text group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></span>
                                                <?php if ($chamado['tem_novidade']): ?>
                                                    <span class="bg-rose-50 text-rose-600 text-[8px] px-1 rounded font-black uppercase tracking-tighter border border-rose-100 italic">Novo!</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $chamado['categoria']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5 <?php echo $prio['text']; ?>">
                                        <i data-lucide="<?php echo $prio['icon']; ?>" class="w-3.5 h-3.5"></i>
                                        <span class="font-bold uppercase tracking-tighter text-[10px]"><?php echo $chamado['prioridade']; ?></span>
                                    </div>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?php echo $style['bg']; ?> <?php echo $style['text']; ?>">
                                        <?php echo $chamado['status']; ?>
                                    </span>
                                </td>
                                <td class="p-3 font-bold text-text-secondary">
                                    <?php if ($chamado['tecnico']): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 rounded bg-primary/10 flex items-center justify-center text-[9px] font-bold text-primary">
                                                <?php echo strtoupper(substr($chamado['tecnico'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo $chamado['tecnico']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-text-secondary/30 italic">Em triagem...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right font-mono text-text-secondary opacity-60 text-[10px]">
                                    <?php echo date('d/m H:i', strtotime($chamado['data_abertura'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-16 text-center">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhum chamado CEH encontrado.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado CEH -->
    <div id="modalChamado" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Solicitação CEH</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Equipamentos Hospitalares</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="abrir_chamado_ceh">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Identificação do Equipamento</label>
                        <input type="text" name="titulo" required placeholder="Ex: Monitor Multiparamétrico - Sala 04" 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Tipo de Serviço</label>
                        <select name="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all cursor-pointer">
                            <option value="Manutenção Corretiva">Manutenção Corretiva</option>
                            <option value="Manutenção Preventiva">Manutenção Preventiva</option>
                            <option value="Calibração">Calibração</option>
                            <option value="Treinamento de Uso">Treinamento de Uso</option>
                            <option value="Dúvida Técnica">Dúvida Técnica</option>
                            <option value="Equipamento Geral" selected>Equipamento Geral</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição do Defeito / Solicitação</label>
                        <textarea name="descricao" required rows="4" placeholder="Detalhes do que está acontecendo com o equipamento..."
                                  class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Anexar Arquivo (Opcional)</label>
                        <input type="file" name="anexo" class="w-full text-xs text-text-secondary file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Abrir Chamado</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalhes do Chamado CEH (Modo Paisagem) -->
    <div id="modalDetalhes" class="modal">
        <div class="bg-white w-full max-w-4xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150 flex flex-col md:flex-row">
            <!-- Coluna Esquerda: Informações -->
            <div class="w-full md:w-1/2 flex flex-col border-r border-border">
                <div id="modal_header_bg" class="px-5 py-4 text-white flex justify-between items-center bg-primary">
                    <div>
                        <h2 class="text-base font-bold flex items-center gap-2">
                            <span id="detalhe_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                            Detalhes CEH
                        </h2>
                        <p id="detalhe_status_label" class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Status: ---</p>
                    </div>
                    <button class="md:hidden p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModalDetalhes()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div class="p-5 flex-grow space-y-4 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 70vh;">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Equipamento / Problema</label>
                        <p id="detalhe_titulo" class="text-sm font-bold text-text">---</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Descrição Técnica</label>
                        <div id="detalhe_descricao" class="text-xs text-text-secondary leading-relaxed bg-background p-3 rounded-lg border border-border/50 italic">---</div>
                    </div>

                    <div id="container_anexo_principal" class="hidden">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Anexo do Chamado</label>
                        <a id="link_anexo_principal" href="#" target="_blank" class="flex items-center gap-2 p-2 bg-primary/5 border border-primary/10 rounded-lg text-primary text-[10px] font-bold hover:bg-primary/10 transition-all">
                            <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                            Ver Anexo Principal
                        </a>
                    </div>

                    <div id="container_resolucao" class="hidden animate-in fade-in slide-in-from-bottom-2">
                        <label class="block text-[10px] font-black text-emerald-600 mb-1 uppercase tracking-widest flex items-center gap-1">
                            <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                            Resolução Técnica
                        </label>
                        <div id="detalhe_resolucao" class="text-xs text-emerald-700 leading-relaxed bg-emerald-50 p-3 rounded-lg border border-emerald-100 font-bold whitespace-pre-wrap">---</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-2 border-t border-border">
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-0.5 uppercase tracking-widest opacity-50">Técnico Responsável</label>
                            <p id="detalhe_tecnico" class="text-[11px] font-bold text-text">---</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-0.5 uppercase tracking-widest opacity-50">Data de Abertura</label>
                            <p id="detalhe_data" class="text-[11px] font-bold text-text text-right">---</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 flex justify-end items-center border-t border-border">
                    <button onclick="fecharModalDetalhes()" class="px-6 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all shadow-sm uppercase tracking-widest">Fechar</button>
                </div>
            </div>

            <!-- Coluna Direita: Interações (Chat) -->
            <div class="w-full md:w-1/2 flex flex-col bg-gray-50/50">
                <div class="px-5 py-4 border-b border-border flex justify-between items-center bg-white">
                    <h3 class="text-xs font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="message-square" class="w-4 h-4 text-primary"></i>
                        Histórico de Interações
                    </h3>
                    <button class="hidden md:block p-1.5 hover:bg-gray-100 rounded-lg transition-colors" onclick="fecharModalDetalhes()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-5 flex-grow flex flex-col justify-between" style="min-height: 400px;">
                    <div id="detalhe_comentarios" class="space-y-3 overflow-y-auto pr-2 custom-scrollbar flex-grow mb-4" style="max-height: 50vh;">
                        <!-- JS Populado -->
                    </div>

                    <form method="POST" action="" id="form_comentario_usuario" enctype="multipart/form-data" class="flex flex-col gap-2 p-3 bg-white border border-border rounded-xl shadow-sm">
                        <input type="hidden" name="acao" value="adicionar_comentario">
                        <input type="hidden" name="chamado_id" id="comentario_chamado_id">
                        <div class="flex gap-2">
                            <input type="text" name="comentario" placeholder="Escreva uma mensagem..." 
                                   class="flex-grow p-2 bg-transparent text-[10px] font-bold focus:outline-none transition-all">
                            <button type="submit" class="bg-primary text-white p-2 rounded-lg hover:bg-primary-hover transition-all shadow-md active:scale-95 flex items-center justify-center">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 px-1">
                            <label class="cursor-pointer group flex items-center gap-1">
                                <i data-lucide="paperclip" class="w-3.5 h-3.5 text-text-secondary group-hover:text-primary transition-colors"></i>
                                <span class="text-[9px] font-bold text-text-secondary group-hover:text-primary transition-colors">Anexar</span>
                                <input type="file" name="anexo_comentario" class="hidden" onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Anexar'">
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChamado = null;

        function abrirModal() { document.getElementById('modalChamado').classList.add('active'); }
        function fecharModal() { document.getElementById('modalChamado').classList.remove('active'); }

        function verDetalhes(chamado) {
            currentChamado = chamado;
            document.getElementById('detalhe_id').textContent = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('detalhe_titulo').textContent = chamado.titulo;
            document.getElementById('detalhe_descricao').textContent = chamado.descricao;
            document.getElementById('detalhe_status_label').textContent = 'Status: ' + chamado.status;
            document.getElementById('detalhe_tecnico').textContent = chamado.tecnico || 'Em Triagem';
            document.getElementById('detalhe_data').textContent = chamado.data_abertura_fmt;
            document.getElementById('comentario_chamado_id').value = chamado.id;

            // Anexo Principal
            const containerAnexo = document.getElementById('container_anexo_principal');
            const linkAnexo = document.getElementById('link_anexo_principal');
            if (chamado.anexo) {
                containerAnexo.classList.remove('hidden');
                linkAnexo.href = 'uploads/ceh/' + chamado.anexo;
            } else {
                containerAnexo.classList.add('hidden');
            }

            // Marcar lido
            if (chamado.tem_novidade) {
                fetch('ceh.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'acao=marcar_lido&chamado_id=' + chamado.id
                });
            }

            const comList = document.getElementById('detalhe_comentarios');
            comList.innerHTML = '';

            // Limpar campo de comentário para não vazar texto de chamado anterior
            const comentarioInput = document.querySelector('#form_comentario_usuario input[name="comentario"]');
            if (comentarioInput) comentarioInput.value = '';
            const fileInput = document.querySelector('#form_comentario_usuario input[type="file"]');
            if (fileInput) { fileInput.value = ''; }

            const formCom = document.getElementById('form_comentario_usuario');
            
            if (chamado.status === 'Resolvido' || chamado.status === 'Cancelado') {
                formCom.classList.add('hidden');
            } else {
                formCom.classList.remove('hidden');
            }

            if (chamado.comentarios && chamado.comentarios.length > 0) {
                chamado.comentarios.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'bg-white p-2 rounded-lg border border-border/40 shadow-sm';
                    div.innerHTML = `
                        <div class="flex justify-between items-center mb-0.5">
                            <span class="text-[8px] font-black text-primary uppercase">${c.autor}</span>
                            <span class="text-[7px] text-text-secondary opacity-50">${c.data_comentario_fmt}</span>
                        </div>
                        <p class="text-[9px] text-text-secondary leading-tight italic">${c.comentario ? '"' + c.comentario + '"' : '<span class="opacity-50 not-italic">Arquivo enviado</span>'}</p>
                        ${c.anexo ? `
                            <div class="mt-2">
                                <a href="uploads/ceh/${c.anexo}" target="_blank" class="inline-flex items-center gap-1.5 p-1.5 bg-gray-50 border border-border rounded text-[8px] font-black text-primary uppercase hover:bg-gray-100 transition-all">
                                    <i data-lucide="paperclip" class="w-2.5 h-2.5"></i>
                                    Ver Anexo
                                </a>
                            </div>
                        ` : ''}
                    `;
                    comList.appendChild(div);
                });
            } else {
                comList.innerHTML = '<p class="text-[9px] text-text-secondary/40 italic text-center py-2">Sem mensagens no momento.</p>';
            }

            const resContainer = document.getElementById('container_resolucao');
            const resText = document.getElementById('detalhe_resolucao');
            const header = document.getElementById('modal_header_bg');

            if (chamado.resolucao) {
                resContainer.classList.remove('hidden');
                resText.textContent = chamado.resolucao;
            } else {
                resContainer.classList.add('hidden');
            }

            header.className = 'px-5 py-4 text-white flex justify-between items-center ';
            if (chamado.status === 'Resolvido') {
                header.classList.add('bg-emerald-500');
            } else if (chamado.status === 'Cancelado') {
                header.classList.add('bg-gray-500');
            } else if (chamado.status === 'Em Atendimento') {
                header.classList.add('bg-amber-500');
            } else {
                header.classList.add('bg-primary');
            }

            document.getElementById('modalDetalhes').classList.add('active');
            lucide.createIcons();
        }

        function fecharModalDetalhes() {
            document.getElementById('modalDetalhes').classList.remove('active');
        }

        // ── Auto-refresh: detecta qualquer mudança nos chamados ─────────────
        (function () {
            const INTERVAL  = 30000;
            const STATUS_EL = document.getElementById('ceh-poll-status');

            function stateHash(list) {
                return list.map(c => c.id + ':' + c.status + ':' + c.nao_lidos).sort().join('|');
            }

            let lastHash = stateHash(
                [...document.querySelectorAll('#ceh-tbody tr[data-id]')]
                    .map(tr => ({
                        id:        tr.dataset.id,
                        status:    tr.dataset.status  || '',
                        nao_lidos: tr.dataset.unread   || '0'
                    }))
            );

            function showToast(msg, autoReload) {
                const old = document.getElementById('ceh-toast');
                if (old) old.remove();
                const t = document.createElement('div');
                t.id = 'ceh-toast';
                t.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;display:flex;align-items:center;gap:10px;background:#2563eb;color:#fff;padding:12px 18px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.3);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;cursor:pointer;';
                t.innerHTML = '&#128276; ' + msg;
                t.onclick = () => location.reload();
                document.body.appendChild(t);
                if (autoReload) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    setTimeout(() => { if (t.parentNode) t.remove(); }, 8000);
                }
            }

            function pulse(ok) {
                if (!STATUS_EL) return;
                STATUS_EL.className = ok
                    ? 'w-2 h-2 rounded-full bg-emerald-400 animate-pulse'
                    : 'w-2 h-2 rounded-full bg-rose-400';
            }

            async function poll() {
                try {
                    const res = await fetch('ceh.php?action=poll', { cache: 'no-store' });
                    if (!res.ok) { pulse(false); return; }
                    const data = await res.json();
                    pulse(true);

                    const currentHash = stateHash(
                        data.chamados.map(c => ({
                            id:        String(c.id),
                            status:    c.status,
                            nao_lidos: String(c.nao_lidos)
                        }))
                    );

                    if (currentHash !== lastHash) {
                        lastHash = currentHash;
                        const modalOpen = document.getElementById('modalDetalhes').classList.contains('active');
                        if (modalOpen) {
                            showToast('Chamados atualizados — feche o modal para ver', false);
                        } else {
                            showToast('Atualizando...', true);
                        }
                    }
                } catch (e) {
                    pulse(false);
                }
            }

            poll();
            setInterval(poll, INTERVAL);
        })();
        // ────────────────────────────────────────────────────────────────────
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
