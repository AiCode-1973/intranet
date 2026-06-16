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
} else {
    $mensagem = "";
    $tipo_mensagem = "";
}

// Processar Atualização de Chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_chamado') {
    $id = intval($_POST['id']);
    $status = sanitize($_POST['status']);
    $prioridade = sanitize($_POST['prioridade']);
    $categoria = sanitize($_POST['categoria'] ?? '');
    $resolucao = $_POST['resolucao'] ?? '';
    $tecnico_id = !empty($_POST['tecnico_id']) ? intval($_POST['tecnico_id']) : null;
    $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE chamados SET status = ?, prioridade = ?, categoria = ?, resolucao = ?, tecnico_id = ?, data_fechamento = ? WHERE id = ?");
    $stmt->bind_param("ssssisi", $status, $prioridade, $categoria, $resolucao, $tecnico_id, $data_fechamento, $id);

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

// Poll endpoint para auto-refresh
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    header('Content-Type: application/json');
    $poll_cond = !empty($_GET['status']) ? "WHERE c.status = '" . $conn->real_escape_string($_GET['status']) . "'" : '';
    $rows = $conn->query("SELECT c.id, c.status,
        (SELECT COUNT(*) FROM chamados_comentarios cc WHERE cc.chamado_id = c.id AND cc.lido_pelo_tecnico = 0) as nao_lidos
        FROM chamados c $poll_cond ORDER BY c.data_abertura DESC");
    $result = [];
    while ($r = $rows->fetch_assoc()) $result[] = $r;
    echo json_encode(['chamados' => $result]);
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

// ── GERENCIAR CATEGORIAS DE SUPORTE ─────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS suporte_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ((int)$conn->query("SELECT COUNT(*) FROM suporte_categorias")->fetch_row()[0] === 0) {
    $conn->query("INSERT INTO suporte_categorias (nome, ordem) VALUES
        ('Hardware',1),('Software',2),('Internet/Rede',3),
        ('E-mail',4),('Impressora',5),('Suporte Geral',6)");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'criar_categoria') {
    $nome_cat = sanitize($_POST['nome_cat'] ?? '');
    if (!empty($nome_cat)) {
        $stmt = $conn->prepare("INSERT INTO suporte_categorias (nome) VALUES (?)");
        $stmt->bind_param("s", $nome_cat);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Criou categoria de suporte: $nome_cat");
    }
    header('Location: suporte_gerenciar.php?cat=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'editar_categoria') {
    $id_cat  = intval($_POST['cat_id'] ?? 0);
    $nome_cat = sanitize($_POST['nome_cat'] ?? '');
    if ($id_cat > 0 && !empty($nome_cat)) {
        $stmt = $conn->prepare("UPDATE suporte_categorias SET nome = ? WHERE id = ?");
        $stmt->bind_param("si", $nome_cat, $id_cat);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Editou categoria de suporte ID $id_cat → $nome_cat");
    }
    header('Location: suporte_gerenciar.php?cat=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_categoria') {
    $id_cat = intval($_POST['cat_id'] ?? 0);
    if ($id_cat > 0) {
        $stmt = $conn->prepare("DELETE FROM suporte_categorias WHERE id = ?");
        $stmt->bind_param("i", $id_cat);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Excluiu categoria de suporte ID $id_cat");
    }
    header('Location: suporte_gerenciar.php?cat=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'toggle_categoria') {
    $id_cat     = intval($_POST['cat_id'] ?? 0);
    $ativo_atual = intval($_POST['ativo_atual'] ?? 1);
    $novo       = $ativo_atual == 1 ? 0 : 1;
    if ($id_cat > 0) $conn->query("UPDATE suporte_categorias SET ativo = $novo WHERE id = $id_cat");
    header('Location: suporte_gerenciar.php?cat=1');
    exit;
}

$cats_res = $conn->query("SELECT * FROM suporte_categorias ORDER BY ordem, nome");
$categorias_lista = [];
while ($c = $cats_res->fetch_assoc()) $categorias_lista[] = $c;

// Filtro por status via GET
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_sql = $filtro_status ? "WHERE c.status = '$filtro_status'" : '';

// Paginação
$por_pagina      = 15;
$pagina_atual    = max(1, intval($_GET['page'] ?? 1));
$sql_count       = "SELECT COUNT(*) as total FROM chamados c JOIN usuarios u ON c.usuario_id = u.id LEFT JOIN setores s ON u.setor_id = s.id LEFT JOIN usuarios t ON c.tecnico_id = t.id $where_sql";
$total_registros = (int) $conn->query($sql_count)->fetch_assoc()['total'];
$total_paginas   = max(1, (int) ceil($total_registros / $por_pagina));
$pagina_atual    = min($pagina_atual, $total_paginas);
$offset          = ($pagina_atual - 1) * $por_pagina;

// Buscar chamados da página atual com detalhes
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante,
               c.satisfacao_nota, c.satisfacao_comentario
        FROM chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN setores s ON u.setor_id = s.id
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        $where_sql
        ORDER BY 
            CASE 
                WHEN c.status = 'Aberto' THEN 1 
                WHEN c.status = 'Em Atendimento' THEN 2 
                WHEN c.status = 'Aguardando Peça' THEN 3 
                ELSE 4 
            END, 
            c.prioridade DESC, 
            c.data_abertura ASC
        LIMIT $por_pagina OFFSET $offset";
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

// Dados para o hash inicial do poll (todos os chamados, não só a página atual)
$poll_hash_res = $conn->query("SELECT c.id, c.status,
    (SELECT COUNT(*) FROM chamados_comentarios cc WHERE cc.chamado_id = c.id AND cc.lido_pelo_tecnico = 0) as nao_lidos
    FROM chamados c $where_sql ORDER BY c.data_abertura DESC");
$poll_hash_data = [];
while ($ph = $poll_hash_res->fetch_assoc()) $poll_hash_data[] = $ph;

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

// Contagens por status para os cards de filtro
$contagens = ['Todos' => 0, 'Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0];
$res_cont = $conn->query("SELECT status, COUNT(*) as total FROM chamados GROUP BY status");
if ($res_cont) {
    while ($rc = $res_cont->fetch_assoc()) {
        if (isset($contagens[$rc['status']])) $contagens[$rc['status']] = $rc['total'];
        $contagens['Todos'] += $rc['total'];
    }
}

$cards_suporte = [
    ['status' => '',                'label' => 'Todos',           'count' => $contagens['Todos'],              'icon' => 'layout-list',   'color' => 'border-gray-400',    'bg' => 'bg-gray-50',    'text' => 'text-gray-600'],
    ['status' => 'Aberto',          'label' => 'Aberto',          'count' => $contagens['Aberto'],             'icon' => 'inbox',         'color' => 'border-blue-400',    'bg' => 'bg-blue-50',    'text' => 'text-blue-600'],
    ['status' => 'Em Atendimento',  'label' => 'Em Atendimento',  'count' => $contagens['Em Atendimento'],     'icon' => 'wrench',        'color' => 'border-amber-400',   'bg' => 'bg-amber-50',   'text' => 'text-amber-600'],
    ['status' => 'Aguardando Peça', 'label' => 'Aguardando Peça', 'count' => $contagens['Aguardando Peça'],    'icon' => 'package',       'color' => 'border-purple-400',  'bg' => 'bg-purple-50',  'text' => 'text-purple-600'],
    ['status' => 'Resolvido',       'label' => 'Resolvido',       'count' => $contagens['Resolvido'],          'icon' => 'check-circle',  'color' => 'border-emerald-400', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
];

// Ranking de chamados resolvidos por técnico
$ranking_tecnicos = [];
$res_rank = $conn->query("
    SELECT u.nome, COUNT(*) as total_resolvidos,
           SUM(CASE WHEN c.satisfacao_nota IS NOT NULL THEN c.satisfacao_nota ELSE 0 END) as soma_notas,
           SUM(CASE WHEN c.satisfacao_nota IS NOT NULL THEN 1 ELSE 0 END) as total_avaliados
    FROM chamados c
    JOIN usuarios u ON c.tecnico_id = u.id
    WHERE c.status = 'Resolvido' AND c.tecnico_id IS NOT NULL
    GROUP BY c.tecnico_id, u.nome
    ORDER BY total_resolvidos DESC
    LIMIT 10
");
if ($res_rank) {
    while ($rr = $res_rank->fetch_assoc()) {
        $rr['media_nota'] = $rr['total_avaliados'] > 0
            ? round($rr['soma_notas'] / $rr['total_avaliados'], 1)
            : null;
        $ranking_tecnicos[] = $rr;
    }
}
$max_resolvidos = !empty($ranking_tecnicos) ? $ranking_tecnicos[0]['total_resolvidos'] : 1;
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
                    Gerenciamento de Chamados
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão técnica e operacional de TI</p>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Indicador Ao Vivo -->
                <div class="flex items-center gap-1.5 px-2 py-1 bg-white border border-border rounded-lg shadow-sm">
                    <span id="suporte-poll-status" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest">Ao Vivo</span>
                </div>
                <button onclick="abrirModalCategorias()" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-primary rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="tag" class="w-3.5 h-3.5 text-violet-500"></i>
                    Categorias
                </button>
                <a href="email_chamados.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="mail" class="w-3.5 h-3.5 text-blue-500"></i>
                    E-mail → Chamado
                </a>
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

        <!-- Cards de Filtro por Status -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <?php foreach ($cards_suporte as $card): ?>
            <?php $ativo = ($filtro_status === $card['status']); ?>
            <a href="?status=<?php echo urlencode($card['status']); ?>" 
               class="bg-white p-4 rounded-xl shadow-sm border-2 flex items-center gap-3 transition-all hover:shadow-md <?php echo $ativo ? $card['color'] . ' shadow-md' : 'border-border hover:border-gray-300'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center <?php echo $card['text']; ?> shrink-0">
                    <i data-lucide="<?php echo $card['icon']; ?>" class="w-4 h-4"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-black <?php echo $ativo ? $card['text'] : 'text-text'; ?> leading-none"><?php echo $card['count']; ?></h3>
                    <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider truncate mt-0.5"><?php echo $card['label']; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($mensagem): ?>
            <div id="suporte-msg" class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 transition-opacity duration-500">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-tighter"><?php echo $mensagem; ?></span>
            </div>
            <script>setTimeout(function(){var m=document.getElementById('suporte-msg');if(m){m.style.opacity='0';setTimeout(function(){m.remove();},500);}},4000);</script>
        <?php endif; ?>

        <?php if (!empty($ranking_tecnicos)): ?>
        <!-- Ranking Técnicos -->
        <div class="bg-white rounded-xl shadow-sm border border-border p-4 mb-6">
            <div class="flex items-center gap-2 mb-4">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-emerald-500"></i>
                <h3 class="text-xs font-black text-text uppercase tracking-widest">Chamados Resolvidos por Técnico</h3>
            </div>
            <div class="space-y-2.5">
                <?php foreach ($ranking_tecnicos as $i => $tec): ?>
                <?php $pct = $max_resolvidos > 0 ? round(($tec['total_resolvidos'] / $max_resolvidos) * 100) : 0; ?>
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-black text-text-secondary w-4 text-right shrink-0"><?php echo $i + 1; ?></span>
                    <span class="text-xs font-bold text-text truncate w-36 shrink-0"><?php echo htmlspecialchars($tec['nome']); ?></span>
                    <div class="flex-1 bg-background rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full bg-emerald-400 transition-all" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <span class="text-xs font-black text-emerald-600 w-6 text-right shrink-0"><?php echo $tec['total_resolvidos']; ?></span>
                    <?php if ($tec['media_nota'] !== null): ?>
                    <span class="text-[10px] font-bold text-amber-500 shrink-0 flex items-center gap-0.5">
                        <i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i>
                        <?php echo $tec['media_nota']; ?>
                    </span>
                    <?php else: ?>
                    <span class="w-10 shrink-0"></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
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
                    <tbody id="suporte-tbody" class="divide-y divide-border text-xs">
                        <?php foreach ($chamados_lista as $chamado): ?>
                        <tr data-id="<?php echo $chamado['id']; ?>" data-status="<?php echo htmlspecialchars($chamado['status']); ?>" data-unread="<?php echo $chamado['tem_novidade'] ? '1' : '0'; ?>" onclick='abrirAtendimento(<?php echo htmlspecialchars(json_encode($chamado, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)' class="hover:bg-background/30 transition-colors group cursor-pointer <?php echo in_array($chamado['status'], ['Resolvido', 'Cancelado']) ? 'opacity-40' : ''; ?>">
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

            <?php if ($total_paginas > 1): ?>
            <div class="px-4 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-[10px] text-text-secondary font-bold">
                    Exibindo <?php echo $offset + 1; ?>–<?php echo min($offset + $por_pagina, $total_registros); ?> de <strong><?php echo $total_registros; ?></strong> chamados
                </p>
                <nav class="flex items-center gap-1">
                    <a href="?status=<?php echo urlencode($filtro_status); ?>&page=<?php echo $pagina_atual - 1; ?>"
                       class="px-2.5 py-1.5 rounded-lg border border-border text-[10px] font-black text-text-secondary transition-all hover:bg-background hover:text-primary <?php echo $pagina_atual <= 1 ? 'pointer-events-none opacity-30' : ''; ?>">
                        <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                    </a>

                    <?php
                    $janela = 2;
                    for ($p = 1; $p <= $total_paginas; $p++):
                        if ($p == 1 || $p == $total_paginas || ($p >= $pagina_atual - $janela && $p <= $pagina_atual + $janela)):
                    ?>
                    <a href="?status=<?php echo urlencode($filtro_status); ?>&page=<?php echo $p; ?>"
                       class="px-3 py-1.5 rounded-lg border text-[10px] font-black transition-all <?php echo $p == $pagina_atual ? 'bg-primary text-white border-primary shadow-sm' : 'border-border text-text-secondary hover:bg-background hover:text-primary'; ?>">
                        <?php echo $p; ?>
                    </a>
                    <?php
                        elseif ($p == $pagina_atual - $janela - 1 || $p == $pagina_atual + $janela + 1):
                            echo '<span class="px-1 text-[10px] text-text-secondary/50">…</span>';
                        endif;
                    endfor;
                    ?>

                    <a href="?status=<?php echo urlencode($filtro_status); ?>&page=<?php echo $pagina_atual + 1; ?>"
                       class="px-2.5 py-1.5 rounded-lg border border-border text-[10px] font-black text-text-secondary transition-all hover:bg-background hover:text-primary <?php echo $pagina_atual >= $total_paginas ? 'pointer-events-none opacity-30' : ''; ?>">
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modal Categorias de Suporte -->
    <div id="modalCategorias" class="modal <?php echo isset($_GET['cat']) ? 'active' : ''; ?>">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150 flex flex-col max-h-[90vh]">
            <div class="bg-violet-600 px-5 py-4 text-white flex justify-between items-center shrink-0">
                <div>
                    <h2 class="text-base font-bold flex items-center gap-2">
                        <i data-lucide="tag" class="w-4 h-4"></i> Categorias de Suporte
                    </h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Gerencie as opções disponíveis</p>
                </div>
                <button onclick="fecharModalCategorias()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-grow">
                <div class="p-4 space-y-2">
                    <?php if (empty($categorias_lista)): ?>
                    <p class="text-center text-xs text-text-secondary py-6 italic">Nenhuma categoria cadastrada.</p>
                    <?php endif; ?>
                    <?php foreach ($categorias_lista as $cat): ?>
                    <div class="flex items-center justify-between p-3 bg-background rounded-xl border border-border <?php echo !$cat['ativo'] ? 'opacity-50' : ''; ?>">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-violet-500/10 flex items-center justify-center shrink-0">
                                <i data-lucide="tag" class="w-3.5 h-3.5 text-violet-500"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-text"><?php echo htmlspecialchars($cat['nome']); ?></span>
                                <?php if (!$cat['ativo']): ?>
                                    <span class="ml-1 text-[8px] font-black text-red-400 uppercase tracking-wider">(inativa)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <form method="POST" class="inline">
                                <input type="hidden" name="acao" value="toggle_categoria">
                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                <input type="hidden" name="ativo_atual" value="<?php echo $cat['ativo']; ?>">
                                <button type="submit" title="<?php echo $cat['ativo'] ? 'Desativar' : 'Ativar'; ?>" class="p-1.5 rounded-lg transition-all <?php echo $cat['ativo'] ? 'text-amber-500 hover:bg-amber-50' : 'text-emerald-500 hover:bg-emerald-50'; ?>">
                                    <i data-lucide="<?php echo $cat['ativo'] ? 'eye-off' : 'eye'; ?>" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            <button onclick="abrirEdicaoCategoria(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['nome'])); ?>')"
                                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition-all">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Excluir a categoria \"<?php echo htmlspecialchars(addslashes($cat['nome'])); ?>\"?')">
                                <input type="hidden" name="acao" value="excluir_categoria">
                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition-all">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulário de edição inline -->
                <div id="formEdicaoCat" class="hidden px-4 pb-3">
                    <form method="POST" class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-2">
                        <input type="hidden" name="acao" value="editar_categoria">
                        <input type="hidden" name="cat_id" id="editCatId">
                        <label class="block text-[10px] font-black text-blue-700 uppercase tracking-widest">Editando</label>
                        <div class="flex gap-2">
                            <input type="text" name="nome_cat" id="editCatNome" required
                                   class="flex-grow p-2 bg-white border border-blue-200 rounded-lg text-xs font-bold focus:outline-none focus:border-blue-400">
                            <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-[10px] font-black uppercase hover:bg-blue-700 transition-all shrink-0">Salvar</button>
                            <button type="button" onclick="cancelarEdicaoCategoria()" class="px-3 py-2 bg-white border border-border text-text-secondary rounded-lg text-[10px] font-black uppercase hover:bg-gray-50 transition-all shrink-0">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Adicionar nova categoria -->
            <div class="p-4 border-t border-border bg-white shrink-0">
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="acao" value="criar_categoria">
                    <input type="text" name="nome_cat" placeholder="Nome da nova categoria..." required
                           class="flex-grow p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    <button type="submit" class="px-4 py-2.5 bg-primary text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-hover transition-all active:scale-95 flex items-center gap-1.5 shrink-0">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Adicionar
                    </button>
                </form>
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
                                <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest">Prioridade</label>
                                <select name="prioridade" id="form_prioridade" class="w-full p-2.5 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                                    <option value="Baixa">Baixa</option>
                                    <option value="Média">Média</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Urgente">Urgente 🔥</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                            <select name="categoria" id="form_categoria" class="w-full p-2.5 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                                <?php foreach ($categorias_lista as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['nome']); ?>"><?php echo htmlspecialchars($cat['nome']); ?><?php echo !$cat['ativo'] ? ' (inativa)' : ''; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
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
            document.getElementById('form_prioridade').value = chamado.prioridade;
            document.getElementById('form_tecnico').value = chamado.tecnico_id || '';
            document.getElementById('form_categoria').value = chamado.categoria || '';
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

        // ── Auto-refresh: detecta qualquer mudança nos chamados ─────────────
        (function () {
            const INTERVAL  = 30000;
            const STATUS_EL = document.getElementById('suporte-poll-status');

            function stateHash(list) {
                return list.map(c => c.id + ':' + c.status + ':' + c.nao_lidos).sort().join('|');
            }

            let lastHash = stateHash(<?php echo json_encode($poll_hash_data); ?>);

            function showToast(msg, autoReload) {
                const old = document.getElementById('suporte-toast');
                if (old) old.remove();
                const t = document.createElement('div');
                t.id = 'suporte-toast';
                t.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;display:flex;align-items:center;gap:10px;background:#2563eb;color:#fff;padding:12px 18px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.3);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;cursor:pointer;';
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
                    const res = await fetch('suporte_gerenciar.php?action=poll' + (_pollStatus ? '&status=' + encodeURIComponent(_pollStatus) : ''), { cache: 'no-store' });
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
                        const modalOpen = document.getElementById('modalAtender').classList.contains('active');
                        if (modalOpen) {
                            showToast('Chamados atualizados — feche o modal para ver', false);
                        } else {
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

        function abrirModalCategorias() {
            document.getElementById('modalCategorias').classList.add('active');
        }
        function fecharModalCategorias() {
            document.getElementById('modalCategorias').classList.remove('active');
            const url = new URL(window.location);
            url.searchParams.delete('cat');
            window.history.replaceState({}, '', url);
        }
        function abrirEdicaoCategoria(id, nome) {
            document.getElementById('editCatId').value = id;
            document.getElementById('editCatNome').value = nome;
            document.getElementById('formEdicaoCat').classList.remove('hidden');
            document.getElementById('editCatNome').focus();
        }
        function cancelarEdicaoCategoria() {
            document.getElementById('formEdicaoCat').classList.add('hidden');
        }
        // ────────────────────────────────────────────────────────────────────
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
