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
        </div>

        <!-- Main Dashboard Modules -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Biblioteca / Protocolos -->
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

            <!-- Tecnologia da Informação - Artigos -->
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

            <!-- Ramais & Telefones -->
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

            <!-- Qualidade / Métricas -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-border flex flex-col h-full">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="bar-chart" class="w-4 h-4 text-primary"></i>
                        <h3 class="text-sm font-bold text-text">Indicadores de Qualidade</h3>
                    </div>
                </div>
                
                <div class="space-y-5 flex-grow">
                    <div>
                        <div class="flex justify-between items-end mb-1.5">
                            <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Satisfação do Cliente</span>
                            <span class="text-xs font-black text-primary">94%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-50 rounded-full overflow-hidden border border-border/20">
                            <div class="h-full bg-primary rounded-full transition-all duration-1000" style="width: 94%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-end mb-1.5">
                            <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Tempo Médio de Atendimento</span>
                            <span class="text-xs font-black text-primary">22 min</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-50 rounded-full overflow-hidden border border-border/20">
                            <div class="h-full bg-indigo-400 rounded-full transition-all duration-1000" style="width: 75%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 p-3 bg-primary/[0.03] border border-primary/10 rounded-lg flex items-center justify-between group cursor-pointer hover:bg-primary/5 transition-all">
                    <div>
                        <p class="text-[10px] font-black text-primary uppercase tracking-tighter">Meta Mensal</p>
                        <p class="text-[11px] font-bold text-text-secondary">Unidade atingiu 98% da meta</p>
                    </div>
                    <i data-lucide="trending-up" class="w-4 h-4 text-primary opacity-30 group-hover:opacity-100 transition-opacity"></i>
                </div>
            </div>

            <!-- Educação Permanente -->
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

            <!-- RH & Holerites -->
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
        </div>

        <!-- Operational Quick Access (Slim) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            // Buscar chamados CEH pendentes para o card
            $ceh_pendentes = $conn->query("SELECT COUNT(*) as total FROM ceh_chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça')")->fetch_assoc()['total'];
            ?>
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
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
