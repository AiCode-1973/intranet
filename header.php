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
            <!-- Notifications (In-App Emails) -->
            <?php
            $user_id_header = $_SESSION['usuario_id'];
            $unread_count = $conn->query("SELECT COUNT(*) as total FROM email_logs WHERE usuario_id = $user_id_header AND lido = 0")->fetch_assoc()['total'];
            $recent_msgs = $conn->query("SELECT * FROM email_logs WHERE usuario_id = $user_id_header ORDER BY data_envio DESC LIMIT 5");
            ?>
            <div class="relative group">
                <button class="relative p-2 text-text-secondary hover:text-primary transition-colors">
                    <i data-lucide="mail" class="w-5 h-5 text-primary"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-black rounded-full border-2 border-white flex items-center justify-center animate-bounce">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown -->
                <div class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-border opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-right group-hover:scale-100 scale-95 z-50 overflow-hidden">
                    <div class="p-4 border-b border-border bg-gray-50 flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-widest text-text-secondary">Mensagens do Admin</span>
                        <a href="<?php echo $root_path; ?>mensagens.php" class="text-[10px] font-bold text-primary hover:underline">Ver todas</a>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <?php if ($recent_msgs->num_rows > 0): ?>
                            <?php while($m = $recent_msgs->fetch_assoc()): ?>
                                <a href="<?php echo $root_path; ?>mensagens.php?id=<?php echo $m['id']; ?>" class="block p-4 border-b border-border/50 hover:bg-primary/[0.02] transition-colors <?php echo $m['lido'] == 0 ? 'bg-primary/[0.03]' : ''; ?>">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-[11px] font-bold text-text truncate pr-2"><?php echo $m['assunto']; ?></p>
                                        <span class="text-[8px] text-text-secondary opacity-50 shrink-0"><?php echo date('d/m H:i', strtotime($m['data_envio'])); ?></span>
                                    </div>
                                    <p class="text-[10px] text-text-secondary line-clamp-1 italic"><?php echo strip_tags($m['mensagem']); ?></p>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                    <div class="p-8 text-center text-[10px] text-text-secondary italic">Nenhuma mensagem recebida.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <style>
                .avatar-3x4 {
                    width: 40px;
                    height: 40px;
                    object-fit: cover;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 
                        0 4px 6px -1px rgba(0,0,0,0.1), 
                        0 2px 4px -1px rgba(0,0,0,0.06),
                        inset 0 0 0 1px rgba(0,0,0,0.05);
                    image-rendering: -webkit-optimize-contrast;
                    image-rendering: crisp-edges;
                    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                }
                .avatar-3x4:hover {
                    transform: scale(1.15) rotate(2deg);
                    z-index: 60;
                }
            </style>
            
            <button class="relative p-2 text-text-secondary hover:text-primary transition-colors">
                <i data-lucide="bell" class="w-5 h-5 text-primary"></i>
            </button>

            <!-- User Profile Avatar with Dropdown -->
            <div class="relative group ml-2 pl-4 border-l border-border">
                <button class="flex items-center gap-2 focus:outline-none">
                    <?php if (!empty($_SESSION['usuario_foto'])): ?>
                        <img src="<?php echo $root_path; ?>uploads/fotos/<?php echo $_SESSION['usuario_foto']; ?>" alt="Foto" class="avatar-3x4 shadow-sm">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center border border-primary/30 overflow-hidden shadow-inner font-bold text-primary">
                            <?php echo substr($_SESSION['usuario_nome'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-text-secondary group-hover:text-primary transition-colors"></i>
                </button>

                <!-- Dropdown Menu -->
                <div class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-border opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-right group-hover:scale-100 scale-95 z-50 overflow-hidden">
                    <div class="p-4 border-b border-border bg-gray-50 flex flex-col gap-0.5">
                        <span class="text-xs font-black text-text truncate"><?php echo $_SESSION['usuario_nome']; ?></span>
                        <span class="text-[10px] text-text-secondary font-bold tracking-tight"><?php echo $_SESSION['usuario_cpf']; ?></span>
                    </div>
                    <div class="p-2">
                        <a href="<?php echo $root_path; ?>alterar_senha.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-text-secondary hover:bg-primary/[0.05] hover:text-primary transition-all group/item">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 group-hover/item:bg-primary/10 flex items-center justify-center transition-colors">
                                <i data-lucide="key-round" class="w-4 h-4"></i>
                            </div>
                            <span>Alterar Senha</span>
                        </a>
                        <hr class="my-2 border-border/50">
                        <a href="<?php echo $root_path; ?>logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-red-500 hover:bg-red-50 transition-all group/item">
                            <div class="w-8 h-8 rounded-lg bg-red-100/50 flex items-center justify-center">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                            </div>
                            <span>Sair do Sistema</span>
                        </a>
                    </div>
                </div>
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

