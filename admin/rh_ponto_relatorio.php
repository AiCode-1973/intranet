<?php
require_once '../config.php';
require_once '../functions.php';

requireRHAdmin();

$msg = $_GET['msg'] ?? '';

// Processar Exclusão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_registro') {
    $id_excluir = intval($_POST['id']);
    if ($conn->query("DELETE FROM rh_ponto_ocorrencias WHERE id = $id_excluir")) {
        $msg = "Registro excluído com sucesso!";
    } else {
        $msg = "Erro ao excluir registro.";
    }
}

// Filtros
$filtro_mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$filtro_ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
$filtro_supervisor = isset($_GET['supervisor_id']) ? intval($_GET['supervisor_id']) : '';
$filtro_usuario = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : '';

// Buscar Superiores (que têm subordinados)
$supervisores_res = $conn->query("
    SELECT DISTINCT s.id, s.nome 
    FROM usuarios s
    JOIN usuarios u ON u.superior_id = s.id
    WHERE s.ativo = 1
    ORDER BY s.nome
");

// Buscar Usuários (para o filtro)
$usuarios_res = $conn->query("SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome");

// Construir Query do Relatório
$where_clauses = ["o.status IN ('VALIDADO', 'APROVADO')"];
$params = [];
$types = "";

if ($filtro_mes) {
    $where_clauses[] = "o.mes = ?";
    $params[] = $filtro_mes;
    $types .= "i";
}
if ($filtro_ano) {
    $where_clauses[] = "o.ano = ?";
    $params[] = $filtro_ano;
    $types .= "i";
}
if ($filtro_supervisor) {
    $where_clauses[] = "o.supervisor_id = ?";
    $params[] = $filtro_supervisor;
    $types .= "i";
}
if ($filtro_usuario) {
    $where_clauses[] = "o.usuario_id = ?";
    $params[] = $filtro_usuario;
    $types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "
    SELECT o.*, u.nome as colaborador_nome, s.nome as supervisor_nome 
    FROM rh_ponto_ocorrencias o 
    JOIN usuarios u ON o.usuario_id = u.id 
    JOIN usuarios s ON o.supervisor_id = s.id 
    WHERE $where_sql
    ORDER BY u.nome ASC, o.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$relatorio_res = $stmt->get_result();

// Buscar itens de cada ocorrência para o PDF detalhado
$ocorrencias = [];
$ids_ocorrencias = [];
while($row = $relatorio_res->fetch_assoc()) {
    $ocorrencias[] = $row;
    $ids_ocorrencias[] = $row['id'];
}

$itens_por_ocorrencia = [];
if (!empty($ids_ocorrencias)) {
    $ids_list = implode(',', $ids_ocorrencias);
    $itens_res = $conn->query("SELECT * FROM rh_ponto_itens WHERE ocorrencia_id IN ($ids_list) ORDER BY data_ponto ASC");
    while($item = $itens_res->fetch_assoc()) {
        $itens_por_ocorrencia[$item['ocorrencia_id']][] = $item;
    }
}

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
    <title>Relatório de Ocorrências - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .shadow-sm { box-shadow: none !important; }
            .border { border-color: #eee !important; }
            .print-full { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto print-full">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
                    Relatório de Ocorrências de Ponto
                </h1>
                <p class="text-text-secondary text-[11px] uppercase font-bold tracking-wider">Visualização consolidada e filtros avançados</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="rh_gerenciar.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar
                </a>
                <button onclick="window.print()" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Imprimir
                </button>
                <button onclick="exportarPDF()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="file-down" class="w-4 h-4"></i> Baixar PDF
                </button>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl bg-white border border-border shadow-sm flex items-center gap-3 animate-in fade-in slide-in-from-top-4 no-print">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-bold text-text uppercase tracking-widest"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Filtros Section -->
        <div class="bg-white p-6 rounded-2xl border border-border shadow-sm mb-8 no-print">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Mês</label>
                    <select name="mes" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Todos</option>
                        <?php foreach($meses as $num => $nome): ?>
                            <option value="<?php echo $num; ?>" <?php echo $num == $filtro_mes ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Ano</label>
                    <select name="ano" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs outline-none focus:ring-1 focus:ring-primary">
                        <?php for($y=2024; $y<=2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $filtro_ano ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Equipe/Supervisor</label>
                    <select name="supervisor_id" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Todas as Equipes</option>
                        <?php while($s = $supervisores_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $filtro_supervisor ? 'selected' : ''; ?>><?php echo $s['nome']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Colaborador</label>
                    <select name="usuario_id" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Todos os Colaboradores</option>
                        <?php while($u = $usuarios_res->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $filtro_usuario ? 'selected' : ''; ?>><?php echo $u['nome']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-xl text-xs font-bold shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
                        Filtrar Resultados
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabela Relatório -->
        <div id="area-relatorio" class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden min-h-[400px]">
            <div class="p-6 border-b border-border flex justify-between items-center opacity-50">
                <div>
                    <h2 class="text-sm font-bold text-text uppercase tracking-tight">Listagem de Ocorrências</h2>
                    <p class="text-[10px] text-text-secondary font-medium">Resultados encontrados: <span class="text-primary"><?php echo count($ocorrencias); ?></span></p>
                </div>
                <div class="text-right hidden md:block">
                    <p class="text-[10px] font-black uppercase text-text-secondary"><?php echo date('d/m/Y H:i'); ?></p>
                    <p class="text-[9px] text-text-secondary opacity-60">Filtro: <?php echo $filtro_mes ? $meses[$filtro_mes] : 'Todos'; ?> / <?php echo $filtro_ano; ?></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Colaborador</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Mês/Ano</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Tipo</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Supervisor</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Status</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right no-print">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if (count($ocorrencias) > 0): ?>
                            <?php foreach($ocorrencias as $row): ?>
                            <tr class="hover:bg-background/30 transition-colors group">
                                <td class="p-4">
                                    <span class="text-sm font-bold text-text"><?php echo $row['colaborador_nome']; ?></span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-xs font-bold text-text-secondary"><?php echo $meses[$row['mes']] . ' / ' . $row['ano']; ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase <?php echo $row['tipo'] == 'BANCO' ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100'; ?>">
                                        <?php echo $row['tipo'] == 'BANCO' ? 'Banco de Horas' : 'Horas Extras'; ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-medium text-text-secondary"><?php echo $row['supervisor_nome']; ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase <?php echo $row['status'] == 'APROVADO' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right no-print flex justify-end gap-2">
                                    <a href="../rh_ponto_detalhes.php?id=<?php echo $row['id']; ?>" target="_blank" class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-all" title="Ver Detalhes">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>
                                    <button onclick="excluirRegistro(<?php echo $row['id']; ?>)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir Registro">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-12 text-center">
                                    <div class="flex flex-col items-center opacity-30">
                                        <i data-lucide="search-x" class="w-12 h-12 mb-4"></i>
                                        <p class="text-xs font-bold uppercase tracking-widest italic">Nenhuma ocorrência encontrada para estes filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Área Invisível para Exportação Detalhada -->
        <div id="area-detalhes-pdf" class="hidden print:block">
            <?php foreach($ocorrencias as $row): ?>
            <div class="mb-12" style="page-break-after: always;">
                <div class="p-8 border-b-4 border-primary bg-white mb-6">
                    <h2 class="text-2xl font-black text-primary mb-1 uppercase tracking-tighter"><?php echo $row['colaborador_nome']; ?></h2>
                    <p class="text-sm font-bold text-text-secondary uppercase tracking-widest">Relatório Individual de Ponto • <?php echo $meses[$row['mes']] . ' / ' . $row['ano']; ?> • <?php echo $row['tipo']; ?></p>
                </div>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 border text-[10px] font-black uppercase text-left">Data</th>
                            <th class="p-3 border text-[10px] font-black uppercase text-center">Entrada</th>
                            <th class="p-3 border text-[10px] font-black uppercase text-center">Saída Almoço</th>
                            <th class="p-3 border text-[10px] font-black uppercase text-center">Volta Almoço</th>
                            <th class="p-3 border text-[10px] font-black uppercase text-center">Saída</th>
                            <th class="p-3 border text-[10px] font-black uppercase text-left">Justificativa/Ocorrência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $itens = $itens_por_ocorrencia[$row['id']] ?? [];
                        foreach($itens as $it): 
                        ?>
                        <tr>
                            <td class="p-3 border text-xs font-bold"><?php echo date('d/m/Y', strtotime($it['data_ponto'])); ?></td>
                            <td class="p-3 border text-xs text-center"><?php echo $it['entrada'] ?: '-'; ?></td>
                            <td class="p-3 border text-xs text-center"><?php echo $it['saida_almoco'] ?: '-'; ?></td>
                            <td class="p-3 border text-xs text-center"><?php echo $it['volta_almoco'] ?: '-'; ?></td>
                            <td class="p-3 border text-xs text-center"><?php echo $it['saida'] ?: '-'; ?></td>
                            <td class="p-3 border text-[10px] leading-tight italic"><?php echo $it['descricao']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="mt-8 grid grid-cols-2 gap-12 no-print">
                    <div class="text-center pt-8 border-t border-gray-300">
                        <p class="text-[10px] font-black uppercase text-text-secondary mb-1">Assinatura do Colaborador</p>
                        <p class="text-xs font-bold"><?php echo $row['colaborador_nome']; ?></p>
                    </div>
                    <div class="text-center pt-8 border-t border-gray-300">
                        <p class="text-[10px] font-black uppercase text-text-secondary mb-1">Validação Superior</p>
                        <p class="text-xs font-bold"><?php echo $row['supervisor_nome']; ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function exportarPDF() {
            const element = document.getElementById('area-detalhes-pdf');
            element.classList.remove('hidden');
            
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     'relatorio-ponto-DETALHADO-<?php echo $filtro_mes . "-" . $filtro_ano; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                element.classList.add('hidden');
            });
        }

        function excluirRegistro(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente este registro de ocorrência?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_registro">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
