<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar abertura de chamado de manutenção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'abrir_manutencao') {
    $titulo = sanitize($_POST['titulo']);
    $descricao = $_POST['descricao'];
    $local = sanitize($_POST['local']);
    $prioridade = isset($_POST['prioridade']) ? sanitize($_POST['prioridade']) : 'Média';
    $categoria = sanitize($_POST['categoria']);
    $anexo = '';

    // Processar upload de anexo
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'manutencao_main_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $diretorio = 'uploads/manutencao/';
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }
        $destino = $diretorio . $novo_nome;
        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $destino)) {
            $anexo = $novo_nome;
        }
    }

    $stmt = $conn->prepare("INSERT INTO manutencao (titulo, descricao, local, prioridade, categoria, usuario_id, anexo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $titulo, $descricao, $local, $prioridade, $categoria, $usuario_id, $anexo);

    if ($stmt->execute()) {
        registrarLog($conn, "Abriu chamado de Manutenção: " . $titulo);
        header("Location: manutencao.php?msg=aberto");
        exit;
    } else {
        $mensagem = "Erro ao abrir chamado: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar Novo Comentário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_comentario') {
    $manutencao_id = intval($_POST['manutencao_id']);
    $usuario_id = $_SESSION['usuario_id'];
    $comentario = sanitize($_POST['comentario']);
    $anexo = '';

    // Processar upload de anexo no comentário
    if (isset($_FILES['anexo_comentario']) && $_FILES['anexo_comentario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anexo_comentario']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'manutencao_com_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $diretorio = 'uploads/manutencao/';
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }
        $destino = $diretorio . $novo_nome;
        if (move_uploaded_file($_FILES['anexo_comentario']['tmp_name'], $destino)) {
            $anexo = $novo_nome;
        }
    }

    // Segurança: Garantir que o chamado pertence ao usuário ou ele é admin
    $check = $conn->query("SELECT id FROM manutencao WHERE id = $manutencao_id AND (usuario_id = $usuario_id OR 1=" . (isAdmin() ? "1" : "0") . ")");
    
    if ($check->num_rows > 0 && (!empty($comentario) || !empty($anexo))) {
        $stmt = $conn->prepare("INSERT INTO manutencao_comentarios (manutencao_id, usuario_id, comentario, lido_pelo_tecnico, lido_pelo_usuario, anexo) VALUES (?, ?, ?, 0, 1, ?)");
        $stmt->bind_param("iiss", $manutencao_id, $usuario_id, $comentario, $anexo);
        if ($stmt->execute()) {
            header("Location: manutencao.php?msg=comentario_ok&id=$manutencao_id");
            exit;
        }
        $stmt->close();
    }
}

// Processar Marcação de Leitura (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'marcar_lido') {
    $manutencao_id = intval($_POST['manutencao_id']);
    $conn->query("UPDATE manutencao_comentarios SET lido_pelo_usuario = 1 WHERE manutencao_id = $manutencao_id");
    exit;
}

