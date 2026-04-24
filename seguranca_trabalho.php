<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!temPermissao($conn, $_SESSION['setor_id'], 'seguranca_trabalho')) {
    header("Location: dashboard.php");
    exit;
}

// Buscar configurações atuais do e-mail
$config_res = $conn->query("SELECT * FROM config_seguranca WHERE id = 1");
$email_config = $config_res->num_rows > 0 ? $config_res->fetch_assoc() : ['email_assunto' => 'Aviso: Documentação para Exame Periódico', 'email_mensagem' => ''];

// Atualizações via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    // 1. Salvar Configurações Globais
    if ($_POST['acao'] === 'salvar_config') {
        $assunto = sanitize($_POST['assunto']);
        $mensagem = $_POST['mensagem'];
        
        $stmt = $conn->prepare("UPDATE config_seguranca SET email_assunto = ?, email_mensagem = ? WHERE id = 1");
        $stmt->bind_param("ss", $assunto, $mensagem);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }

    // 2. Enviar Aviso Direto (Usando Configurações Salvas)
    if ($_POST['acao'] === 'enviar_aviso') {
        $uid = (int)$_POST['usuario_id'];
        $ano_atual = (int)date('Y');
        
        $res = $conn->query("SELECT nome, email FROM usuarios WHERE id = $uid");
        $u = $res->fetch_assoc();
        
        if ($u && !empty($u['email'])) {
            $assunto = $email_config['email_assunto'];
            $corpo = $email_config['email_mensagem'];
            
            // Se não houver mensagem salva, usa o padrão
            if (empty($corpo)) {
                $corpo = "
                    <div style='font-family: sans-serif; color: #333;'>
                        <h2 style='color: #0056b3;'>Olá, {NOME_USUARIO}!</h2>
                        <p>Informamos que está disponível a documentação para a realização do seu <strong>Exame Periódico</strong>.</p>
                        <p>Por favor, procure o <strong>responsável pela Segurança do Trabalho</strong> no RH para retirar os documentos necessários.</p>
                        <br>
                        <p style='font-size: 12px; color: #666;'>Este é um e-mail automático da Intranet APAS.</p>
                    </div>
                ";
            }
            
            // Substitui o placeholder pelo nome real
            $corpo_final = str_replace('{NOME_USUARIO}', $u['nome'], $corpo);
            
            if (enviarEmail($u['email'], $assunto, $corpo_final)) {
                $conn->query("UPDATE usuarios SET ultimo_aviso_periodico = $ano_atual WHERE id = $uid");
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erro ao enviar e-mail.']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Usuário sem e-mail.']);
        }
        exit;
    }

    // 3. Atualizar Status do Card
    if ($_POST['acao'] === 'update_status') {
        $uid = (int)$_POST['usuario_id'];
        $new_status = sanitize($_POST['status']);
        $stmt = $conn->prepare("UPDATE usuarios SET status_seguranca = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $uid);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        }
        exit;
    }
}

$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_atual = (int)date('Y');

$query = "SELECT u.*, s.nome as setor_nome FROM usuarios u LEFT JOIN setores s ON u.setor_id = s.id WHERE MONTH(u.data_admissao) = ? ORDER BY DAY(u.data_admissao) ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $mes_selecionado);
$stmt->execute();
$usuarios = $stmt->get_result();

$meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

