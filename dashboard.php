<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Verifica se o usuário já aceitou os termos de uso
$uid = $_SESSION['usuario_id'];
$termos_row = $conn->query("SELECT aceite_termos FROM usuarios WHERE id = $uid")->fetch_assoc();
$precisa_aceitar = empty($termos_row['aceite_termos']);

$total_avisos = $conn->query("SELECT COUNT(*) as total FROM mural WHERE ativo = 1 AND (data_expiracao IS NULL OR data_expiracao >= CURDATE())")->fetch_assoc()['total'];
$ultimo_aviso = $conn->query("SELECT titulo FROM mural WHERE ativo = 1 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

$total_eventos_hoje = $conn->query("SELECT COUNT(*) as total FROM agenda WHERE ativo = 1 AND data_evento = CURDATE()")->fetch_assoc()['total'];

$mes_dia_atual = date('m-d');
$total_niver_hoje = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE data_nascimento LIKE '%-$mes_dia_atual' AND ativo = 1")->fetch_assoc()['total'];

$mes_atual = date('m');
$total_niver_mes = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE MONTH(data_nascimento) = '$mes_atual' AND ativo = 1")->fetch_assoc()['total'];

$total_chamados_pendentes = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE usuario_id = $uid AND status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];

// Total chamados manutenção pendentes
$total_manutencao_pendentes = $conn->query("SELECT COUNT(*) as total FROM manutencao WHERE usuario_id = $uid AND status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];

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

// Banner do Dashboard
$banners_ativos = [];
$res_banner = $conn->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY created_at DESC");
if ($res_banner && $res_banner->num_rows > 0) {
    while ($row = $res_banner->fetch_assoc()) {
        $banners_ativos[] = $row;
    }
}

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

        <!-- Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'mural')): ?>
            <a href="mural.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-orange-400 hover:shadow-md transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-all shrink-0">
                        <i data-lucide="megaphone" class="w-5 h-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-2xl font-black text-text leading-none"><?php echo $total_avisos; ?></h3>
                        <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider mt-0.5">Avisos Ativos</p>
                        <?php if ($ultimo_aviso): ?>
                        <p class="text-[9px] text-orange-400 truncate mt-0.5 leading-tight"><?php echo htmlspecialchars($ultimo_aviso['titulo']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'agenda')): ?>
            <a href="agenda.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-emerald-400 hover:shadow-md transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-all shrink-0">
                        <i data-lucide="calendar" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-text leading-none"><?php echo $total_eventos_hoje; ?></h3>
                        <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider mt-0.5">Eventos Hoje</p>
                        <p class="text-[9px] text-emerald-400 mt-0.5"><?php echo date('d/m'); ?></p>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'aniversariantes')): ?>
            <a href="aniversariantes.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-pink-400 hover:shadow-md transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center text-pink-500 group-hover:bg-pink-500 group-hover:text-white transition-all shrink-0">
                        <i data-lucide="cake" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black <?php echo $total_niver_hoje > 0 ? 'text-pink-500' : 'text-text'; ?> leading-none"><?php echo $total_niver_mes; ?></h3>
                        <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider mt-0.5">Aniversariantes</p>
                        <?php if ($total_niver_hoje > 0): ?>
                        <p class="text-[9px] text-pink-500 font-bold mt-0.5"><?php echo $total_niver_hoje; ?> hoje!</p>
                        <?php else: ?>
                        <p class="text-[9px] text-text-secondary/50 mt-0.5">no m&ecirc;s</p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'educacao')): ?>
            <a href="educacao.php" class="bg-white p-4 rounded-xl shadow-sm border border-border group hover:border-violet-400 hover:shadow-md transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center text-violet-500 group-hover:bg-violet-500 group-hover:text-white transition-all shrink-0">
                        <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-grow min-w-0">
                        <h3 class="text-2xl font-black text-text leading-none"><?php echo $percentual_global; ?><span class="text-sm font-bold text-text-secondary ml-0.5">%</span></h3>
                        <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider mt-0.5">Meu Progresso</p>
                        <div class="mt-1.5 h-1 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-violet-400 rounded-full" style="width:<?php echo $percentual_global; ?>%"></div>
                        </div>
                    </div>
                </div>
            </a>
            <?php endif; ?>

        </div>

        <!-- Service Cards: TI &middot; CEH &middot; Manuten&ccedil;&atilde;o -->
        <?php $ceh_pendentes = $conn->query("SELECT COUNT(*) as total FROM ceh_chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Pe&ccedil;a')")->fetch_assoc()['total']; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'suporte')): ?>
            <a href="suporte.php" class="bg-white rounded-xl border border-border shadow-sm flex items-center gap-4 p-5 hover:border-indigo-400 hover:shadow-md transition-all group overflow-hidden relative">
                <div class="w-14 h-14 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-all duration-300 shrink-0 relative">
                    <i data-lucide="monitor-dot" class="w-7 h-7"></i>
                    <?php if ($total_chamados_pendentes > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow ring-2 ring-red-500/20">
                        <?php echo $total_chamados_pendentes; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow min-w-0">
                    <h3 class="text-sm font-bold text-text group-hover:text-indigo-600 transition-colors">Suporte de TI</h3>
                    <p class="text-[10px] text-text-secondary leading-relaxed mt-0.5 mb-2.5">Reporte problemas em equipamentos ou sistemas de TI.</p>
                    <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Abrir chamado &rarr;</span>
                </div>
                <div class="absolute -right-3 -bottom-3 opacity-[0.04] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i data-lucide="monitor-dot" class="w-20 h-20"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'ceh')): ?>
            <a href="ceh.php" class="bg-white rounded-xl border border-border shadow-sm flex items-center gap-4 p-5 hover:border-primary hover:shadow-md transition-all group overflow-hidden relative">
                <div class="w-14 h-14 rounded-xl bg-primary/5 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-300 shrink-0 relative">
                    <i data-lucide="stethoscope" class="w-7 h-7"></i>
                    <?php if ($ceh_pendentes > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow ring-2 ring-red-500/20">
                        <?php echo $ceh_pendentes; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow min-w-0">
                    <h3 class="text-sm font-bold text-text group-hover:text-primary transition-colors">Central de Equipamentos</h3>
                    <p class="text-[10px] text-text-secondary leading-relaxed mt-0.5 mb-2.5">Manuten&ccedil;&atilde;o e calibra&ccedil;&atilde;o de equipamentos hospitalares (CEH).</p>
                    <span class="text-[9px] font-black text-primary uppercase tracking-widest">Abrir chamado &rarr;</span>
                </div>
                <div class="absolute -right-3 -bottom-3 opacity-[0.04] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i data-lucide="stethoscope" class="w-20 h-20"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'manutencao')): ?>
            <a href="manutencao.php" class="bg-white rounded-xl border border-border shadow-sm flex items-center gap-4 p-5 hover:border-orange-400 hover:shadow-md transition-all group overflow-hidden relative">
                <div class="w-14 h-14 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-all duration-300 shrink-0 relative">
                    <i data-lucide="wrench" class="w-7 h-7"></i>
                    <?php if ($total_manutencao_pendentes > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow ring-2 ring-red-500/20">
                        <?php echo $total_manutencao_pendentes; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow min-w-0">
                    <h3 class="text-sm font-bold text-text group-hover:text-orange-600 transition-colors">Infraestrutura &amp; Manuten&ccedil;&atilde;o</h3>
                    <p class="text-[10px] text-text-secondary leading-relaxed mt-0.5 mb-2.5">Relate problemas em infraestrutura ou equipamentos.</p>
                    <span class="text-[9px] font-black text-orange-500 uppercase tracking-widest">Abrir ordem &rarr;</span>
                </div>
                <div class="absolute -right-3 -bottom-3 opacity-[0.04] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i data-lucide="wrench" class="w-20 h-20"></i>
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
            <?php if (temPermissao($conn, $_SESSION['setor_id'], 'normas')): ?>
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
                
                <a href="normas.php" class="mt-6 w-full py-2 bg-primary/5 hover:bg-primary text-primary hover:text-white rounded-lg text-[9px] font-black transition-all uppercase tracking-widest border border-primary/10 text-center">
                    Ver Diretrizes da Diretoria
                </a>
            </div>
            <?php endif; ?>

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

            <!-- Banner do Dashboard (Carrossel) -->
            <?php if (!empty($banners_ativos)): ?>
            <div id="banner-carousel" class="rounded-xl border border-border shadow-sm overflow-hidden relative bg-gray-100 group" style="height:260px">
                <!-- Slides -->
                <?php foreach ($banners_ativos as $idx => $banner): ?>
                <div class="banner-slide absolute inset-0 transition-opacity duration-700 <?php echo $idx === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0'; ?>" style="will-change:opacity;backface-visibility:hidden">
                    <!-- Área clicável para lightbox (cobre toda a imagem) -->
                    <button type="button"
                            onclick="bannerLightbox('uploads/banners/<?php echo htmlspecialchars($banner['imagem']); ?>', '<?php echo htmlspecialchars(addslashes($banner['titulo'])); ?>')"
                            class="absolute inset-0 w-full h-full cursor-zoom-in focus:outline-none"
                            aria-label="Ampliar imagem"></button>
                        <img src="uploads/banners/<?php echo htmlspecialchars($banner['imagem']); ?>"
                             alt="<?php echo htmlspecialchars($banner['titulo']); ?>"
                             class="absolute inset-0 w-full h-full object-contain pointer-events-none"
                             style="image-rendering:high-quality"
                             <?php echo $idx === 0 ? 'loading="eager"' : 'loading="lazy"'; ?>>
                        <?php if (!empty($banner['link_url'])): ?>
                        <div class="absolute bottom-3 right-3 z-10 pointer-events-none">
                            <span class="flex items-center gap-1 px-2 py-1 bg-black/50 backdrop-blur-sm text-white rounded-lg text-[9px] font-black uppercase tracking-widest">
                                <i data-lucide="external-link" class="w-3 h-3"></i> Ver mais
                            </span>
                        </div>
                        <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank" rel="noopener noreferrer"
                           class="absolute bottom-3 right-3 z-20 flex items-center gap-1 px-2 py-1 bg-black/50 backdrop-blur-sm text-white rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-black/70 transition-colors">
                            <i data-lucide="external-link" class="w-3 h-3"></i> Ver mais
                        </a>
                        <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if (count($banners_ativos) > 1): ?>
                <!-- Setas de navegação -->
                <button onclick="bannerPrev()" class="absolute left-2 top-1/2 -translate-y-1/2 z-20 w-7 h-7 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center transition-all opacity-0 group-hover:opacity-100">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <button onclick="bannerNext()" class="absolute right-2 top-1/2 -translate-y-1/2 z-20 w-7 h-7 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center transition-all opacity-0 group-hover:opacity-100">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
                <!-- Indicadores (bolinhas) -->
                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 z-20 flex gap-1.5">
                    <?php foreach ($banners_ativos as $idx => $banner): ?>
                    <button onclick="bannerGoTo(<?php echo $idx; ?>)" class="banner-dot w-2 h-2 rounded-full transition-all <?php echo $idx === 0 ? 'bg-white scale-125' : 'bg-white/50'; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Lightbox -->
            <div id="banner-lightbox" class="fixed inset-0 z-[999] flex items-center justify-center bg-black/80 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300" onclick="bannerLightboxClose()">
                <div class="relative max-w-5xl w-full mx-4" onclick="event.stopPropagation()">
                    <button onclick="bannerLightboxClose()" class="absolute -top-10 right-0 text-white/80 hover:text-white flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i> Fechar
                    </button>
                    <img id="banner-lightbox-img" src="" alt="" class="w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
                </div>
            </div>

            <script>
            (function() {
                const slides = document.querySelectorAll('#banner-carousel .banner-slide');
                const dots   = document.querySelectorAll('#banner-carousel .banner-dot');
                let current  = 0;
                let timer    = null;

                function goTo(n) {
                    slides[current].classList.replace('opacity-100', 'opacity-0');
                    slides[current].classList.replace('z-10', 'z-0');
                    if (dots[current]) { dots[current].classList.remove('bg-white', 'scale-125'); dots[current].classList.add('bg-white/50'); }
                    current = (n + slides.length) % slides.length;
                    slides[current].classList.replace('opacity-0', 'opacity-100');
                    slides[current].classList.replace('z-0', 'z-10');
                    if (dots[current]) { dots[current].classList.remove('bg-white/50'); dots[current].classList.add('bg-white', 'scale-125'); }
                }

                function next() { goTo(current + 1); }
                function prev() { goTo(current - 1); }

                function startTimer() { if (slides.length > 1) timer = setInterval(next, 5000); }
                function resetTimer()  { clearInterval(timer); startTimer(); }

                window.bannerNext  = function() { next(); resetTimer(); };
                window.bannerPrev  = function() { prev(); resetTimer(); };
                window.bannerGoTo  = function(n) { goTo(n); resetTimer(); };

                window.bannerLightbox = function(src, alt) {
                    const lb  = document.getElementById('banner-lightbox');
                    const img = document.getElementById('banner-lightbox-img');
                    img.src = src;
                    img.alt = alt;
                    lb.classList.remove('opacity-0', 'pointer-events-none');
                    lb.classList.add('opacity-100');
                    document.body.style.overflow = 'hidden';
                };

                window.bannerLightboxClose = function() {
                    const lb = document.getElementById('banner-lightbox');
                    lb.classList.remove('opacity-100');
                    lb.classList.add('opacity-0', 'pointer-events-none');
                    document.body.style.overflow = '';
                };

                // Fechar com ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') window.bannerLightboxClose();
                });

                startTimer();
            })();
            </script>
            <?php endif; ?>
        </div>

    </div>
    
    <?php include 'footer.php'; ?>

    <!-- Modal de Detalhes da Informação -->
    <div id="modalInfoDetalhes" class="modal-info p-4" onclick="fecharModalInfo(event)">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex transition-all group" onclick="event.stopPropagation()">

            <!-- Coluna Esquerda — Identidade visual -->
            <div class="bg-primary w-56 shrink-0 relative overflow-hidden flex flex-col justify-between p-7">
                <!-- Círculos decorativos -->
                <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-white/10 rounded-full"></div>
                <div class="absolute -top-6 -right-6 w-28 h-28 bg-white/10 rounded-full group-hover:scale-110 transition-transform duration-700"></div>

                <div class="relative z-10 flex flex-col gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                        <i id="infoIcone" data-lucide="info" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <span id="infoTipo" class="text-[9px] font-black uppercase tracking-[0.2em] text-white/60 block mb-1"></span>
                        <h2 id="infoTitulo" class="text-lg font-black text-white leading-snug tracking-tight"></h2>
                    </div>
                </div>

                <div class="relative z-10">
                    <a id="infoLinkExterno" href="#" target="_blank" class="hidden mt-4 w-full bg-white/20 hover:bg-white/30 text-white px-4 py-2.5 rounded-xl text-[10px] font-black flex items-center justify-center gap-2 transition-all active:scale-95 uppercase tracking-widest">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Acessar Link
                    </a>
                </div>
            </div>

            <!-- Coluna Direita — Conteúdo -->
            <div class="flex flex-col flex-grow min-w-0">
                <!-- Barra superior -->
                <div class="flex items-center justify-between px-7 pt-6 pb-4 border-b border-border shrink-0">
                    <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary"></i>
                        Informativo
                    </span>
                    <button onclick="fecharModalInfo()" class="p-2 hover:bg-gray-100 rounded-full transition-all active:scale-90 text-text-secondary">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Área de conteúdo scrollável -->
                <div class="flex-grow overflow-y-auto px-7 py-6">
                    <div id="infoConteudo" class="text-sm text-text leading-relaxed"></div>
                </div>

                <!-- Rodapé -->
                <div class="px-7 py-4 border-t border-border shrink-0 flex justify-end">
                    <button onclick="fecharModalInfo()" class="px-6 py-2 text-[10px] font-black text-text-secondary hover:text-text border border-border rounded-xl transition-all uppercase tracking-widest">
                        Fechar
                    </button>
                </div>
            </div>

        </div>
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

        <?php if ($precisa_aceitar): ?>
        // Abre o modal de termos automaticamente
        window.addEventListener('DOMContentLoaded', function () {
            document.getElementById('modalTermos').style.display = 'flex';
        });
        <?php endif; ?>

        function recusarTermos() {
            const corpo = document.getElementById('modalTermosCorpo');
            const rodape = document.getElementById('modalTermosRodape');
            corpo.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px 24px;gap:20px;">
                    <div style="background:#fef2f2;border:2px solid #fecaca;border-radius:50%;padding:20px;">
                        <i data-lucide="shield-x" style="width:48px;height:48px;color:#dc2626;"></i>
                    </div>
                    <div>
                        <h3 style="font-size:17px;font-weight:800;color:#1e293b;margin-bottom:8px;">Acesso Não Autorizado</h3>
                        <p style="font-size:13px;color:#64748b;line-height:1.6;max-width:420px;">
                            O aceite da <strong style="color:#dc2626;">Política de Uso</strong> é obrigatório para utilizar a Intranet APAS Baixada Santista.
                        </p>
                        <p style="font-size:12px;color:#94a3b8;margin-top:10px;line-height:1.6;">
                            Sem o aceite, não é possível acessar nenhum recurso da plataforma.
                            Se tiver dúvidas sobre a política, entre em contato com o setor de TI.
                        </p>
                    </div>
                </div>
            `;
            rodape.innerHTML = `
                <div style="display:flex;gap:10px;">
                    <button onclick="location.href='logout.php'"
                            style="background:#dc2626;color:#fff;border:none;padding:10px 22px;border-radius:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;">
                        Sair da Intranet
                    </button>
                    <button onclick="location.reload()"
                            style="background:#e2e8f0;color:#475569;border:none;padding:10px 22px;border-radius:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;">
                        Voltar e Ler os Termos
                    </button>
                </div>
            `;
            lucide.createIcons();
        }

        function aceitarTermos() {
            const btn = document.getElementById('btnAceitarTermos');
            btn.disabled = true;
            btn.textContent = 'Registrando...';
            fetch('aceitar_termos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'acao=aceitar'
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    document.getElementById('modalTermos').style.display = 'none';
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Li e Aceito os Termos';
                    alert('Erro ao registrar aceite. Tente novamente.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Li e Aceito os Termos';
                alert('Erro de conexão. Tente novamente.');
            });
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

    <!-- ── Modal Termos de Uso ───────────────────────────────────────────── -->
    <div id="modalTermos" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:20px;box-shadow:0 24px 64px rgba(0,0,0,.35);width:100%;max-width:520px;overflow:hidden;display:flex;flex-direction:column;max-height:78vh;">
            <!-- Cabeçalho -->
            <div style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:24px 28px;color:#fff;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="background:rgba(255,255,255,.15);padding:10px;border-radius:12px;">
                        <i data-lucide="file-check-2" style="width:28px;height:28px;"></i>
                    </div>
                    <div>
                        <h2 style="font-size:18px;font-weight:800;letter-spacing:-.3px;line-height:1.2;">Política de Uso da Intranet</h2>
                        <p style="font-size:11px;opacity:.8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-top:2px;">Leia com atenção antes de continuar</p>
                    </div>
                </div>
            </div>

            <!-- Corpo -->
            <div id="modalTermosCorpo" style="padding:24px 28px;flex-grow:1;overflow-y:auto;">
                <p style="font-size:12px;color:#64748b;line-height:1.7;margin-bottom:16px;">
                    Para utilizar a Intranet APAS você deve ler e aceitar a <strong style="color:#0d9488;">Política de Uso</strong>
                    disponibilizada abaixo. O aceite é obrigatório e registrado com data/hora.
                </p>
                <!-- Visualizador de PDF -->
                <div style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;height:220px;background:#f8fafc;">
                    <iframe src="politica_uso_intranet_colaborador_apas.pdf"
                            style="width:100%;height:100%;border:none;"
                            title="Política de Uso da Intranet APAS">
                        <p style="padding:16px;font-size:12px;color:#64748b;">
                            Seu navegador não suporta visualização de PDF.
                            <a href="politica_uso_intranet_colaborador_apas.pdf" target="_blank" style="color:#0d9488;font-weight:700;">Clique aqui para abrir</a>.
                        </p>
                    </iframe>
                </div>
                <a href="politica_uso_intranet_colaborador_apas.pdf" target="_blank"
                   style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:11px;font-weight:700;color:#0d9488;text-decoration:none;letter-spacing:.03em;">
                    <i data-lucide="external-link" style="width:13px;height:13px;"></i>
                    Abrir em nova aba
                </a>

                <!-- Checkbox de confirmação -->
                <label style="display:flex;align-items:flex-start;gap:10px;margin-top:18px;padding:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;cursor:pointer;">
                    <input type="checkbox" id="chkTermos" onchange="var b=document.getElementById('btnAceitarTermos');b.disabled=!this.checked;b.style.opacity=this.checked?'1':'.5';"
                           style="width:16px;height:16px;margin-top:2px;accent-color:#0d9488;flex-shrink:0;">
                    <span style="font-size:12px;color:#166534;font-weight:600;line-height:1.5;">
                        Declaro que li e concordo com a <strong>Política de Uso da Intranet APAS Baixada Santista</strong> e me comprometo a cumprir todas as diretrizes nela estabelecidas.
                    </span>
                </label>
            </div>

            <!-- Rodapé -->
            <div id="modalTermosRodape" style="padding:16px 28px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;flex-shrink:0;gap:10px;">
                <button onclick="recusarTermos()"
                        style="background:#fff;color:#dc2626;border:2px solid #fecaca;padding:10px 22px;border-radius:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;transition:background .2s;"
                        onmouseover="this.style.background='#fef2f2'"
                        onmouseout="this.style.background='#fff'">
                    Não Aceito
                </button>
                <button id="btnAceitarTermos" disabled onclick="aceitarTermos()"
                        style="background:#0d9488;color:#fff;border:none;padding:10px 28px;border-radius:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;opacity:.5;transition:opacity .2s;"
                        onmouseover="if(!this.disabled)this.style.background='#0f766e'"
                        onmouseout="this.style.background='#0d9488'"
                        onfocus="this.style.opacity=this.disabled?'.5':'1'"
                        >
                    Li e Aceito os Termos
                </button>
            </div>
        </div>
    </div>
    <!-- ─────────────────────────────────────────────────────────────────── -->

</body>
</html>
