<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Filtros
$filtro_cat = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : '';
$where_clauses = ["m.ativo = 1 AND (m.data_expiracao IS NULL OR m.data_expiracao >= CURDATE())"];

if ($filtro_cat) {
    $where_clauses[] = "m.categoria = '$filtro_cat'";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Buscar avisos
$sql = "
    SELECT m.*, u.nome as autor_nome 
    FROM mural m
    LEFT JOIN usuarios u ON m.autor_id = u.id
    $where_sql
    ORDER BY m.prioridade DESC, m.created_at DESC
";
$res = $conn->query($sql);
$avisos = [];
$stats = ['Informativo' => 0, 'Evento' => 0, 'Urgente' => 0, 'RH' => 0];

while($row = $res->fetch_assoc()) {
    $avisos[] = $row;
    if (isset($stats[$row['categoria']])) $stats[$row['categoria']]++;
}

$categorias = [
    'Informativo' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'icon' => 'info', 'border' => 'border-blue-100'],
    'Evento' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => 'calendar', 'border' => 'border-emerald-100'],
    'Urgente' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'icon' => 'alert-circle', 'border' => 'border-rose-100'],
    'RH' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'icon' => 'users', 'border' => 'border-purple-100']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mural de Avisos - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-5xl mx-auto flex-grow">
        <!-- Header Section (Slim) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="megaphone" class="w-6 h-6"></i>
                    Mural de Avisos
                </h1>
                <p class="text-text-secondary text-xs mt-1">Comunicados e notícias institucionais da unidade</p>
            </div>

            <?php if (isAdmin()): ?>
            <a href="admin/mural_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                <i data-lucide="settings" class="w-4 h-4"></i>
                Gerenciar Mural
            </a>
            <?php endif; ?>
        </div>

        <!-- Quick Stats / Filters (Slim) -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <a href="mural.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_cat ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($avisos); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach($categorias as $nome => $style): 
                $active = ($filtro_cat == $nome);
            ?>
            <a href="?categoria=<?php echo $nome; ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $style['icon']; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$nome]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $nome; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Notices List -->
        <div class="space-y-4">
            <?php if (count($avisos) > 0): ?>
                <?php foreach ($avisos as $aviso): 
                    $cat = isset($categorias[$aviso['categoria']]) ? $categorias[$aviso['categoria']] : $categorias['Informativo'];
                    $is_prioridade_alta = $aviso['prioridade'] == 'Alta';
                ?>
                <div class="bg-white rounded-xl shadow-sm border <?php echo $is_prioridade_alta ? 'border-red-200 bg-red-50/10' : 'border-border'; ?> overflow-hidden hover:shadow-md transition-all group relative">
                    <?php if ($is_prioridade_alta): ?>
                        <div class="absolute top-0 right-0 px-3 py-1 bg-red-600 text-white text-[9px] font-black uppercase tracking-widest rounded-bl-xl animate-pulse flex items-center gap-1.5 shadow-lg">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                            Urgente
                        </div>
                    <?php endif; ?>

                    <div class="p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="px-2 py-0.5 <?php echo $cat['bg']; ?> <?php echo $cat['text']; ?> rounded-md text-[9px] font-black uppercase tracking-widest border <?php echo $cat['border']; ?> flex items-center gap-1.5">
                                <i data-lucide="<?php echo $cat['icon']; ?>" class="w-2.5 h-2.5"></i>
                                <?php echo $aviso['categoria']; ?>
                            </span>
                            <span class="text-[10px] font-bold text-text-secondary/40">•</span>
                            <div class="flex items-center gap-3 text-[10px] font-bold text-text-secondary uppercase tracking-tighter">
                                <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> <?php echo date('d/m/Y', strtotime($aviso['created_at'])); ?></span>
                                <span class="flex items-center gap-1"><i data-lucide="user" class="w-3 h-3"></i> <?php echo $aviso['autor_nome']; ?></span>
                            </div>
                        </div>

                        <h2 class="text-base font-bold text-text mb-2 tracking-tight group-hover:text-primary transition-colors">
                            <?php echo $aviso['titulo']; ?>
                        </h2>
                        
                        <div class="text-[13px] text-text-secondary leading-relaxed max-w-none">
                            <?php echo nl2br($aviso['conteudo']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl border-2 border-dashed border-border p-16 text-center shadow-sm">
                    <div class="w-16 h-16 bg-background rounded-full flex items-center justify-center mx-auto mb-4 text-text-secondary opacity-20">
                        <i data-lucide="megaphone-off" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-sm font-bold text-text mb-1">Fila de avisos vazia</h3>
                    <p class="text-text-secondary text-[11px] max-w-xs mx-auto">Nenhum comunicado cadastrado nesta categoria ou para este período.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
