<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!temPermissao($conn, $_SESSION['setor_id'], 'seguranca_trabalho')) {
    header("Location: dashboard.php");
    exit;
}

// Atualizar status via AJAX/POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'update_status') {
        $uid = (int)$_POST['usuario_id'];
        $new_status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE usuarios SET status_seguranca = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $uid);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }

    if ($_POST['acao'] === 'enviar_aviso') {
        $uid = (int)$_POST['usuario_id'];
        $ano_atual = (int)date('Y');
        $assunto_editado = sanitize($_POST['assunto'] ?? "Aviso: Documentação para Exame Periódico");
        $mensagem_editada = $_POST['mensagem'] ?? ""; // Mensagem HTML vinda do editor/textarea
        
        // Buscar dados do usuário
        $res = $conn->query("SELECT nome, email FROM usuarios WHERE id = $uid");
        $u = $res->fetch_assoc();
        
        if ($u && !empty($u['email'])) {
            if (empty($mensagem_editada)) {
                $mensagem_editada = "
                    <div style='font-family: sans-serif; color: #333;'>
                        <h2 style='color: #0056b3;'>Olá, {$u['nome']}!</h2>
                        <p>Informamos que está disponível a documentação para a realização do seu <strong>Exame Periódico</strong>.</p>
                        <p>Por favor, procure o <strong>responsável pela Segurança do Trabalho</strong> no RH para retirar os documentos necessários.</p>
                        <br>
                        <p style='font-size: 12px; color: #666;'>Este é um e-mail automático da Intranet APAS.</p>
                    </div>
                ";
            }
            
            if (enviarEmail($u['email'], $assunto_editado, $mensagem_editada)) {
                $conn->query("UPDATE usuarios SET ultimo_aviso_periodico = $ano_atual WHERE id = $uid");
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Falha ao enviar e-mail.']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Usuário não possui e-mail cadastrado.']);
        }
        exit;
    }
}

$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_atual = (int)date('Y');

$query = "
    SELECT u.*, s.nome as setor_nome 
    FROM usuarios u
    LEFT JOIN setores s ON u.setor_id = s.id
    WHERE MONTH(u.data_admissao) = ? 
    ORDER BY DAY(u.data_admissao) ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $mes_selecionado);
