<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];

// Buscar Documentos do Colaborador
$documentos = $conn->query("
    SELECT * FROM rh_documentos 
    WHERE usuario_id = $usuario_id 
    ORDER BY ano DESC, mes DESC, created_at DESC
");

// Buscar Políticas Ativas
$politicas = $conn->query("
    SELECT * FROM rh_politicas 
    WHERE ativo = 1 
    ORDER BY titulo ASC
");

// Buscar Mensagens do RH
$mensagens = $conn->query("
    SELECT * FROM rh_mensagens 
    WHERE usuario_id = $usuario_id 
    ORDER BY created_at DESC
");

// Marcar como lidas ao acessar a página
$conn->query("UPDATE rh_mensagens SET lida = 1 WHERE usuario_id = $usuario_id AND lida = 0");

// Buscar Ocorrências Pendentes para validação do Supervisor (caso seja supervisor de alguém)
$pendentes_supervisor = $conn->query("
    SELECT o.*, u.nome as colaborador_nome 
    FROM rh_ponto_ocorrencias o 
    JOIN usuarios u ON o.usuario_id = u.id 
    WHERE o.supervisor_id = $usuario_id AND o.status = 'PENDENTE'
    ORDER BY o.created_at ASC
");

$meses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recursos Humanos - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .rh-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .rh-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        .bg-pattern { background-image: radial-gradient(circle at 2px 2px, rgba(0,0,0,0.03) 1px, transparent 0); background-size: 24px 24px; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 bg-pattern">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto min-h-screen">
        <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <h1 class="text-3xl font-black text-primary mb-2 tracking-tight">Central do Colaborador</h1>
                <p class="text-text-secondary text-sm font-medium">Bem-vindo à sua área de Recursos Humanos. Aqui você encontra seus documentos e manuais da instituição.</p>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <a href="rh_ponto.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-primary rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4"></i> Minhas Ocorrências
                </a>
                <a href="rh_ponto_novo.php" class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-primary/20 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Nova Ocorrência
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Coluna Esquerda: Mensagens e Documentos -->
            <div class="lg:col-span-2 space-y-8">
                <?php if ($pendentes_supervisor->num_rows > 0): ?>
                    <!-- Sessão Supervisor: Validações Pendentes -->
                    <section class="animate-in fade-in slide-in-from-top-4 duration-500">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500 border border-amber-500/20">
                                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-text">Validações Pendentes</h2>
                                <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Solicitações aguardando sua revisão</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <?php while($ps = $pendentes_supervisor->fetch_assoc()): ?>
                            <div class="bg-amber-50/20 p-6 rounded-3xl border border-amber-100/50 flex flex-col md:flex-row md:items-center justify-between gap-6 group hover:border-amber-200 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-500 font-bold text-xs uppercase">
                                        <?php echo substr($ps['colaborador_nome'], 0, 2); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-amber-900"><?php echo $ps['colaborador_nome']; ?></h3>
                                        <p class="text-[10px] text-amber-800/60 font-medium">Ocorrência de <?php echo $meses[$ps['mes']] . '/' . $ps['ano']; ?> • <?php echo $ps['tipo']; ?></p>
                                    </div>
                                </div>
                                <a href="rh_ponto_detalhes.php?id=<?php echo $ps['id']; ?>" class="px-4 py-2 bg-amber-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-amber-500/20 hover:scale-105 active:scale-95 transition-all">
                                    Revisar Agora
                                </a>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <section>
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20">
                            <i data-lucide="mail" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-text">Comunicados Individuais</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Mensagens diretas do RH para você</p>
                        </div>
                    </div>

                    <div class="space-y-4 mb-12">
                        <?php if ($mensagens->num_rows > 0): ?>
                            <?php while($m = $mensagens->fetch_assoc()): ?>
                            <div class="rh-card bg-emerald-50/30 p-6 rounded-3xl border border-emerald-100/50 relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-500/5 rounded-full blur-xl"></div>
                                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-3 relative z-10">
                                    <h3 class="text-sm font-bold text-emerald-900"><?php echo $m['assunto']; ?></h3>
                                    <span class="text-[9px] font-black text-emerald-600/60 uppercase"><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></span>
                                </div>
                                <div class="text-xs text-emerald-800/80 leading-relaxed relative z-10 whitespace-pre-wrap"><?php echo $m['mensagem']; ?></div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-8 bg-gray-50/50 border border-dashed border-border rounded-3xl text-center">
                                <p class="text-[11px] font-bold text-text-secondary opacity-40 uppercase tracking-widest italic">Nenhuma mensagem direta no momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                            <i data-lucide="file-check" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-text">Meus Documentos</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Holerites e Comprovantes Pessoais</p>
                        </div>
                    </div>

                    <?php if ($documentos->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php while($d = $documentos->fetch_assoc()): ?>
                            <?php 
                                $arquivo_nome = basename($d['arquivo_path']);
                                $extensao = pathinfo($arquivo_nome, PATHINFO_EXTENSION);
                                $is_pdf = strtolower($extensao) == 'pdf';
                                $download_link = $root_path . "download.php?file=" . urlencode($arquivo_nome) . "&type=documento";
                            ?>
                            <a href="<?php echo $download_link; ?>" 
                               target="_blank" 
                               class="rh-card bg-white p-5 rounded-3xl border border-border flex items-center gap-4 group transition-all hover:border-primary active:scale-[0.98]">
                                <div class="w-12 h-12 rounded-2xl bg-gray-50 flex flex-col items-center justify-center text-text-secondary group-hover:bg-primary group-hover:text-white transition-all shadow-sm">
                                    <span class="text-[10px] font-black uppercase leading-none"><?php echo $d['mes'] ? $meses[$d['mes']] : 'DOC'; ?></span>
                                    <span class="text-xs font-bold"><?php echo $d['ano'] ?: ''; ?></span>
                                </div>
                                <div class="flex-grow">
                                    <h3 class="text-sm font-bold text-text leading-tight group-hover:text-primary transition-colors"><?php echo $d['titulo']; ?></h3>
                                    <span class="text-[9px] font-black uppercase text-text-secondary opacity-50"><?php echo $d['categoria']; ?></span>
                                </div>
                                <div class="flex flex-col items-center gap-1 opacity-20 group-hover:opacity-100 transition-all">
                                    <i data-lucide="<?php echo $is_pdf ? 'eye' : 'download'; ?>" class="w-5 h-5 text-primary"></i>
                                    <span class="text-[8px] font-black uppercase tracking-tighter text-primary"><?php echo $is_pdf ? 'Ver' : 'Baixar'; ?></span>
                                </div>
                            </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white/50 border-2 border-dashed border-border rounded-3xl p-12 text-center">
                            <i data-lucide="file-search" class="w-12 h-12 text-text-secondary opacity-20 mx-auto mb-4"></i>
                            <h3 class="text-sm font-bold text-text-secondary uppercase tracking-widest leading-relaxed">Nenhum documento disponível ainda</h3>
                            <p class="text-[11px] text-text-secondary opacity-60 mt-2 px-12 italic">Fique tranquilo, assim que o RH disponibilizar seus holerites ou contratos, eles aparecerão aqui.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Coluna Direita: Políticas e Manuais -->
            <div class="space-y-8">
                <section>
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-500 border border-indigo-500/20">
                            <i data-lucide="book-open" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-text">Políticas Internas</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Manuais e Procedimentos</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php if ($politicas->num_rows > 0): ?>
                            <?php while($p = $politicas->fetch_assoc()): ?>
                            <div class="rh-card bg-white p-5 rounded-3xl border border-border group">
                                <div class="flex items-start justify-between mb-3">
                                    <h3 class="text-sm font-bold text-text group-hover:text-indigo-500 transition-colors"><?php echo $p['titulo']; ?></h3>
                                    <i data-lucide="file-text" class="w-4 h-4 text-indigo-500/40"></i>
                                </div>
                                <p class="text-[11px] text-text-secondary leading-relaxed mb-4 line-clamp-3"><?php echo $p['descricao']; ?></p>
                                <?php if ($p['arquivo_path']): 
                                        $pol_nome = basename($p['arquivo_path']);
                                        $pol_link = $root_path . "download.php?file=" . urlencode($pol_nome) . "&type=politica";
                                    ?>
                                    <a href="<?php echo $pol_link; ?>" target="_blank" class="w-full py-2.5 bg-indigo-50 text-indigo-600 rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 group-hover:bg-indigo-500 group-hover:text-white transition-all shadow-md active:scale-95">
                                        <i data-lucide="external-link" class="w-3 h-3"></i> Acessar Manual
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-6 bg-gray-50 rounded-3xl text-center border border-border">
                                <p class="text-xs font-bold text-text-secondary opacity-40 italic">Nenhum manual publicado.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Sessão de Contato/Suporte RH -->
                <div class="p-6 bg-primary rounded-3xl shadow-xl shadow-primary/20 text-white relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-all duration-700"></div>
                    <h3 class="font-bold mb-2 relative z-10">Dúvidas com o RH?</h3>
                    <p class="text-[11px] text-white/70 mb-4 relative z-10 font-medium">Entre em contato com o setor para dúvidas sobre holerites, férias ou benefícios.</p>
                    <a href="telefones.php" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest bg-white text-primary px-4 py-2 rounded-xl relative z-10 transition-transform active:scale-95 shadow-lg">
                        <i data-lucide="phone" class="w-3 h-3"></i> Ramal do RH
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
