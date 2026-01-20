<?php
// Detecta se está na pasta admin para ajustar os caminhos relativos
$in_admin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$root_path = $in_admin ? '../' : '';
?>

<?php include $root_path . 'sidebar.php'; ?>

<!-- Top Header -->
<header class="fixed top-0 left-0 lg:left-64 right-0 bg-white border-b border-border shadow-sm z-40 h-16">
    <div class="flex items-center justify-between lg:justify-end h-full px-4 lg:px-8 gap-6">
        <!-- Mobile Menu Toggle -->
        <button onclick="toggleSidebar()" class="lg:hidden p-2 text-text-secondary hover:text-primary transition-colors">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>

        <!-- Welcome Message -->
        <div class="text-sm text-text-secondary hidden md:block">
            Bem-vindo, <span class="font-semibold text-text"><?php echo $_SESSION['usuario_nome']; ?></span>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-4">
            <!-- Notifications -->
            <button class="relative p-2 text-text-secondary hover:text-primary transition-colors">
                <i data-lucide="mail" class="w-5 h-5 text-primary"></i>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
            </button>
            
            <button class="relative p-2 text-text-secondary hover:text-primary transition-colors">
                <i data-lucide="bell" class="w-5 h-5 text-primary"></i>
            </button>

            <!-- User Profile Avatar -->
            <div class="flex items-center gap-2 ml-2 pl-4 border-l border-border">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center border border-primary/30 overflow-hidden shadow-inner font-bold text-primary">
                    <?php echo substr($_SESSION['usuario_nome'], 0, 1); ?>
                </div>
                <a href="<?php echo $root_path; ?>logout.php" class="text-text-secondary hover:text-red-500 transition-colors" title="Sair">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('opacity-0', 'invisible');
            overlay.classList.add('opacity-100', 'visible');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0', 'invisible');
            overlay.classList.remove('opacity-100', 'visible');
        }
    }
</script>

<!-- Main Content Wrapper -->
<div class="min-h-screen flex flex-col pl-0 lg:pl-64 pt-16 bg-background">

