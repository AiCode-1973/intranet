<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar Aceite/Recusa do Colaborador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $troca_id = intval($_POST['troca_id']);
    
    if ($_POST['acao'] == 'aceitar') {
        $stmt = $conn->prepare("UPDATE trocas_plantao SET status = 'Aceito', aceite_colaborador = 1, data_aceite = NOW() WHERE id = ? AND colaborador_id = ? AND status = 'Pendente'");
        $stmt->bind_param("ii", $troca_id, $usuario_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $mensagem = "Troca aceita com sucesso! Agora aguarda aprovação da gerência.";
            $tipo_mensagem = "success";
        }
    } elseif ($_POST['acao'] == 'recusar') {
        $stmt = $conn->prepare("UPDATE trocas_plantao SET status = 'Recusado' WHERE id = ? AND colaborador_id = ? AND status = 'Pendente'");
        $stmt->bind_param("ii", $troca_id, $usuario_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $mensagem = "Troca recusada.";
            $tipo_mensagem = "warning";
        }
    }
}

// Buscar trocas onde o usuário é Solicitante
$minhas_solicitacoes = $conn->query("
    SELECT t.*, u.nome as nome_colaborador 
    FROM trocas_plantao t 
    JOIN usuarios u ON t.colaborador_id = u.id 
    WHERE t.solicitante_id = $usuario_id 
    ORDER BY t.created_at DESC
");

// Buscar trocas onde o usuário é Colaborador (Pedidos para ele)
$pedidos_recebidos = $conn->query("
    SELECT t.*, u.nome as nome_solicitante 
    FROM trocas_plantao t 
    JOIN usuarios u ON t.solicitante_id = u.id 
    WHERE t.colaborador_id = $usuario_id 
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Trocas de Plantão - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-4 md:p-6 w-full max-w-6xl mx-auto flex-grow">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-primary flex items-center gap-3">
                    <i data-lucide="refresh-cw" class="w-8 h-8"></i>
                    Trocas de Plantão
                </h1>
                <p class="text-text-secondary text-sm mt-1">Gerencie suas solicitações e pedidos de troca recebidos.</p>
            </div>
            <a href="plantao_nova.php" class="flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nova Solicitação
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-2xl border flex items-start gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-amber-50 border-amber-100 text-amber-700'; ?> animate-in fade-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span class="text-sm font-bold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Pedidos Recebidos -->
            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary flex items-center gap-2 px-2">
                    <i data-lucide="inbox" class="w-4 h-4 text-primary"></i>
                    Pedidos para Mim
                </h3>
                
                <div class="grid gap-4">
                    <?php if ($pedidos_recebidos->num_rows > 0): ?>
                        <?php while($t = $pedidos_recebidos->fetch_assoc()): ?>
                            <div class="bg-white p-5 rounded-3xl border border-border shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                            <?php echo substr($t['nome_solicitante'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-tighter text-text-secondary leading-none mb-1">Solicitante</p>
                                            <p class="text-sm font-bold text-text"><?php echo $t['nome_solicitante']; ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase border <?php 
                                        echo $t['status'] == 'Pendente' ? 'bg-amber-50 text-amber-600 border-amber-100' : 
                                            ($t['status'] == 'Aceito' || $t['status'] == 'Aprovado' ? 'bg-green-50 text-green-600 border-green-100' : 'bg-red-50 text-red-600 border-red-100'); 
                                    ?>">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4 p-4 bg-gray-50 rounded-2xl border border-border/50">
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-primary/60 mb-1">Meu Plantão</p>
                                        <p class="text-xs font-bold text-text"><?php echo date('d/m/Y', strtotime($t['data_plantao_colaborador'])); ?></p>
                                        <p class="text-[9px] text-text-secondary mt-1">Troca por: <span class="text-primary font-black"><?php echo date('d/m/Y', strtotime($t['data_troca_colaborador'])); ?></span></p>
                                    </div>
                                    <div class="border-l border-border/50 pl-4">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-amber-600/60 mb-1">Plantão do Colega</p>
                                        <p class="text-xs font-bold text-text"><?php echo date('d/m/Y', strtotime($t['data_plantao_solicitante'])); ?></p>
                                        <p class="text-[9px] text-text-secondary mt-1">Troca por: <span class="text-amber-600 font-black"><?php echo date('d/m/Y', strtotime($t['data_troca_solicitante'])); ?></span></p>
                                    </div>
                                </div>

                                <?php if ($t['status'] == 'Pendente'): ?>
                                    <div class="flex gap-2">
                                        <form method="POST" action="" class="flex-grow">
                                            <input type="hidden" name="troca_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" name="acao" value="aceitar" class="w-full py-2 bg-green-500 hover:bg-green-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-green-500/10">Aceitar Troca</button>
                                        </form>
                                        <form method="POST" action="">
                                            <input type="hidden" name="troca_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" name="acao" value="recusar" class="px-4 py-2 bg-white border border-border text-red-500 hover:bg-red-50 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all">Recusar</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-12 text-center bg-white rounded-3xl border border-border opacity-50 italic text-xs">Nenhum pedido recebido.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Minhas Solicitações -->
            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary flex items-center gap-2 px-2">
                    <i data-lucide="send" class="w-4 h-4 text-primary"></i>
                    Minhas Solicitações
                </h3>

                <div class="grid gap-4">
                    <?php if ($minhas_solicitacoes->num_rows > 0): ?>
                        <?php while($t = $minhas_solicitacoes->fetch_assoc()): ?>
                            <div class="bg-white p-5 rounded-3xl border border-border shadow-sm">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-600 font-bold text-sm">
                                            <?php echo substr($t['nome_colaborador'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-tighter text-text-secondary leading-none mb-1">Colega</p>
                                            <p class="text-sm font-bold text-text"><?php echo $t['nome_colaborador']; ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase border <?php 
                                        echo $t['status'] == 'Pendente' ? 'bg-amber-50 text-amber-600 border-amber-100' : 
                                            ($t['status'] == 'Aceito' || $t['status'] == 'Aprovado' ? 'bg-green-50 text-green-600 border-green-100' : 'bg-red-50 text-red-600 border-red-100'); 
                                    ?>">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-2xl border border-border/50">
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-primary/60 mb-1">Meu Plantão</p>
                                        <p class="text-xs font-bold text-text"><?php echo date('d/m/Y', strtotime($t['data_plantao_solicitante'])); ?></p>
                                        <p class="text-[9px] text-text-secondary mt-1">Troca por: <span class="text-primary font-black"><?php echo date('d/m/Y', strtotime($t['data_troca_solicitante'])); ?></span></p>
                                    </div>
                                    <div class="border-l border-border/50 pl-4">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-amber-600/60 mb-1">Plantão do Colega</p>
                                        <p class="text-xs font-bold text-text"><?php echo date('d/m/Y', strtotime($t['data_plantao_colaborador'])); ?></p>
                                        <p class="text-[9px] text-text-secondary mt-1">Troca por: <span class="text-amber-600 font-black"><?php echo date('d/m/Y', strtotime($t['data_troca_colaborador'])); ?></span></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-12 text-center bg-white rounded-3xl border border-border opacity-50 italic text-xs">Nenhuma solicitação enviada.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