// Mensagens de feedback
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'aberto') {
        $mensagem = "Ordem de Serviço aberta com sucesso! A equipe de infraestrutura foi notificada.";
        $tipo_mensagem = "success";
    } elseif ($_GET['msg'] == 'comentario_ok') {
        $mensagem = "Resposta enviada com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Filtros
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_clauses = [];

if (!isAdmin()) {
    $where_clauses[] = "m.usuario_id = $usuario_id";
}

if ($filtro_status) {
    $where_clauses[] = "m.status = '$filtro_status'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Buscar chamados de manutenção
$sql = "SELECT m.*, u.nome as solicitante, t.nome as tecnico 
        FROM manutencao m 
        JOIN usuarios u ON m.usuario_id = u.id 
        LEFT JOIN usuarios t ON m.tecnico_id = t.id 
        $where_sql
        ORDER BY m.data_abertura DESC";
$res = $conn->query($sql);
$chamados = [];
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0, 'Cancelado' => 0];

while($row = $res->fetch_assoc()) {
    $c_id = $row['id'];
    
    // Comentários não lidos
    $unread_res = $conn->query("SELECT COUNT(*) FROM manutencao_comentarios WHERE manutencao_id = $c_id AND lido_pelo_usuario = 0");
    $row['tem_novidade'] = ($unread_res->fetch_row()[0] > 0);

    // Buscar comentários
    $comentarios_res = $conn->query("SELECT mc.*, u.nome as autor FROM manutencao_comentarios mc 
                                     JOIN usuarios u ON mc.usuario_id = u.id 
                                     WHERE mc.manutencao_id = $c_id 
                                     ORDER BY mc.data_comentario ASC");
    $row['comentarios'] = [];
    while($coment = $comentarios_res->fetch_assoc()) {
        $row['comentarios'][] = $coment;
    }
    
    $chamados[] = $row;
    if (isset($stats[$row['status']])) $stats[$row['status']]++;
}

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'dot' => 'bg-blue-500', 'icon' => 'clock'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'dot' => 'bg-amber-500', 'icon' => 'play-circle'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'dot' => 'bg-purple-500', 'icon' => 'package'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'dot' => 'bg-green-500', 'icon' => 'check-circle'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'dot' => 'bg-red-500', 'icon' => 'x-circle']
];

