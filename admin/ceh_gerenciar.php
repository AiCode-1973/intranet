<?php
require_once '../config.php';
require_once '../functions.php';

requireCEH();

$mensagem = '';
$tipo_mensagem = '';

// Processar atualização do chamado CEH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_chamado_ceh') {
    $id = intval($_POST['id']);
    $status = sanitize($_POST['status']);
    $resolucao = $_POST['resolucao'];
    $tecnico_id = intval($_POST['tecnico_id']);
    $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE ceh_chamados SET status = ?, resolucao = ?, tecnico_id = ?, data_fechamento = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $status, $resolucao, $tecnico_id, $data_fechamento, $id);

    if ($stmt->execute()) {
        $mensagem = "Chamado CEH #$id atualizado!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Atualizou chamado CEH #$id para status: $status");
    } else {
        $mensagem = "Erro ao atualizar: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar Novo Comentário do Técnico
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_comentario_tecnico') {
    $chamado_id = intval($_POST['chamado_id']);
    $usuario_id = $_SESSION['usuario_id'];
    $comentario = sanitize($_POST['comentario']);
    $anexo = '';

    // Processar upload de anexo no comentário
    if (isset($_FILES['anexo_comentario']) && $_FILES['anexo_comentario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anexo_comentario']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'ceh_com_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destino = '../uploads/ceh/' . $novo_nome;
        
        if (!is_dir('../uploads/ceh/')) {
            mkdir('../uploads/ceh/', 0777, true);
        }

        if (move_uploaded_file($_FILES['anexo_comentario']['tmp_name'], $destino)) {
            $anexo = $novo_nome;
        }
    }

    if (!empty($comentario) || !empty($anexo)) {
        $stmt = $conn->prepare("INSERT INTO ceh_comentarios (chamado_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario, anexo) VALUES (?, ?, ?, 1, 0, ?)");
        $stmt->bind_param("iiss", $chamado_id, $usuario_id, $comentario, $anexo);
        if ($stmt->execute()) {
            $mensagem = "Comentário adicionado com sucesso!";
            $tipo_mensagem = "success";
        }
        $stmt->close();
    }
}

// Processar Marcação de Leitura (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'marcar_lido_tecnico') {
    $chamado_id = intval($_POST['chamado_id']);
    $conn->query("UPDATE ceh_comentarios SET lido_pelo_tecnico = 1 WHERE chamado_id = $chamado_id");
    exit;
}

