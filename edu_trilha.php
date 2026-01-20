<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$trilha_id = intval($_GET['id']);
$user_id = $_SESSION['usuario_id'];

// Buscar Trilha
$res_trilha = $conn->query("SELECT * FROM edu_trilhas WHERE id = $trilha_id");
if ($res_trilha->num_rows == 0) header("Location: educacao.php");
$trilha = $res_trilha->fetch_assoc();

// Buscar Cursos da Trilha
$cursos = $conn->query("SELECT c.*, tc.ordem 
                        FROM edu_trilha_curso tc 
                        JOIN edu_cursos c ON tc.curso_id = c.id 
                        WHERE tc.trilha_id = $trilha_id 
                        ORDER BY tc.ordem ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $trilha['titulo']; ?> - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="min-h-screen flex flex-col">
        <!-- Hero Header -->
        <div class="bg-primary text-white py-12 px-6">
            <div class="max-w-5xl mx-auto">
                <a href="educacao.php" class="inline-flex items-center gap-2 text-xs font-bold text-white/60 hover:text-white transition-colors mb-6 uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar para Início
                </a>
                <h1 class="text-3xl font-bold mb-4"><?php echo $trilha['titulo']; ?></h1>
                <p class="text-white/70 max-w-2xl text-sm leading-relaxed"><?php echo $trilha['descricao']; ?></p>
            </div>
        </div>

        <div class="p-6 max-w-5xl mx-auto w-full -mt-8 flex-grow">
            <div class="grid grid-cols-1 gap-6">
                <?php while($c = $cursos->fetch_assoc()): ?>
                    <?php 
                        // Calcular progresso do curso
                        $total_aulas_res = $conn->query("SELECT COUNT(*) as t FROM edu_aulas WHERE curso_id = {$c['id']}");
                        $total_aulas = $total_aulas_res->fetch_assoc()['t'];
                        
                        $concluidas_res = $conn->query("SELECT COUNT(*) as t FROM edu_progresso p 
                                                      JOIN edu_aulas a ON p.aula_id = a.id 
                                                      WHERE p.usuario_id = $user_id AND a.curso_id = {$c['id']} AND p.concluido = 1");
                        $concluidas = $concluidas_res->fetch_assoc()['t'];
                        $perc = ($total_aulas > 0) ? round(($concluidas / $total_aulas) * 100) : 0;
                    ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-border overflow-hidden group hover:border-primary transition-all flex flex-col md:flex-row">
                        <div class="md:w-48 h-32 md:h-auto bg-gray-100 relative shrink-0">
                            <?php if ($c['capa']): ?>
                                <img src="<?php echo $c['capa']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-primary/5 text-primary opacity-20">
                                    <i data-lucide="book" class="w-10 h-10"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-2 left-2 w-6 h-6 bg-white/90 backdrop-blur rounded shadow-sm text-[10px] font-black flex items-center justify-center text-primary">
                                <?php echo $c['ordem']; ?>
                            </div>
                        </div>
                        <div class="p-6 flex-grow">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-text group-hover:text-primary transition-colors"><?php echo $c['titulo']; ?></h3>
                                    <p class="text-[10px] text-primary font-bold uppercase tracking-tight mb-1"><?php echo $c['instrutor'] ?: 'Autor não informado'; ?></p>
                                    <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest opacity-60"><?php echo $c['carga_horaria']; ?> de conteúdo</span>
                                </div>
                                <div class="w-full md:w-32 bg-gray-100 h-2 rounded-full overflow-hidden relative">
                                    <div class="h-full bg-primary transition-all duration-1000" style="width: <?php echo $perc; ?>%"></div>
                                    <span class="absolute right-0 -top-4 text-[9px] font-black text-text-secondary"><?php echo $perc; ?>%</span>
                                </div>
                            </div>
                            <p class="text-xs text-text-secondary mb-6 line-clamp-2"><?php echo $c['descricao']; ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-bold text-text-secondary uppercase">
                                    <i data-lucide="layers" class="w-3.5 h-3.5 inline mr-1"></i> <?php echo $total_aulas; ?> Lições
                                </span>
                                <a href="edu_curso.php?id=<?php echo $c['id']; ?>" class="bg-primary px-5 py-2 rounded-xl text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:bg-primary-hover active:scale-95 transition-all">
                                    <?php echo ($perc > 0) ? 'Continuar' : 'Começar'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
