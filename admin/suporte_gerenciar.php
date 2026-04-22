<?php
require_once '../config.php';
require_once '../functions.php';

requireTecnico();

if (isset($_GET['msg']) && $_GET['msg'] == 'sucesso') {
    $msg_id = intval($_GET['id']);
    $mensagem = "Chamado #$msg_id atualizado!";
    $tipo_mensagem = "success";
} elseif (isset($_GET['msg']) && $_GET['msg'] == 'comentario_ok') {
    $mensagem = "Comentário adicionado com sucesso!";
    $tipo_mensagem = "success";
}

// Processar Atualização de Chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_chamado') {
    $id = intval($_POST['id']);
    $status = sanitize($_POST['status']);
    $resolucao = $_POST['resolucao'];
    $tecnico_id = intval($_POST['tecnico_id']);
    $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE chamados SET status = ?, resolucao = ?, tecnico_id = ?, data_fechamento = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $status, $resolucao, $tecnico_id, $data_fechamento, $id);

    if ($stmt->execute()) {
        registrarLog($conn, "Atualizou chamado #$id para status: $status");
        header("Location: suporte_gerenciar.php?msg=sucesso&id=$id");
        exit;
    } else {
        $mensagem = "Erro ao atualizar: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar Novo Comentário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_comentario') {
    $chamado_id = intval($_POST['chamado_id']);
    $usuario_id = $_SESSION['usuario_id'];
    $comentario = sanitize($_POST['comentario']);

    if (!empty($comentario)) {
        // Ao inserir comentário do técnico, marcar como lido pelo técnico (1) e não lido pelo usuário (0)
        $stmt = $conn->prepare("INSERT INTO chamados_comentarios (chamado_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario) VALUES (?, ?, ?, 1, 0)");
        $stmt->bind_param("iis", $chamado_id, $usuario_id, $comentario);
        if ($stmt->execute()) {
            header("Location: suporte_gerenciar.php?msg=comentario_ok&id=$chamado_id");
            exit;
        }
        $stmt->close();
    }
}

// Processar Marcação de Leitura pelo Técnico (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'marcar_lido_tecnico') {
    $chamado_id = intval($_POST['chamado_id']);
    $conn->query("UPDATE chamados_comentarios SET lido_pelo_tecnico = 1 WHERE chamado_id = $chamado_id");
    exit;
}

