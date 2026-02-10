<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$where = "WHERE ativo = 1";
if ($search) {
    $where .= " AND (titulo LIKE '%$search%' OR conteudo LIKE '%$search%' OR categoria LIKE '%$search%')";
}

$artigos = $conn->query("SELECT * FROM ti_artigos $where ORDER BY categoria, titulo");
$categorias = [];
while($row = $artigos->fetch_assoc()) {
    $categorias[$row['categoria']][] = $row;
}

// Buscar reservas de equipamentos para os próximos 7 dias
$hoje_sql = date('Y-m-d');
$proximos_sql = date('Y-m-d', strtotime('+7 days'));
$reservas_equip = $conn->query("
    SELECT a.*, u.nome as autor_nome 
    FROM agenda a
    LEFT JOIN usuarios u ON a.autor_id = u.id
    WHERE a.ativo = 1 
    AND (a.reserva_projetor = 1 OR a.reserva_notebook = 1)
    AND a.data_evento >= '$hoje_sql'
    ORDER BY a.data_evento ASC, a.hora_inicio ASC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tecnologia da Informação - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-4 md:p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Header informativo solicitado -->
            <div class="bg-gradient-to-r from-primary to-indigo-600 rounded-2xl p-6 md:p-8 text-white mb-6 shadow-lg relative overflow-hidden">
                <div class="relative z-10">
                    <h1 class="text-2xl md:text-3xl font-black mb-2 tracking-tight">Tecnologia da Informação</h1>
                    <p class="text-white/80 text-sm md:text-base max-w-xl font-medium">Como podemos ajudar você hoje? Encontre tutoriais e resolva problemas comuns.</p>
                    
                    <div class="mt-6 max-w-lg">
                        <form action="ti_artigos.php" method="GET" class="relative">
                            <input type="text" name="search" value="<?php echo $search; ?>" placeholder="O que você está procurando?" class="w-full px-5 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-xl text-white placeholder:text-white/50 focus:outline-none focus:bg-white/20 transition-all shadow-inner text-sm">
                            <button type="submit" class="absolute right-2 top-2 bg-white text-primary p-1.5 rounded-lg shadow-lg hover:scale-105 transition-transform">
                                <i data-lucide="search" class="w-5 h-5"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <!-- Elementos decorativos -->
                <div class="absolute -right-6 -bottom-6 opacity-10">
                    <i data-lucide="monitor" class="w-48 h-48"></i>
                </div>
            </div>

            <!-- Card de Reservas de Equipamentos (Agenda TI) -->
            <?php if ($reservas_equip && $reservas_equip->num_rows > 0): ?>
            <div class="mb-8 p-6 bg-white rounded-3xl border border-border shadow-sm overflow-hidden relative group">
                <div class="absolute top-0 right-0 p-8 opacity-[0.03] group-hover:scale-110 transition-transform duration-700">
                    <i data-lucide="calendar-check" class="w-24 h-24"></i>
                </div>
                
                <div class="flex items-center justify-between mb-6 relative z-10">
                    <div>
                        <h2 class="text-sm font-black text-text uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                            Monitoramento de Equipamentos
                        </h2>
                        <p class="text-[10px] text-text-secondary font-bold uppercase mt-1 tracking-tighter opacity-60">Próximos agendamentos da agenda institucional</p>
                    </div>
                    <a href="agenda.php" class="text-[10px] font-black text-primary uppercase tracking-widest hover:underline flex items-center gap-1">
                        Ver Agenda Completa
                        <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 relative z-10">
                    <?php while($reserva = $reservas_equip->fetch_assoc()): 
                        $data_f = date('d/m', strtotime($reserva['data_evento']));
                        $hora_f = date('H:i', strtotime($reserva['hora_inicio']));
                        $is_hoje = ($reserva['data_evento'] == $hoje_sql);
                    ?>
                    <div class="p-4 rounded-2xl <?php echo $is_hoje ? 'bg-amber-50 border-amber-100' : 'bg-background/50 border-border'; ?> border flex flex-col gap-3 group/item hover:border-primary/30 transition-all">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black <?php echo $is_hoje ? 'text-amber-600' : 'text-primary'; ?> uppercase"><?php echo $is_hoje ? 'HOJE' : $data_f; ?> • <?php echo $hora_f; ?></span>
                                <h4 class="text-xs font-bold text-text truncate max-w-[150px]"><?php echo $reserva['titulo']; ?></h4>
                            </div>
                            <div class="flex gap-1">
                                <?php if($reserva['reserva_projetor']): ?>
                                    <div class="w-6 h-6 rounded-lg bg-white shadow-sm flex items-center justify-center text-primary" title="Projetor">
                                        <i data-lucide="projector" class="w-3.5 h-3.5"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if($reserva['reserva_notebook']): ?>
                                    <div class="w-6 h-6 rounded-lg bg-white shadow-sm flex items-center justify-center text-indigo-500" title="Notebook">
                                        <i data-lucide="laptop" class="w-3.5 h-3.5"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-[9px] font-bold text-text-secondary/60 uppercase">
                            <span class="flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3 h-3"></i>
                                <?php echo $reserva['local_evento'] ?: 'S/ Local'; ?>
                            </span>
                            <span class="truncate max-w-[80px]">Org: <?php echo $reserva['autor_nome']; ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($categorias)): ?>
                <div class="text-center py-20 bg-white rounded-3xl border border-border shadow-sm">
                    <div class="w-20 h-20 bg-primary/5 rounded-full flex items-center justify-center mx-auto mb-4 text-primary opacity-30">
                        <i data-lucide="help-circle" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-lg font-bold text-text">Nenhum artigo encontrado</h3>
                    <p class="text-text-secondary text-sm">Tente uma busca diferente ou navegue pelas categorias.</p>
                    <?php if ($search): ?>
                        <a href="ti_artigos.php" class="mt-4 inline-block text-primary font-bold text-xs uppercase tracking-widest border-b-2 border-primary/20 hover:border-primary transition-all">Limpar Busca</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($categorias as $cat => $arts): ?>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-border hover:shadow-md transition-shadow">
                            <h2 class="text-[10px] font-black text-primary uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                                <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                <?php echo $cat ?: 'Diversos'; ?>
                            </h2>
                            <div class="space-y-0.5">
                                <?php foreach ($arts as $art): ?>
                                    <a href="ti_artigo_ver.php?id=<?php echo $art['id']; ?>" class="flex items-center justify-between p-2 rounded-lg hover:bg-background transition-colors group">
                                        <span class="text-xs font-bold text-text group-hover:text-primary transition-colors"><?php echo $art['titulo']; ?></span>
                                        <i data-lucide="arrow-right" class="w-3 h-3 text-text-secondary opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- CTA para Suporte -->
            <div class="mt-12 bg-indigo-50 rounded-3xl p-8 border border-indigo-100 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4 text-center md:text-left">
                    <div class="w-14 h-14 bg-white rounded-2xl shadow-sm flex items-center justify-center text-indigo-500">
                        <i data-lucide="headset" class="w-8 h-8"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-indigo-900">Não encontrou o que precisava?</h3>
                        <p class="text-xs text-indigo-700 font-medium">Nossa equipe técnica está pronta para ajudar você pessoalmente.</p>
                    </div>
                </div>
                <a href="suporte.php" class="whitespace-nowrap bg-indigo-500 hover:bg-indigo-600 text-white px-8 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200 transition-all hover:-translate-y-1 active:scale-95">Abrir Chamado de TI</a>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    </div> <!-- Fecha o div pl-64 do header.php -->
</body>
</html>
