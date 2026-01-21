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
    
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-4 md:p-8 mt-16 lg:mt-0">
            <div class="max-w-5xl mx-auto">
                <!-- Header informativo solicitado -->
                <div class="bg-gradient-to-r from-primary to-indigo-600 rounded-3xl p-8 md:p-12 text-white mb-8 shadow-xl relative overflow-hidden">
                    <div class="relative z-10">
                        <h1 class="text-3xl md:text-4xl font-black mb-4 tracking-tight">Tecnologia da Informação</h1>
                        <p class="text-white/80 text-lg max-w-2xl font-medium">Como podemos ajudar você hoje? Encontre tutoriais, resolva problemas comuns e aprenda a usar nossas ferramentas.</p>
                        
                        <div class="mt-8 max-w-xl">
                            <form action="ti_artigos.php" method="GET" class="relative">
                                <input type="text" name="search" value="<?php echo $search; ?>" placeholder="O que você está procurando?" class="w-full px-6 py-4 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl text-white placeholder:text-white/50 focus:outline-none focus:bg-white/20 transition-all shadow-inner">
                                <button type="submit" class="absolute right-3 top-3 bg-white text-primary p-2 rounded-xl shadow-lg hover:scale-105 transition-transform">
                                    <i data-lucide="search" class="w-6 h-6"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Elementos decorativos -->
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <i data-lucide="monitor" class="w-64 h-64"></i>
                    </div>
                </div>

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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($categorias as $cat => $arts): ?>
                            <div class="bg-white rounded-3xl p-6 shadow-sm border border-border hover:shadow-md transition-shadow">
                                <h2 class="text-xs font-black text-primary uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                                    <i data-lucide="folder-open" class="w-4 h-4"></i>
                                    <?php echo $cat ?: 'Diversos'; ?>
                                </h2>
                                <div class="space-y-1">
                                    <?php foreach ($arts as $art): ?>
                                        <a href="ti_artigo_ver.php?id=<?php echo $art['id']; ?>" class="flex items-center justify-between p-3 rounded-xl hover:bg-background transition-colors group">
                                            <span class="text-sm font-bold text-text group-hover:text-primary transition-colors"><?php echo $art['titulo']; ?></span>
                                            <i data-lucide="arrow-right" class="w-4 h-4 text-text-secondary opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
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
        </main>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