// Processar exclusão do chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_chamado' && isAdmin()) {
    $id = intval($_POST['id']);
    
    $stmt = $conn->prepare("DELETE FROM chamados WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $mensagem = "Chamado #$id removido com sucesso!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu chamado #$id");
    } else {
        $mensagem = "Erro ao excluir: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Buscar todos os chamados com detalhes
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante,
               c.satisfacao_nota, c.satisfacao_comentario
        FROM chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN setores s ON u.setor_id = s.id
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        ORDER BY 
            CASE 
                WHEN c.status = 'Aberto' THEN 1 
                WHEN c.status = 'Em Atendimento' THEN 2 
                WHEN c.status = 'Aguardando Peça' THEN 3 
                ELSE 4 
            END, 
            c.prioridade DESC, 
            c.data_abertura ASC";
$res_chamados = $conn->query($sql);
$chamados_lista = [];

while ($row = $res_chamados->fetch_assoc()) {
    // Buscar anexos do chamado
    $c_id = $row['id'];
    $anexos_res = $conn->query("SELECT * FROM chamados_anexos WHERE chamado_id = $c_id");
    $row['anexos'] = [];
    while ($anexo = $anexos_res->fetch_assoc()) {
        $row['anexos'][] = $anexo;
    }

    // Verificar se há comentários não lidos pelo técnico neste chamado
    $unread_res = $conn->query("SELECT COUNT(*) FROM chamados_comentarios WHERE chamado_id = $c_id AND lido_pelo_tecnico = 0");
    $row['tem_novidade'] = ($unread_res->fetch_row()[0] > 0);

    // Buscar comentários do chamado
    $comentarios_res = $conn->query("SELECT cc.*, u.nome as autor FROM chamados_comentarios cc JOIN usuarios u ON cc.usuario_id = u.id WHERE cc.chamado_id = $c_id ORDER BY cc.data_comentario ASC");
    $row['comentarios'] = [];
    while ($coment = $comentarios_res->fetch_assoc()) {
        $row['comentarios'][] = $coment;
    }

    $chamados_lista[] = $row;
}

// Buscar lista de técnicos (Filtra especificamente pelo atributo is_tecnico = TI)
$tecnicos = $conn->query("SELECT id, nome FROM usuarios WHERE is_tecnico = 1 AND ativo = 1 ORDER BY nome ASC");

$status_styles = [
    'Aberto' => 'bg-blue-50 text-blue-600 border-blue-100',
    'Em Atendimento' => 'bg-amber-50 text-amber-600 border-amber-100',
    'Aguardando Peça' => 'bg-purple-50 text-purple-600 border-purple-100',
    'Resolvido' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
    'Cancelado' => 'bg-gray-50 text-gray-400 border-gray-100'
];

$prioridade_styles = [
    'Baixa' => 'text-gray-400',
    'Média' => 'text-primary font-bold',
    'Alta' => 'text-orange-500 font-bold',
    'Urgente' => 'text-rose-600 font-black'
];

// Stats para o dashboard superior
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Total' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as total FROM chamados WHERE status IN ('Aberto', 'Em Atendimento') GROUP BY status");
while($row = $res_stats->fetch_assoc()) {
    $stats[$row['status']] = $row['total'];
}
$stats['Total'] = $conn->query("SELECT COUNT(*) FROM chamados")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Suporte - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        
        /* Barra de rolagem personalizada para o histórico */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--color-primary, #0056b3);
        }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header (Slim Style) -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                    Gerencial de Chamados
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão técnica e operacional de TI</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="../suporte.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    Visão Usuário
                </a>
                <a href="../suporte.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Dashboard Superior (Slim) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500">
                    <i data-lucide="inbox" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Aberto']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Abertos</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-500">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Em Atendimento']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Em Curso</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Total']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Total Histórico</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo date('H:i'); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Hora Atual</p>
                </div>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 animate-in slide-in-from-top-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-tighter"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Table (Slim Style) -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Assunto</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Solicitante</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Avaliação</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php foreach ($chamados_lista as $chamado): ?>
                        <tr onclick='abrirAtendimento(<?php echo json_encode($chamado); ?>)' class="hover:bg-background/30 transition-colors group cursor-pointer <?php echo in_array($chamado['status'], ['Resolvido', 'Cancelado']) ? 'opacity-40' : ''; ?>">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <span class="font-mono text-[9px] bg-gray-50 border border-border px-1 rounded text-text-secondary/50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        <?php if ($chamado['tem_novidade']): ?>
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white animate-pulse"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold text-text leading-tight group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></p>
                                            <?php if ($chamado['tem_novidade']): ?>
                                                <span class="bg-rose-50 text-rose-600 text-[8px] px-1 rounded font-black uppercase tracking-tighter border border-rose-100 italic">Novo!</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[9px] text-text-secondary uppercase font-black opacity-50"><?php echo $chamado['categoria']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <p class="font-bold text-text leading-tight"><?php echo $chamado['solicitante']; ?></p>
                                <p class="text-[9px] text-text-secondary uppercase font-black opacity-50"><?php echo $chamado['setor_solicitante']; ?></p>
                            </td>
                            <td class="p-3 text-center">
                                <span class="<?php echo $prioridade_styles[$chamado['prioridade']]; ?> uppercase tracking-tighter text-[10px]">
                                    <?php echo $chamado['prioridade']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase border <?php echo $status_styles[$chamado['status']]; ?>">
                                    <?php echo $chamado['status']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <?php if ($chamado['tecnico_nome']): ?>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded bg-primary/10 flex items-center justify-center text-[9px] font-bold text-primary">
                                            <?php echo substr($chamado['tecnico_nome'], 0, 1); ?>
                                        </div>
                                        <span class="text-[11px] font-bold text-text-secondary"><?php echo $chamado['tecnico_nome']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-300 italic text-[10px]">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($chamado['satisfacao_nota']): ?>
                                    <div class="flex flex-col items-center">
                                        <div class="flex items-center text-amber-500">
                                            <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                                            <span class="font-bold ml-1"><?php echo $chamado['satisfacao_nota']; ?>/5</span>
                                        </div>
                                        <?php if($chamado['satisfacao_comentario']): ?>
                                            <span class="text-[8px] text-text-secondary italic max-w-[80px] truncate" title="<?php echo $chamado['satisfacao_comentario']; ?>">"<?php echo $chamado['satisfacao_comentario']; ?>"</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='abrirAtendimento(<?php echo json_encode($chamado); ?>)' class="px-3 py-1 bg-primary text-white rounded-lg font-black uppercase tracking-widest text-[9px] transition-all hover:bg-primary-hover shadow-md shadow-primary/10 active:scale-95">
                                        Gerenciar
                                    </button>
                                    <?php if (isAdmin()): ?>
                                    <button onclick="excluirChamado(<?php echo $chamado['id']; ?>)" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition-all active:scale-90" title="Excluir Registro">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Atendimento (Slim Pattern - Paisagem) -->
    <div id="modalAtender" class="modal">
        <div class="bg-white w-full max-w-5xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150 flex flex-col max-h-[90vh]">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center shrink-0">
                <div>
                    <h2 class="text-base font-bold text-white uppercase flex items-center gap-2">
                        <span id="view_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                        Atendimento Técnico
                    </h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Gestão da Ocorrência</p>
                </div>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="flex flex-col md:flex-row overflow-hidden flex-grow">
                <!-- Coluna Esquerda: Informações e Conversa -->
                <div class="w-full md:w-1/2 border-r border-border flex flex-col bg-gray-50/30">
                    <div class="p-4 border-b border-border bg-gray-50 shrink-0">
                        <h3 class="text-sm font-bold text-text mb-1" id="view_titulo">---</h3>
                        <p class="text-xs text-text-secondary leading-relaxed bg-white p-3 rounded-lg border border-border/50 max-h-24 overflow-y-auto italic" id="view_descricao">---</p>
                        <div class="mt-2 flex gap-4 text-[9px] font-black text-text-secondary/40 uppercase tracking-widest">
                            <span id="view_solicitante">---</span>
                            <span id="view_data">---</span>
                        </div>
                    </div>

                    <!-- Área de Anexos (Compacta) -->
                    <div id="container_anexos_view" class="hidden p-3 bg-white border-b border-border shrink-0">
                        <label class="block text-[9px] font-black text-text-secondary mb-1.5 uppercase tracking-widest flex items-center gap-1.5">
                            <i data-lucide="paperclip" class="w-3 h-3"></i>
                            Anexos
                        </label>
                        <div id="view_anexos_list" class="flex flex-wrap gap-2"></div>
                    </div>

                    <!-- Setor de Interação / Comentários (Ocupa o resto da coluna) -->
                    <div class="p-4 flex flex-col flex-grow overflow-hidden">
                        <label class="block text-[9px] font-black text-text-secondary mb-2 uppercase tracking-widest flex items-center gap-1.5 shrink-0">
                            <i data-lucide="message-square" class="w-3 h-3"></i>
                            Histórico de Conversa
                        </label>
                        
                        <div id="view_comentarios_list" class="flex-grow space-y-3 mb-3 overflow-y-auto pr-2 custom-scrollbar max-h-[185px]">
                            <!-- JS Populado -->
                        </div>

                        <form method="POST" action="" class="flex gap-2 shrink-0">
                            <input type="hidden" name="acao" value="adicionar_comentario">
                            <input type="hidden" name="chamado_id" id="comentario_chamado_id">
                            <input type="text" name="comentario" required placeholder="Pedir informação ou responder..." 
                                   class="flex-grow p-2 bg-white border border-border rounded-lg text-[10px] font-bold focus:outline-none focus:border-primary transition-all">
                            <button type="submit" class="bg-primary text-white p-2 rounded-lg hover:bg-primary-hover transition-all shadow-md active:scale-95">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Coluna Direita: Formulário de Ação e Feedback -->
                <div class="w-full md:w-1/2 flex flex-col overflow-y-auto custom-scrollbar">
                    <!-- Visualização da Avaliação (Se houver) -->
                    <div id="container_feedback" class="hidden p-4 bg-amber-50 border-b border-amber-100 shrink-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[9px] font-black text-amber-700 uppercase tracking-widest flex items-center gap-1">
                                <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                                Avaliação do Usuário
                            </span>
                            <span id="feedback_nota" class="text-xs font-bold text-amber-700">--/5</span>
                        </div>
                        <p id="feedback_texto" class="text-[10px] text-amber-800 italic leading-relaxed">---</p>
                    </div>

                    <form method="POST" action="" class="p-5 flex flex-col flex-grow">
                        <input type="hidden" name="acao" value="atualizar_chamado">
                        <input type="hidden" name="id" id="form_id">
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest">Avançar Status</label>
                                <select name="status" id="form_status" class="w-full p-2.5 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                                    <option value="Aberto">Aberto</option>
                                    <option value="Em Atendimento">Em Atendimento</option>
                                    <option value="Aguardando Peça">Aguardando Peça</option>
                                    <option value="Resolvido">Resolvido ✅</option>
                                    <option value="Cancelado">Cancelado ❌</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest">Técnico Atribuído</label>
                                <select name="tecnico_id" id="form_tecnico" class="w-full p-2.5 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                                    <option value="">Selecione o Técnico</option>
                                    <?php 
                                    $tecnicos->data_seek(0);
                                    while($t = $tecnicos->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex-grow flex flex-col">
                            <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest">Resolução / Observações Técnicas</label>
                            <textarea name="resolucao" id="form_resolucao" placeholder="Documente o atendimento ou a solução aplicada..."
                                      class="flex-grow w-full p-3 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all min-h-[150px]"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-border shrink-0">
                            <button type="button" onclick="fecharModal()" class="px-4 py-2 text-[10px] font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-8 py-2 rounded-lg text-[10px] font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function abrirAtendimento(chamado) {
            document.getElementById('view_id').textContent = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('view_titulo').textContent = chamado.titulo;
            document.getElementById('view_descricao').textContent = chamado.descricao;
            document.getElementById('view_solicitante').textContent = 'Solicitante: ' + chamado.solicitante + ' (' + chamado.setor_solicitante + ')';
            document.getElementById('view_data').textContent = 'Aberto em: ' + chamado.data_abertura;
            
            document.getElementById('form_id').value = chamado.id;
            document.getElementById('comentario_chamado_id').value = chamado.id;
            document.getElementById('form_status').value = chamado.status;
            document.getElementById('form_tecnico').value = chamado.tecnico_id || '';
            document.getElementById('form_resolucao').value = chamado.resolucao || '';

            // Marcar como lido pelo técnico (AJAX)
            if (chamado.tem_novidade) {
                fetch('suporte_gerenciar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'acao=marcar_lido_tecnico&chamado_id=' + chamado.id
                });
            }

            // Lógica para exibir feedback do usuário
            const feedbackContainer = document.getElementById('container_feedback');
            const feedbackNota = document.getElementById('feedback_nota');
            const feedbackTexto = document.getElementById('feedback_texto');

            if (chamado.satisfacao_nota) {
                feedbackContainer.classList.remove('hidden');
                feedbackNota.textContent = chamado.satisfacao_nota + '/5';
                feedbackTexto.textContent = chamado.satisfacao_comentario ? '"' + chamado.satisfacao_comentario + '"' : 'Usuário não deixou comentário.';
            } else {
                feedbackContainer.classList.add('hidden');
            }

            // Exibir anexos se houver (Visão Técnico)
            const anexoContainer = document.getElementById('container_anexos_view');
            const anexoList = document.getElementById('view_anexos_list');
            anexoList.innerHTML = '';
            
            if (chamado.anexos && chamado.anexos.length > 0) {
                anexoContainer.classList.remove('hidden');
                chamado.anexos.forEach(anexo => {
                    const item = document.createElement('a');
                    // Ajustar o caminho para sair da pasta admin/
                    item.href = (anexo.caminho_arquivo.startsWith('../') ? '' : '../') + anexo.caminho_arquivo;
                    item.target = '_blank';
                    item.className = 'flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-border hover:border-primary hover:text-primary rounded-lg text-[10px] font-bold text-text-secondary transition-all shadow-sm group';
                    
                    // Detectar se é imagem para o ícone
                    const ext = anexo.nome_original.split('.').pop().toLowerCase();
                    const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                    
                    item.innerHTML = `
                        <i data-lucide="${isImg ? 'image' : 'file-text'}" class="w-3.5 h-3.5"></i>
                        <span>${anexo.nome_original}</span>
                        <i data-lucide="download" class="w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                    `;
                    anexoList.appendChild(item);
                });
            } else {
                anexoContainer.classList.add('hidden');
            }

            // Exibir comentários se houver (Mostra tudo com rolagem, altura ajustada para o visual de 3)
            const comList = document.getElementById('view_comentarios_list');
            comList.innerHTML = '';
            if (chamado.comentarios && chamado.comentarios.length > 0) {
                // Removemos o slice para mostrar todas as interações
                chamado.comentarios.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'bg-white p-2.5 rounded-xl border border-border/50 shadow-sm relative';
                    div.innerHTML = `
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[9px] font-black text-primary uppercase">${c.autor}</span>
                            <span class="text-[8px] text-text-secondary opacity-50">${c.data_comentario}</span>
                        </div>
                        <p class="text-[10px] text-text-secondary leading-tight italic">"${c.comentario}"</p>
                    `;
                    comList.appendChild(div);
                });
                
                // Rola para o final do histórico de conversas automaticamente
                setTimeout(() => {
                    comList.scrollTop = comList.scrollHeight;
                }, 100);
            } else {
                comList.innerHTML = '<p class="text-[10px] text-text-secondary/40 italic text-center py-4">Sem interações registradas.</p>';
            }
            
            document.getElementById('modalAtender').classList.add('active');
            lucide.createIcons();
        }
        function fecharModal() { document.getElementById('modalAtender').classList.remove('active'); }

        function excluirChamado(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente este chamado? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_chamado">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
