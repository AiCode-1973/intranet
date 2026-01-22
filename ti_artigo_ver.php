<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ti_artigos.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT a.*, u.nome as autor_nome FROM ti_artigos a LEFT JOIN usuarios u ON a.autor_id = u.id WHERE a.id = ? AND a.ativo = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$artigo = $stmt->get_result()->fetch_assoc();

if (!$artigo) {
    header('Location: ti_artigos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $artigo['titulo']; ?> - TI - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-4 md:p-8">
        <div class="max-w-4xl mx-auto">
            <a href="ti_artigos.php" class="inline-flex items-center gap-2 text-[10px] font-black text-primary uppercase tracking-widest mb-6 hover:translate-x-[-4px] transition-transform">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Voltar para Base de Conhecimento
            </a>

            <article class="bg-white rounded-3xl shadow-sm border border-border p-8 md:p-12 overflow-hidden relative">
                <!-- Header do Artigo -->
                <div class="mb-8 pb-8 border-b border-border/50">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="px-3 py-1 bg-primary/5 text-primary text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo $artigo['categoria']; ?></span>
                        <span class="text-[10px] text-text-secondary font-bold"><?php echo formatarData($artigo['created_at']); ?></span>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black text-text tracking-tight mb-4"><?php echo $artigo['titulo']; ?></h1>
                    <div class="flex items-center gap-2 text-xs text-text-secondary font-medium">
                        <span class="opacity-50">Publicado por</span>
                        <span class="text-primary font-bold"><?php echo $artigo['autor_nome']; ?></span>
                    </div>
                </div>

                <!-- Conteúdo do Artigo -->
                <div class="prose prose-sm md:prose-base max-w-none text-text-secondary leading-relaxed">
                    <?php echo nl2br($artigo['conteudo']); ?>
                </div>

                <!-- Feedback Sutil -->
                <div class="mt-12 pt-8 border-t border-border/50 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest">Este artigo foi útil?</p>
                    <div class="flex items-center gap-2">
                        <button class="flex items-center gap-2 px-4 py-2 border border-border rounded-xl hover:bg-emerald-50 hover:text-emerald-500 transition-all text-xs font-bold">
                            <i data-lucide="thumbs-up" class="w-4 h-4"></i> Sim
                        </button>
                        <button class="flex items-center gap-2 px-4 py-2 border border-border rounded-xl hover:bg-red-50 hover:text-red-500 transition-all text-xs font-bold">
                            <i data-lucide="thumbs-down" class="w-4 h-4"></i> Não
                        </button>
                    </div>
                </div>

                <!-- Decoração sutil -->
                <div class="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
                    <i data-lucide="file-text" class="w-32 h-32"></i>
                </div>
            </article>

            <!-- Relacionados ou Próximos -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white/50 border border-border rounded-2xl p-4 flex items-center justify-center text-[10px] font-black text-text-secondary uppercase tracking-widest">
                    Fim dos Artigos desta categoria
                </div>
                <a href="suporte.php" class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-lg text-indigo-500">
                            <i data-lucide="headset" class="w-4 h-4"></i>
                        </div>
                        <span class="text-xs font-bold text-indigo-900">Ainda com dúvidas?</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-indigo-300 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    </div> <!-- Fecha o div pl-64 do header.php -->
</body>
</html>
