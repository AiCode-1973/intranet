<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar abertura de chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'abrir_chamado') {
    $titulo = sanitize($_POST['titulo']);
    $descricao = $_POST['descricao'];
    $prioridade = isset($_POST['prioridade']) ? sanitize($_POST['prioridade']) : 'Média';
    $categoria = sanitize($_POST['categoria']);

    $stmt = $conn->prepare("INSERT INTO chamados (titulo, descricao, prioridade, categoria, usuario_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $titulo, $descricao, $prioridade, $categoria, $usuario_id);

    if ($stmt->execute()) {
        $chamado_id = $stmt->insert_id;
        
        // Processar anexos múltiplos
        if (isset($_FILES['anexos']) && count($_FILES['anexos']['name']) > 0) {
            $diretorio_anexos = 'uploads/suporte/';
            if (!is_dir($diretorio_anexos)) {
                mkdir($diretorio_anexos, 0777, true);
            }

            foreach ($_FILES['anexos']['name'] as $key => $nome_original) {
                if ($_FILES['anexos']['error'][$key] === UPLOAD_ERR_OK) {
                    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
                    $nome_arquivo = 'chamado_' . $chamado_id . '_' . time() . '_' . $key . '.' . $extensao;
                    $caminho_final = $diretorio_anexos . $nome_arquivo;

                    if (move_uploaded_file($_FILES['anexos']['tmp_name'][$key], $caminho_final)) {
                        $tipo_arquivo = $_FILES['anexos']['type'][$key];
                        $stmt_anexo = $conn->prepare("INSERT INTO chamados_anexos (chamado_id, caminho_arquivo, nome_original, tipo_arquivo) VALUES (?, ?, ?, ?)");
                        $stmt_anexo->bind_param("isss", $chamado_id, $caminho_final, $nome_original, $tipo_arquivo);
                        $stmt_anexo->execute();
                        $stmt_anexo->close();
                    }
                }
            }
        }

        registrarLog($conn, "Abriu chamado de TI: " . $titulo);
        header("Location: suporte.php?msg=aberto");
        exit;
    } else {
        $mensagem = "Erro ao abrir chamado: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar Novo Comentário do Solicitante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_comentario') {
    $chamado_id = intval($_POST['chamado_id']);
    $usuario_id = $_SESSION['usuario_id'];
    $comentario = sanitize($_POST['comentario']);

    // Segurança: Garantir que o chamado pertence ao usuário ou ele é admin
    $check = $conn->query("SELECT id FROM chamados WHERE id = $chamado_id AND (usuario_id = $usuario_id OR 1=" . (isAdmin() ? "1" : "0") . ")");
    
    if ($check->num_rows > 0 && !empty($comentario)) {
        $stmt = $conn->prepare("INSERT INTO chamados_comentarios (chamado_id, usuario_id, comentario) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $chamado_id, $usuario_id, $comentario);
        if ($stmt->execute()) {
            header("Location: suporte.php?msg=comentario_ok&id=$chamado_id");
            exit;
        }
        $stmt->close();
    }
}

// Processar Pesquisa de Satisfação
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'enviar_satisfacao') {
    $id = intval($_POST['id']);
    $nota = intval($_POST['satisfacao_nota']);
    $comentario = sanitize($_POST['satisfacao_comentario']);
    $data_satisfacao = date('Y-m-d H:i:s');

    // Validação de segurança: o chamado deve pertencer ao usuário e estar Resolvido
    $check_stmt = $conn->prepare("SELECT id FROM chamados WHERE id = ? AND usuario_id = ? AND status = 'Resolvido'");
    $check_stmt->bind_param("ii", $id, $usuario_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE chamados SET satisfacao_nota = ?, satisfacao_comentario = ?, data_satisfacao = ? WHERE id = ?");
        $stmt->bind_param("issi", $nota, $comentario, $data_satisfacao, $id);
        if ($stmt->execute()) {
            registrarLog($conn, "Enviou pesquisa de satisfação para chamado #$id");
            header("Location: suporte.php?msg=avaliado");
            exit;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Mensagens de feedback
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'aberto') {
        $mensagem = "Chamado aberto com sucesso! Em breve um técnico irá atendê-lo.";
        $tipo_mensagem = "success";
    } elseif ($_GET['msg'] == 'avaliado') {
        $mensagem = "Obrigado pelo seu feedback! Pesquisa de satisfação enviada.";
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
    $where_clauses[] = "c.usuario_id = $usuario_id";
}

if ($filtro_status) {
    $where_clauses[] = "c.status = '$filtro_status'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Buscar chamados
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico 
        FROM chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        $where_sql
        ORDER BY c.data_abertura DESC";
$res = $conn->query($sql);
$chamados = [];
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0, 'Cancelado' => 0];

while($row = $res->fetch_assoc()) {
    // Buscar anexos do chamado
    $c_id = $row['id'];
    $anexos_res = $conn->query("SELECT * FROM chamados_anexos WHERE chamado_id = $c_id");
    $row['anexos'] = [];

    // Buscar comentários do chamado
    $comentarios_res = $conn->query("SELECT cc.*, u.nome as autor FROM chamados_comentarios cc 
                                     JOIN usuarios u ON cc.usuario_id = u.id 
                                     WHERE cc.chamado_id = $c_id 
                                     ORDER BY cc.data_comentario ASC");
    $row['comentarios'] = [];
    while($coment = $comentarios_res->fetch_assoc()) {
        $row['comentarios'][] = $coment;
    }
    while($anexo = $anexos_res->fetch_assoc()) {
        $row['anexos'][] = $anexo;
    }
    
    $chamados[] = $row;
    if (isset($stats[$row['status']])) $stats[$row['status']]++;
}

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'dot' => 'bg-blue-500'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'dot' => 'bg-amber-500'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'dot' => 'bg-purple-500'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'dot' => 'bg-green-500'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'dot' => 'bg-red-500']
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
    <title>Suporte de TI - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section (Slim Style) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="monitor-dot" class="w-6 h-6"></i>
                    Suporte de TI
                </h1>
                <p class="text-text-secondary text-xs mt-1">Abertura e acompanhamento de chamados técnicos</p>
            </div>

            <div class="flex items-center gap-2">
                <?php if (isAdmin()): ?>
                <a href="admin/suporte_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Painel Gestor
                </a>
                <?php endif; ?>
                <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-[11px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-wider">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Novo Chamado
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-6 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 animate-in slide-in-from-top-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid (Slim Style) -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <a href="suporte.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_status ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($chamados); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach(['Aberto', 'Em Atendimento', 'Resolvido', 'Cancelado'] as $st): 
                $active = ($filtro_status == $st);
                $style = $status_styles[$st];
                $icon = $st == 'Aberto' ? 'clock' : ($st == 'Em Atendimento' ? 'play-circle' : ($st == 'Resolvido' ? 'check-circle' : 'x-circle'));
            ?>
            <a href="?status=<?php echo $st; ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$st]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $st; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Chamados List (Tabela Slim) -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Assunto</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico</th>
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
                                        <span class="text-[9px] font-mono font-bold text-text-secondary opacity-50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-text group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></span>
                                            <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $chamado['categoria']; ?></span>
                                        </div>
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
                                <td class="p-3 font-bold text-text-secondary">
                                    <?php if ($chamado['tecnico']): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 rounded bg-primary/10 flex items-center justify-center text-[9px] font-bold text-primary">
                                                <?php echo strtoupper(substr($chamado['tecnico'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo $chamado['tecnico']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-text-secondary/30 italic">Aguardando...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right font-mono text-text-secondary opacity-60 text-[10px]">
                                    <?php echo date('d/m H:i', strtotime($chamado['data_abertura'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-16 text-center">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhum chamado encontrado.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado -->
    <div id="modalChamado" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Novo Chamado</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Abertura de Ticket</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="abrir_chamado">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Assunto</label>
                        <input type="text" name="titulo" required placeholder="Resuma o problema" 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                        <select name="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all cursor-pointer">
                            <option value="Hardware">Hardware</option>
                            <option value="Software">Software</option>
                            <option value="Internet/Rede">Internet / Rede</option>
                            <option value="E-mail">E-mail</option>
                            <option value="Impressora">Impressoras</option>
                            <option value="Suporte Geral" selected>Suporte Geral</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição</label>
                        <textarea name="descricao" required rows="4" placeholder="Detalhes do chamado..."
                                  class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Anexar Arquivos (Fotos, Prints, etc.)</label>
                        <input type="file" name="anexos[]" multiple 
                               class="w-full p-2 bg-background border border-border rounded-lg text-[10px] font-bold focus:outline-none focus:border-primary transition-all file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-primary file:text-white hover:file:bg-primary-hover cursor-pointer">
                        <p class="text-[9px] text-text-secondary mt-1 italic">Você pode selecionar múltiplos arquivos de uma vez.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Abrir Chamado</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalhes do Chamado -->
    <div id="modalDetalhes" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div id="modal_header_bg" class="px-5 py-4 text-white flex justify-between items-center bg-primary">
                <div>
                    <h2 class="text-base font-bold flex items-center gap-2">
                        <span id="detalhe_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                        Detalhes do Chamado
                    </h2>
                    <p id="detalhe_status_label" class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Status: ---</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModalDetalhes()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Assunto</label>
                    <p id="detalhe_titulo" class="text-sm font-bold text-text">---</p>
                </div>

                <!-- Histórico de Interações (Comentários) -->
                <div class="pt-4 border-t border-border">
                    <label class="block text-[10px] font-black text-text-secondary mb-2 uppercase tracking-widest opacity-50 flex items-center gap-1.5">
                        <i data-lucide="message-square" class="w-3 h-3"></i>
                        Interações Técnicas
                    </label>
                    
                    <div id="detalhe_comentarios" class="space-y-3 mb-4 max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                        <!-- JS Populado -->
                    </div>

                    <form method="POST" action="" id="form_comentario_usuario" class="flex gap-2 group border-t border-border/10 pt-3">
                        <input type="hidden" name="acao" value="adicionar_comentario">
                        <input type="hidden" name="chamado_id" id="comentario_chamado_id">
                        <input type="text" name="comentario" required placeholder="Responder técnico ou adicionar nota..." 
                               class="flex-grow p-2 bg-background border border-border rounded-lg text-[10px] font-bold focus:outline-none focus:border-primary transition-all">
                        <button type="submit" class="bg-primary text-white px-3 rounded-lg hover:bg-primary-hover transition-all shadow-md active:scale-95">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Descrição do Problema</label>
                    <div id="detalhe_descricao" class="text-xs text-text-secondary leading-relaxed bg-background p-3 rounded-lg border border-border/50 max-h-32 overflow-y-auto italic">---</div>
                </div>

                <div id="container_anexos" class="hidden">
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest opacity-50">Arquivos Anexados</label>
                    <div id="detalhe_anexos" class="flex flex-wrap gap-2"></div>
                </div>

                <div id="container_resolucao" class="hidden animate-in fade-in slide-in-from-bottom-2">
                    <label class="block text-[10px] font-black text-emerald-600 mb-1 uppercase tracking-widest flex items-center gap-1">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                        Resolução Técnica
                    </label>
                    <div id="detalhe_resolucao" class="text-xs text-emerald-700 leading-relaxed bg-emerald-50 p-3 rounded-lg border border-emerald-100 font-bold whitespace-pre-wrap">---</div>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-border">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-0.5 uppercase tracking-widest opacity-50">Técnico Responsável</label>
                        <p id="detalhe_tecnico" class="text-[11px] font-bold text-text">---</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-0.5 uppercase tracking-widest opacity-50">Data de Abertura</label>
                        <p id="detalhe_data" class="text-[11px] font-bold text-text text-right">---</p>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-50 flex justify-between items-center">
                <div id="btn_satisfacao_container" class="hidden">
                    <button onclick="exibirPesquisaSatisfacao()" class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">
                        <i data-lucide="star" class="w-3.5 h-3.5"></i>
                        Avaliar Atendimento
                    </button>
                </div>
                <button onclick="fecharModalDetalhes()" class="px-6 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all shadow-sm uppercase tracking-widest">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Pesquisa de Satisfação -->
    <div id="modalSatisfacao" class="modal">
        <div class="bg-white w-full max-w-sm mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-amber-500 px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Avaliação de Atendimento</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Sua opinião é importante</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModalSatisfacao()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="enviar_satisfacao">
                <input type="hidden" name="id" id="satisfacao_chamado_id">
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-3 uppercase tracking-widest text-center">Como você avalia o atendimento técnico?</label>
                    <div class="flex justify-center gap-2 mb-2">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <label class="cursor-pointer group">
                            <input type="radio" name="satisfacao_nota" value="<?php echo $i; ?>" required class="peer hidden">
                            <div class="w-10 h-10 rounded-lg border-2 border-border flex items-center justify-center text-sm font-bold text-text-secondary peer-checked:bg-amber-500 peer-checked:border-amber-500 peer-checked:text-white transition-all group-hover:border-amber-300 shadow-sm active:scale-90">
                                <?php echo $i; ?>
                            </div>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <!-- Legenda das notas (Escala Linear) -->
                    <div class="flex justify-between items-center px-1 text-[9px] font-bold uppercase tracking-tighter text-text-secondary/50 border-t border-border/40 pt-2">
                        <span class="flex items-center gap-1"><i data-lucide="frown" class="w-3 h-3 text-rose-400"></i> Ruim</span>
                        <span class="text-text-secondary/30 italic">Regular</span>
            document.getElementById('comentario_chamado_id').value = chamado.id;
                        <span class="flex items-center gap-1 text-right">Excelente <i data-lucide="smile" class="w-3 h-3 text-emerald-400"></i></span>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Comentário Adicional (Opcional)</label>
                    <textarea name="satisfacao_comentario" rows="3" placeholder="Conte-nos um pouco mais sobre sua experiência..."
                              class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-amber-500 transition-all"></textarea>
                </div>

                <div class="flex flex-col gap-2 pt-2">
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Enviar Avaliação</button>
                    <button type="button" onclick="fecharModalSatisfacao()" class="w-full py-2 text-[10px] font-bold text-text-secondary hover:text-text transition-colors uppercase tracking-widest">Agora não</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentChamado = null;

        function abrirModal() { document.getElementById('modalChamado').classList.add('active'); }
        function fecharModal() { document.getElementById('modalChamado').classList.remove('active'); }

        function verDetalhes(chamado) {
            currentChamado = chamado; // Armazenar chamado atual para pesquisa de satisfação
            document.getElementById('detalhe_id').textContent = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('detalhe_titulo').textContent = chamado.titulo;
            document.getElementById('detalhe_descricao').textContent = chamado.descricao;
            document.getElementById('detalhe_status_label').textContent = 'Status: ' + chamado.status;
            document.getElementById('detalhe_tecnico').textContent = chamado.tecnico || 'Pendente';
            document.getElementById('detalhe_data').textContent = chamado.data_abertura;

            const resCcomentários
            const comList = document.getElementById('detalhe_comentarios');
            comList.innerHTML = '';
            const formCom = document.getElementById('form_comentario_usuario');
            
            // Ocultar formulário de comentário se o chamado estiver fechado
            if (chamado.status === 'Resolvido' || chamado.status === 'Cancelado') {
                formCom.classList.add('hidden');
            } else {
                formCom.classList.remove('hidden');
            }

            if (chamado.comentarios && chamado.comentarios.length > 0) {
                chamado.comentarios.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'bg-background p-2 rounded-lg border border-border/40';
                    div.innerHTML = `
                        <div class="flex justify-between items-center mb-0.5">
                            <span class="text-[8px] font-black text-primary uppercase">${c.autor}</span>
                            <span class="text-[7px] text-text-secondary opacity-50">${c.data_comentario}</span>
                        </div>
                        <p class="text-[9px] text-text-secondary leading-tight italic">"${c.comentario}"</p>
                    `;
                    comList.appendChild(div);
                });
            } else {
                comList.innerHTML = '<p class="text-[9px] text-text-secondary/40 italic text-center py-2">Sem mensagens no momento.</p>';
            }

            // Exibir ontainer = document.getElementById('container_resolucao');
            const resText = document.getElementById('detalhe_resolucao');
            const header = document.getElementById('modal_header_bg');
            const satisBtn = document.getElementById('btn_satisfacao_container');

            if (chamado.resolucao) {
                resContainer.classList.remove('hidden');
                resText.textContent = chamado.resolucao;
            } else {
                resContainer.classList.add('hidden');
            }

            // Exibir anexos se houver
            const anexoContainer = document.getElementById('container_anexos');
            const anexoList = document.getElementById('detalhe_anexos');
            anexoList.innerHTML = '';
            
            if (chamado.anexos && chamado.anexos.length > 0) {
                anexoContainer.classList.remove('hidden');
                chamado.anexos.forEach(anexo => {
                    const item = document.createElement('a');
                    item.href = anexo.caminho_arquivo;
                    item.target = '_blank';
                    item.className = 'flex items-center gap-1.5 px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-[9px] font-bold text-text-secondary transition-all border border-border';
                    
                    const isImage = anexo.tipo_arquivo.includes('image');
                    item.innerHTML = `<i data-lucide="${isImage ? 'image' : 'file-text'}" class="w-3 h-3"></i> <span>${anexo.nome_original}</span>`;
                    anexoList.appendChild(item);
                });
            } else {
                anexoContainer.classList.add('hidden');
            }

            // Exibir ou ocultar botão de pesquisa de satisfação
            // Só exibe se estiver Resolvido e ainda não foi avaliado
            if (chamado.status === 'Resolvido' && !chamado.satisfacao_nota) {
                satisBtn.classList.remove('hidden');
            } else {
                satisBtn.classList.add('hidden');
            }

            // Ajustar cores do header baseado no status
            header.className = 'px-5 py-4 text-white flex justify-between items-center ';
            if (chamado.status === 'Resolvido') {
                header.classList.add('bg-emerald-500');
            } else if (chamado.status === 'Cancelado') {
                header.classList.add('bg-gray-500');
            } else if (chamado.status === 'Em Atendimento') {
                header.classList.add('bg-amber-500');
            } else {
                header.classList.add('bg-primary');
            }

            document.getElementById('modalDetalhes').classList.add('active');
            lucide.createIcons(); // Recria os ícones dentro do modal
        }

        function fecharModalDetalhes() {
            document.getElementById('modalDetalhes').classList.remove('active');
        }

        function exibirPesquisaSatisfacao() {
            if (!currentChamado) return;
            document.getElementById('satisfacao_chamado_id').value = currentChamado.id;
            document.getElementById('modalSatisfacao').classList.add('active');
        }

        function fecharModalSatisfacao() {
            document.getElementById('modalSatisfacao').classList.remove('active');
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
