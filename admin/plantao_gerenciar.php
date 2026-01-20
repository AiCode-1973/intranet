<?php
require_once '../config.php';
require_once '../functions.php';

requireAdminDashboard();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar Aprovação/Reprovação da Gerência
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $troca_id = intval($_POST['troca_id']);
    
    if ($_POST['acao'] == 'aprovar') {
        $stmt = $conn->prepare("UPDATE trocas_plantao SET status = 'Aprovado', aprovacao_gerencia = 1, gerente_id = ?, data_aprovacao = NOW() WHERE id = ? AND status = 'Aceito'");
        $stmt->bind_param("ii", $usuario_id, $troca_id);
        if ($stmt->execute()) {
            $mensagem = "Troca de plantão aprovada com sucesso!";
            $tipo_mensagem = "success";
        }
    } elseif ($_POST['acao'] == 'reprovar') {
        $stmt = $conn->prepare("UPDATE trocas_plantao SET status = 'Reprovado', aprovacao_gerencia = 0, gerente_id = ?, data_aprovacao = NOW() WHERE id = ? AND status = 'Aceito'");
        $stmt->bind_param("ii", $usuario_id, $troca_id);
        if ($stmt->execute()) {
            $mensagem = "Troca de plantão reprovada.";
            $tipo_mensagem = "warning";
        }
    }
}

// Buscar todas as trocas pendentes de aprovação da gerência (que já foram aceitas pelo colaborador)
$trocas_pendentes = $conn->query("
    SELECT t.*, s.nome as nome_solicitante, c.nome as nome_colaborador, st.nome as nome_setor
    FROM trocas_plantao t 
    JOIN usuarios s ON t.solicitante_id = s.id 
    JOIN usuarios c ON t.colaborador_id = c.id 
    JOIN setores st ON s.setor_id = st.id
    WHERE t.status = 'Aceito' 
    ORDER BY t.created_at DESC
");

// Histórico recente
$historico = $conn->query("
    SELECT t.*, s.nome as nome_solicitante, c.nome as nome_colaborador
    FROM trocas_plantao t 
    JOIN usuarios s ON t.solicitante_id = s.id 
    JOIN usuarios c ON t.colaborador_id = c.id 
    WHERE t.status IN ('Aprovado', 'Reprovado', 'Recusado')
    ORDER BY t.updated_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Trocas de Plantão - Admin</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-4 md:p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-8">
            <h1 class="text-2xl font-black text-primary flex items-center gap-3">
                <i data-lucide="shield-check" class="w-8 h-8"></i>
                Gestão de Trocas de Plantão
            </h1>
            <p class="text-text-secondary text-sm mt-1">Aprovação final das trocas aceitas entre colaboradores.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-2xl border flex items-start gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-amber-50 border-amber-100 text-amber-700'; ?> animate-in fade-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span class="text-sm font-bold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Lista de Aprovações Pendentes -->
            <div class="lg:col-span-2 space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary px-2">Aprovações Aguardando Gerência</h3>
                
                <div class="grid gap-4">
                    <?php if ($trocas_pendentes->num_rows > 0): ?>
                        <?php while($t = $trocas_pendentes->fetch_assoc()): ?>
                            <div class="bg-white p-6 rounded-3xl border border-border shadow-sm flex flex-col md:flex-row gap-6 relative overflow-hidden">
                                <div class="flex-grow space-y-4">
                                    <div class="flex items-center gap-4">
                                        <span class="px-2 py-0.5 bg-primary/10 text-primary text-[9px] font-black uppercase rounded"><?php echo $t['nome_setor']; ?></span>
                                        <span class="text-[10px] text-text-secondary font-bold"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="p-4 bg-gray-50 rounded-2xl border border-border/50">
                                            <p class="text-[10px] font-black uppercase text-primary mb-2">Solicitante: <?php echo $t['nome_solicitante']; ?></p>
                                            <div class="space-y-1">
                                                <p class="text-xs text-text-secondary">Plantão: <span class="text-text font-bold"><?php echo date('d/m/Y', strtotime($t['data_plantao_solicitante'])); ?></span></p>
                                                <p class="text-xs text-text-secondary">Troca: <span class="text-text font-bold"><?php echo date('d/m/Y', strtotime($t['data_troca_solicitante'])); ?></span></p>
                                            </div>
                                        </div>
                                        <div class="p-4 bg-gray-50 rounded-2xl border border-border/50">
                                            <p class="text-[10px] font-black uppercase text-amber-600 mb-2">Colaborador: <?php echo $t['nome_colaborador']; ?></p>
                                            <div class="space-y-1">
                                                <p class="text-xs text-text-secondary">Plantão: <span class="text-text font-bold"><?php echo date('d/m/Y', strtotime($t['data_plantao_colaborador'])); ?></span></p>
                                                <p class="text-xs text-text-secondary">Troca: <span class="text-text font-bold"><?php echo date('d/m/Y', strtotime($t['data_troca_colaborador'])); ?></span></p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($t['observacoes']): ?>
                                        <p class="text-xs text-text-secondary bg-primary/5 p-3 rounded-xl italic">
                                            "<?php echo $t['observacoes']; ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col gap-2 justify-center shrink-0 md:w-40 border-t md:border-t-0 md:border-l border-border pt-4 md:pt-0 md:pl-6">
                                    <form method="POST" action="">
                                        <input type="hidden" name="troca_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" name="acao" value="aprovar" class="w-full py-3 bg-primary hover:bg-primary-hover text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-primary/10 mb-2">Aprovar</button>
                                        <button type="submit" name="acao" value="reprovar" class="w-full py-3 bg-white border border-border text-red-500 hover:bg-red-50 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all">Reprovar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-12 text-center bg-white rounded-3xl border border-border opacity-50 italic text-xs">Nenhuma troca aguardando aprovação.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Histórico Recente -->
            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary px-2">Histórico Recente</h3>
                <div class="bg-white rounded-3xl border border-border overflow-hidden shadow-sm">
                    <?php if ($historico->num_rows > 0): ?>
                        <div class="divide-y divide-border">
                            <?php while($h = $historico->fetch_assoc()): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-xs font-bold text-text truncate pr-2"><?php echo $h['nome_solicitante']; ?> ↔ <?php echo $h['nome_colaborador']; ?></p>
                                        <span class="text-[8px] font-black px-1.5 py-0.5 rounded uppercase <?php echo $h['status'] == 'Aprovado' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                            <?php echo $h['status']; ?>
                                        </span>
                                    </div>
                                    <p class="text-[9px] text-text-secondary">Decisão em: <?php echo date('d/m H:i', strtotime($h['updated_at'])); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-[10px] text-text-secondary italic">Nenhum histórico.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
