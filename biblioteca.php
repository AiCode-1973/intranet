<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Filtros
$categoria_filtro = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : '';
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';

$where = "WHERE 1=1";
if ($categoria_filtro) $where .= " AND categoria = '$categoria_filtro'";
if ($busca) $where .= " AND (titulo LIKE '%$busca%' OR descricao LIKE '%$busca%')";

// Buscar documentos
$documentos = $conn->query("SELECT * FROM biblioteca $where ORDER BY data_upload DESC");

// Categorias para o filtro
$categorias = ['Manual', 'POP', 'Políticas', 'Formulários', 'Comunicados', 'Outros'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos & Biblioteca - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="library" class="w-6 h-6"></i>
                    Documentos & Biblioteca
                </h1>
                <p class="text-text-secondary text-xs mt-1">Repositório oficial de normas, manuais e procedimentos</p>
            </div>

            <?php if (isAdmin()): ?>
            <a href="admin/biblioteca_gerenciar.php" class="bg-primary/10 hover:bg-primary/20 text-primary px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2">
                <i data-lucide="settings" class="w-4 h-4"></i> Gerenciar Arquivos
            </a>
            <?php endif; ?>
        </div>

        <!-- Search & Filter Bar -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-border mb-8 flex flex-col md:flex-row gap-4">
            <div class="flex-grow relative">
                <input type="text" id="buscaInput" value="<?php echo $busca; ?>" placeholder="Pesquisar documento pelo título ou descrição..." 
                       class="w-full pl-10 pr-4 py-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-text-secondary opacity-50"></i>
            </div>
            <div class="w-full md:w-48">
                <select id="categoriaSelect" class="w-full px-3 py-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    <option value="">Todas Categorias</option>
                    <?php foreach($categorias as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $categoria_filtro == $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button onclick="filtrar()" class="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">
                Filtrar
            </button>
        </div>

        <!-- Documents Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($documentos->num_rows > 0): ?>
                <?php while ($doc = $documentos->fetch_assoc()): ?>
                <div class="bg-white p-5 rounded-2xl border border-border shadow-sm hover:border-primary hover:shadow-lg transition-all group flex flex-col h-full">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500">
                            <i data-lucide="file-text" class="w-6 h-6"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary text-[8px] font-black uppercase tracking-widest border border-border">
                            <?php echo $doc['categoria']; ?>
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-text mb-2 line-clamp-2"><?php echo $doc['titulo']; ?></h3>
                    <p class="text-[11px] text-text-secondary leading-relaxed mb-6 flex-grow line-clamp-3"><?php echo $doc['descricao'] ?: 'Sem descrição disponível.'; ?></p>

                    <div class="flex items-center justify-between pt-4 border-t border-border mt-auto">
                        <span class="text-[9px] font-bold text-text-secondary opacity-40 uppercase"><?php echo date('d/m/Y', strtotime($doc['data_upload'])); ?></span>
                        <a href="uploads/biblioteca/<?php echo $doc['arquivo_path']; ?>" target="_blank" 
                           class="flex items-center gap-1.5 text-xs font-bold text-primary hover:translate-x-1 transition-transform">
                            Download <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 text-center">
                    <div class="w-16 h-16 bg-background rounded-full flex items-center justify-center mx-auto mb-4 border border-border">
                        <i data-lucide="search-x" class="w-8 h-8 text-text-secondary opacity-20"></i>
                    </div>
                    <p class="text-xs font-bold text-text-secondary uppercase tracking-widest">Nenhum documento encontrado.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filtrar() {
            const busca = document.getElementById('buscaInput').value;
            const categoria = document.getElementById('categoriaSelect').value;
            window.location.href = `biblioteca.php?busca=${encodeURIComponent(busca)}&categoria=${categoria}`;
        }
        document.getElementById('buscaInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') filtrar();
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
