<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar Exclusão de Ocorrência
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_ocorrencia') {
    $id_excluir = intval($_POST['id']);
    
    // Só permite excluir se for do próprio usuário e se estiver em status PENDENTE ou RASCUNHO/REJEITADO
    // Decisão: vamos permitir excluir apenas se não estiver VALIDADO/APROVADO para evitar quebra de fluxo
    $check = $conn->query("SELECT status FROM rh_ponto_ocorrencias WHERE id = $id_excluir AND usuario_id = $usuario_id");
    if ($check->num_rows > 0) {
        $status_atual = $check->fetch_assoc()['status'];
        if ($status_atual == 'PENDENTE' || $status_atual == 'RASCUNHO' || $status_atual == 'REJEITADO') {
            $conn->query("DELETE FROM rh_ponto_ocorrencias WHERE id = $id_excluir");
            $mensagem = "Ocorrência excluída com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Excluiu ocorrência de ponto ID: $id_excluir");
        } else {
            $mensagem = "Não é possível excluir uma ocorrência que já foi validada ou aprovada.";
            $tipo_mensagem = "danger";
        }
    }
}

// Buscar Ocorrências do Funcionário
$ocorrencias = $conn->query("
    SELECT o.*, u.nome as supervisor_nome 
    FROM rh_ponto_ocorrencias o 
    JOIN usuarios u ON o.supervisor_id = u.id 
    WHERE o.usuario_id = $usuario_id 
    ORDER BY o.created_at DESC
");

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$status_colors = [
    'PENDENTE' => 'bg-amber-50 text-amber-600 border-amber-100',
    'VALIDADO' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
    'APROVADO' => 'bg-blue-50 text-blue-600 border-blue-100',
    'REJEITADO' => 'bg-red-50 text-red-600 border-red-100',
    'RASCUNHO' => 'bg-gray-50 text-gray-500 border-gray-200'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Ocorrências de Ponto - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 bg-pattern">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-5xl mx-auto min-h-screen">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-black text-primary tracking-tight">Histórico de Ocorrências</h1>
                <p class="text-text-secondary text-xs font-medium">Acompanhe o status das suas solicitações de ajuste de ponto.</p>
            </div>
            
            <?php if ($mensagem): ?>
                <div class="fixed top-24 right-6 z-50 p-4 rounded-2xl border shadow-2xl animate-bounce-short <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                        <span class="text-xs font-black uppercase tracking-widest"><?php echo $mensagem; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex gap-2">
                <a href="rh.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-text rounded-xl text-xs font-bold transition-all shadow-sm">Voltar</a>
                <a href="rh_ponto_novo.php" class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-primary/20">Nova Ocorrência</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <?php if ($ocorrencias->num_rows > 0): ?>
                <?php while($o = $ocorrencias->fetch_assoc()): ?>
                <div class="bg-white p-6 rounded-3xl border border-border shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6 hover:border-primary/30 transition-all group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-gray-50 flex flex-col items-center justify-center text-text-secondary">
                            <span class="text-[9px] font-black uppercase leading-none"><?php echo substr($meses[$o['mes']], 0, 3); ?></span>
                            <span class="text-xs font-bold"><?php echo $o['ano']; ?></span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-text mb-1">Ocorrência #<?php echo $o['id']; ?> - <?php echo $o['tipo'] == 'BANCO' ? 'Banco de Horas' : 'Horas Extras'; ?></h3>
                            <p class="text-[10px] text-text-secondary font-medium italic">Supervisor: <span class="text-primary"><?php echo $o['supervisor_nome']; ?></span> • Enviado em <?php echo date('d/m/Y', strtotime($o['created_at'])); ?></p>
                            <?php if($o['status'] == 'REJEITADO' && $o['observacao_supervisor']): ?>
                                <p class="text-[9px] text-red-500 font-bold mt-2 flex items-center gap-1">
                                    <i data-lucide="info" class="w-3 h-3"></i> Motivo: <?php echo $o['observacao_supervisor']; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-tighter <?php echo $status_colors[$o['status']]; ?>">
                            <?php echo $o['status']; ?>
                        </div>
                        
                        <?php if($o['status'] == 'RASCUNHO' || $o['status'] == 'REJEITADO'): ?>
                            <a href="rh_ponto_novo.php?id=<?php echo $o['id']; ?>" class="p-2 text-primary hover:bg-primary/5 rounded-lg transition-all" title="Editar e Reenviar">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </a>
                        <?php else: ?>
                            <a href="rh_ponto_detalhes.php?id=<?php echo $o['id']; ?>" class="p-2 text-text-secondary hover:bg-gray-100 rounded-lg transition-all" title="Ver Detalhes">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>

                        <?php if($o['status'] == 'PENDENTE' || $o['status'] == 'RASCUNHO' || $o['status'] == 'REJEITADO'): ?>
                            <button onclick="excluirOcorrencia(<?php echo $o['id']; ?>)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir Ocorrência">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="py-20 text-center bg-white/50 border-2 border-dashed border-border rounded-3xl">
                    <i data-lucide="calendar-x" class="w-12 h-12 text-text-secondary/20 mx-auto mb-4"></i>
                    <p class="text-xs font-bold text-text-secondary uppercase tracking-widest italic">Nenhuma ocorrência registrada.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <form id="formExcluir" method="POST" action="" style="display:none;">
        <input type="hidden" name="acao" value="excluir_ocorrencia">
        <input type="hidden" name="id" id="excluir_id">
    </form>

    <script>
        function excluirOcorrencia(id) {
            if (confirm('Tem certeza que deseja excluir esta ocorrência? Esta ação não pode ser desfeita.')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
    </script>
</body>
</html>