$stmt->execute();
$usuarios = $stmt->get_result();

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

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
    <title>Segurança do Trabalho - Periódicos - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="hard-hat" class="w-6 h-6"></i>
                    Controle de Periódicos
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão de exames e treinamentos periódicos por mês de admissão</p>
            </div>

            <div class="flex items-center gap-2">
                <form action="" method="GET" class="flex items-center gap-2">
                    <select name="mes" onchange="this.form.submit()" class="bg-white border border-border px-3 py-2 rounded-lg text-xs font-bold focus:outline-none focus:border-primary shadow-sm hover:border-primary transition-all">
                        <?php foreach($meses as $num => $nome): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mes_selecionado == $num ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($usuarios->num_rows > 0): ?>
                <?php while ($u = $usuarios->fetch_assoc()): 
                    $curr_status = $u['status_seguranca'] ?: 'pendente';
                    $ja_enviado = ($u['ultimo_aviso_periodico'] == $ano_atual);
                ?>
                <div class="bg-white p-6 rounded-3xl border border-border shadow-sm hover:border-primary/40 hover:shadow-xl transition-all group relative flex flex-col h-full">
                        <div class="absolute right-4 top-4 flex flex-col items-center gap-2">
                            <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex flex-col items-center justify-center border border-primary/10">
                                <span class="text-sm font-black leading-none"><?php echo date('d', strtotime($u['data_admissao'])); ?></span>
                                <span class="text-[7px] font-bold uppercase opacity-50"><?php echo substr($meses[(int)date('m', strtotime($u['data_admissao']))], 0, 3); ?></span>
                            </div>

                            <button onclick="abrirModalEmail(<?php echo $u['id']; ?>, '<?php echo addslashes($u['nome']); ?>')" 
                                    title="Configurar e enviar e-mail"
                                    id="btn-aviso-<?php echo $u['id']; ?>"
                                    class="w-8 h-8 shadow-sm border rounded-lg flex items-center justify-center transition-all group/btn <?php echo $ja_enviado ? 'bg-emerald-50 border-emerald-200 text-emerald-500' : 'bg-white border-border text-text-secondary hover:text-primary hover:border-primary'; ?>">
                                <i data-lucide="mail-plus" class="w-4 h-4 group-hover/btn:scale-110 transition-transform"></i>
                            </button>
                        </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-gray-50 border-2 border-white shadow-sm flex items-center justify-center text-primary font-black text-lg overflow-hidden shrink-0">
                            <?php if (!empty($u['foto'])): ?>
                                <img src="uploads/fotos/<?php echo $u['foto']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="opacity-30"><?php echo substr($u['nome'], 0, 1); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 pr-10">
                            <h4 class="text-xs font-bold text-text leading-tight line-clamp-2 mt-0.5 group-hover:text-primary transition-colors"><?php echo $u['nome']; ?></h4>
                            <div class="flex flex-col gap-1 mt-1.5">
                                <span class="text-[10px] font-bold text-text-secondary/70 flex items-center gap-1.5 uppercase tracking-tighter">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5 text-primary/60"></i>
                                    <?php echo $u['setor_nome'] ?: 'Setor não informado'; ?>
                                </span>
                                <span class="text-[10px] font-bold text-primary/60 flex items-center gap-1.5 uppercase tracking-tighter">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                    Adm: <?php echo date('d/m/Y', strtotime($u['data_admissao'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <label class="block text-[8px] font-black text-text-secondary uppercase tracking-widest mb-2 opacity-50 ml-1">Status do Periódico</label>
                        <div class="grid grid-cols-4 gap-1 p-1 bg-gray-50 rounded-2xl border border-border/50">
                            <?php foreach($status_options as $val => $opt): 
                                $is_active = ($curr_status === $val);
                            ?>
                                <button onclick="updateStatus(<?php echo $u['id']; ?>, '<?php echo $val; ?>')" 
                                        class="py-1.5 rounded-xl text-[8px] font-black uppercase tracking-tighter transition-all <?php echo $is_active ? $opt['bg'] . ' ' . $opt['color'] . ' shadow-sm border border-current/10' : 'text-text-secondary/40 hover:text-text-secondary'; ?>">
                                    <?php echo $opt['label']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Editar Email -->
    <div id="modalEmail" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-border flex justify-between items-center bg-gray-50">
                <h3 class="text-sm font-black text-primary uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="mail-edit" class="w-5 h-5"></i>
                    Editar Aviso de Periódico
                </h3>
                <button onclick="fecharModalEmail()" class="text-text-secondary hover:text-primary transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-[11px] text-text-secondary mb-4 italic" id="modalUserLabel">Editando e-mail para: ...</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Assunto do E-mail</label>
                        <input type="text" id="emailAssunto" class="w-full bg-gray-50 border border-border px-4 py-3 rounded-xl text-sm focus:outline-none focus:border-primary font-medium" value="Aviso: Documentação para Exame Periódico">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Mensagem (HTML)</label>
                        <textarea id="emailMensagem" rows="8" class="w-full bg-gray-50 border border-border px-4 py-3 rounded-xl text-xs focus:outline-none focus:border-primary font-mono"></textarea>
                    </div>
                </div>
            </div>
            <div class="p-6 bg-gray-50 border-t border-border flex justify-end gap-3">
                <button onclick="fecharModalEmail()" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase text-text-secondary hover:bg-gray-200 transition-all">Cancelar</button>
                <button id="btnEnviarEmail" onclick="dispararEmail()" class="px-8 py-2.5 bg-primary text-white rounded-xl text-xs font-black uppercase shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all flex items-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Enviar Agora
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;

        function abrirModalEmail(userId, userName) {
            currentUserId = userId;
            document.getElementById('modalUserLabel').innerText = `Editando e-mail para: ${userName}`;
            
            // Texto padrão se estiver vazio
            const padrao = `<div style='font-family: sans-serif; color: #333;'>
    <h2 style='color: #0056b3;'>Olá, ${userName}!</h2>
    <p>Informamos que está disponível a documentação para a realização do seu <strong>Exame Periódico</strong>.</p>
    <p>Por favor, procure o <strong>responsável pela Segurança do Trabalho</strong> no RH para retirar os documentos necessários.</p>
    <br>
    <p style='font-size: 12px; color: #666;'>Este é um e-mail automático da Intranet APAS.</p>
</div>`;
            
            document.getElementById('emailMensagem').value = padrao;
            document.getElementById('modalEmail').classList.remove('hidden');
        }

        function fecharModalEmail() {
            document.getElementById('modalEmail').classList.add('hidden');
            currentUserId = null;
        }

        async function dispararEmail() {
            if (!currentUserId) return;

            const btn = document.getElementById('btnEnviarEmail');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Enviando...';
            lucide.createIcons();
            btn.disabled = true;

            const formData = new FormData();
            formData.append('acao', 'enviar_aviso');
            formData.append('usuario_id', currentUserId);
            formData.append('assunto', document.getElementById('emailAssunto').value);
            formData.append('mensagem', document.getElementById('emailMensagem').value);

            try {
                const response = await fetch('seguranca_trabalho.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    const iconeBotao = document.getElementById(`btn-aviso-${currentUserId}`);
                    iconeBotao.classList.remove('bg-white', 'border-border', 'text-text-secondary', 'hover:text-primary', 'hover:border-primary');
                    iconeBotao.classList.add('text-emerald-500', 'border-emerald-200', 'bg-emerald-50');
                    iconeBotao.title = 'Aviso já enviado este ano';
                    
                    alert('E-mail enviado com sucesso!');
                    fecharModalEmail();
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('Erro na requisição.');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                lucide.createIcons();
            }
        }

        async function updateStatus(usuarioId, newStatus) {
            const formData = new FormData();
            formData.append('acao', 'update_status');
            formData.append('usuario_id', usuarioId);
            formData.append('status', newStatus);
            try {
                const response = await fetch('seguranca_trabalho.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) window.location.reload();
                else alert('Erro ao atualizar status: ' + data.error);
            } catch (err) { alert('Erro na requisição.'); }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>