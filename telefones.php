<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Buscar Setores para Filtro
$setores = $conn->query("SELECT DISTINCT s.id, s.nome FROM setores s JOIN telefones t ON s.id = t.setor_id ORDER BY s.nome");

// Buscar todos os ramais inicialmente
$telefones = $conn->query("
    SELECT t.*, s.nome as setor_nome 
    FROM telefones t 
    LEFT JOIN setores s ON t.setor_id = s.id 
    ORDER BY t.tipo, t.ordem, t.nome
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Ramais - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .phone-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .phone-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -10px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header & Search -->
        <div class="mb-12 text-center max-w-2xl mx-auto">
            <h1 class="text-3xl font-black text-primary mb-4 tracking-tight">Lista de Ramais & Telefones</h1>
            <p class="text-text-secondary text-sm mb-8">Encontre rapidamente ramais internos, setores e contatos externos essenciais.</p>
            
            <div class="relative group">
                <i data-lucide="search" class="absolute left-6 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                <input type="text" id="searchInput" placeholder="Busque por nome, setor ou ramal..." 
                    class="w-full pl-14 pr-6 py-5 bg-white border-2 border-border rounded-3xl text-sm font-bold shadow-xl focus:outline-none focus:border-primary transition-all placeholder:text-text-secondary/50">
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap justify-center gap-2 mb-12" id="filterContainer">
            <button data-filter="all" class="filter-btn px-6 py-2 bg-primary text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg shadow-primary/20 transition-all active:scale-95">Ver Todos</button>
            <?php while($s = $setores->fetch_assoc()): ?>
                <button data-filter="setor-<?php echo $s['id']; ?>" class="filter-btn px-6 py-2 bg-white border border-border text-text-secondary hover:border-primary hover:text-primary rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm transition-all active:scale-95">
                    <?php echo $s['nome']; ?>
                </button>
            <?php endwhile; ?>
            <button data-filter="externo" class="filter-btn px-6 py-2 bg-white border border-border text-text-secondary hover:border-indigo-500 hover:text-indigo-500 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm transition-all active:scale-95">Externos</button>
        </div>

        <!-- Results Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="phoneGrid">
            <?php while ($t = $telefones->fetch_assoc()): 
                $filter_class = $t['tipo'] == 'externo' ? 'externo' : 'setor-' . $t['setor_id'];
            ?>
            <div class="phone-card bg-white rounded-3xl border border-border p-6 flex flex-col items-center text-center relative overflow-hidden" 
                 data-search="<?php echo strtolower($t['nome'] . ' ' . $t['ramal'] . ' ' . $t['setor_nome']); ?>"
                 data-category="<?php echo $filter_class; ?>">
                
                <!-- Icon Background Decoration -->
                <div class="absolute -top-4 -right-4 w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center opacity-40">
                    <i data-lucide="<?php echo $t['tipo'] == 'externo' ? 'external-link' : ($t['tipo'] == 'colaborador' ? 'user' : 'building-2'); ?>" class="w-12 h-12 text-gray-200"></i>
                </div>

                <div class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center text-primary mb-4 border border-primary/10 relative z-10">
                    <i data-lucide="<?php echo $t['tipo'] == 'externo' ? 'phone-outgoing' : 'phone'; ?>" class="w-8 h-8"></i>
                </div>

                <h3 class="text-sm font-black text-text mb-1 relative z-10 leading-tight"><?php echo $t['nome']; ?></h3>
                <div class="flex items-center gap-2 mb-6 opacity-60">
                    <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest"><?php echo $t['setor_nome'] ?: ($t['tipo'] == 'externo' ? 'Contato Externo' : 'Geral'); ?></p>
                    <span class="w-1 h-1 bg-border rounded-full"></span>
                    <p class="text-[9px] font-black <?php echo $t['unidade'] == 'Hospital' ? 'text-primary' : 'text-indigo-500'; ?> uppercase tracking-widest"><?php echo $t['unidade'] ?: 'Hospital'; ?></p>
                </div>

                <div class="mt-auto w-full pt-4 border-t border-dashed border-border flex flex-col gap-2">
                    <?php if ($t['ramal']): ?>
                        <div class="flex items-center justify-between px-2">
                            <span class="text-[9px] font-black text-text-secondary uppercase">Ramal</span>
                            <span class="text-lg font-black font-mono text-primary"><?php echo $t['ramal']; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($t['telefone']): ?>
                        <div class="flex items-center justify-between px-2">
                            <span class="text-[9px] font-black text-text-secondary uppercase">Telefone</span>
                            <span class="text-xs font-bold text-text"><?php echo $t['telefone']; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- No Results -->
        <div id="noResults" class="hidden py-24 text-center">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-300">
                <i data-lucide="search-x" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-text mb-2">Nenhum ramal encontrado</h3>
            <p class="text-text-secondary text-sm">Tente outros termos de busca ou filtros.</p>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const phoneGrid = document.getElementById('phoneGrid');
        const cards = document.querySelectorAll('.phone-card');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const noResults = document.getElementById('noResults');

        let currentFilter = 'all';
        let currentSearch = '';

        function updatedDisplay() {
            let visibleCount = 0;
            cards.forEach(card => {
                const matchesSearch = card.getAttribute('data-search').includes(currentSearch);
                const matchesFilter = currentFilter === 'all' || card.getAttribute('data-category') === currentFilter;
                
                if (matchesSearch && matchesFilter) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            noResults.classList.toggle('hidden', visibleCount > 0);
        }

        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.toLowerCase();
            updatedDisplay();
        });

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // UI Toggle
                filterBtns.forEach(b => {
                    b.classList.remove('bg-primary', 'text-white', 'shadow-lg', 'shadow-primary/20');
                    b.classList.add('bg-white', 'text-text-secondary', 'border', 'border-border');
                });
                btn.classList.add('bg-primary', 'text-white', 'shadow-lg', 'shadow-primary/20');
                btn.classList.remove('bg-white', 'text-text-secondary', 'border', 'border-border');

                currentFilter = btn.getAttribute('data-filter');
                updatedDisplay();
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
