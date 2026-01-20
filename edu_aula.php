<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$aula_id = intval($_GET['id']);
$user_id = $_SESSION['usuario_id'];

// Buscar Aula
$res_aula = $conn->query("SELECT a.*, c.titulo as curso_titulo FROM edu_aulas a JOIN edu_cursos c ON a.curso_id = c.id WHERE a.id = $aula_id");
if ($res_aula->num_rows == 0) header("Location: educacao.php");
$aula = $res_aula->fetch_assoc();

// Marcar como Concluída (Simplificado: ao abrir a aula, já marca. Em vídeo real, seria via JS no final do player)
$check = $conn->query("SELECT id FROM edu_progresso WHERE usuario_id = $user_id AND aula_id = $aula_id");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO edu_progresso (usuario_id, aula_id, concluido, data_conclusao) VALUES ($user_id, $aula_id, 1, NOW())");
}

// Próxima Aula
$proxima_aula = $conn->query("SELECT id FROM edu_aulas WHERE curso_id = {$aula['curso_id']} AND ordem > {$aula['ordem']} ORDER BY ordem ASC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $aula['titulo']; ?> - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body class="bg-background text-text font-sans scroll-smooth">
    <?php include 'header.php'; ?>
    
    <div class="flex flex-col min-h-screen">
        <!-- Lesson Header -->
        <div class="bg-white border-b border-border px-6 py-4 flex items-center justify-between sticky top-[64px] z-40 bg-white/80 backdrop-blur">
            <div class="flex items-center gap-4">
                <a href="edu_curso.php?id=<?php echo $aula['curso_id']; ?>" class="w-8 h-8 rounded-full border border-border flex items-center justify-center hover:bg-gray-50 transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <span class="text-[9px] font-black text-primary uppercase tracking-widest"><?php echo $aula['curso_titulo']; ?></span>
                    <h1 class="text-sm font-bold text-text"><?php echo $aula['titulo']; ?></h1>
                </div>
            </div>
            
            <?php if ($proxima_aula): ?>
                <a href="edu_aula.php?id=<?php echo $proxima_aula['id']; ?>" class="bg-primary text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-all flex items-center gap-2">
                    Próxima Aula <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            <?php else: ?>
                <a href="edu_curso.php?id=<?php echo $aula['curso_id']; ?>" class="bg-green-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all flex items-center gap-2">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Concluir Curso
                </a>
            <?php endif; ?>
        </div>

        <!-- Content Area -->
        <div class="max-w-5xl mx-auto w-full p-6 flex-grow">
            <!-- Player / Viewer -->
            <div class="bg-black rounded-3xl overflow-hidden shadow-2xl mb-8 aspect-video flex items-center justify-center">
                <?php if ($aula['tipo'] == 'video'): ?>
                    <?php 
                        // Tentar converter link do YouTube em embed
                        $video_url = $aula['conteudo'];
                        if (strpos($video_url, 'youtube.com/watch?v=') !== false) {
                            $video_id = explode('v=', $video_url)[1];
                            $video_url = "https://www.youtube.com/embed/" . explode('&', $video_id)[0];
                        } elseif (strpos($video_url, 'youtu.be/') !== false) {
                            $video_id = explode('youtu.be/', $video_url)[1];
                            $video_url = "https://www.youtube.com/embed/" . explode('?', $video_id)[0];
                        }
                    ?>
                    <iframe src="<?php echo $video_url; ?>" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                <?php elseif ($aula['tipo'] == 'pdf'): ?>
                     <iframe src="<?php echo $aula['conteudo']; ?>" class="w-full h-full bg-white"></iframe>
                <?php else: ?>
                    <div class="w-full h-full bg-white p-6 md:p-12 text-black overflow-y-auto rich-text ql-snow">
                        <div class="ql-editor p-0">
                            <?php echo $aula['conteudo']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="bg-white rounded-3xl border border-border p-8 mb-12">
                <h2 class="text-xl font-bold text-text mb-4 tracking-tight">Sobre esta lição</h2>
                <p class="text-text-secondary text-sm leading-relaxed italic"><?php echo $aula['descricao'] ?: 'Nenhuma observação adicional para esta aula.'; ?></p>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <style>
        .rich-text { font-family: 'Inter', sans-serif; }
        .rich-text p { margin-bottom: 1rem; }
    </style>
</body>
</html>
