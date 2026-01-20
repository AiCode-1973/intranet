<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$curso_id = intval($_GET['id']);
$user_id = $_SESSION['usuario_id'];

// Buscar Curso
$res_curso = $conn->query("SELECT * FROM edu_cursos WHERE id = $curso_id");
if ($res_curso->num_rows == 0) header("Location: educacao.php");
$curso = $res_curso->fetch_assoc();

// Buscar Aulas
$aulas = $conn->query("SELECT a.*, p.concluido 
                       FROM edu_aulas a 
                       LEFT JOIN edu_progresso p ON a.id = p.aula_id AND p.usuario_id = $user_id 
                       WHERE a.curso_id = $curso_id 
                       ORDER BY a.ordem ASC");

$total_aulas = $aulas->num_rows;
$concluidas = 0;
$aulas_arr = [];
while($a = $aulas->fetch_assoc()) {
    if($a['concluido']) $concluidas++;
    $aulas_arr[] = $a;
}
$perc = ($total_aulas > 0) ? round(($concluidas / $total_aulas) * 100) : 0;

// Verificar se pode fazer a prova
$pode_prova = ($concluidas == $total_aulas && $total_aulas > 0);

// Verificar se já tem certificado
$certificado = $conn->query("SELECT * FROM edu_certificados WHERE usuario_id = $user_id AND curso_id = $curso_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $curso['titulo']; ?> - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="max-w-4xl mx-auto p-6 flex-grow">
        <a href="educacao.php" class="inline-flex items-center gap-2 text-[10px] font-black text-text-secondary hover:text-primary transition-colors mb-8 uppercase tracking-widest">
            <i data-lucide="chevron-left" class="w-4 h-4"></i> Módulos de Ensino
        </a>

        <div class="bg-white rounded-3xl shadow-xl border border-border overflow-hidden mb-8">
            <?php if ($curso['capa']): ?>
                <div class="w-full h-48 md:h-64 overflow-hidden relative flex items-center justify-center bg-gray-100">
                    <img src="<?php echo $curso['capa']; ?>" class="w-full h-full object-cover object-top">
                    <div class="absolute inset-0 bg-gradient-to-t from-white via-transparent to-transparent"></div>
                </div>
            <?php endif; ?>
            <div class="p-8">
            <div class="flex flex-col md:flex-row justify-between items-start gap-6">
                <div class="flex-grow">
                    <h1 class="text-3xl font-bold text-text mb-1 tracking-tight"><?php echo $curso['titulo']; ?></h1>
                    <p class="text-[11px] text-primary font-bold uppercase tracking-widest mb-4">Instrutor: <?php echo $curso['instrutor'] ?: 'Não informado'; ?></p>
                    <p class="text-text-secondary text-sm leading-relaxed mb-6"><?php echo $curso['descricao']; ?></p>
                    
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-background rounded-full border border-border">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-primary"></i>
                            <span class="text-[10px] font-black text-text-secondary uppercase"><?php echo $curso['carga_horaria']; ?></span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-background rounded-full border border-border">
                            <i data-lucide="book-open" class="w-3.5 h-3.5 text-primary"></i>
                            <span class="text-[10px] font-black text-text-secondary uppercase"><?php echo $total_aulas; ?> Lições</span>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-56 bg-gray-50/50 rounded-2xl p-6 border border-border text-center flex flex-col items-center">
                    <div class="relative w-20 h-20 mb-3">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle cx="40" cy="40" r="34" stroke="currentColor" stroke-width="6" fill="transparent" class="text-gray-200" />
                            <circle cx="40" cy="40" r="34" stroke="currentColor" stroke-width="6" fill="transparent" class="text-primary transition-all duration-1000" stroke-dasharray="213.6" stroke-dashoffset="<?php echo 213.6 - (213.6 * $perc / 100); ?>" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-lg font-black text-primary"><?php echo $perc; ?>%</span>
                    </div>
                    <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-4">Progresso</p>
                    
                    <?php if ($perc < 100 && $total_aulas > 0): ?>
                        <a href="edu_aula.php?id=<?php echo $aulas_arr[$concluidas]['id']; ?>" class="w-full py-2 bg-primary text-white rounded-xl text-[9px] font-black uppercase tracking-widest shadow-md hover:bg-primary-hover active:scale-95 transition-all">
                            <?php echo ($perc > 0) ? 'Retomar Aula' : 'Começar Agora'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lessons List (Curriculum Timeline) -->
        <div class="relative px-2">
            <h2 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] mb-8 flex items-center gap-2">
                <i data-lucide="list-checks" class="w-4 h-4 text-primary"></i>
                Conteúdo Programático
            </h2>
            
            <div class="relative space-y-8">
                <!-- Vertical Line -->
                <div class="absolute left-5 top-2 bottom-6 w-0.5 bg-gray-100"></div>

                <?php foreach($aulas_arr as $index => $a): ?>
                    <div class="relative flex items-start gap-6 group">
                        <!-- Icon / Stage Node -->
                        <div class="relative z-10 shrink-0 mt-1">
                            <?php if ($a['concluido']): ?>
                                <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center shadow-lg shadow-green-200">
                                    <i data-lucide="check" class="w-5 h-5"></i>
                                </div>
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-white border-2 <?php echo ($index == $concluidas) ? 'border-primary' : 'border-gray-200'; ?> flex items-center justify-center text-text-secondary group-hover:border-primary group-hover:text-primary transition-all shadow-sm">
                                    <span class="text-xs font-black"><?php echo $index + 1; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Content Card -->
                        <a href="edu_aula.php?id=<?php echo $a['id']; ?>" class="flex-grow bg-white p-5 rounded-2xl border border-border hover:border-primary hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-between group/card">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <?php 
                                        $icon = 'play-circle';
                                        $color = 'text-blue-500';
                                        $bg = 'bg-blue-50';
                                        if($a['tipo'] == 'pdf') { $icon = 'file-text'; $color = 'text-red-500'; $bg = 'bg-red-50'; }
                                        elseif($a['tipo'] == 'texto') { $icon = 'align-left'; $color = 'text-indigo-500'; $bg = 'bg-indigo-50'; }
                                        elseif($a['tipo'] == 'slide') { $icon = 'presentation'; $color = 'text-orange-500'; $bg = 'bg-orange-50'; }
                                    ?>
                                    <span class="px-2 py-0.5 <?php echo $bg; ?> <?php echo $color; ?> rounded text-[8px] font-black uppercase tracking-wider flex items-center gap-1">
                                        <i data-lucide="<?php echo $icon; ?>" class="w-2.5 h-2.5"></i>
                                        <?php echo $a['tipo']; ?>
                                    </span>
                                    <?php if ($a['concluido']): ?>
                                        <span class="text-[8px] font-black text-green-600 uppercase tracking-wider">Concluído</span>
                                    <?php elseif ($index == $concluidas): ?>
                                        <span class="text-[8px] font-black text-primary animate-pulse uppercase tracking-wider">Próxima Parada</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-sm font-bold text-text group-hover/card:text-primary transition-colors"><?php echo $a['titulo']; ?></h3>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-lucide="chevron-right" class="w-4 h-4 text-border group-hover/card:text-primary group-hover/card:translate-x-1 transition-all"></i>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Final Action: Exam or Certificate -->
        <div class="mt-12 p-8 rounded-3xl <?php echo $pode_prova ? 'bg-indigo-600' : 'bg-gray-100'; ?> text-white transition-all overflow-hidden relative">
            <?php if (!$pode_prova): ?>
                <div class="flex flex-col items-center text-center opacity-40">
                    <i data-lucide="lock" class="w-12 h-12 mb-4"></i>
                    <h3 class="text-lg font-bold text-text">Avaliação Final e Certificação</h3>
                    <p class="text-xs text-text-secondary">Conclua todas as lições acima para liberar sua prova.</p>
                </div>
            <?php elseif ($certificado): ?>
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">Parabéns!</h3>
                        <p class="text-white/80 text-sm">Você concluiu este curso e sua certificação já está disponível.</p>
                    </div>
                    <a href="edu_certificado.php?id=<?php echo $certificado['id']; ?>" target="_blank" class="bg-white text-indigo-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:scale-105 active:scale-95 transition-all flex items-center gap-2">
                        <i data-lucide="award" class="w-5 h-5"></i> Baixar Certificado
                    </a>
                </div>
            <?php else: ?>
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">Lições Concluídas!</h3>
                        <p class="text-white/80 text-sm">Você está pronto para realizar a avaliação final deste curso.</p>
                    </div>
                    <a href="edu_prova.php?id=<?php echo $curso_id; ?>" class="bg-white text-indigo-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:scale-105 active:scale-95 transition-all flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i> Iniciar Prova
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