$prioridade_labels = [
    'Baixa' => ['text' => 'text-gray-400', 'icon' => 'arrow-down'],
    'Média' => ['text' => 'text-blue-500', 'icon' => 'minus'],
    'Alta' => ['text' => 'text-orange-500', 'icon' => 'arrow-up'],
    'Urgente' => ['text' => 'text-red-600 font-bold', 'icon' => 'alert-circle']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenção & Infraestrutura - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .chat-container { scrollbar-width: thin; scrollbar-color: rgba(0,0,0,0.1) transparent; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 text-[13px]">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="wrench" class="w-6 h-6"></i>
                    Manutenção & Infraestrutura
                </h1>
                <p class="text-text-secondary text-xs mt-1">Reparos prediais, elétricos, hidráulicos e infraestrutura</p>
            </div>

            <div class="flex items-center gap-2">
                <?php if (isAdmin()): ?>
                <a href="admin/manutencao_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Gerenciar Demandas
                </a>
                <?php endif; ?>
                <button onclick="abrirModal()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-[11px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-wider">
                    <i data-lucide="hammer" class="w-4 h-4"></i>
                    Abrir Ordem de Serviço
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-6 flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <a href="manutencao.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_status ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($chamados); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach(['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Resolvido'] as $st): 
                $active = ($filtro_status == $st);
                $style = $status_styles[$st];
            ?>
            <a href="?status=<?php echo urlencode($st); ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $style['icon']; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$st]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $st; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- List -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">OS / Descrição</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Local</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php if (count($chamados) > 0): ?>
                            <?php foreach ($chamados as $chamado): 
                                $style = $status_styles[$chamado['status']];
                                $prio = $prioridade_labels[$chamado['prioridade']];
                            ?>
                            <tr onclick='verDetalhes(<?php echo json_encode($chamado); ?>)' class="hover:bg-background/20 transition-colors group cursor-pointer">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <span class="text-[9px] font-mono font-bold text-text-secondary opacity-50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php if ($chamado['tem_novidade']): ?>
                                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white animate-pulse"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-text group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></span>
                                                <?php if ($chamado['tem_novidade']): ?>
                                                    <span class="bg-rose-50 text-rose-600 text-[8px] px-1 rounded font-black uppercase tracking-tighter border border-rose-100 italic">Novo!</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $chamado['categoria']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5 text-text-secondary">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 opacity-50"></i>
                                        <span class="font-bold text-[10px]"><?php echo $chamado['local']; ?></span>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5 <?php echo $prio['text']; ?>">
                                        <i data-lucide="<?php echo $prio['icon']; ?>" class="w-3.5 h-3.5"></i>
                                        <span class="font-bold uppercase tracking-tighter text-[10px]"><?php echo $chamado['prioridade']; ?></span>
                                    </div>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?php echo $style['bg']; ?> <?php echo $style['text']; ?>">
                                        <?php echo $chamado['status']; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right font-mono text-text-secondary opacity-60 text-[10px]">
                                    <?php echo date('d/m H:i', strtotime($chamado['data_abertura'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-16 text-center">
                                    <i data-lucide="hard-hat" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhuma ordem de serviço encontrada.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado -->
    <div id="modalManutencao" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-orange-600 px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Nova Ordem de Serviço</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Infraestrutura</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" class="p-5">
                <input type="hidden" name="acao" value="abrir_manutencao">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Assunto / Descrição Curta</label>
                        <input type="text" name="titulo" required placeholder="Ex: Lâmpada queimada, Vazamento..." 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Local exato</label>
                        <input type="text" name="local" required placeholder="Ex: Sala 02, Recepção, Banheiro 1º andar" 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all">
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                        <select name="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all cursor-pointer">
                            <option value="Elétrica">Elétrica</option>
                            <option value="Hidráulica">Hidráulica</option>
                            <option value="Pedreiro/Pintura">Pedreiro/Pintura</option>
                            <option value="Mobiliário">Mobiliário</option>
                            <option value="Ar Condicionado">Ar Condicionado</option>
                            <option value="Informática/Infra">Informática/Infra</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Prioridade</label>
                        <select name="prioridade" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all cursor-pointer">
                            <option value="Baixa">Baixa</option>
                            <option value="Média" selected>Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Anexar Foto (Opcional)</label>
                        <div class="relative group">
                            <input type="file" name="anexo" id="anexo_input" class="hidden" accept="image/*,.pdf">
                            <label for="anexo_input" class="flex items-center justify-center gap-2 w-full p-3 bg-background border-2 border-dashed border-border rounded-xl cursor-pointer hover:border-orange-600 hover:bg-orange-50 transition-all">
                                <i data-lucide="camera" class="w-5 h-5 text-text-secondary group-hover:text-orange-600"></i>
                                <span id="file_name" class="text-xs font-bold text-text-secondary group-hover:text-orange-600">Clique para selecionar foto ou PDF</span>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Detalhes da Ocorrência</label>
                        <textarea name="descricao" required rows="4" placeholder="Descreva com detalhes o que está acontecendo..."
                                  class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Abrir Ordem</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalhes Modo Paisagem (2 Colunas) -->
    <div id="modalDetalhes" class="modal">
        <div class="bg-white w-full max-w-5xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden flex flex-col md:flex-row animate-in zoom-in duration-150 h-[85vh]">
            <!-- Coluna Esquerda: Informações -->
            <div class="w-full md:w-1/2 flex flex-col border-r border-border bg-gray-50/50">
                <div id="modal_header_bg" class="px-5 py-4 text-white flex justify-between items-center bg-orange-600">
                    <div>
                        <h2 class="text-base font-bold flex items-center gap-2">
                            <span id="detalhe_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                            Detalhes da O.S.
                        </h2>
                        <p id="detalhe_status_label" class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Status: ---</p>
                    </div>
                    <button class="md:hidden p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModalDetalhes()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 flex-grow overflow-y-auto space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Assunto</label>
                            <p id="detalhe_titulo" class="text-sm font-bold text-text">---</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Categoria / Local</label>
                            <p id="detalhe_categoria_local" class="text-xs font-bold text-orange-600 uppercase">---</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Descrição Original</label>
                        <div id="detalhe_descricao" class="text-xs text-text-secondary leading-relaxed bg-white p-4 rounded-xl border border-border/60 italic shadow-sm">---</div>
                    </div>

                    <div id="detalhe_anexo_container" class="hidden">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Anexo Original</label>
                        <a id="detalhe_anexo_link" href="#" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-border rounded-xl hover:border-orange-600 transition-all group">
                            <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition-all">
                                <i data-lucide="file-text" class="w-5 h-5"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[11px] font-bold text-text">Ver Arquivo Anexo</span>
                                <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter">Clique para abrir</span>
                            </div>
                        </a>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-border">
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Solicitante</label>
                            <p id="detalhe_solicitante" class="text-[11px] font-bold text-text">---</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Técnico Responsável</label>
                            <p id="detalhe_tecnico" class="text-[11px] font-bold text-text">---</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Chat/Histórico -->
            <div class="w-full md:w-1/2 flex flex-col bg-white">
                <div class="px-5 py-4 border-b border-border flex justify-between items-center bg-white sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <i data-lucide="message-square" class="w-4 h-4 text-orange-600"></i>
                        <h3 class="text-xs font-black text-text uppercase tracking-widest">Histórico & Chat</h3>
                    </div>
                    <button class="hidden md:block p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-text-secondary" onclick="fecharModalDetalhes()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Lista de Mensagens -->
                <div id="chat_mensagens" class="flex-grow overflow-y-auto p-5 space-y-4 bg-gray-50/30 chat-container">
                    <!-- Mensagens via JS -->
                </div>

                <!-- Input de Mensagem -->
                <div class="p-4 border-t border-border bg-white">
                    <form id="formComentario" method="POST" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="acao" value="adicionar_comentario">
                        <input type="hidden" name="manutencao_id" id="comentario_manutencao_id">
                        
                        <div class="relative group">
                            <input type="file" name="anexo_comentario" id="anexo_com_input" class="hidden" accept="image/*,.pdf">
                            <div id="status_anexo_com" class="hidden absolute -top-8 left-0 right-0 bg-orange-600 text-white text-[9px] font-bold px-2 py-1 rounded-t-lg flex items-center justify-between">
                                <span class="flex items-center gap-1"><i data-lucide="paperclip" class="w-3 h-3"></i> Arquivo pronto para envio</span>
                                <button type="button" onclick="resetAnexoCom()"><i data-lucide="x" class="w-3 h-3"></i></button>
                            </div>
                            <textarea name="comentario" id="chat_textarea" rows="2" required placeholder="Digite sua mensagem aqui..."
                                      class="w-full p-3 bg-background border border-border rounded-xl text-xs font-medium focus:outline-none focus:border-orange-600 transition-all resize-none"></textarea>
                        </div>

                        <div class="flex justify-between items-center gap-2">
                            <label for="anexo_com_input" class="p-2 text-text-secondary hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest">
                                <i data-lucide="paperclip" class="w-4 h-4"></i>
                                Anexar Foto
                            </label>
                            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2 rounded-lg text-[10px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-widest active:scale-95">
                                <span>Enviar</span>
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Configurações do Lucide
        lucide.createIcons();

        const usuarioAtualId = <?php echo $usuario_id; ?>;

        function abrirModal() {
            document.getElementById('modalManutencao').classList.add('active');
        }

        function fecharModal() {
            document.getElementById('modalManutencao').classList.remove('active');
        }

        function fecharModalDetalhes() {
            document.getElementById('modalDetalhes').classList.remove('active');
        }

        function verDetalhes(chamado) {
            document.getElementById('detalhe_id').innerText = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('detalhe_titulo').innerText = chamado.titulo;
            document.getElementById('detalhe_categoria_local').innerText = chamado.categoria + ' • ' + chamado.local;
            document.getElementById('detalhe_descricao').innerText = chamado.descricao;
            document.getElementById('detalhe_status_label').innerText = 'Status: ' + chamado.status;
            document.getElementById('detalhe_solicitante').innerText = chamado.solicitante;
            document.getElementById('detalhe_tecnico').innerText = chamado.tecnico ? chamado.tecnico : 'Em triagem...';
            document.getElementById('comentario_manutencao_id').value = chamado.id;

            // Header color
            const modalHeader = document.getElementById('modal_header_bg');
            modalHeader.className = 'px-5 py-4 text-white flex justify-between items-center ';
            if (chamado.status === 'Resolvido') modalHeader.classList.add('bg-green-600');
            else if (chamado.status === 'Cancelado') modalHeader.classList.add('bg-red-600');
            else if (chamado.status === 'Em Atendimento') modalHeader.classList.add('bg-amber-600');
            else modalHeader.classList.add('bg-orange-600');

            // Handling anexo original
            const anexoContainer = document.getElementById('detalhe_anexo_container');
            if (chamado.anexo) {
                anexoContainer.classList.remove('hidden');
                document.getElementById('detalhe_anexo_link').href = 'uploads/manutencao/' + chamado.anexo;
            } else {
                anexoContainer.classList.add('hidden');
            }

            // Chat/Comentarios
            const chatContainer = document.getElementById('chat_mensagens');
            chatContainer.innerHTML = '';
            
            if (chamado.comentarios && chamado.comentarios.length > 0) {
                chamado.comentarios.forEach(coment => {
                    const isMe = (coment.usuario_id == usuarioAtualId);
                    const date = new Date(coment.data_comentario).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'});
                    
                    const div = document.createElement('div');
                    div.className = `flex flex-col ${isMe ? 'items-end' : 'items-start'}`;
                    
                    let html = `
                        <div class="max-w-[85%] ${isMe ? 'bg-orange-600 text-white rounded-l-xl rounded-tr-xl' : 'bg-white border border-border text-text rounded-r-xl rounded-tl-xl'} shadow-sm overflow-hidden">
                            <div class="px-3 py-1.5 flex justify-between items-center gap-4 text-[9px] font-bold uppercase tracking-tighter ${isMe ? 'text-white/60' : 'text-text-secondary opacity-60'}">
                                <span>${isMe ? 'Você' : coment.autor}</span>
                                <span>${date}</span>
                            </div>
                            <div class="px-3 pb-2 text-xs font-medium leading-relaxed">
                                ${coment.comentario}
                            </div>
                    `;

                    if (coment.anexo) {
                        html += `
                            <div class="px-3 pb-3">
                                <a href="uploads/manutencao/${coment.anexo}" target="_blank" class="flex items-center gap-2 p-2 rounded-lg ${isMe ? 'bg-white/10 hover:bg-white/20' : 'bg-background hover:bg-gray-100'} transition-all border ${isMe ? 'border-white/10' : 'border-border'}">
                                    <i data-lucide="file-text" class="w-3 h-3"></i>
                                    <span class="text-[9px] font-bold uppercase tracking-widest">Ver Anexo</span>
                                </a>
                            </div>
                        `;
                    }

                    html += `</div>`;
                    div.innerHTML = html;
                    chatContainer.appendChild(div);
                });
            } else {
                chatContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-10 text-text-secondary opacity-30 italic">
                        <i data-lucide="message-circle-off" class="w-8 h-8 mb-2"></i>
                        <p class="text-[10px] font-bold uppercase tracking-widest">Sem comentários</p>
                    </div>
                `;
            }

            document.getElementById('modalDetalhes').classList.add('active');
            lucide.createIcons();
            
            setTimeout(() => {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }, 100);

            // Marcar como lido via AJAX
            if (chamado.tem_novidade) {
                const formData = new URLSearchParams();
                formData.append('acao', 'marcar_lido');
                formData.append('manutencao_id', chamado.id);
                fetch('manutencao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });
            }
        }

        // File name preview
        document.getElementById('anexo_input').onchange = function() {
            document.getElementById('file_name').innerText = this.files[0] ? this.files[0].name : "Clique para selecionar foto ou PDF";
        };

        document.getElementById('anexo_com_input').onchange = function() {
            if (this.files[0]) {
                document.getElementById('status_anexo_com').classList.remove('hidden');
                document.getElementById('chat_textarea').classList.add('pt-6');
            }
        };

        function resetAnexoCom() {
            document.getElementById('anexo_com_input').value = '';
            document.getElementById('status_anexo_com').classList.add('hidden');
            document.getElementById('chat_textarea').classList.remove('pt-6');
        }

        // Auto-open modal if ID is in URL
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            if (id) {
                const chamadosList = <?php echo json_encode($chamados); ?>;
                const chamado = chamadosList.find(c => c.id == id);
                if (chamado) verDetalhes(chamado);
            }
        }
    </script>
</body>
</html>