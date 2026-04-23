<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!temPermissao($conn, $_SESSION['setor_id'], 'normas')) {
    header("Location: dashboard.php");
    exit;
}

// Filtros
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';

$where = "WHERE ativo = 1";
if ($busca) {
    $where .= " AND (titulo LIKE '%$busca%' OR descricao LIKE '%$busca%')";
}

// Buscar Normas
$normas = $conn->query("SELECT * FROM normas_procedimentos $where ORDER BY data_publicacao DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Normas & Procedimentos - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                    Diretrizes & Normas da Diretoria
                </h1>
                <p class="text-text-secondary text-xs mt-1">Acervo completo de regulamentações e procedimentos internos</p>
            </div>

            <?php if (isAdmin()): ?>
            <a href="admin/normas_gerenciar.php" class="bg-primary/10 hover:bg-primary/20 text-primary px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2">
                <i data-lucide="settings" class="w-4 h-4"></i> Gerenciar Normas
            </a>
            <?php endif; ?>
        </div>

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-border mb-8 flex flex-col md:flex-row gap-4">
            <div class="flex-grow relative">
                <input type="text" id="buscaInput" value="<?php echo $busca; ?>" placeholder="Pesquisar norma pelo título ou conteúdo..." 
                       class="w-full pl-10 pr-4 py-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-text-secondary opacity-50"></i>
            </div>
            <button onclick="filtrar()" class="bg-primary hover:bg-primary-hover text-white px-8 py-2 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">
                Pesquisar
            </button>
        </div>

        <!-- Normas Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($normas && $normas->num_rows > 0): ?>
                <?php while ($norma = $normas->fetch_assoc()): ?>
                <div class="bg-white p-5 rounded-2xl border border-border shadow-sm hover:border-primary hover:shadow-lg transition-all group flex flex-col h-full cursor-pointer" 
                     onclick='verNorma(<?php echo json_encode($norma, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                    
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500">
                            <i data-lucide="shield-check" class="w-6 h-6"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary text-[8px] font-black uppercase tracking-widest border border-border">
                            Diretriz
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-text mb-2 line-clamp-2"><?php echo $norma['titulo']; ?></h3>
                    <p class="text-[11px] text-text-secondary leading-relaxed mb-6 flex-grow line-clamp-3"><?php echo strip_tags($norma['descricao']) ?: 'Norma oficial da diretoria.'; ?></p>

                    <div class="flex items-center justify-between pt-4 border-t border-border mt-auto">
                        <span class="text-[9px] font-bold text-text-secondary opacity-40 uppercase"><?php echo date('d/m/Y', strtotime($norma['data_publicacao'])); ?></span>
                        <div class="flex items-center gap-2 text-xs font-bold text-primary group-hover:translate-x-1 transition-transform">
                            Visualizar <i data-lucide="eye" class="w-4 h-4"></i>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 text-center">
                    <div class="w-16 h-16 bg-background rounded-full flex items-center justify-center mx-auto mb-4 border border-border">
                        <i data-lucide="search-x" class="w-8 h-8 text-text-secondary opacity-20"></i>
                    </div>
                    <p class="text-xs font-bold text-text-secondary uppercase tracking-widest">Nenhuma norma encontrada.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reusando o modal de visualização do Dashboard -->
    <div id="modalNorma" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="fecharModalNorma()"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-3xl p-4">
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="p-6 border-b border-border flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                            <i data-lucide="shield-check"></i>
                        </div>
                        <div>
                            <h2 id="normaTitulo" class="text-lg font-bold text-text mb-0.5">Título da Norma</h2>
                            <p id="normaData" class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Publicado em: --/--/----</p>
                        </div>
                    </div>
                    <button onclick="fecharModalNorma()" class="p-2 hover:bg-gray-200 rounded-full transition-colors">
                        <i data-lucide="x" class="w-5 h-5 text-text-secondary"></i>
                    </button>
                </div>
                <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <div id="normaDescricao" class="text-sm text-text-secondary leading-relaxed whitespace-pre-wrap">--</div>
                    
                    <div id="normaArquivoArea" class="mt-8 pt-8 border-t border-border hidden">
                        <h4 class="text-xs font-black text-text uppercase tracking-widest mb-4">Documento Anexo</h4>
                        <a id="normaDownload" href="#" target="_blank" class="flex items-center justify-between p-4 bg-primary/5 rounded-2xl border border-primary/10 group hover:bg-primary hover:border-primary transition-all">
                            <div class="flex items-center gap-3">
                                <i data-lucide="file-text" class="w-6 h-6 text-primary group-hover:text-white"></i>
                                <span class="text-xs font-bold text-text group-hover:text-white">Ver Documento Oficial</span>
                            </div>
                            <i data-lucide="external-link" class="w-5 h-5 text-primary group-hover:text-white group-hover:translate-x-1 transition-all"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filtrar() {
            const busca = document.getElementById('buscaInput').value;
            window.location.href = `normas.php?busca=${encodeURIComponent(busca)}`;
        }
        document.getElementById('buscaInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') filtrar();
        });

        function verNorma(norma) {
            document.getElementById('normaTitulo').textContent = norma.titulo;
            document.getElementById('normaData').textContent = 'Publicado em: ' + new Date(norma.data_publicacao).toLocaleDateString('pt-BR');
            document.getElementById('normaDescricao').textContent = norma.descricao;
            
            const arqArea = document.getElementById('normaArquivoArea');
            if (norma.arquivo_path) {
                arqArea.classList.remove('hidden');
                document.getElementById('normaDownload').href = 'uploads/normas/' + norma.arquivo_path;
            } else {
                arqArea.classList.add('hidden');
            }
            
            document.getElementById('modalNorma').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            lucide.createIcons();
        }

        function fecharModalNorma() {
            document.getElementById('modalNorma').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>