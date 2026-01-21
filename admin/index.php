<?php
require_once '../config.php';
require_once '../functions.php';

requireAdminDashboard();

$total_usuarios = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$total_setores = $conn->query("SELECT COUNT(*) as total FROM setores")->fetch_assoc()['total'];
$total_permissoes = $conn->query("SELECT COUNT(*) as total FROM permissoes")->fetch_assoc()['total'];
$total_logs = $conn->query("SELECT COUNT(*) as total FROM logs_acesso WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$total_manutencao = $conn->query("SELECT COUNT(*) as total FROM manutencao WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
$total_ti = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
$total_biblioteca = $conn->query("SELECT COUNT(*) as total FROM biblioteca")->fetch_assoc()['total'];
$total_educacao = $conn->query("SELECT COUNT(*) as total FROM educacao_treinamentos")->fetch_assoc()['total'];
$total_telefones = $conn->query("SELECT COUNT(*) as total FROM telefones")->fetch_assoc()['total'];
$total_politicas = $conn->query("SELECT COUNT(*) as total FROM rh_politicas")->fetch_assoc()['total'];
$total_ti_artigos = $conn->query("SELECT COUNT(*) as total FROM ti_artigos")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                <i data-lucide="settings-2" class="w-6 h-6"></i>
                Gestão Administrativa
            </h1>
            <p class="text-text-secondary text-xs mt-1">Configurações e auditoria do sistema</p>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_usuarios; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Usuários</p>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-500 group-hover:bg-teal-500 group-hover:text-white transition-all">
                    <i data-lucide="briefcase" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_setores; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Setores</p>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-500 group-hover:bg-amber-500 group-hover:text-white transition-all">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_permissoes; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Permissões</p>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_logs; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Acessos Hoje</p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Grid -->
        <h2 class="text-[10px] font-black text-text-secondary uppercase tracking-widest mb-4 flex items-center gap-3">
            Módulos Disponíveis
            <span class="flex-grow h-px bg-border/50"></span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Basic Admin Modules (Visible to RH managers too) -->
            <?php if (isAdmin() || isRHAdmin()): ?>
            <a href="usuarios.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                    <i data-lucide="user-cog" class="w-5 h-5"></i>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Usuários</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gerencie cadastros e perfis.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>
            
            <a href="setores.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Setores</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Estrutura organizacional.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>
            <?php endif; ?>

            <!-- RH Specific -->
            <?php if (isRHAdmin()): ?>
            <a href="rh_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-indigo-600 transition-all">
                <div class="w-10 h-10 rounded-xl bg-indigo-600/10 flex items-center justify-center text-indigo-600 mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <?php if ($total_politicas > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-indigo-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-indigo-600/20">
                            <?php echo $total_politicas; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Recursos Humanos</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão de políticas e documentos do colaborador.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-indigo-600 transition-all"></i>
                </div>
            </a>
            <?php endif; ?>

            <!-- Specialized Modules -->
            <?php if (isEduAdmin() || isAdmin()): ?>
            <a href="educacao_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-emerald-500 transition-all">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 mb-4 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                    <?php if ($total_educacao > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-emerald-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-emerald-500/20">
                            <?php echo $total_educacao; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Educação Permanente</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão centralizada de trilhas, cursos e provas.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-emerald-500 transition-all"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isTecnico()): ?>
            <a href="suporte_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-blue-600 transition-all">
                <div class="w-10 h-10 rounded-xl bg-blue-600/10 flex items-center justify-center text-blue-600 mb-4 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="monitor" class="w-5 h-5"></i>
                    <?php if ($total_ti > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-red-500/20">
                            <?php echo $total_ti; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Suporte TI</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão de chamados técnicos.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-blue-600 transition-all"></i>
                </div>
            </a>

            <a href="ti_artigos.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                    <?php if ($total_ti_artigos > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-primary/20">
                            <?php echo $total_ti_artigos; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Artigos de TI</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gerencie a base de conhecimento.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isManutencao()): ?>
            <a href="manutencao_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-orange-600 transition-all">
                <div class="w-10 h-10 rounded-xl bg-orange-600/10 flex items-center justify-center text-orange-600 mb-4 group-hover:bg-orange-600 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="wrench" class="w-5 h-5"></i>
                    <?php if ($total_manutencao > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-red-500/20">
                            <?php echo $total_manutencao; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Manutenção</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão de ordens de serviço prediais.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-orange-600 transition-all"></i>
                </div>
            </a>
            <?php endif; ?>

            <!-- Global Admin Modules (Full Admin only to avoid dashboard clutter) -->
            <?php if (isAdmin()): ?>
            <a href="biblioteca_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-indigo-500 transition-all">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-500 mb-4 group-hover:bg-indigo-500 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="library" class="w-5 h-5"></i>
                    <?php if ($total_biblioteca > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-indigo-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-indigo-500/20">
                            <?php echo $total_biblioteca; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Biblioteca</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão de documentos e arquivos.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-indigo-500 transition-all"></i>
                </div>
            </a>

            <a href="telefones_gerenciar.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-blue-500 transition-all">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-500 mb-4 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300 relative">
                    <i data-lucide="phone" class="w-5 h-5"></i>
                    <?php if ($total_telefones > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-blue-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-blue-500/20">
                            <?php echo $total_telefones; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Ramais</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Gestão da lista telefônica e ramais internos.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-blue-500 transition-all"></i>
                </div>
            </a>

            <a href="permissoes.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                    <i data-lucide="key" class="w-5 h-5"></i>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Permissões</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Níveis de acesso e segurança.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>
            
            <a href="logs.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                    <i data-lucide="file-search" class="w-5 h-5"></i>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Auditoria</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Rastreabilidade de ações.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>

            <a href="comunicacao.php" class="bg-white p-5 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                </div>
                <h3 class="text-base font-bold text-text mb-1 tracking-tight">Comunicação</h3>
                <p class="text-xs text-text-secondary leading-relaxed">Envio de comunicados e e-mails.</p>
                <div class="mt-4 flex justify-end">
                    <i data-lucide="arrow-right" class="w-4 h-4 text-border group-hover:text-primary transition-all"></i>
                </div>
            </a>
            <?php endif; ?>
            <!-- Troca de Plantão (Novo) -->
            <a href="plantao_gerenciar.php" class="bg-white p-6 rounded-3xl border border-border shadow-sm hover:shadow-xl transition-all group overflow-hidden relative">
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 group-hover:bg-primary/10 transition-colors"></div>
                <div class="relative z-10">
                    <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black text-text uppercase tracking-widest mb-2">Troca de Plantão</h3>
                    <p class="text-[10px] text-text-secondary font-bold leading-relaxed mb-4">Aprovar solicitações de troca entre funcionários.</p>
                    <div class="flex items-center gap-2 text-[10px] font-black text-primary uppercase tracking-tighter">
                        Gerenciar trocas
                        <i data-lucide="arrow-right" class="w-3 h-3 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
    </div> <!-- Close Main Content Wrapper -->
</body>
</html>
