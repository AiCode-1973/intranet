<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Filtro de data (Padrão: mês atual)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

$sql = "
    SELECT a.*, u.nome as autor_nome 
    FROM agenda a
    LEFT JOIN usuarios u ON a.autor_id = u.id
    WHERE a.ativo = 1 
    AND MONTH(a.data_evento) = $mes 
    AND YEAR(a.data_evento) = $ano
    ORDER BY a.data_evento ASC, a.hora_inicio ASC
";
$res_eventos = $conn->query($sql);
$eventos = [];
$stats = ['Total' => 0, 'Hoje' => 0, 'Reunião' => 0, 'Treinamento' => 0];

$hoje = date('Y-m-d');
while($row = $res_eventos->fetch_assoc()) {
    $eventos[] = $row;
    $stats['Total']++;
    if ($row['data_evento'] == $hoje) $stats['Hoje']++;
    if (isset($stats[$row['categoria']])) $stats[$row['categoria']]++;
}

$categorias = [
    'Reunião' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => 'users', 'border' => 'border-emerald-100'],
    'Treinamento' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'icon' => 'graduation-cap', 'border' => 'border-indigo-100'],
    'Celebração' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'icon' => 'star', 'border' => 'border-amber-100'],
    'Importante' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'icon' => 'alert-circle', 'border' => 'border-rose-100'],
    'Outros' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'icon' => 'calendar', 'border' => 'border-gray-100']
];

$meses_pt = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de Eventos - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-5xl mx-auto flex-grow">
        <!-- Header Section (Slim) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="calendar" class="w-6 h-6"></i>
                    Agenda Institucional
                </h1>
                <p class="text-text-secondary text-xs mt-1">Calendário de reuniões e eventos da unidade</p>
            </div>

            <div class="flex items-center gap-2">
                <div class="bg-white p-1 rounded-lg border border-border flex items-center gap-1 shadow-sm">
                    <form method="GET" class="flex items-center gap-1">
                        <select name="mes" class="bg-transparent text-[11px] font-bold text-text-secondary focus:outline-none px-2 py-1 cursor-pointer">
                            <?php foreach($meses_pt as $m => $nome): ?>
                                <option value="<?php echo $m; ?>" <?php echo $mes == $m ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="ano" class="bg-transparent text-[11px] font-bold text-text-secondary focus:outline-none px-2 py-1 cursor-pointer">
                            <?php for($a = date('Y')-1; $a <= date('Y')+1; $a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo $ano == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="p-1.5 bg-primary/5 text-primary rounded-md hover:bg-primary hover:text-white transition-all">
                            <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
                <?php if (isAdmin()): ?>
                    <a href="admin/agenda_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        Gerenciar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats (Slim Style) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group transition-all">
                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Total']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">No Mês</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group transition-all">
                <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-500">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Hoje']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Para Hoje</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group transition-all">
                <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500">
                    <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Treinamento']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Treinamentos</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group transition-all">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-500">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Reunião']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Reuniões</p>
                </div>
            </div>
        </div>

        <!-- Event List (Timeline Style Slim) -->
        <div class="space-y-4">
            <?php if (count($eventos) > 0): ?>
                <?php 
                $data_agrupador = '';
                foreach ($eventos as $evento): 
                    $data_raw = $evento['data_evento'];
                    $dia = date('d', strtotime($data_raw));
                    $data_formatada = date('d/m/Y', strtotime($data_raw));
                    $cat = isset($categorias[$evento['categoria']]) ? $categorias[$evento['categoria']] : $categorias['Outros'];
                    $is_hoje = ($data_raw == $hoje);
                ?>
                    <?php if ($data_agrupador != $data_raw): 
                        $data_agrupador = $data_raw;
                    ?>
                        <div class="pt-4 pb-2 border-b border-border/50 flex items-center gap-3">
                            <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest"><?php echo $data_formatada; ?></span>
                            <?php if($is_hoje): ?>
                                <span class="bg-rose-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded uppercase animate-pulse">Hoje</span>
                            <?php endif; ?>
                            <div class="flex-grow h-px bg-border/40"></div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-xl border border-border p-4 flex gap-5 hover:border-primary/50 transition-all group shadow-sm hover:shadow-md relative overflow-hidden">
                        <?php if($is_hoje): ?>
                            <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
                        <?php endif; ?>

                        <!-- Date/Time Column -->
                        <div class="flex flex-col items-center justify-center min-w-[50px] border-r border-border/50 pr-4">
                            <span class="text-xl font-black <?php echo $is_hoje ? 'text-rose-500' : 'text-primary'; ?> leading-tight"><?php echo $dia; ?></span>
                            <span class="text-[10px] font-bold text-text-secondary/60 uppercase"><?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></span>
                        </div>

                        <!-- Content Column -->
                        <div class="flex-grow">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h4 class="text-sm font-bold text-text tracking-tight group-hover:text-primary transition-colors"><?php echo $evento['titulo']; ?></h4>
                                    <span class="px-1.5 py-0.5 <?php echo $cat['bg']; ?> <?php echo $cat['text']; ?> rounded-md text-[9px] font-black uppercase tracking-widest border <?php echo $cat['border']; ?> flex items-center gap-1">
                                        <i data-lucide="<?php echo $cat['icon']; ?>" class="w-2.5 h-2.5"></i>
                                        <?php echo $evento['categoria']; ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] font-bold text-text-secondary/50 uppercase tracking-tighter">
                                    <?php if ($evento['hora_fim']): ?>
                                        <span>Até <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="text-xs text-text-secondary leading-relaxed mb-3">
                                <?php echo nl2br($evento['descricao']); ?>
                            </p>

                            <div class="flex items-center gap-4 text-[10px] font-bold text-text-secondary uppercase opacity-60">
                                <?php if ($evento['local_evento']): ?>
                                <span class="flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-3 h-3 text-primary"></i>
                                    <?php echo $evento['local_evento']; ?>
                                </span>
                                <?php endif; ?>
                                
                                <span class="flex items-center gap-1.5">
                                    <i data-lucide="user" class="w-3 h-3 text-primary"></i>
                                    Org: <?php echo $evento['autor_nome']; ?>
                                </span>

                                <?php if ($evento['reserva_projetor']): ?>
                                <span class="flex items-center gap-1 px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded-md border border-blue-100 animate-in fade-in zoom-in duration-300">
                                    <i data-lucide="projector" class="w-2.5 h-2.5"></i>
                                    Projetor
                                </span>
                                <?php endif; ?>

                                <?php if ($evento['reserva_notebook']): ?>
                                <span class="flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded-md border border-indigo-100 animate-in fade-in zoom-in duration-300">
                                    <i data-lucide="laptop" class="w-2.5 h-2.5"></i>
                                    Notebook
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl border-2 border-dashed border-border p-16 text-center shadow-sm">
                    <div class="w-16 h-16 bg-background rounded-full flex items-center justify-center mx-auto mb-4 text-text-secondary opacity-20">
                        <i data-lucide="calendar-off" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-sm font-bold text-text mb-1">Nenhum evento registrado</h3>
                    <p class="text-text-secondary text-[10px] max-w-xs mx-auto">Não há compromissos para este mês selecionado.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
