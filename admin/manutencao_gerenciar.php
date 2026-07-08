<?php
require_once '../config.php';
require_once '../functions.php';

requireManutencao();

$mensagem = '';
$tipo_mensagem = '';

// Processar ações administrativas (Atribuir técnico, Mudar Status, Resolver)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $id = intval($_POST['id']);
    
    if ($_POST['acao'] == 'atualizar_chamado') {
        $status = sanitize($_POST['status']);
        $tecnico_id = $_POST['tecnico_id'] ? intval($_POST['tecnico_id']) : null;
        $resolucao = isset($_POST['resolucao']) ? trim($_POST['resolucao']) : '';
        $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

        // Estado anterior para comparação
        $ant_res = $conn->query("SELECT status, resolucao, tecnico_id FROM manutencao WHERE id = $id");
        $anterior = ($ant_res && $ant_res->num_rows > 0) ? $ant_res->fetch_assoc() : null;

        $stmt = $conn->prepare("UPDATE manutencao SET status = ?, tecnico_id = ?, resolucao = ?, data_fechamento = ? WHERE id = ?");
        $stmt->bind_param("sissi", $status, $tecnico_id, $resolucao, $data_fechamento, $id);

        if ($stmt->execute()) {
            $mensagem = "Chamado #$id atualizado com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Atualizou chamado de manutenção #$id para $status");

            // Gerar notificação automática para o solicitante
            $partes = [];
            if ($anterior && $anterior['status'] !== $status) {
                $partes[] = "Status alterado para: {$status}";
            }
            $resolucao_anterior = trim($anterior['resolucao'] ?? '');
            if ($resolucao !== $resolucao_anterior && $resolucao !== '') {
                $partes[] = "Resolução registrada: {$resolucao}";
            }
            if (!empty($partes)) {
                $notif = '🔧 ' . implode("\n", $partes);
                $admin_id = intval($_SESSION['usuario_id']);
                $stmt2 = $conn->prepare("INSERT INTO manutencao_comentarios (manutencao_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario) VALUES (?, ?, ?, 1, 0)");
                $stmt2->bind_param("iis", $id, $admin_id, $notif);
                $stmt2->execute();
                $stmt2->close();
            }
        } else {
            $mensagem = "Erro ao atualizar: " . $conn->error;
            $tipo_mensagem = "danger";
        }
        $stmt->close();
    }

    // Processar exclusão do chamado
    if ($_POST['acao'] == 'excluir_chamado' && isAdmin()) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM manutencao WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $mensagem = "Ordem de Serviço #$id removida com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Excluiu ordem de serviço #$id");
        } else {
            $mensagem = "Erro ao excluir: " . $conn->error;
            $tipo_mensagem = "danger";
        }
        $stmt->close();
    }

    if ($_POST['acao'] == 'adicionar_comentario') {
        $man_id = intval($_POST['manutencao_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');
        if ($man_id && $comentario) {
            $uid = $_SESSION['usuario_id'];
            $stmt = $conn->prepare("INSERT INTO manutencao_comentarios (manutencao_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario) VALUES (?, ?, ?, 1, 0)");
            $stmt->bind_param("iis", $man_id, $uid, $comentario);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE manutencao_comentarios SET lido_pelo_tecnico = 1 WHERE manutencao_id = $man_id");
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_POST['acao'] == 'limpar_chat') {
        $man_id = intval($_POST['manutencao_id'] ?? 0);
        if ($man_id) {
            $stmt = $conn->prepare("DELETE FROM manutencao_comentarios WHERE manutencao_id = ?");
            $stmt->bind_param("i", $man_id);
            $stmt->execute();
            $stmt->close();
            registrarLog($conn, "Limpou histórico do chat da O.S. #$man_id");
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Poll endpoint para auto-refresh
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    header('Content-Type: application/json');
    $poll_cond = !empty($_GET['status']) ? "WHERE m.status = '" . $conn->real_escape_string($_GET['status']) . "'" : '';
    $rows = $conn->query("SELECT m.id, m.status,
        (SELECT COUNT(*) FROM manutencao_comentarios mc WHERE mc.manutencao_id = m.id AND mc.lido_pelo_tecnico = 0) as nao_lidos
        FROM manutencao m $poll_cond ORDER BY m.data_abertura DESC");
    $result = [];
    while ($r = $rows->fetch_assoc()) $result[] = $r;
    echo json_encode(['chamados' => $result]);
    exit;
}

// Endpoint: buscar comentários de um chamado
if (isset($_GET['action']) && $_GET['action'] === 'get_comments') {
    header('Content-Type: application/json');
    $man_id = intval($_GET['id'] ?? 0);
    if (!$man_id) { echo json_encode(['comentarios' => []]); exit; }
    $conn->query("UPDATE manutencao_comentarios SET lido_pelo_tecnico = 1 WHERE manutencao_id = $man_id");
    $res = $conn->query("SELECT mc.*, u.nome as autor FROM manutencao_comentarios mc
        LEFT JOIN usuarios u ON mc.usuario_id = u.id
        WHERE mc.manutencao_id = $man_id
        ORDER BY mc.data_comentario ASC");
    $comentarios = [];
    while ($r = $res->fetch_assoc()) $comentarios[] = $r;
    echo json_encode(['comentarios' => $comentarios]);
    exit;
}

// Filtros
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_sql = $filtro_status ? "WHERE m.status = '$filtro_status'" : "";

// Buscar chamados
$sql = "SELECT m.*, COALESCE(u.nome, '(usuário removido)') as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante,
        (SELECT GROUP_CONCAT(tf.ramal ORDER BY tf.ordem SEPARATOR ', ')
         FROM telefones tf
         WHERE tf.setor_id = u.setor_id AND tf.ramal IS NOT NULL AND tf.ramal != '') as ramais_setor,
        (SELECT COUNT(*) FROM manutencao_comentarios mc WHERE mc.manutencao_id = m.id AND mc.lido_pelo_tecnico = 0) as nao_lidos
        FROM manutencao m 
        LEFT JOIN usuarios u ON m.usuario_id = u.id 
        LEFT JOIN usuarios t ON m.tecnico_id = t.id 
        LEFT JOIN setores s ON u.setor_id = s.id
        $where_sql
        ORDER BY m.data_abertura DESC";
$res = $conn->query($sql);
if (!$res) {
    die('<div style="font-family:monospace;color:red;padding:20px">Erro na consulta: ' . htmlspecialchars($conn->error) . '</div>');
}
$chamados = [];
while($row = $res->fetch_assoc()) $chamados[] = $row;

// Contagens por status (sempre sem filtro)
$contagens = ['Todos' => 0, 'Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0];
$cnt_res = $conn->query("SELECT status, COUNT(*) as total FROM manutencao GROUP BY status");
if ($cnt_res) {
    while ($row = $cnt_res->fetch_assoc()) {
        if (isset($contagens[$row['status']])) $contagens[$row['status']] = intval($row['total']);
        $contagens['Todos'] += intval($row['total']);
    }
}

// Buscar técnicos (Marcados explicitamente como técnicos de manutenção)
$tecnicos_res = $conn->query("SELECT id, nome FROM usuarios WHERE is_manutencao = 1 AND ativo = 1 ORDER BY nome");
$tecnicos = [];
while($row = $tecnicos_res->fetch_assoc()) $tecnicos[] = $row;

// Dados para o hash inicial do poll (mesma query do endpoint ?action=poll)
$poll_hash_where = $filtro_status ? "WHERE m.status = '" . $conn->real_escape_string($filtro_status) . "'" : '';
$poll_hash_res = $conn->query("SELECT m.id, m.status,
    (SELECT COUNT(*) FROM manutencao_comentarios mc WHERE mc.manutencao_id = m.id AND mc.lido_pelo_tecnico = 0) as nao_lidos
    FROM manutencao m $poll_hash_where ORDER BY m.data_abertura DESC");
$poll_hash_data = [];
while ($ph = $poll_hash_res->fetch_assoc()) $poll_hash_data[] = $ph;

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Manutenção - APAS Intranet</title>
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
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="wrench" class="w-6 h-6"></i>
                    Gestão de Manutenção & Infraestrutura
                </h1>
                <p class="text-text-secondary text-xs mt-1">Painel administrativo para controle de ordens de serviço</p>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Indicador Ao Vivo -->
                <div class="flex items-center gap-1.5 px-2 py-1 bg-white border border-border rounded-lg shadow-sm">
                    <span id="man-poll-status" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Ao Vivo</span>
                </div>
                <a href="../manutencao.php" class="text-[11px] font-bold text-text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar à lista
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div id="man-msg" class="p-3 rounded-lg border mb-6 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 transition-opacity duration-500">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
            <script>setTimeout(function(){var m=document.getElementById('man-msg');if(m){m.style.opacity='0';setTimeout(function(){m.remove();},500);}},4000);</script>
        <?php endif; ?>

        <!-- Cards de Status -->
        <?php
        $cards = [
            ['key' => '',               'label' => 'Todos',           'icon' => 'layers',        'color' => 'border-border hover:border-primary',          'active_color' => 'border-primary bg-primary/5',             'num_color' => 'text-primary'],
            ['key' => 'Aberto',         'label' => 'Aberto',          'icon' => 'circle-dot',    'color' => 'border-border hover:border-blue-400',          'active_color' => 'border-blue-400 bg-blue-50',              'num_color' => 'text-blue-600'],
            ['key' => 'Em Atendimento', 'label' => 'Em Atendimento',  'icon' => 'wrench',        'color' => 'border-border hover:border-amber-400',         'active_color' => 'border-amber-400 bg-amber-50',            'num_color' => 'text-amber-600'],
            ['key' => 'Aguardando Peça','label' => 'Aguardando Peça', 'icon' => 'package',       'color' => 'border-border hover:border-purple-400',        'active_color' => 'border-purple-400 bg-purple-50',          'num_color' => 'text-purple-600'],
            ['key' => 'Resolvido',      'label' => 'Resolvido',       'icon' => 'check-circle',  'color' => 'border-border hover:border-green-400',         'active_color' => 'border-green-400 bg-green-50',            'num_color' => 'text-green-600'],
        ];
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
            <?php foreach ($cards as $card):
                $isActive = ($filtro_status === $card['key']);
                $count = $card['key'] === '' ? $contagens['Todos'] : ($contagens[$card['key']] ?? 0);
                $href = '?' . ($card['key'] ? 'status=' . urlencode($card['key']) : '');
                $baseClass = 'flex flex-col gap-1.5 p-3 bg-white rounded-xl border-2 transition-all cursor-pointer no-underline';
                $colorClass = $isActive ? $card['active_color'] : $card['color'];
            ?>
            <a href="<?php echo $href; ?>" class="<?php echo $baseClass . ' ' . $colorClass; ?>">
                <div class="flex items-center justify-between">
                    <i data-lucide="<?php echo $card['icon']; ?>" class="w-4 h-4 <?php echo $card['num_color']; ?> opacity-70"></i>
                    <?php if ($isActive): ?>
                        <span class="w-1.5 h-1.5 rounded-full <?php echo str_replace('text-', 'bg-', $card['num_color']); ?>"></span>
                    <?php endif; ?>
                </div>
                <span class="text-2xl font-black <?php echo $card['num_color']; ?>"><?php echo $count; ?></span>
                <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest leading-tight"><?php echo $card['label']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / OS</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Solicitante</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Local / Categ.</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Responsável</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="man-tbody" class="divide-y divide-border text-xs">
                        <?php if (empty($chamados)): ?>
                        <tr><td colspan="6" class="p-8 text-center text-xs text-text-secondary opacity-50">Nenhuma ordem de serviço encontrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($chamados as $c): 
                            $style = $status_styles[$c['status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-500'];
                        ?>
                        <tr data-id="<?php echo $c['id']; ?>" data-status="<?php echo htmlspecialchars($c['status']); ?>" data-unread="<?php echo intval($c['nao_lidos']); ?>" class="hover:bg-background/20 transition-colors group<?php echo $c['nao_lidos'] > 0 ? ' bg-amber-50/40' : ''; ?>">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-shrink-0">
                                        <span class="font-bold text-text">#<?php echo str_pad($c['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        <?php if ($c['nao_lidos'] > 0): ?>
                                            <span class="absolute -top-1.5 -right-3 w-4 h-4 bg-rose-500 text-white text-[8px] font-black rounded-full flex items-center justify-center ring-2 ring-white"><?php echo $c['nao_lidos']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[11px] text-text-secondary truncate max-w-[180px]"><?php echo $c['titulo']; ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-text"><?php echo $c['solicitante']; ?></span>
                                    <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $c['setor_solicitante']; ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-text-secondary"><?php echo $c['local']; ?></span>
                                    <span class="text-[9px] text-primary font-black uppercase tracking-widest"><?php echo $c['categoria']; ?></span>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?php echo $style['bg']; ?> <?php echo $style['text']; ?>">
                                    <?php echo $c['status']; ?>
                                </span>
                            </td>
                            <td class="p-3 font-bold text-text-secondary">
                                <?php echo $c['tecnico_nome'] ?: '<span class="italic opacity-30">Pendente</span>'; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick='abrirModal(<?php echo json_encode($c); ?>)' 
                                            class="p-2 hover:bg-primary/10 text-text-secondary hover:text-primary transition-all rounded-lg" title="Editar/Ver">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <?php if (isAdmin()): ?>
                                    <button onclick="excluirChamado(<?php echo $c['id']; ?>)" 
                                            class="p-2 hover:bg-rose-50 text-text-secondary hover:text-rose-500 transition-all rounded-lg" title="Excluir OS">
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
            <!-- Paginação -->
            <div id="paginacao" class="flex items-center justify-between px-4 py-2.5 border-t border-border bg-background/30">
                <span id="pag-info" class="text-[10px] font-bold text-text-secondary uppercase tracking-widest"></span>
                <div class="flex items-center gap-1" id="pag-btns"></div>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditar" class="modal">
        <div class="bg-white w-full max-w-4xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden flex flex-col" style="max-height:88vh">
            <!-- Cabeçalho -->
            <div class="bg-primary px-3 py-2 text-white flex justify-between items-center flex-shrink-0">
                <div class="flex items-center gap-2">
                    <span class="bg-white/20 text-white text-[10px] font-mono font-bold px-2 py-0.5 rounded">#<span id="modal-id-display"></span></span>
                    <h2 class="text-xs font-black uppercase tracking-widest">Gestão da Ordem de Serviço</h2>
                </div>
                <button class="p-1 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex overflow-hidden">
                <!-- Coluna esquerda: Formulário -->
                <div class="w-[42%] p-2.5 overflow-y-auto border-r border-border flex flex-col gap-2">
                    <div class="p-2 bg-background rounded-lg border border-border">
                        <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-0.5">Solicitação</p>
                        <p id="modal-titulo" class="text-xs font-bold text-text mb-0.5 leading-snug"></p>
                        <p id="modal-descricao" class="text-[11px] text-text-secondary italic leading-relaxed"></p>
                        <div class="grid grid-cols-3 gap-x-2 mt-1.5">
                            <div>
                                <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-0.5">Solicitante</p>
                                <p id="modal-solicitante" class="text-[11px] font-semibold text-text leading-tight"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-0.5">Abertura</p>
                                <p id="modal-data-abertura" class="text-[11px] font-semibold text-text leading-tight"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-0.5">Ramal</p>
                                <p id="modal-ramal" class="text-[11px] font-semibold text-text leading-tight"></p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" class="flex flex-col gap-1.5">
                        <input type="hidden" name="acao" value="atualizar_chamado">
                        <input type="hidden" name="id" id="modal-id">

                        <div>
                            <label class="block text-[9px] font-black text-text-secondary mb-0.5 uppercase tracking-widest">Status</label>
                            <select name="status" id="modal-status" class="w-full px-2 py-1 bg-background border border-border rounded-lg text-xs font-bold">
                                <?php foreach($status_styles as $status => $st): ?>
                                    <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-text-secondary mb-0.5 uppercase tracking-widest">Técnico Responsável</label>
                            <select name="tecnico_id" id="modal-tecnico" class="w-full px-2 py-1 bg-background border border-border rounded-lg text-xs font-bold">
                                <option value="">Selecione...</option>
                                <?php foreach($tecnicos as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-text-secondary mb-0.5 uppercase tracking-widest">Resolução / Observações</label>
                            <textarea name="resolucao" id="modal-resolucao" rows="2" class="w-full px-2 py-1 bg-background border border-border rounded-lg text-xs font-bold resize-none"></textarea>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="fecharModal()" class="px-3 py-1 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-5 py-1 rounded-lg text-xs font-bold shadow-md transition-all">Salvar</button>
                        </div>
                    </form>
                </div>

                <!-- Coluna direita: Chat/Histórico -->
                <div class="w-[58%] flex flex-col overflow-hidden">
                    <div class="px-3 py-1.5 border-b border-border bg-background/50 flex-shrink-0 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="message-circle" class="w-3.5 h-3.5 text-primary"></i>
                            <h3 class="text-[10px] font-black text-text uppercase tracking-widest">Histórico &amp; Chat</h3>
                        </div>
                        <button onclick="limparChat()" title="Limpar histórico" class="flex items-center gap-1 text-[10px] font-bold text-rose-400 hover:text-rose-600 transition-colors px-2 py-0.5 rounded hover:bg-rose-50">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                            Limpar
                        </button>
                    </div>
                    <div id="man-chat-msgs" class="flex-1 overflow-y-auto p-2.5 space-y-2 bg-gray-50/30">
                        <div class="flex flex-col items-center justify-center h-full opacity-20 py-6">
                            <i data-lucide="loader" class="w-6 h-6 mb-1"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest">Carregando...</p>
                        </div>
                    </div>
                    <div class="p-2 border-t border-border bg-white flex-shrink-0">
                        <div class="flex gap-2">
                            <textarea id="man-chat-input" rows="1" placeholder="Atualização técnica... (Ctrl+Enter para enviar)" class="flex-grow px-2.5 py-1.5 bg-background border border-border rounded-lg text-xs resize-none focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"></textarea>
                            <button onclick="enviarComentario()" class="bg-primary hover:bg-primary-hover text-white px-3 rounded-lg transition-all flex items-center">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ── Paginação ──────────────────────────────────────────────
        const POR_PAGINA = 15;
        let paginaAtual = 1;

        function linhasVisiveis() {
            return Array.from(document.querySelectorAll('#man-tbody tr[data-id]'));
        }

        function renderPaginacao() {
            const linhas = linhasVisiveis();
            const total = linhas.length;
            const totalPags = Math.max(1, Math.ceil(total / POR_PAGINA));
            if (paginaAtual > totalPags) paginaAtual = totalPags;

            const inicio = (paginaAtual - 1) * POR_PAGINA;
            const fim = inicio + POR_PAGINA;

            linhas.forEach((tr, i) => {
                tr.style.display = (i >= inicio && i < fim) ? '' : 'none';
            });

            const exibindo = Math.min(fim, total) - inicio;
            document.getElementById('pag-info').textContent =
                total === 0 ? '' : `Exibindo ${inicio + 1}–${Math.min(fim, total)} de ${total}`;

            // Botões
            const container = document.getElementById('pag-btns');
            container.innerHTML = '';
            const btnClass = 'px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-widest transition-all ';

            // Anterior
            const prev = document.createElement('button');
            prev.innerHTML = '&lsaquo;';
            prev.disabled = paginaAtual === 1;
            prev.className = btnClass + (paginaAtual === 1 ? 'text-text-secondary/30 cursor-not-allowed' : 'hover:bg-primary/10 text-primary');
            prev.onclick = () => { paginaAtual--; renderPaginacao(); };
            container.appendChild(prev);

            // Números
            const range = 2;
            for (let p = 1; p <= totalPags; p++) {
                if (p !== 1 && p !== totalPags && Math.abs(p - paginaAtual) > range) {
                    if (p === 2 || p === totalPags - 1) {
                        const dots = document.createElement('span');
                        dots.textContent = '…';
                        dots.className = 'px-1 text-[10px] text-text-secondary/50';
                        container.appendChild(dots);
                    }
                    continue;
                }
                const btn = document.createElement('button');
                btn.textContent = p;
                btn.className = btnClass + (p === paginaAtual
                    ? 'bg-primary text-white shadow-sm'
                    : 'hover:bg-primary/10 text-text-secondary');
                btn.onclick = ((pg) => () => { paginaAtual = pg; renderPaginacao(); })(p);
                container.appendChild(btn);
            }

            // Próximo
            const next = document.createElement('button');
            next.innerHTML = '&rsaquo;';
            next.disabled = paginaAtual === totalPags;
            next.className = btnClass + (paginaAtual === totalPags ? 'text-text-secondary/30 cursor-not-allowed' : 'hover:bg-primary/10 text-primary');
            next.onclick = () => { paginaAtual++; renderPaginacao(); };
            container.appendChild(next);
        }

        document.addEventListener('DOMContentLoaded', renderPaginacao);
        // ──────────────────────────────────────────────────────────────

        let modalChamadoId = null;

        function abrirModal(dados) {
            modalChamadoId = dados.id;
            document.getElementById('modal-id').value = dados.id;
            document.getElementById('modal-id-display').innerText = dados.id.toString().padStart(3, '0');
            document.getElementById('modal-titulo').innerText = dados.titulo;
            document.getElementById('modal-descricao').innerText = dados.descricao;
            document.getElementById('modal-solicitante').innerText = dados.solicitante || '(desconhecido)';
            if (dados.data_abertura) {
                const d = new Date(dados.data_abertura.replace(' ', 'T'));
                document.getElementById('modal-data-abertura').innerText =
                    d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
            } else {
                document.getElementById('modal-data-abertura').innerText = '—';
            }
            document.getElementById('modal-ramal').innerText = dados.ramais_setor || '—';
            document.getElementById('modal-status').value = dados.status;
            document.getElementById('modal-tecnico').value = dados.tecnico_id || "";
            document.getElementById('modal-resolucao').value = dados.resolucao || "";
            document.getElementById('man-chat-input').value = '';

            document.getElementById('modalEditar').classList.add('active');
            lucide.createIcons();
            carregarChat(dados.id);
        }

        function fecharModal() {
            document.getElementById('modalEditar').classList.remove('active');
            modalChamadoId = null;
        }

        let _chatReqId = 0;

        function carregarChat(id) {
            const box = document.getElementById('man-chat-msgs');
            box.innerHTML = '<div class="flex items-center justify-center py-10 opacity-30"><i data-lucide="loader" class="w-6 h-6"></i></div>';
            lucide.createIcons();
            const reqId = ++_chatReqId;
            fetch('manutencao_gerenciar.php?action=get_comments&id=' + id, { cache: 'no-store' })
                .then(r => r.json())
                .then(data => { if (reqId === _chatReqId) renderChat(data.comentarios); })
                .catch(() => { if (reqId === _chatReqId) box.innerHTML = '<p class="text-xs text-center opacity-40 py-8">Erro ao carregar mensagens.</p>'; });
        }

        function renderChat(comentarios) {
            const box = document.getElementById('man-chat-msgs');
            box.innerHTML = '';
            const meId = <?php echo intval($_SESSION['usuario_id'] ?? 0); ?>;

            if (!comentarios || comentarios.length === 0) {
                box.innerHTML = '<div class="flex flex-col items-center justify-center opacity-20 py-10"><i data-lucide="message-circle" class="w-10 h-10 mb-2"></i><p class="text-[10px] font-black uppercase tracking-widest text-center">Nenhuma mensagem<br>nesta O.S.</p></div>';
                lucide.createIcons();
                return;
            }

            comentarios.forEach(function(c) {
                const isMe = (parseInt(c.usuario_id) === meId);
                const div = document.createElement('div');
                div.className = 'flex flex-col ' + (isMe ? 'items-end' : 'items-start');
                const bubbleClass = isMe
                    ? 'bg-orange-600 text-white rounded-l-2xl rounded-tr-2xl'
                    : 'bg-white border border-border text-text rounded-r-2xl rounded-tl-2xl';
                const dt = new Date(c.data_comentario.replace(' ', 'T')).toLocaleString('pt-BR');
                div.innerHTML = '<div class="max-w-[90%] ' + bubbleClass + ' p-3 shadow-sm">'
                    + '<p class="text-[10px] font-black uppercase tracking-tighter mb-1 opacity-70">' + (c.autor || 'Sistema') + '</p>'
                    + '<p class="text-xs leading-relaxed font-medium">' + (c.comentario || '').replace(/\n/g, '<br>') + '</p>'
                    + '<p class="text-[8px] mt-1 opacity-50 font-bold text-right">' + dt + '</p>'
                    + '</div>';
                box.appendChild(div);
            });
            box.scrollTop = box.scrollHeight;
        }

        function enviarComentario() {
            const input = document.getElementById('man-chat-input');
            const texto = input.value.trim();
            if (!texto || !modalChamadoId) return;

            input.disabled = true;
            const id = modalChamadoId;
            fetch('manutencao_gerenciar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'acao=adicionar_comentario&manutencao_id=' + id + '&comentario=' + encodeURIComponent(texto)
            })
            .then(r => r.json())
            .then(function(data) {
                input.disabled = false;
                input.value = '';
                if (data.ok) carregarChat(id);
            })
            .catch(function() { input.disabled = false; });
        }

        function limparChat() {
            if (!modalChamadoId) return;
            if (!confirm('Apagar todo o histórico de mensagens desta O.S.? Esta ação não pode ser desfeita.')) return;
            const id = modalChamadoId;
            fetch('manutencao_gerenciar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'acao=limpar_chat&manutencao_id=' + id
            })
            .then(r => r.json())
            .then(function(data) { if (data.ok) carregarChat(id); })
            .catch(function() {});
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModal();
        });

        document.getElementById('man-chat-input') && document.getElementById('man-chat-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); enviarComentario(); }
        });

        function excluirChamado(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente esta ordem de serviço? Esta ação não pode ser desfeita.')) {
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

        // ── Auto-refresh: detecta qualquer mudança nas ordens de serviço ─────────────
        (function () {
            const INTERVAL  = 30000;
            const STATUS_EL = document.getElementById('man-poll-status');

            function stateHash(list) {
                return list.map(c => c.id + ':' + c.status + ':' + c.nao_lidos).sort().join('|');
            }

            let lastHash = stateHash(<?php echo json_encode($poll_hash_data); ?>);

            function showToast(msg, autoReload) {
                const old = document.getElementById('man-toast');
                if (old) old.remove();
                const t = document.createElement('div');
                t.id = 'man-toast';
                t.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;display:flex;align-items:center;gap:10px;background:#ea580c;color:#fff;padding:12px 18px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.3);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;cursor:pointer;';
                t.innerHTML = '&#128276; ' + msg;
                t.onclick = () => location.reload();
                document.body.appendChild(t);
                if (autoReload) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    setTimeout(() => { if (t.parentNode) t.remove(); }, 8000);
                }
            }

            function pulse(ok) {
                if (!STATUS_EL) return;
                STATUS_EL.className = ok
                    ? 'w-2 h-2 rounded-full bg-emerald-400 animate-pulse'
                    : 'w-2 h-2 rounded-full bg-rose-400';
            }

            async function poll() {
                try {
                    const _pollStatus = new URLSearchParams(window.location.search).get('status') || '';
                    const res = await fetch('manutencao_gerenciar.php?action=poll' + (_pollStatus ? '&status=' + encodeURIComponent(_pollStatus) : ''), { cache: 'no-store' });
                    if (!res.ok) { pulse(false); return; }
                    const data = await res.json();
                    pulse(true);

                    const currentHash = stateHash(
                        data.chamados.map(c => ({
                            id:        String(c.id),
                            status:    c.status,
                            nao_lidos: String(c.nao_lidos)
                        }))
                    );

                    if (currentHash !== lastHash) {
                        lastHash = currentHash;
                        const modalOpen = document.getElementById('modalEditar').classList.contains('active');
                        if (modalOpen && modalChamadoId) {
                            // Atualiza o chat em tempo real sem fechar o modal
                            carregarChat(modalChamadoId);
                        } else if (!modalOpen) {
                            showToast('Atualizando...', true);
                        }
                    }
                } catch (e) {
                    pulse(false);
                }
            }

            poll();
            setInterval(poll, INTERVAL);
        })();
        // ────────────────────────────────────────────────────────────────────
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
