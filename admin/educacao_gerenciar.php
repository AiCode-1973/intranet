<?php
require_once '../config.php';
require_once '../functions.php';

requireEduAdmin();

// Estatísticas Rápidas
$total_trilhas = $conn->query("SELECT COUNT(*) as t FROM edu_trilhas")->fetch_assoc()['t'];
$total_cursos = $conn->query("SELECT COUNT(*) as t FROM edu_cursos")->fetch_assoc()['t'];
$total_questoes = $conn->query("SELECT COUNT(*) as t FROM edu_questoes")->fetch_assoc()['t'];
$total_conclusoes = $conn->query("SELECT COUNT(*) as t FROM edu_certificados")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Educação Permanente - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-primary flex items-center gap-3">
                    <i data-lucide="graduation-cap" class="w-8 h-8"></i>
                    Painel Administrativo: Educação
                </h1>
                <p class="text-text-secondary text-sm mt-1">Gestão completa do ambiente de aprendizado (LMS)</p>
            </div>
            <a href="index.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-text rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard Admin
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex flex-col">
                <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest opacity-60">Trilhas</span>
                <span class="text-2xl font-bold text-primary"><?php echo $total_trilhas; ?></span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex flex-col">
                <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest opacity-60">Cursos</span>
                <span class="text-2xl font-bold text-indigo-500"><?php echo $total_cursos; ?></span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex flex-col">
                <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest opacity-60">Questões</span>
                <span class="text-2xl font-bold text-emerald-500"><?php echo $total_questoes; ?></span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex flex-col">
                <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest opacity-60">Certificados</span>
                <span class="text-2xl font-bold text-amber-500"><?php echo $total_conclusoes; ?></span>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Trilhas -->
            <a href="edu_trilhas.php" class="bg-white p-6 rounded-2xl border border-border group hover:border-primary hover:shadow-xl transition-all flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-500">
                    <i data-lucide="map" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Trilhas de Aprendizado</h3>
                <p class="text-xs text-text-secondary leading-relaxed mb-4">Organize cursos em roteiros para cargos ou setores específicos.</p>
                <div class="mt-auto px-4 py-1.5 bg-gray-50 text-[10px] font-black text-text-secondary uppercase rounded-full group-hover:bg-primary/5 group-hover:text-primary transition-colors">Gerenciar Trilhas</div>
            </a>

            <!-- Cursos -->
            <a href="edu_cursos.php" class="bg-white p-6 rounded-2xl border border-border group hover:border-indigo-500 hover:shadow-xl transition-all flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 mb-4 group-hover:bg-indigo-500 group-hover:text-white transition-all duration-500">
                    <i data-lucide="book-open" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Cursos & Aulas</h3>
                <p class="text-xs text-text-secondary leading-relaxed mb-4">Crie conteúdo, suba PDFs, links de vídeos e organize os módulos.</p>
                <div class="mt-auto px-4 py-1.5 bg-gray-50 text-[10px] font-black text-text-secondary uppercase rounded-full group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">Gerenciar Cursos</div>
            </a>

            <!-- Provas -->
            <a href="edu_provas.php" class="bg-white p-6 rounded-2xl border border-border group hover:border-emerald-500 hover:shadow-xl transition-all flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 mb-4 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
                    <i data-lucide="clipboard-check" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Provas & Questões</h3>
                <p class="text-xs text-text-secondary leading-relaxed mb-4">Configure avaliações, nota mínima e banco de questões por curso.</p>
                <div class="mt-auto px-4 py-1.5 bg-gray-50 text-[10px] font-black text-text-secondary uppercase rounded-full group-hover:bg-emerald-50 group-hover:text-emerald-600 transition-colors">Gerenciar Avaliações</div>
            </a>
        </div>

        <!-- Relatórios em breve -->
        <div class="mt-12 bg-gray-50 rounded-2xl border border-dashed border-border p-8 text-center">
            <div class="flex flex-col items-center gap-3">
                <i data-lucide="bar-chart-3" class="w-10 h-10 text-text-secondary opacity-30"></i>
                <h4 class="text-sm font-bold text-text-secondary opacity-60 uppercase tracking-widest">Painel de Relatórios & BI</h4>
                <p class="text-[11px] text-text-secondary italic">Em breve: Acompanhamento de performance, tempo de estudo e exportação para RH.</p>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
