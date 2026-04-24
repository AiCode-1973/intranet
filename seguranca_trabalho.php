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
        
        // Buscar dados do usuário
        $res = $conn->query("SELECT nome, email FROM usuarios WHERE id = $uid");
        $u = $res->fetch_assoc();
        
        if ($u && !empty($u['email'])) {
            $assunto = "Aviso: Documentação para Exame Periódico";
            $mensagem = "
                <div style='font-family: sans-serif; color: #333;'>
                    <h2 style='color: #0056b3;'>Olá, {$u['nome']}!</h2>
                    <p>Informamos que está disponível a documentação para a realização do seu <strong>Exame Periódico</strong>.</p>
                    <p>Por favor, procure o <strong>responsável pela Segurança do Trabalho</strong> no RH para retirar os documentos necessários.</p>
                    <br>
                    <p style='font-size: 12px; color: #666;'>Este é um e-mail automático da Intranet APAS.</p>
                </div>
            ";
            
            if (enviarEmail($u['email'], $assunto, $mensagem)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Falha ao enviar e-mail. Verifique a configuração SMTP.']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Usuário não possui e-mail cadastrado.']);
        }
        exit;
    }
}

// Filtro por mês
$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');

// Buscar usuários admitidos no mês selecionado
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
    'atrasado' => ['label' => 'Atrasado', 'color' => 'text-red-500', 'bg' => 'bg-red-50']
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
        <!-- Header Section -->
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

        <!-- Info Stats Card -->
        <div class="bg-primary/5 rounded-3xl p-8 mb-8 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl shadow-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="clipboard-check" class="w-10 h-10"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-primary">Exames Periódicos</h2>
                    <p class="text-xs font-bold text-primary/60 uppercase tracking-widest mt-1">Mês de Referência: <?php echo $meses[$mes_selecionado]; ?></p>
                </div>
            </div>
            <div class="bg-white px-6 py-4 rounded-2xl shadow-sm border border-border flex flex-col items-center min-w-[120px]">
                <span class="text-2xl font-black text-primary"><?php echo $usuarios->num_rows; ?></span>
                <span class="text-[8px] font-black text-text-secondary uppercase tracking-widest leading-none">Total no Mês</span>
            </div>
        </div>

        <!-- Colaboradores Grid -->
        <h3 class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
            <i data-lucide="users" class="w-4 h-4"></i>
            Funcionários com Períodico em <?php echo $meses[$mes_selecionado]; ?>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($usuarios->num_rows > 0): ?>
                <?php while ($u = $usuarios->fetch_assoc()): 
                    $curr_status = $u['status_seguranca'] ?: 'pendente';
                ?>
                <div class="bg-white p-6 rounded-3xl border border-border shadow-sm hover:border-primary/40 hover:shadow-xl transition-all group relative flex flex-col h-full">
                        <div class="absolute right-4 top-4 flex flex-col items-center gap-2">
                            <!-- Badge de Dia -->
                            <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex flex-col items-center justify-center border border-primary/10">
                                <span class="text-sm font-black leading-none"><?php echo date('d', strtotime($u['data_admissao'])); ?></span>
                                <span class="text-[7px] font-bold uppercase opacity-50"><?php echo substr($meses[(int)date('m', strtotime($u['data_admissao']))], 0, 3); ?></span>
                            </div>

                            <!-- Botão de Aviso -->
                            <button onclick="enviarAviso(<?php echo $u['id']; ?>)" 
                                    title="Enviar aviso de documentação"
                                    class="w-10 h-10 bg-white shadow-sm border border-border rounded-xl flex items-center justify-center text-text-secondary hover:text-primary hover:border-primary transition-all group/btn">
                                <i data-lucide="mail-warning" class="w-5 h-5 group-hover/btn:scale-110 transition-transform"></i>
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
                            <div class="flex flex-col gap-0.5 mt-1">
                                <span class="text-[8px] font-bold text-text-secondary/60 flex items-center gap-1 uppercase tracking-tighter">
                                    <i data-lucide="briefcase" class="w-2.5 h-2.5"></i>
                                    <?php echo $u['setor_nome'] ?: 'Setor não informado'; ?>
                                </span>
                                <span class="text-[8px] font-bold text-primary/40 flex items-center gap-1 uppercase tracking-tighter">
                                    <i data-lucide="calendar" class="w-2.5 h-2.5"></i>
                                    Adm: <?php echo date('d/m/Y', strtotime($u['data_admissao'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Selector -->
                    <div class="mt-auto">
                        <label class="block text-[8px] font-black text-text-secondary uppercase tracking-widest mb-2 opacity-50 ml-1">Status do Periódico</label>
                        <div class="grid grid-cols-3 gap-1.5 p-1 bg-gray-50 rounded-2xl border border-border/50">
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
            <?php else: ?>
                <div class="col-span-full py-24 text-center bg-white rounded-3xl border-2 border-dashed border-border/60">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="calendar-x" class="w-10 h-10 text-text-secondary opacity-20"></i>
                    </div>
                    <h4 class="text-sm font-bold text-text mb-1">Nenhum periódico encontrado</h4>
                    <p class="text-xs text-text-secondary">Não há colaboradores com data de admissão no mês de <?php echo $meses[$mes_selecionado]; ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function enviarAviso(usuarioId) {
            if (!confirm('Deseja enviar um e-mail de aviso para este colaborador?')) return;

            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>';
            lucide.createIcons();
            btn.disabled = true;

            const formData = new FormData();
            formData.append('acao', 'enviar_aviso');
            formData.append('usuario_id', usuarioId);

            try {
                const response = await fetch('seguranca_trabalho.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('E-mail enviado com sucesso!');
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('Erro na requisição.');
            } finally {
                btn.innerHTML = originalContent;
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
                const response = await fetch('seguranca_trabalho.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao atualizar status: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('Erro na requisição.');
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>