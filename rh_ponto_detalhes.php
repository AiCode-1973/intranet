<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$is_rh = isRHAdmin();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header("Location: rh.php");
    exit();
}

// Buscar Ocorrência
$stmt = $conn->prepare("
    SELECT o.*, u.nome as colaborador_nome, s.nome as supervisor_nome 
    FROM rh_ponto_ocorrencias o 
    JOIN usuarios u ON o.usuario_id = u.id 
    JOIN usuarios s ON o.supervisor_id = s.id 
    WHERE o.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();

if (!$o) {
    header("Location: rh.php");
    exit();
}

// Verificar Permissão de Acesso
// Apenas o Colaborador, o Supervisor designado ou o RH podem ver
if ($o['usuario_id'] != $usuario_id && $o['supervisor_id'] != $usuario_id && !$is_rh) {
    header("Location: rh.php");
    exit();
}

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    $obs = sanitize($_POST['observacao'] ?? '');

    if ($acao == 'validar' && $o['supervisor_id'] == $usuario_id) {
        $conn->query("UPDATE rh_ponto_ocorrencias SET status = 'VALIDADO', observacao_supervisor = '$obs' WHERE id = $id");
        $mensagem = "Ocorrência validada e encaminhada ao RH!";
        header("Location: rh.php?msg=sucesso_validacao");
        exit();
    }

    if ($acao == 'aprovar' && $is_rh) {
        $conn->query("UPDATE rh_ponto_ocorrencias SET status = 'APROVADO', observacao_rh = '$obs' WHERE id = $id");
        $mensagem = "Ocorrência aprovada com sucesso!";
        header("Location: admin/rh_gerenciar.php?msg=sucesso_aprovacao");
        exit();
    }

    if ($acao == 'rejeitar') {
        if ($is_rh) {
            // Se o RH rejeita, volta para PENDENTE para o Supervisor revisar com base no motivo do RH
            $conn->query("UPDATE rh_ponto_ocorrencias SET status = 'PENDENTE', observacao_rh = '$obs' WHERE id = $id");
            header("Location: admin/rh_gerenciar.php?msg=sucesso_rejeicao");
        } else {
            // Se o Supervisor rejeita, volta para REJEITADO para o Colaborador editar
            $conn->query("UPDATE rh_ponto_ocorrencias SET status = 'REJEITADO', observacao_supervisor = '$obs' WHERE id = $id");
            header("Location: rh.php?msg=rejeitado");
        }
        exit();
    }
}

