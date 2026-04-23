<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Dados para os cards superiores
$total_avisos = $conn->query("SELECT COUNT(*) as total FROM mural WHERE ativo = 1 AND (data_expiracao IS NULL OR data_expiracao >= CURDATE())")->fetch_assoc()['total'];
$ultimo_aviso = $conn->query("SELECT titulo FROM mural WHERE ativo = 1 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

$total_eventos_hoje = $conn->query("SELECT COUNT(*) as total FROM agenda WHERE ativo = 1 AND data_evento = CURDATE()")->fetch_assoc()['total'];

$mes_dia_atual = date('m-d');
$total_niver_hoje = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE data_nascimento LIKE '%-$mes_dia_atual' AND ativo = 1")->fetch_assoc()['total'];

$mes_atual = date('m');
$total_niver_mes = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE MONTH(data_nascimento) = '$mes_atual' AND ativo = 1")->fetch_assoc()['total'];

$uid = $_SESSION['usuario_id'];
if (isAdmin()) {
    $total_chamados_pendentes = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
} else {
    $total_chamados_pendentes = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE usuario_id = $uid AND status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
}

// Total chamados manutenção pendentes
if (isAdmin()) {
    $total_manutencao_pendentes = $conn->query("SELECT COUNT(*) as total FROM manutencao WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
} else {
    $total_manutencao_pendentes = $conn->query("SELECT COUNT(*) as total FROM manutencao WHERE usuario_id = $uid AND status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
}

// Total documentos biblioteca
$total_biblioteca = $conn->query("SELECT COUNT(*) as total FROM biblioteca")->fetch_assoc()['total'];

// Total treinamentos
$total_educacao = $conn->query("SELECT COUNT(*) as total FROM edu_cursos")->fetch_assoc()['total'];

// Calcular Progresso Global do Aluno logado
$user_id = $_SESSION['usuario_id'];
$total_aulas_lms = $conn->query("SELECT COUNT(*) as t FROM edu_aulas")->fetch_assoc()['t'];
$concluidas_lms = $conn->query("SELECT COUNT(*) as t FROM edu_progresso WHERE usuario_id = $user_id AND concluido = 1")->fetch_assoc()['t'];
$percentual_global = ($total_aulas_lms > 0) ? round(($concluidas_lms / $total_aulas_lms) * 100) : 0;

// Total holerites/docs novos para o usuário
$total_rh_novos = $conn->query("SELECT COUNT(*) as total FROM rh_documentos WHERE usuario_id = $user_id")->fetch_assoc()['total'];

// Saudação
$hour = date('H');
$greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
$userName = explode(' ', $_SESSION['usuario_nome'])[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Principal - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .modal-info { display: none; position: fixed; z-index: 1000; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 1rem; }
        .modal-info.active { display: flex; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-4 md:p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Dashboard Header (Slim) -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
                    <?php echo $greeting; ?>, <?php echo $userName; ?>!
                </h1>
                <p class="text-text-secondary text-xs mt-0.5">Visão geral e acessos rápidos da unidade</p>
            </div>
            
            <div class="flex items-center gap-2 bg-white p-1 rounded-lg border border-border shadow-sm">
                <div class="px-3 py-1 flex flex-col items-center">
                    <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest leading-none">Hoje</span>
                    <span class="text-[11px] font-bold text-text"><?php echo date('d/m/Y'); ?></span>
                </div>
                <div class="w-px h-6 bg-border"></div>
                <div class="px-3 py-1 flex items-center gap-2 text-primary">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    <span class="text-[11px] font-black font-mono"><?php echo date('H:i'); ?></span>
                </div>
            </div>
        </div>

        <!-- Upper Quick Grid (Slim Cards) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'mural')): ?>
            <a href="mural.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-all">
                        <i data-lucide="megaphone" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text"><?php echo $total_avisos; ?></h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Mural de Avisos</p>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'agenda')): ?>
            <a href="agenda.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                        <i data-lucide="calendar" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text"><?php echo $total_eventos_hoje; ?></h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Eventos Hoje</p>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'aniversariantes')): ?>
            <a href="aniversariantes.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-pink-50 flex items-center justify-center text-pink-500 group-hover:bg-pink-500 group-hover:text-white transition-all">
                        <i data-lucide="cake" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text"><?php echo $total_niver_mes; ?></h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">No Mês</p>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'suporte')): ?>
            <a href="suporte.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-primary transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                        <i data-lucide="monitor-dot" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text"><?php echo $total_chamados_pendentes; ?></h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">TI Pendente</p>
                    </div>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Main Dashboard Modules -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Biblioteca / Protocolos -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'biblioteca')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="library" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Documentos & Biblioteca</h3>
                    </div>
                    <?php if ($total_biblioteca > 0): ?>
                        <span class="bg-primary/5 text-primary text-[8px] font-black px-1.5 py-0.5 rounded uppercase"><?php echo $total_biblioteca; ?> Arquivos</span>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-2 flex-grow">
                    <?php 
                    $recentes = $conn->query("SELECT * FROM biblioteca ORDER BY data_upload DESC LIMIT 3");
                    if ($recentes->num_rows > 0):
                        while($rdoc = $recentes->fetch_assoc()):
                    ?>
                        <a href="uploads/biblioteca/<?php echo $rdoc['arquivo_path']; ?>" target="_blank" class="flex items-center justify-between p-2 bg-background rounded-lg border border-border/50 hover:border-primary/30 transition-all group">
                            <span class="text-[11px] font-bold text-text-secondary group-hover:text-primary truncate pr-2"><?php echo $rdoc['titulo']; ?></span>
                            <i data-lucide="download" class="w-3 h-3 text-text-secondary/40 group-hover:text-primary flex-shrink-0"></i>
                        </a>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <div class="h-full flex items-center justify-center py-8 opacity-20 italic text-[10px]">
                            Nenhum arquivo recente.
                        </div>
                    <?php endif; ?>
                </div>
                
                <a href="biblioteca.php" class="mt-6 w-full py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10 text-center">
                    Acessar Acervo Completo
                </a>
            </div>
            <?php endif; ?>

            <!-- Tecnologia da Informação - Artigos -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'ti_artigos')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full group hover:border-primary transition-all overflow-hidden relative">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center opacity-40 group-hover:scale-110 transition-transform">
                    <i data-lucide="monitor" class="w-10 h-10 text-blue-200"></i>
                </div>
                <div class="relative z-10 flex flex-col h-full">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="monitor" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Tecnologia da Informação</h3>
                    </div>
                    <p class="text-[11px] font-bold text-primary mb-2">Como podemos ajudar você?</p>
                    <p class="text-[10px] text-text-secondary leading-relaxed mb-6 flex-grow">Acesse nossa base de conhecimento, manuais e resolva problemas técnicos comuns de forma rápida.</p>
                    
                    <a href="ti_artigos.php" class="w-full py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10 text-center">
                        Explorar Artigos de Ajuda
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ramais & Telefones -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'telefones')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full group hover:border-primary transition-all overflow-hidden relative">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center opacity-40 group-hover:scale-110 transition-transform">
                    <i data-lucide="phone" class="w-10 h-10 text-gray-200"></i>
                </div>
                <div class="relative z-10 flex flex-col h-full">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="phone" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Ramais & Telefones</h3>
                    </div>
                    <p class="text-[10px] text-text-secondary leading-relaxed mb-6 flex-grow">Acesso rápido aos contatos internos, setores e telefones externos essenciais.</p>
                    
                    <a href="telefones.php" class="w-full py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10 text-center">
                        Consultar Lista
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informações & Saúde -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'informacoes')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full group hover:border-primary transition-all overflow-hidden relative">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Informações & Saúde</h3>
                    </div>
                    <span class="text-[8px] font-black bg-primary/5 text-primary px-1.5 py-0.5 rounded uppercase tracking-widest">Informativos</span>
                </div>
                
                <div class="space-y-4 flex-grow overflow-y-auto pr-1 no-scrollbar max-h-[250px]">
                    <?php 
                    $info_res = $conn->query("SELECT * FROM informacoes WHERE ativo = 1 ORDER BY created_at DESC LIMIT 6");
                    if ($info_res->num_rows > 0):
                        while($info = $info_res->fetch_assoc()):
                            $has_url = !empty($info['url']);
                    ?>
                        <div onclick='verDetalhesInfo(<?php echo json_encode($info, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)' 
                             class="group/item bg-background/30 rounded-lg p-3 border border-border/40 hover:border-primary/30 transition-all cursor-pointer">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-black text-primary uppercase tracking-widest flex items-center gap-1.5">
                                    <i data-lucide="<?php echo $info['icone'] ?: 'info'; ?>" class="w-3.5 h-3.5"></i>
                                    <?php echo $info['titulo']; ?>
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <i data-lucide="eye" class="w-3 h-3 text-primary opacity-0 group-hover/item:opacity-100 transition-all"></i>
                                    <?php if ($has_url): ?>
                                        <a href="<?php echo $info['url']; ?>" target="_blank" onclick="event.stopPropagation()" class="p-1 hover:bg-primary/10 rounded transition-colors text-text-secondary/60 hover:text-primary">
                                            <i data-lucide="external-link" class="w-3 h-3"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="h-0.5 w-full bg-gray-100 rounded-full overflow-hidden mt-2">
                                <div class="h-full bg-primary/20 group-hover/item:bg-primary transition-all duration-500 w-full transform origin-left scale-x-0 group-hover/item:scale-x-100"></div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <div class="h-full flex items-center justify-center py-8 opacity-20 italic text-[10px]">
                            Nenhuma informação cadastrada.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>


            <!-- Normas e Procedimentos (Diretoria) -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Normas & Procedimentos</h3>
                    </div>
                </div>
                
                <div class="space-y-2 flex-grow">
                    <?php 
                    $normas_res = $conn->query("SELECT * FROM normas_procedimentos WHERE ativo = 1 ORDER BY data_publicacao DESC LIMIT 4");
                    if ($normas_res && $normas_res->num_rows > 0):
                        while($nr = $normas_res->fetch_assoc()):
                    ?>
                        <div onclick='verNorma(<?php echo json_encode($nr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)' 
                             class="flex items-center justify-between p-2.5 bg-background/50 rounded-lg border border-border/40 hover:border-primary/20 transition-all group cursor-pointer">
                            <div class="flex flex-col truncate pr-2">
                                <span class="text-[10px] font-bold text-text group-hover:text-primary transition-colors"><?php echo $nr['titulo']; ?></span>
                                <span class="text-[8px] text-text-secondary opacity-60 uppercase font-black tracking-tighter"><?php echo date('d/m/Y', strtotime($nr['data_publicacao'])); ?></span>
                            </div>
                            <?php if ($nr['arquivo_path']): ?>
                                <div class="p-1.5 bg-white border border-border rounded shadow-sm group-hover:text-primary transition-all">
                                    <i data-lucide="eye" class="w-3 h-3"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <div class="h-full flex items-center justify-center py-8 opacity-20 italic text-[10px]">
                            Nenhuma norma publicada.
                        </div>
                    <?php endif; ?>
                </div>
                
                <a href="#" class="mt-6 w-full py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10 text-center">
                    Ver Diretrizes da Diretoria
                </a>
            </div>

            <!-- Educação Permanente -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'educacao')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="graduation-cap" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Educação Permanente</h3>
                    </div>
                </div>

                <div class="space-y-3 flex-grow">
                    <?php 
                    $treinos = $conn->query("SELECT * FROM edu_cursos WHERE status = 1 ORDER BY created_at DESC LIMIT 2");
                    if ($treinos->num_rows > 0):
                        while($tr = $treinos->fetch_assoc()):
                    ?>
                        <div class="bg-gray-50/30 rounded-xl p-3 border border-border/60 group hover:border-primary/50 hover:bg-white hover:shadow-md transition-all flex items-center gap-4">
                            <div class="w-14 h-14 rounded-lg bg-white overflow-hidden border border-border/80 shrink-0 shadow-sm">
                                <?php if ($tr['capa']): ?>
                                    <img src="<?php echo $tr['capa']; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-primary/5 text-primary opacity-20">
                                        <i data-lucide="book" class="w-6 h-6"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow min-w-0">
                                <h4 class="text-[11px] font-bold text-text truncate uppercase tracking-tight group-hover:text-primary transition-colors"><?php echo $tr['titulo']; ?></h4>
                                <p class="text-[9px] text-text-secondary line-clamp-1 mb-2 opacity-70"><?php echo $tr['instrutor'] ?: 'Instrutor não informado'; ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1 text-[8px] font-black text-primary/60 uppercase">
                                        <i data-lucide="clock" class="w-2.5 h-2.5"></i> <?php echo $tr['carga_horaria']; ?>
                                    </span>
                                    <a href="edu_curso.php?id=<?php echo $tr['id']; ?>" class="text-[9px] font-black text-primary uppercase flex items-center gap-1 group/link">
                                        Acessar <i data-lucide="chevron-right" class="w-3 h-3 group-hover/link:translate-x-0.5 transition-transform"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="h-full flex items-center justify-center py-8 opacity-20 italic text-[10px]">
                            Sem treinamentos agendados.
                        </div>
                    <?php endif; ?>
                </div>

                    <a href="educacao.php" class="mt-4 text-center py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10">
                        Ver Todos Treinamentos
                    </a>
                </div>
            <?php endif; ?>

            <!-- RH & Holerites -->
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'rh')): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full group hover:border-indigo-500 transition-all overflow-hidden relative">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center opacity-40 group-hover:scale-110 transition-transform">
                        <i data-lucide="users" class="w-10 h-10 text-indigo-200"></i>
                    </div>
                    <div class="relative z-10 flex flex-col h-full">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="users" class="w-4 h-4 text-indigo-500"></i>
                            <h3 class="text-sm font-bold text-text">RH & Holerites</h3>
                        </div>
                        <p class="text-[10px] text-text-secondary leading-relaxed mb-6 flex-grow">Acesse seus comprovantes de rendimentos, políticas internas e manuais do colaborador.</p>
                        
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Documentos Disponíveis</span>
                            <span class="text-xs font-black text-indigo-500"><?php echo $total_rh_novos; ?></span>
                        </div>

                        <a href="rh.php" class="w-full py-2 bg-indigo-500/5 hover:bg-indigo-500 text-indigo-500 hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-indigo-500/10 text-center">
                            Minha Área de RH
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Operational Quick Access (Slim) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            // Buscar chamados CEH pendentes para o card
            $ceh_pendentes = $conn->query("SELECT COUNT(*) as total FROM ceh_chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
            ?>
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'ceh')): ?>
            <a href="ceh.php" class="bg-white p-5 rounded-xl border border-border shadow-sm flex items-center h-full gap-5 hover:border-primary transition-all group cursor-pointer overflow-hidden relative">
                <div class="w-16 h-16 rounded-xl bg-primary/5 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500 relative">
                    <i data-lucide="stethoscope" class="w-8 h-8"></i>
                    <?php if ($ceh_pendentes > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-red-500/20">
                            <?php echo $ceh_pendentes; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow">
                    <h3 class="text-base font-bold text-text tracking-tight group-hover:text-primary transition-colors">Central de Equipamentos</h3>
                    <p class="text-[11px] text-text-secondary leading-relaxed mb-2">Solicite manutenção, calibração ou reporte problemas em equipamentos hospitalares (CEH).</p>
                    <div class="flex items-center gap-4">
                        <span class="text-[9px] font-black text-primary uppercase tracking-widest border-b border-primary/20 pb-0.5">Novo Chamado</span>
                        <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest border-b border-border pb-0.5">Meus Chamados</span>
                    </div>
                </div>
                <!-- Subtle context info -->
                <div class="absolute -right-2 -bottom-2 opacity-[0.03] group-hover:opacity-[0.08] transition-opacity">
                    <i data-lucide="stethoscope" class="w-24 h-24"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'manutencao')): ?>
            <a href="manutencao.php" class="bg-white p-5 rounded-xl border border-border shadow-sm flex items-center h-full gap-5 hover:border-primary transition-all group cursor-pointer overflow-hidden relative">
                <div class="w-16 h-16 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-all duration-500 relative">
                    <i data-lucide="wrench" class="w-8 h-8"></i>
                    <?php if ($total_manutencao_pendentes > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-red-500/20">
                            <?php echo $total_manutencao_pendentes; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-base font-bold text-text tracking-tight group-hover:text-primary transition-colors">Infraestrutura & Manutenção</h3>
                    <p class="text-[11px] text-text-secondary leading-relaxed mb-2">Relate problemas em infraestrutura ou equipamentos para a equipe de manutenção.</p>
                    <div class="flex items-center gap-4">
                        <span class="text-[9px] font-black text-orange-600 uppercase tracking-widest border-b border-orange-200 pb-0.5">Abrir Ordem</span>
                        <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest border-b border-border pb-0.5">Chamados Ativos</span>
                    </div>
                </div>
                <!-- Subtle context info -->
                <div class="absolute -right-2 -bottom-2 opacity-[0.03] group-hover:opacity-[0.08] transition-opacity">
                    <i data-lucide="wrench" class="w-24 h-24"></i>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>

    <!-- Modal de Detalhes da Informação -->
    <div id="modalInfoDetalhes" class="modal-info" onclick="fecharModalInfo(event)">
        <!-- ... existing content ... -->
    </div>

    <!-- Modal Visualizar Norma -->
    <div id="modalVisualizarNorma" class="modal-info p-4" onclick="fecharModalNorma(event)">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden transform transition-all group flex flex-col" onclick="event.stopPropagation()">
            <div class="bg-primary p-6 md:p-10 text-white relative overflow-hidden shrink-0">
                <div class="absolute right-0 top-0 -mr-16 -mt-16 w-64 h-64 bg-white/10 rounded-full group-hover:scale-110 transition-transform duration-700"></div>
                
                <div class="relative z-10 pr-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                            <i data-lucide="shield-check" class="w-6 h-6 text-white"></i>
                        </div>
                        <span id="normaData" class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80 decoration-white/30 underline underline-offset-4"></span>
                    </div>
                    <h2 id="normaTitulo" class="text-xl md:text-3xl font-black leading-tight tracking-tighter"></h2>
                </div>

                <button onclick="fecharModalNorma()" class="absolute top-6 right-6 md:top-8 md:right-8 p-3 hover:bg-white/20 rounded-full transition-all active:scale-90 z-20">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="p-6 md:p-10 overflow-y-auto custom-scrollbar flex-grow">
                <div class="mb-8 p-6 bg-gray-50 rounded-2xl border-l-4 border-primary/30 relative">
                    <div class="absolute top-0 right-0 p-4 opacity-[0.05]">
                        <i data-lucide="quote" class="w-12 h-12 text-primary"></i>
                    </div>
                    <label class="text-[10px] font-black text-primary uppercase tracking-widest mb-3 block">Diretriz Completa</label>
                    <div id="normaDescricao" class="text-sm text-text-secondary leading-relaxed whitespace-pre-wrap font-medium">
                    </div>
                </div>
            </div>

            <div class="p-6 md:p-10 pt-0 md:pt-0 shrink-0">
                <div class="pt-6 border-t border-border flex flex-col md:flex-row justify-between items-center gap-4">
                    <button onclick="fecharModalNorma()" class="px-8 py-3 text-[10px] font-black text-text-secondary hover:text-text transition-all uppercase tracking-widest order-2 md:order-1">Sair</button>
                    <a id="normaDownload" href="#" target="_blank" class="w-full md:w-auto bg-primary hover:bg-primary-hover text-white px-10 py-4 rounded-2xl text-[10px] font-black shadow-xl shadow-primary/20 transition-all flex items-center justify-center gap-3 active:scale-95 uppercase tracking-widest order-1 md:order-2">
                        Visualizar Documento <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function verNorma(norma) {
            document.getElementById('normaTitulo').textContent = norma.titulo;
            document.getElementById('normaDescricao').textContent = norma.descricao || 'Nao há descrição detalhada para esta norma.';
            
            // Formatar data
            const [ano, mes, dia] = norma.data_publicacao.split('-');
            document.getElementById('normaData').textContent = `Publicado em ${dia}/${mes}/${ano}`;
            
            // Link de Download/Visualização
            const downloadBtn = document.getElementById('normaDownload');
            if (norma.arquivo_path) {
                downloadBtn.href = norma.arquivo_path;
                downloadBtn.classList.remove('hidden');
            } else {
                downloadBtn.classList.add('hidden');
            }
            
            document.getElementById('modalVisualizarNorma').classList.add('active');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function fecharModalNorma(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('modalVisualizarNorma').classList.remove('active');
        }

        function verDetalhesInfo(info) {
            document.getElementById('infoTitulo').textContent = info.titulo;
            document.getElementById('infoTipo').textContent = info.tipo;
            document.getElementById('infoConteudo').innerHTML = info.conteudo ? info.conteudo.replace(/\n/g, '<br>') : '<em class="opacity-50">Sem conteúdo adicional.</em>';
            
            // Ícone
            const iconeName = info.icone || 'info';
            const iconeElement = document.getElementById('infoIcone');
            iconeElement.setAttribute('data-lucide', iconeName);
            
            // Link Externo
            const linkBtn = document.getElementById('infoLinkExterno');
            if (info.url && info.url.trim() !== '') {
                linkBtn.href = info.url;
                linkBtn.classList.remove('hidden');
            } else {
                linkBtn.classList.add('hidden');
            }
            
            // Ativar modal
            document.getElementById('modalInfoDetalhes').classList.add('active');
            
            // Atualizar ícones lucide dentro do modal
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function fecharModalInfo() {
            document.getElementById('modalInfoDetalhes').classList.remove('active');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        }
    </script>
</body>
</html>
