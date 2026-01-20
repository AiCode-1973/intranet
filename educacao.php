<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$user_id = $_SESSION['usuario_id'];

// Buscar Trilhas Ativas
$trilhas = $conn->query("SELECT * FROM edu_trilhas WHERE status = 1 ORDER BY created_at DESC");

// Calcular Progresso Global (Opcional, simplificado por enquanto)
$total_aulas = $conn->query("SELECT COUNT(*) as t FROM edu_aulas")->fetch_assoc()['t'];
$concluidas = $conn->query("SELECT COUNT(*) as t FROM edu_progresso WHERE usuario_id = $user_id AND concluido = 1")->fetch_assoc()['t'];
$percentual_global = ($total_aulas > 0) ? round(($concluidas / $total_aulas) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Aprendizagem - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Dashboard Style Header -->
        <div class="mb-10 flex flex-col md:flex-row justify-between items-end md:items-center gap-6">
            <div>
                <h1 class="text-2xl font-bold text-primary flex items-center gap-3">
                    <i data-lucide="graduation-cap" class="w-8 h-8"></i>
                    Portal do Conhecimento
                </h1>
                <p class="text-text-secondary text-sm mt-1">Desenvolva suas habilidades através de nossas trilhas guiadas.</p>
            </div>

            <div class="w-full md:w-64 bg-white p-4 rounded-2xl shadow-sm border border-border">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Progresso Global</span>
                    <span class="text-xs font-bold text-primary"><?php echo $percentual_global; ?>%</span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-primary transition-all duration-1000" style="width: <?php echo $percentual_global; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Section: Trilhas de Aprendizado -->
        <div class="mb-6 border-b border-border pb-2">
            <h2 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em]">Trilhas Disponíveis</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <?php while($t = $trilhas->fetch_assoc()): ?>
                <a href="edu_trilha.php?id=<?php echo $t['id']; ?>" class="bg-white rounded-3xl border border-border shadow-sm hover:border-primary hover:shadow-2xl transition-all group overflow-hidden flex flex-col">
                    <div class="h-40 bg-gray-100 overflow-hidden relative group-hover:bg-primary/5 transition-colors">
                        <div class="absolute inset-0 flex items-center justify-center opacity-10 group-hover:scale-110 transition-transform duration-700">
                             <i data-lucide="map" class="w-24 h-24"></i>
                        </div>
                        <div class="absolute bottom-4 left-4">
                            <span class="px-2 py-1 bg-white/90 backdrop-blur rounded-full text-[9px] font-black text-primary uppercase border border-primary/10">Trilha de Aprendizado</span>
                        </div>
                    </div>
                    
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="text-lg font-bold text-text mb-2 group-hover:text-primary transition-colors"><?php echo $t['titulo']; ?></h3>
                        <p class="text-xs text-text-secondary leading-relaxed mb-6 line-clamp-3">
                            <?php echo $t['descricao'] ?: 'Explore a jornada de conhecimento preparada especialmente para você.'; ?>
                        </p>
                        
                        <div class="mt-auto flex items-center justify-between pt-4 border-t border-border">
                            <?php 
                                $count_cursos = $conn->query("SELECT COUNT(*) as c FROM edu_trilha_curso WHERE trilha_id = {$t['id']}")->fetch_assoc()['c'];
                            ?>
                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-text-secondary uppercase">
                                <i data-lucide="book" class="w-3.5 h-3.5"></i>
                                <?php echo $count_cursos; ?> Cursos
                            </div>
                            <span class="text-[10px] font-black text-primary uppercase flex items-center gap-1">
                                Iniciar Jornada <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Inline: Cursos Avulsos (Opcional, mas bom ter) -->
        <div class="mb-6 border-b border-border pb-2 flex justify-between items-end">
            <h2 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em]">Cursos Avulsos</h2>
            <a href="#" class="text-[10px] font-bold text-text-secondary hover:text-primary transition-colors uppercase">Ver Todos</a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php 
            $cursos_livres = $conn->query("SELECT * FROM edu_cursos 
                                          WHERE status = 1 
                                          AND id NOT IN (SELECT curso_id FROM edu_trilha_curso) 
                                          ORDER BY RAND() LIMIT 4");
            while($cl = $cursos_livres->fetch_assoc()):
            ?>
                <a href="edu_curso.php?id=<?php echo $cl['id']; ?>" class="bg-white rounded-2xl border border-border hover:border-indigo-500 transition-all group overflow-hidden flex flex-col">
                    <div class="h-24 bg-gray-100 shrink-0">
                        <?php if ($cl['capa']): ?>
                            <img src="<?php echo $cl['capa']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-indigo-500/20">
                                <i data-lucide="book" class="w-8 h-8"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 flex-grow flex flex-col">
                        <h4 class="text-xs font-bold text-text mb-0.5 truncate uppercase"><?php echo $cl['titulo']; ?></h4>
                        <p class="text-[9px] text-primary font-bold uppercase tracking-tight mb-1"><?php echo $cl['instrutor'] ?: 'Autor não informado'; ?></p>
                        <p class="text-[10px] text-text-secondary line-clamp-1 mb-3 opacity-60"><?php echo $cl['carga_horaria']; ?> de capacitação</p>
                        <div class="mt-auto w-full h-1 bg-gray-50 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 w-0 group-hover:w-full transition-all duration-500"></div>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