// Buscar Itens
$itens = $conn->query("SELECT * FROM rh_ponto_itens WHERE ocorrencia_id = $id ORDER BY data_ponto ASC");

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Ocorrência - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm 10mm; }
            .no-print { display: none !important; }
            .bg-pattern { background: none !important; }
            .shadow-xl, .shadow-2xl { box-shadow: none !important; }
            .rounded-3xl, .rounded-2xl { border-radius: 4px !important; }
            .border-border { border-color: #ccc !important; }
            header, aside, nav, footer, #sidebar, [class*="sidebar"] { display: none !important; }
            body { margin: 0 !important; padding: 0 !important; font-size: 10px !important; }
            .ml-64 { margin-left: 0 !important; }
            .p-6 { padding: 0 !important; }
            .p-8, .md\:p-12 { padding: 8px !important; }
            .max-w-5xl { max-width: 100% !important; }
            .mb-12 { margin-bottom: 8px !important; }
            .mb-8 { margin-bottom: 6px !important; }
            .mt-16 { margin-top: 12px !important; }
            .mt-8 { margin-top: 8px !important; }
            .gap-16 { gap: 20px !important; }
            table { font-size: 9px !important; width: 100% !important; }
            th, td { padding: 3px 5px !important; font-size: 9px !important; }
            h2 { font-size: 13px !important; }
            .text-6xl { font-size: 36px !important; }
            .space-y-4 > * + * { margin-top: 4px !important; }
        }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 bg-pattern">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-5xl mx-auto min-h-screen">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 no-print">
            <div>
                <h1 class="text-2xl font-black text-primary tracking-tight">Ocorrência de Ponto #<?php echo $id; ?></h1>
                <p class="text-text-secondary text-xs font-medium">Visualização e processamento da solicitação.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.print()" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-text rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Imprimir
                </button>
                <a href="<?php echo $is_rh ? 'admin/rh_gerenciar.php' : 'rh.php'; ?>" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-text rounded-xl text-xs font-bold transition-all shadow-sm">Voltar</a>
            </div>
        </div>

        <?php if ($o['status'] == 'PENDENTE' && $o['observacao_rh']): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-2xl flex items-center gap-4 animate-in pulse duration-1000">
                <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-red-900">Retorno do RH</h3>
                    <p class="text-xs text-red-800/80">O RH solicitou ajustes nesta ocorrência: <span class="font-bold">"<?php echo $o['observacao_rh']; ?>"</span></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 md:p-12 rounded-3xl border border-border shadow-2xl relative overflow-hidden">
            <!-- Marca d'água de status -->
            <div class="absolute top-10 right-10 rotate-12 opacity-10 pointer-events-none">
                <span class="text-6xl font-black uppercase"><?php echo $o['status']; ?></span>
            </div>

            <!-- Cabeçalho (Estilo Papel Timbrado) -->
            <div class="flex flex-col md:flex-row justify-between items-start mb-12 pb-8 border-b-2 border-primary/10">
                <div class="space-y-4">
                    <div class="mb-2">
                        <img src="imagens/logo_apas.png" alt="Logo APAS" class="h-12 object-contain">
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase text-text-secondary opacity-50 tracking-[0.2em]">Colaborador</span>
                        <h2 class="text-xl font-bold text-text"><?php echo $o['colaborador_nome']; ?></h2>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase text-text-secondary opacity-50 tracking-[0.2em]">Referência</span>
                        <p class="text-sm font-bold text-primary italic"><?php echo $meses[$o['mes']] . ' de ' . $o['ano']; ?></p>
                    </div>
                </div>
                <div class="mt-6 md:mt-0 text-right md:text-right space-y-4">
                   <div class="flex flex-col items-end">
                        <span class="text-[9px] font-black uppercase text-text-secondary opacity-50 tracking-[0.2em]">Tipo de Ajuste</span>
                        <span class="px-3 py-1 bg-primary/5 rounded-full text-[10px] font-black text-primary uppercase tracking-widest border border-primary/10"><?php echo $o['tipo'] == 'BANCO' ? 'Banco de Horas' : 'Horas Extras'; ?></span>
                   </div>
                   <div class="flex flex-col items-end">
                        <span class="text-[9px] font-black uppercase text-text-secondary opacity-50 tracking-[0.2em]">Status Atual</span>
                        <span class="text-xs font-black uppercase"><?php echo $o['status']; ?></span>
                   </div>
                </div>
            </div>

            <!-- Tabela de Dados -->
            <div class="overflow-x-auto mb-6">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border border-border">
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase border-r border-border w-28 text-center">Data</th>
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase border-r border-border w-20 text-center">Entrada</th>
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase border-r border-border w-20 text-center">S. Almoço</th>
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase border-r border-border w-20 text-center">V. Almoço</th>
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase border-r border-border w-20 text-center">Saída</th>
                            <th class="p-2 text-[10px] font-black text-text-secondary uppercase">Descrição / Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($i = $itens->fetch_assoc()): ?>
                        <tr class="border border-border hover:bg-gray-50/50">
                            <td class="p-2 text-xs font-bold border-r border-border text-center"><?php echo date('d/m/Y', strtotime($i['data_ponto'])); ?></td>
                            <td class="p-2 text-xs border-r border-border text-center"><?php echo $i['entrada'] ?: '-'; ?></td>
                            <td class="p-2 text-xs border-r border-border text-center"><?php echo $i['saida_almoco'] ?: '-'; ?></td>
                            <td class="p-2 text-xs border-r border-border text-center"><?php echo $i['volta_almoco'] ?: '-'; ?></td>
                            <td class="p-2 text-xs border-r border-border text-center"><?php echo $i['saida'] ?: '-'; ?></td>
                            <td class="p-2 text-xs italic text-text-secondary"><?php echo $i['descricao']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Assinaturas e Observações -->
            <?php if (!empty($o['observacao'])): ?>
            <div class="mt-8 p-5 bg-gray-50 border border-border rounded-2xl">
                <p class="text-[10px] font-black uppercase text-text-secondary tracking-wider mb-2">Observação Geral do Colaborador</p>
                <p class="text-sm text-text italic"><?php echo nl2br(htmlspecialchars($o['observacao'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Assinaturas -->
            <div class="grid grid-cols-2 gap-16 mt-16">
                <div>
                    <div class="border-t-2 border-gray-400 pt-4">
                        <p class="text-[10px] font-black uppercase text-text-secondary text-center tracking-widest">Assinatura do Colaborador</p>
                        <p class="text-xs font-bold text-center mt-1"><?php echo $o['colaborador_nome']; ?></p>
                    </div>
                </div>
                <div>
                    <div class="border-t-2 border-gray-400 pt-4">
                        <p class="text-[10px] font-black uppercase text-text-secondary text-center tracking-widest">Assinatura do Supervisor</p>
                        <p class="text-xs font-bold text-center mt-1"><?php echo $o['supervisor_nome']; ?></p>
                        <?php if($o['observacao_supervisor']): ?>
                            <p class="mt-4 p-3 bg-gray-50 rounded-xl text-xs italic border border-border">"<?php echo $o['observacao_supervisor']; ?>"</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <p class="text-[10px] text-text-secondary font-bold text-center mt-12 opacity-40">Documento gerado eletronicamente em <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <!-- Ações do Supervisor / RH (Somente se pendente/validado e tiver permissão) -->
        <?php if($o['status'] != 'APROVADO' && $o['status'] != 'REJEITADO'): ?>
            <div class="mt-8 bg-white p-8 rounded-3xl border border-border shadow-lg no-print">
                <h3 class="text-sm font-bold text-text mb-4">Ações de Validação</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Observações / Motivo (Opcional)</label>
                        <textarea name="observacao" rows="3" class="w-full p-3 bg-background border border-border rounded-xl text-xs outline-none focus:ring-1 focus:ring-primary" placeholder="Adicione um comentário..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="submit" name="acao" value="rejeitar" class="px-6 py-2 bg-red-50 text-red-600 rounded-xl text-xs font-bold hover:bg-red-100 transition-all">Rejeitar Solicitação</button>
                        
                        <?php if($o['status'] == 'PENDENTE' && $o['supervisor_id'] == $usuario_id): ?>
                            <button type="submit" name="acao" value="validar" class="px-8 py-2 bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/20 hover:scale-105 active:scale-95 transition-all">Validar (Enviar ao RH)</button>
                        <?php endif; ?>

                        <?php if($o['status'] == 'VALIDADO' && $is_rh): ?>
                            <button type="submit" name="acao" value="aprovar" class="px-8 py-2 bg-primary text-white rounded-xl text-xs font-bold shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all">Aprovar e Finalizar</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