// Processar exclusão do chamado CEH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_chamado_ceh' && isAdmin()) {
    $id = intval($_POST['id']);
    
    $stmt = $conn->prepare("DELETE FROM ceh_chamados WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $mensagem = "Chamado CEH #$id removido!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu chamado CEH #$id");
    } else {
        $mensagem = "Erro ao excluir: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Buscar todos os chamados CEH com detalhes
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante
        FROM ceh_chamados c 
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
$chamados_array = [];

while($row = $res_chamados->fetch_assoc()) {
    $c_id = $row['id'];
    
    // Comentários não lidos pelo técnico
    $unread_res = $conn->query("SELECT COUNT(*) FROM ceh_comentarios WHERE chamado_id = $c_id AND lido_pelo_tecnico = 0");
    $row['tem_novidade'] = ($unread_res->fetch_row()[0] > 0);

    // Buscar comentários
    $comentarios_res = $conn->query("SELECT cc.*, u.nome as autor, u.id as autor_id FROM ceh_comentarios cc 
                                     JOIN usuarios u ON cc.usuario_id = u.id 
                                     WHERE cc.chamado_id = $c_id 
                                     ORDER BY cc.data_comentario ASC");
    $row['comentarios'] = [];
    while($coment = $comentarios_res->fetch_assoc()) {
        $coment['data_comentario_fmt'] = $coment['data_comentario']
            ? date('d/m/Y H:i', strtotime($coment['data_comentario']))
            : '-';
        $row['comentarios'][] = $coment;
    }
    $comentarios_res->free();
    $unread_res->free();
    $row['data_abertura_fmt'] = $row['data_abertura']
        ? date('d/m/Y H:i', strtotime($row['data_abertura']))
        : '-';
    
    $chamados_array[] = $row;
}

// Buscar lista de técnicos especialistas do setor CEH ou marcados na ficha de usuário (is_ceh)
$tecnicos = $conn->query("SELECT id, nome FROM usuarios WHERE (setor_id = 15 OR is_ceh = 1 OR is_admin = 1) AND ativo = 1 ORDER BY nome ASC");

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
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Total' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as total FROM ceh_chamados WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça') GROUP BY status");
while($row = $res_stats->fetch_assoc()) {
    $stats[$row['status']] = $row['total'];
}
$stats['Total'] = $conn->query("SELECT COUNT(*) FROM ceh_chamados")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar CEH - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                    Gerencial CEH
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão de Equipamentos Hospitalares</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="../ceh.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    Visão Usuário
                </a>
                <a href="../dashboard.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Painel Central
                </a>
            </div>
        </div>

        <!-- Dashboard Superior -->
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
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-500">
                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Em Atendimento']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Em Curso</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-500">
                    <i data-lucide="component" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Aguardando Peça']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Peças</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Total']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Total Geral</p>
                </div>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 animate-in slide-in-from-top-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-tighter"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Equipamento</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Solicitante</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico CEH</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php if (!empty($chamados_array)): ?>
                            <?php foreach ($chamados_array as $chamado): ?>
                            <tr class="hover:bg-background/30 transition-colors group <?php echo in_array($chamado['status'], ['Resolvido', 'Cancelado']) ? 'opacity-40' : ''; ?>">
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <div class="relative">
                                            <span class="font-mono text-[9px] bg-gray-50 border border-border px-1 rounded text-text-secondary/50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php if ($chamado['tem_novidade']): ?>
                                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full border border-white animate-pulse"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-text leading-tight group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></p>
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
                                        <span class="text-gray-300 italic text-[10px]">Não atribuído</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick='abrirAtendimento(<?php echo htmlspecialchars(json_encode($chamado, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)' class="px-3 py-1 bg-primary text-white rounded-lg font-black uppercase tracking-widest text-[9px] transition-all hover:bg-primary-hover shadow-md shadow-primary/10 active:scale-95">
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
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-16 text-center">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhum chamado CEH nas listas.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Atendimento -->
    <div id="modalAtender" class="modal">
        <div class="bg-white w-full max-w-5xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150 flex flex-col max-h-[90vh]">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center shrink-0">
                <div>
                    <h2 class="text-base font-bold text-white uppercase flex items-center gap-2">
                        <span id="view_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                        Gestão Técnica CEH
                    </h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Atendimento de Equipamento</p>
                </div>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="flex flex-grow overflow-hidden flex-col md:flex-row">
                <!-- Coluna Esquerda: Detalhes e Formulário -->
                <div class="w-full md:w-1/2 flex flex-col border-r border-border bg-gray-50/30 overflow-y-auto">
                    <div class="p-4 border-b border-border bg-white">
                        <h3 class="text-sm font-bold text-text mb-1" id="view_titulo">---</h3>
                        <p class="text-xs text-text-secondary leading-relaxed p-3 rounded-lg bg-background border border-border/50" id="view_descricao">---</p>
                        
                        <!-- Anexo Principal do Chamado -->
                        <div id="view_anexo_main" class="mt-3 hidden">
                            <p class="text-[9px] font-black text-text-secondary uppercase mb-1 tracking-widest">Anexo do Chamado:</p>
                            <a id="link_anexo_main" href="#" target="_blank" class="inline-flex items-center gap-2 p-2 bg-blue-50 border border-blue-100 rounded-lg text-blue-600 hover:bg-blue-100 transition-colors">
                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                <span class="text-[10px] font-bold uppercase tracking-tight">Ver Arquivo Anexo</span>
                            </a>
                        </div>

                        <div class="mt-3 flex gap-4 text-[9px] font-black text-text-secondary/60 uppercase tracking-widest">
                            <span id="view_solicitante">---</span>
                            <span id="view_data">---</span>
                        </div>
                    </div>

                    <form method="POST" action="" class="p-5 space-y-4 flex-grow">
                        <input type="hidden" name="acao" value="atualizar_chamado_ceh">
                        <input type="hidden" name="id" id="form_id">
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Atualizar Status</label>
                                <select name="status" id="form_status" class="w-full p-2 bg-white border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all shadow-sm">
                                    <option value="Aberto">Aberto</option>
                                    <option value="Em Atendimento">Em Atendimento</option>
                                    <option value="Aguardando Peça">Aguardando Peça</option>
                                    <option value="Resolvido">Resolvido ✅</option>
                                    <option value="Cancelado">Cancelado ❌</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Técnico Responsável</label>
                                <select name="tecnico_id" id="form_tecnico" class="w-full p-2 bg-white border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all shadow-sm">
                                    <option value="">Selecione o Técnico</option>
                                    <?php 
                                    if ($tecnicos) {
                                        $tecnicos->data_seek(0);
                                        while($t = $tecnicos->fetch_assoc()): ?>
                                            <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                                        <?php endwhile;
                                    } ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Laudo / Resolução Técnica</label>
                            <textarea name="resolucao" id="form_resolucao" rows="8" placeholder="Documente o que foi feito no equipamento ou o motivo da espera..."
                                      class="w-full p-2 bg-white border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all shadow-sm"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border/50">
                            <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white px-6 py-2.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest flex items-center justify-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Atualizar Chamado
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Coluna Direita: Histórico de Comentários (Chat) -->
                <div class="w-full md:w-1/2 flex flex-col bg-white">
                    <div class="px-4 py-3 border-b border-border bg-gray-50 flex justify-between items-center">
                        <h3 class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                             <i data-lucide="message-square" class="w-4 h-4 text-primary"></i>
                             Histórico de Interações
                        </h3>
                    </div>

                    <!-- Chat Container -->
                    <div id="chat_container" class="flex-grow overflow-y-auto p-4 space-y-4 bg-[#f8f9fa] max-h-[400px] md:max-h-none">
                        <!-- Comentários serão inseridos aqui via JS -->
                    </div>

                    <!-- Input de Comentário -->
                    <div class="p-4 border-t border-border bg-white shrink-0">
                        <form id="form_comentario" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="adicionar_comentario_tecnico">
                            <input type="hidden" name="chamado_id" id="comentario_chamado_id">
                            
                            <div class="relative group">
                                <textarea name="comentario" rows="2" placeholder="Digite uma mensagem para o solicitante..." 
                                          class="w-full p-3 pr-12 bg-background border border-border rounded-xl text-xs font-bold focus:outline-none focus:border-primary transition-all resize-none"></textarea>
                                
                                <label class="absolute right-3 bottom-3 p-1.5 text-text-secondary hover:text-primary cursor-pointer transition-colors rounded-lg hover:bg-primary/5" title="Anexar Arquivo">
                                    <input type="file" name="anexo_comentario" class="hidden" onchange="updateFileName(this)">
                                    <i data-lucide="paperclip" class="w-4 h-4"></i>
                                </label>
                            </div>
                            
                            <div id="file_name_display" class="hidden mt-2 p-2 bg-blue-50 border border-blue-100 rounded-lg flex items-center justify-between">
                                <span class="text-[10px] font-bold text-blue-600 truncate max-w-[200px]" id="selected_file_name"></span>
                                <button type="button" onclick="clearFile()" class="text-blue-400 hover:text-blue-600"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                            </div>

                            <button type="submit" class="mt-3 w-full bg-primary hover:bg-primary-hover text-white py-2 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i> Enviar Resposta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const USUARIO_ATUAL_ID = <?php echo $_SESSION['usuario_id']; ?>;

        function updateFileName(input) {
            const display = document.getElementById('file_name_display');
            const nameSpan = document.getElementById('selected_file_name');
            if (input.files && input.files[0]) {
                nameSpan.textContent = input.files[0].name;
                display.classList.remove('hidden');
            }
        }

        function clearFile() {
            const input = document.querySelector('input[name="anexo_comentario"]');
            input.value = '';
            document.getElementById('file_name_display').classList.add('hidden');
        }

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

            // Anexo Principal
            const anexoMain = document.getElementById('view_anexo_main');
            const linkAnexo = document.getElementById('link_anexo_main');
            if (chamado.anexo) {
                linkAnexo.href = '../uploads/ceh/' + chamado.anexo;
                anexoMain.classList.remove('hidden');
            } else {
                anexoMain.classList.add('hidden');
            }

            // Chat / Comentários
            const container = document.getElementById('chat_container');
            container.innerHTML = '';

            if (chamado.comentarios && chamado.comentarios.length > 0) {
                chamado.comentarios.forEach(coment => {
                    const isMe = coment.usuario_id == USUARIO_ATUAL_ID;
                    const date = coment.data_comentario_fmt;
                    
                    let anexoHtml = '';
                    if (coment.anexo) {
                        anexoHtml = `
                            <div class="mt-2 pt-2 border-t border-black/5">
                                <a href="../uploads/ceh/${coment.anexo}" target="_blank" class="flex items-center gap-1.5 text-[9px] font-bold ${isMe ? 'text-white/80 hover:text-white' : 'text-primary hover:text-primary-hover'} transition-colors">
                                    <i data-lucide="paperclip" class="w-3 h-3"></i>
                                    VER ANEXO
                                </a>
                            </div>
                        `;
                    }

                    const html = `
                        <div class="flex ${isMe ? 'justify-end' : 'justify-start'} animate-in fade-in slide-in-from-bottom-2">
                            <div class="max-w-[85%]">
                                <div class="flex items-center gap-2 mb-1 ${isMe ? 'flex-row-reverse' : ''}">
                                    <span class="text-[9px] font-black uppercase text-text-secondary/50 tracking-widest">${coment.autor}</span>
                                    <span class="text-[8px] text-text-secondary/30 font-bold">${date}</span>
                                </div>
                                <div class="p-3 rounded-2xl text-xs leading-relaxed shadow-sm ${isMe ? 'bg-primary text-white rounded-tr-none' : 'bg-white text-text border border-border/50 rounded-tl-none'}">
                                    ${coment.comentario || '<i class="opacity-50">Anexo enviado</i>'}
                                    ${anexoHtml}
                                </div>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', html);
                });
                
                // Marcar como lido via AJAX se houver novidades
                if (chamado.tem_novidade) {
                    const formData = new FormData();
                    formData.append('acao', 'marcar_lido_tecnico');
                    formData.append('chamado_id', chamado.id);
                    fetch('', { method: 'POST', body: formData });
                }
            } else {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-12 text-text-secondary/30 opacity-50">
                        <i data-lucide="message-circle" class="w-8 h-8 mb-2"></i>
                        <p class="text-[10px] font-black uppercase tracking-widest">Nenhuma interação até o momento</p>
                    </div>
                `;
            }

            document.getElementById('modalAtender').classList.add('active');
            lucide.createIcons();
            
            // Scroll para o fim do chat
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 50);
        }
        function fecharModal() { document.getElementById('modalAtender').classList.remove('active'); }

        function excluirChamado(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente este chamado CEH? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_chamado_ceh">
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