$status_options = [
    'pendente' => ['label' => 'Pendente', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50'],
    'realizado' => ['label' => 'Realizado', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'],
    'atrasado' => ['label' => 'Atrasado', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
    'vencido' => ['label' => 'Vencido', 'color' => 'text-rose-700', 'bg' => 'bg-rose-50']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segurança do Trabalho - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="hard-hat" class="w-6 h-6"></i>
                    Controle de Periódicos
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão de exames por mês de admissão</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Ícone de Configuração Global -->
                <button onclick="abrirModalConfig()" class="p-2.5 bg-white border border-border rounded-xl text-text-secondary hover:text-primary hover:border-primary transition-all shadow-sm flex items-center gap-2 text-xs font-bold uppercase tracking-wider">
                    <i data-lucide="settings-2" class="w-4 h-4"></i>
                    Configurar E-mail
                </button>

                <form action="" method="GET" class="flex items-center gap-2">
                    <select name="mes" onchange="this.form.submit()" class="bg-white border border-border px-3 py-2.5 rounded-xl text-xs font-bold focus:outline-none focus:border-primary shadow-sm">
                        <?php foreach($meses as $num => $nome): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mes_selecionado == $num ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($u = $usuarios->fetch_assoc()): 
                $ja_enviado = ($u['ultimo_aviso_periodico'] == $ano_atual);
            ?>
                <div class="bg-white p-6 rounded-3xl border border-border shadow-sm hover:border-primary/40 transition-all relative flex flex-col h-full">
                    <div class="absolute right-4 top-4 flex flex-col items-center gap-2">
                        <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex flex-col items-center justify-center border border-primary/10">
                            <span class="text-sm font-black"><?php echo date('d', strtotime($u['data_admissao'])); ?></span>
                            <span class="text-[7px] font-bold uppercase opacity-50"><?php echo substr($meses[(int)date('m', strtotime($u['data_admissao']))], 0, 3); ?></span>
                        </div>

                        <!-- Botão de Envio Direto -->
                        <button onclick="enviarAvisoDireto(<?php echo $u['id']; ?>)" 
                                id="btn-aviso-<?php echo $u['id']; ?>"
                                class="w-8 h-8 shadow-sm border rounded-lg flex items-center justify-center transition-all <?php echo $ja_enviado ? 'bg-emerald-50 border-emerald-200 text-emerald-500' : 'bg-white border-border text-text-secondary hover:text-primary hover:border-primary'; ?>">
                            <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-gray-50 border-2 border-white shadow-sm flex items-center justify-center overflow-hidden shrink-0">
                            <?php if ($u['foto']): ?>
                                <img src="uploads/fotos/<?php echo $u['foto']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-primary font-black opacity-30"><?php echo substr($u['nome'], 0, 1); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 pr-10">
                            <h4 class="text-xs font-bold text-text truncate"><?php echo $u['nome']; ?></h4>
                            <div class="flex flex-col gap-1 mt-1.5">
                                <span class="text-[10px] font-bold text-text-secondary/70 flex items-center gap-1.5 uppercase">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5"></i> <?php echo $u['setor_nome']; ?>
                                </span>
                                <span class="text-[10px] font-bold text-primary/60 flex items-center gap-1.5 uppercase">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Adm: <?php echo date('d/m/Y', strtotime($u['data_admissao'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto pt-4">
                        <div class="grid grid-cols-4 gap-1 p-1 bg-gray-50 rounded-2xl border border-border/50">
                            <?php foreach($status_options as $val => $opt): ?>
                                <button onclick="updateStatus(<?php echo $u['id']; ?>, '<?php echo $val; ?>')" 
                                        class="py-1.5 rounded-xl text-[8px] font-black uppercase tracking-tighter transition-all <?php echo ($u['status_seguranca'] ?: 'pendente') === $val ? $opt['bg'] . ' ' . $opt['color'] . ' shadow-sm border border-current/10' : 'text-text-secondary/40 hover:text-text-secondary'; ?>">
                                    <?php echo $opt['label']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Configuração Global -->
    <div id="modalConfig" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl animate-in zoom-in duration-200">
            <div class="p-6 border-b border-border flex justify-between items-center bg-gray-50 rounded-t-3xl">
                <h3 class="text-sm font-black text-primary uppercase flex items-center gap-2">
                    <i data-lucide="settings-2" class="w-5 h-5"></i> Configurar E-mail Padrão
                </h3>
                <button onclick="fecharModalConfig()"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Assunto Padrão</label>
                    <input type="text" id="configAssunto" class="w-full bg-gray-50 border border-border px-4 py-3 rounded-xl text-sm" value="<?php echo htmlspecialchars($email_config['email_assunto'] ?: 'Aviso: Documentação para Exame Periódico'); ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Mensagem Padrão (Use {NOME_USUARIO} para o nome)</label>
                    <textarea id="configMensagem" rows="10" class="w-full bg-gray-50 border border-border px-4 py-3 rounded-xl text-xs font-mono"><?php 
                        echo htmlspecialchars($email_config['email_mensagem'] ?: "<div style='font-family: sans-serif; color: #333;'>
    <h2 style='color: #0056b3;'>Olá, {NOME_USUARIO}!</h2>
    <p>Informamos que está disponível a documentação para a realização do seu <strong>Exame Periódico</strong>.</p>
    <p>Por favor, procure o <strong>responsável pela Segurança do Trabalho</strong> no RH para retirar os documentos necessários.</p>
    <br>
    <p style='font-size: 12px; color: #666;'>Este é um e-mail automático da Intranet APAS.</p>
</div>"); 
                    ?></textarea>
                </div>
            </div>
            <div class="p-6 bg-gray-50 border-t border-border rounded-b-3xl flex justify-end gap-3">
                <button onclick="fecharModalConfig()" class="px-6 py-2.5 text-xs font-black uppercase text-text-secondary">Cancelar</button>
                <button onclick="salvarConfiguracao()" class="px-8 py-2.5 bg-primary text-white rounded-xl text-xs font-black uppercase shadow-lg shadow-primary/20">Salvar Alterações</button>
            </div>
        </div>
    </div>

    <script>
        function abrirModalConfig() { document.getElementById('modalConfig').classList.remove('hidden'); }
        function fecharModalConfig() { document.getElementById('modalConfig').classList.add('hidden'); }

        async function salvarConfiguracao() {
            const formData = new FormData();
            formData.append('acao', 'salvar_config');
            formData.append('assunto', document.getElementById('configAssunto').value);
            formData.append('mensagem', document.getElementById('configMensagem').value);
            const res = await fetch('seguranca_trabalho.php', { method: 'POST', body: formData });
            if ((await res.json()).success) { alert('Configuração salva!'); fecharModalConfig(); }
        }

        async function enviarAvisoDireto(userId) {
            if (!confirm('Deseja enviar o e-mail agora com as configurações salvas?')) return;
            const btn = document.getElementById(`btn-aviso-${userId}`);
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            lucide.createIcons();
            
            const formData = new FormData();
            formData.append('acao', 'enviar_aviso');
            formData.append('usuario_id', userId);
            
            const res = await fetch('seguranca_trabalho.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                btn.className = 'w-8 h-8 shadow-sm border rounded-lg flex items-center justify-center transition-all bg-emerald-50 border-emerald-200 text-emerald-500';
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
                alert('E-mail enviado!');
            } else { alert('Erro: ' + data.error); btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i>'; }
            lucide.createIcons();
        }

        async function updateStatus(userId, status) {
            const formData = new FormData();
            formData.append('acao', 'update_status');
            formData.append('usuario_id', userId);
            formData.append('status', status);
            const res = await fetch('seguranca_trabalho.php', { method: 'POST', body: formData });
            if ((await res.json()).success) window.location.reload();
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>