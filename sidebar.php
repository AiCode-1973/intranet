<?php
// Detecta se está na pasta admin para ajustar os caminhos relativos
$in_admin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$root_path = $in_admin ? '../' : '';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside id="mainSidebar" class="fixed top-0 left-0 h-screen w-64 bg-sidebar text-white z-50 flex flex-col shadow-2xl transition-transform duration-300 -translate-x-full lg:translate-x-0 overflow-hidden">
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-[-1] lg:hidden opacity-0 invisible transition-all duration-300" onclick="toggleSidebar()"></div>
    <!-- Logo -->
    <div class="p-6 pt-8 mb-4 flex-shrink-0">
        <a href="<?php echo $root_path; ?>dashboard.php" class="flex items-center gap-3">
            <div class="bg-white rounded-md p-1.5 flex items-center justify-center">
                <i data-lucide="hospital" class="w-6 h-6 text-sidebar"></i>
            </div>
            <div class="flex flex-col">
                <span class="text-sm font-bold tracking-wider leading-tight">APAS <br>Intranet</span>
            </div>
        </a>
    </div>

    <!-- User Profile Summary (Mobile or Desktop mini) -->
    <div class="px-6 mb-6">
        <div class="flex items-center gap-3 p-3 bg-white/5 rounded-2xl border border-white/10">
            <?php if (!empty($_SESSION['usuario_foto'])): ?>
                <img src="<?php echo $root_path; ?>uploads/fotos/<?php echo $_SESSION['usuario_foto']; ?>" alt="Foto" class="w-12 h-12 object-cover rounded-full border border-white/20 shadow-lg">
            <?php else: ?>
                <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 text-white font-bold text-sm">
                    <?php echo substr($_SESSION['usuario_nome'], 0, 1); ?>
                </div>
            <?php endif; ?>
            <div class="flex flex-col min-w-0">
                <span class="text-[11px] font-black text-white truncate"><?php echo $_SESSION['usuario_nome']; ?></span>
                <span class="text-[9px] text-white/50 uppercase font-bold tracking-tighter truncate"><?php echo $_SESSION['usuario_cpf']; ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-grow px-2 space-y-1 overflow-y-auto no-scrollbar min-h-0">
        <?php
        $menu_items = [
            ['icon' => 'layout-dashboard', 'label' => 'Início', 'link' => $root_path . 'dashboard.php'],
            ['icon' => 'megaphone', 'label' => 'Mural de Avisos', 'link' => $root_path . 'mural.php'],
            ['icon' => 'calendar', 'label' => 'Agenda de Eventos', 'link' => $root_path . 'agenda.php'],
            ['icon' => 'cake', 'label' => 'Aniversariantes', 'link' => $root_path . 'aniversariantes.php'],
            ['icon' => 'activity', 'label' => 'Assistencial', 'link' => '#'],
            ['icon' => 'shield-check', 'label' => 'Qualidade & Segurança', 'link' => '#'],
            ['icon' => 'users', 'label' => 'Recursos Humanos', 'link' => $root_path . 'rh.php'],
            ['icon' => 'briefcase', 'label' => 'Setores Administrativos', 'link' => '#'],
            ['icon' => 'clipboard-list', 'label' => 'Protocolo de Documentos', 'link' => '#'],
            ['icon' => 'monitor', 'label' => 'Tecnologia da Informação', 'link' => '#'],
            ['icon' => 'wrench', 'label' => 'Infraestrutura & Manutenção', 'link' => $root_path . 'manutencao.php'],
            ['icon' => 'shield-alert', 'label' => 'Segurança do Trabalho', 'link' => '#'],
            ['icon' => 'files', 'label' => 'Documentos & Biblioteca', 'link' => $root_path . 'biblioteca.php'],
            ['icon' => 'graduation-cap', 'label' => 'Educação Permanente', 'link' => $root_path . 'educacao.php'],
            ['icon' => 'headset', 'label' => 'Ouvidoria', 'link' => '#'],
            ['icon' => 'monitor-dot', 'label' => 'Suporte de TI', 'link' => $root_path . 'suporte.php'],
            ['icon' => 'phone', 'label' => 'Ramais & Telefones', 'link' => $root_path . 'telefones.php'],
            ['icon' => 'refresh-cw', 'label' => 'Troca de Plantão', 'link' => $root_path . 'plantao_trocas.php'],
        ];

        foreach ($menu_items as $item) {
            $is_active = ($current_page == basename($item['link']));
            $active_class = $is_active 
                ? 'bg-white/20 text-white font-semibold' 
                : 'text-white/80 hover:bg-white/10 hover:text-white';
            
            echo "<a href=\"{$item['link']}\" class=\"flex items-center gap-3 px-4 py-2 rounded-md text-xs transition-all {$active_class}\">
                    <i data-lucide=\"{$item['icon']}\" class=\"w-4 h-4\"></i>
                    <span>{$item['label']}</span>
                  </a>";
        }
        ?>
    </nav>

    <!-- Admin Link at Bottom -->
    <?php if (isRHAdmin() || isEduAdmin()): ?>
    <div class="p-4 border-t border-white/10 mt-auto flex-shrink-0">
        <a href="<?php echo $in_admin ? 'index.php' : 'admin/index.php'; ?>" 
           class="flex items-center justify-between gap-3 px-4 py-3 rounded-md text-sm text-white/80 hover:bg-white/10 hover:text-white transition-all">
            <div class="flex items-center gap-3">
                <i data-lucide="settings" class="w-5 h-5"></i>
                <span>Administração</span>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    </div>
    <?php endif; ?>
</aside>
