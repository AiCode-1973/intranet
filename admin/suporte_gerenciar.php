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
        // Processar anexos enviados pelo técnico
        if (isset($_FILES['anexo_tecnico']) && $_FILES['anexo_tecnico']['error'] === UPLOAD_ERR_OK) {
            $diretorio_anexos = '../uploads/suporte/';
            if (!is_dir($diretorio_anexos)) {
                mkdir($diretorio_anexos, 0777, true);
            }
            $nome_original = basename($_FILES['anexo_tecnico']['name']);
            $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
            if (in_array($extensao, $extensoes_permitidas) && $_FILES['anexo_tecnico']['size'] <= 10485760) {
                $nome_arquivo = 'tec_' . $id . '_' . time() . '.' . $extensao;
                $caminho_final = $diretorio_anexos . $nome_arquivo;
                if (move_uploaded_file($_FILES['anexo_tecnico']['tmp_name'], $caminho_final)) {
                    $caminho_db = 'uploads/suporte/' . $nome_arquivo;
                    $tipo_arquivo = $_FILES['anexo_tecnico']['type'];
                    $stmt_a = $conn->prepare("INSERT INTO chamados_anexos (chamado_id, caminho_arquivo, nome_original, tipo_arquivo) VALUES (?, ?, ?, ?)");
                    $stmt_a->bind_param("isss", $id, $caminho_db, $nome_original, $tipo_arquivo);
                    $stmt_a->execute();
                    $stmt_a->close();
                }
            }
        }
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

// Excluir Anexo (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_anexo') {
    header('Content-Type: application/json');
    $anexo_id = intval($_POST['anexo_id']);
    $res = $conn->query("SELECT caminho_arquivo FROM chamados_anexos WHERE id = $anexo_id");
    if ($res && $row_a = $res->fetch_assoc()) {
        $caminho = '../' . $row_a['caminho_arquivo'];
        if (file_exists($caminho)) @unlink($caminho);
        $conn->query("DELETE FROM chamados_anexos WHERE id = $anexo_id");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'erro' => 'Anexo não encontrado']);
    }
    exit;
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
    $conds_poll = [];
    if (!empty($_GET['status'])) $conds_poll[] = "c.status = '" . $conn->real_escape_string($_GET['status']) . "'";
    if (!empty($_GET['busca'])) {
        $b_poll = $conn->real_escape_string(trim($_GET['busca']));
        $conds_poll[] = "(c.titulo LIKE '%$b_poll%' OR c.descricao LIKE '%$b_poll%' OR u.nome LIKE '%$b_poll%' OR CAST(c.id AS CHAR) LIKE '%$b_poll%' OR c.categoria LIKE '%$b_poll%')";
    }
    $poll_where = $conds_poll ? 'WHERE ' . implode(' AND ', $conds_poll) : '';
    $rows = $conn->query("SELECT c.id, c.status,
        (SELECT COUNT(*) FROM chamados_comentarios cc WHERE cc.chamado_id = c.id AND cc.lido_pelo_tecnico = 0) as nao_lidos
        FROM chamados c JOIN usuarios u ON c.usuario_id = u.id $poll_where ORDER BY c.data_abertura DESC");
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

// ── GERENCIAR MENSAGENS RÁPIDAS ──────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS suporte_msgs_rapidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(120) NOT NULL,
    texto TEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ((int)$conn->query("SELECT COUNT(*) FROM suporte_msgs_rapidas")->fetch_row()[0] === 0) {
    $conn->query("INSERT INTO suporte_msgs_rapidas (titulo, texto, ordem) VALUES
        ('Sem solução no momento', 'Chamado verificado. No momento não foi possível resolver o problema. Aguardando mais informações ou peça necessária.', 1),
        ('Problema resolvido remotamente', 'Problema identificado e resolvido remotamente via acesso ao equipamento do usuário. Sistema normalizado.', 2),
        ('Equipamento encaminhado para manutenção', 'Equipamento retirado para manutenção na bancada técnica. Usuário notificado sobre prazo estimado de retorno.', 3),
        ('Reinstalação de software realizada', 'Software desinstalado e reinstalado corretamente. Configurações básicas aplicadas e testadas com o usuário.', 4),
        ('Aguardando retorno do fornecedor', 'Chamado encaminhado ao fornecedor/fabricante para suporte especializado. Aguardando retorno com prazo de solução.', 5),
        ('Orientação ao usuário realizada', 'Usuário orientado sobre o correto uso do recurso/equipamento. Dúvida esclarecida e situação normalizada.', 6)");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'criar_msg_rapida') {
    $titulo_mr = sanitize($_POST['titulo_mr'] ?? '');
    $texto_mr  = $_POST['texto_mr'] ?? '';
    if (!empty($titulo_mr) && !empty($texto_mr)) {
        $stmt = $conn->prepare("INSERT INTO suporte_msgs_rapidas (titulo, texto) VALUES (?, ?)");
        $stmt->bind_param("ss", $titulo_mr, $texto_mr);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Criou mensagem rápida: $titulo_mr");
    }
    header('Location: suporte_gerenciar.php?mr=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'editar_msg_rapida') {
    $id_mr    = intval($_POST['mr_id'] ?? 0);
    $titulo_mr = sanitize($_POST['titulo_mr'] ?? '');
    $texto_mr  = $_POST['texto_mr'] ?? '';
    if ($id_mr > 0 && !empty($titulo_mr) && !empty($texto_mr)) {
        $stmt = $conn->prepare("UPDATE suporte_msgs_rapidas SET titulo = ?, texto = ? WHERE id = ?");
        $stmt->bind_param("ssi", $titulo_mr, $texto_mr, $id_mr);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Editou mensagem rápida ID $id_mr");
    }
    header('Location: suporte_gerenciar.php?mr=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_msg_rapida') {
    $id_mr = intval($_POST['mr_id'] ?? 0);
    if ($id_mr > 0) {
        $stmt = $conn->prepare("DELETE FROM suporte_msgs_rapidas WHERE id = ?");
        $stmt->bind_param("i", $id_mr);
        $stmt->execute();
        $stmt->close();
        registrarLog($conn, "Excluiu mensagem rápida ID $id_mr");
    }
    header('Location: suporte_gerenciar.php?mr=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'toggle_msg_rapida') {
    $id_mr      = intval($_POST['mr_id'] ?? 0);
    $ativo_atual = intval($_POST['ativo_atual'] ?? 1);
    $novo       = $ativo_atual == 1 ? 0 : 1;
    if ($id_mr > 0) $conn->query("UPDATE suporte_msgs_rapidas SET ativo = $novo WHERE id = $id_mr");
    header('Location: suporte_gerenciar.php?mr=1');
    exit;
}

// AJAX: retorna mensagens rápidas ativas em JSON (para uso no modal de atendimento)
if (isset($_GET['action']) && $_GET['action'] === 'get_msgs_rapidas') {
    header('Content-Type: application/json');
    $rows = $conn->query("SELECT id, titulo, texto FROM suporte_msgs_rapidas WHERE ativo = 1 ORDER BY ordem, titulo");
    $msgs = [];
    while ($r = $rows->fetch_assoc()) $msgs[] = $r;
    echo json_encode($msgs);
    exit;
}

$msgs_rapidas_res = $conn->query("SELECT * FROM suporte_msgs_rapidas ORDER BY ordem, titulo");
$msgs_rapidas_lista = [];
while ($m = $msgs_rapidas_res->fetch_assoc()) $msgs_rapidas_lista[] = $m;

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
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$busca_sql = '';
if ($busca !== '') {
    $b = $conn->real_escape_string($busca);
    $busca_sql = "(c.titulo LIKE '%$b%' OR c.descricao LIKE '%$b%' OR u.nome LIKE '%$b%' OR CAST(c.id AS CHAR) LIKE '%$b%' OR c.categoria LIKE '%$b%')";
}

$conds = [];
if ($filtro_status) $conds[] = "c.status = '" . $conn->real_escape_string($filtro_status) . "'";
if ($busca_sql) $conds[] = $busca_sql;
$where_sql = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

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
               c.satisfacao_nota, c.satisfacao_comentario,
               (SELECT GROUP_CONCAT(tf.ramal ORDER BY tf.ordem SEPARATOR ', ')
                FROM telefones tf
                WHERE tf.setor_id = u.setor_id AND tf.ramal IS NOT NULL AND tf.ramal != '') as ramais_setor
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
    FROM chamados c JOIN usuarios u ON c.usuario_id = u.id LEFT JOIN setores s ON u.setor_id = s.id
    $where_sql ORDER BY c.data_abertura DESC");
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

// ── Estatísticas para o Dashboard ────────────────────────────────────────────
// Por prioridade
$stats_prioridade = [];
$res_pri = $conn->query("SELECT prioridade, COUNT(*) as total FROM chamados WHERE prioridade IS NOT NULL AND prioridade != '' GROUP BY prioridade ORDER BY FIELD(prioridade,'Urgente','Alta','Média','Baixa')");
if ($res_pri) while ($r = $res_pri->fetch_assoc()) $stats_prioridade[] = $r;

// Por categoria (top 8)
$stats_categoria = [];
$res_cat = $conn->query("SELECT COALESCE(categoria,'Sem categoria') as categoria, COUNT(*) as total FROM chamados GROUP BY categoria ORDER BY total DESC LIMIT 8");
if ($res_cat) while ($r = $res_cat->fetch_assoc()) $stats_categoria[] = $r;

// Evolução mensal — últimos 6 meses
$stats_mensal = [];
$res_men = $conn->query("SELECT DATE_FORMAT(data_abertura,'%b/%y') as mes, COUNT(*) as abertos,
    SUM(CASE WHEN status='Resolvido' THEN 1 ELSE 0 END) as resolvidos
    FROM chamados WHERE data_abertura >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_abertura,'%Y-%m') ORDER BY DATE_FORMAT(data_abertura,'%Y-%m') ASC");
if ($res_men) while ($r = $res_men->fetch_assoc()) $stats_mensal[] = $r;

// Tempo médio de resolução (horas)
$tempo_medio_h = null;
$res_tm = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, data_abertura, data_fechamento)) as media FROM chamados WHERE status='Resolvido' AND data_fechamento IS NOT NULL");
if ($res_tm) { $tm = $res_tm->fetch_assoc(); $tempo_medio_h = $tm['media'] !== null ? round($tm['media'], 1) : null; }

// Satisfação média
$satisfacao_media = null; $satisfacao_total = 0;
$res_sat = $conn->query("SELECT AVG(satisfacao_nota) as media, COUNT(*) as total FROM chamados WHERE satisfacao_nota IS NOT NULL");
if ($res_sat) { $sat = $res_sat->fetch_assoc(); $satisfacao_media = $sat['media'] !== null ? round($sat['media'], 1) : null; $satisfacao_total = intval($sat['total']); }

// Chamados sem técnico atribuído (abertos/em atendimento)
$sem_tecnico = 0;
$res_st = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE tecnico_id IS NULL AND status IN ('Aberto','Em Atendimento')");
if ($res_st) $sem_tecnico = intval($res_st->fetch_assoc()['total']);

// Máximos para barras
$max_pri = !empty($stats_prioridade) ? max(array_column($stats_prioridade, 'total')) : 1;
$max_cat = !empty($stats_categoria)  ? max(array_column($stats_categoria,  'total')) : 1;
$max_men = !empty($stats_mensal)     ? max(array_column($stats_mensal,     'abertos')) : 1;
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
                <button onclick="abrirModalMsgsRapidas()" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-primary rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500"></i>
                    Msgs. Rápidas
                </button>
                <button onclick="abrirDashboard()" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-primary rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="bar-chart-2" class="w-3.5 h-3.5 text-emerald-500"></i>
                    Dashboard
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

        <!-- Barra de Pesquisa -->
        <form method="GET" action="" class="mb-4 flex gap-2">
            <?php if ($filtro_status): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtro_status); ?>">
            <?php endif; ?>
            <div class="relative flex-grow max-w-md">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-secondary/50 pointer-events-none"></i>
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                       placeholder="Pesquisar por ID, título, solicitante, categoria..."
                       class="w-full pl-8 pr-4 py-2 bg-white border border-border rounded-lg text-xs font-bold text-text placeholder-text-secondary/40 focus:outline-none focus:border-primary transition-all shadow-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-xs font-bold shadow-md hover:bg-primary-hover transition-all active:scale-95 uppercase tracking-wider flex items-center gap-1.5">
                <i data-lucide="search" class="w-3.5 h-3.5"></i> Buscar
            </button>
            <?php if ($busca !== ''): ?>
            <a href="?status=<?php echo urlencode($filtro_status); ?>" class="px-3 py-2 bg-white border border-border rounded-lg text-xs font-bold text-text-secondary hover:text-rose-500 hover:border-rose-300 transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="x" class="w-3.5 h-3.5"></i> Limpar
            </a>
            <?php endif; ?>
        </form>

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
                    <a href="?status=<?php echo urlencode($filtro_status); ?>&busca=<?php echo urlencode($busca); ?>&page=<?php echo $pagina_atual - 1; ?>"
                       class="px-2.5 py-1.5 rounded-lg border border-border text-[10px] font-black text-text-secondary transition-all hover:bg-background hover:text-primary <?php echo $pagina_atual <= 1 ? 'pointer-events-none opacity-30' : ''; ?>">
                        <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                    </a>

                    <?php
                    $janela = 2;
                    for ($p = 1; $p <= $total_paginas; $p++):
                        if ($p == 1 || $p == $total_paginas || ($p >= $pagina_atual - $janela && $p <= $pagina_atual + $janela)):
                    ?>
                    <a href="?status=<?php echo urlencode($filtro_status); ?>&busca=<?php echo urlencode($busca); ?>&page=<?php echo $p; ?>"
                       class="px-3 py-1.5 rounded-lg border text-[10px] font-black transition-all <?php echo $p == $pagina_atual ? 'bg-primary text-white border-primary shadow-sm' : 'border-border text-text-secondary hover:bg-background hover:text-primary'; ?>">
                        <?php echo $p; ?>
                    </a>
                    <?php
                        elseif ($p == $pagina_atual - $janela - 1 || $p == $pagina_atual + $janela + 1):
                            echo '<span class="px-1 text-[10px] text-text-secondary/50">…</span>';
                        endif;
                    endfor;
                    ?>

                    <a href="?status=<?php echo urlencode($filtro_status); ?>&busca=<?php echo urlencode($busca); ?>&page=<?php echo $pagina_atual + 1; ?>"
                       class="px-2.5 py-1.5 rounded-lg border border-border text-[10px] font-black text-text-secondary transition-all hover:bg-background hover:text-primary <?php echo $pagina_atual >= $total_paginas ? 'pointer-events-none opacity-30' : ''; ?>">
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modal Dashboard de Estatísticas -->
    <div id="modalDashboard" class="modal">
        <div class="bg-[#0d1117] rounded-2xl shadow-2xl flex flex-col border border-white/10 overflow-hidden" style="width:96vw;max-width:1280px;max-height:92vh">

            <!-- Accent bar -->
            <div class="h-1 w-full bg-gradient-to-r from-emerald-500 via-blue-500 to-violet-500 shrink-0"></div>

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/8 shrink-0">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 text-emerald-400"></i>
                        <h2 class="text-sm font-black text-white uppercase tracking-widest">Dashboard de Chamados</h2>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium ml-6">Atualizado em <?php echo date('d/m/Y \à\s H:i'); ?></p>
                </div>
                <button onclick="fecharDashboard()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors text-slate-500 hover:text-white">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Landscape body: left + right -->
            <div class="flex flex-row flex-1 min-h-0">

                <!-- ── LEFT PANEL ── KPIs + Status + Ranking ──────────── -->
                <div class="w-[38%] shrink-0 flex flex-col gap-4 p-5 border-r border-white/8 overflow-y-auto" style="scrollbar-width:thin;scrollbar-color:#1e2330 transparent">

                    <!-- KPI 2×2 -->
                    <?php $taxa = $contagens['Todos'] > 0 ? round(($contagens['Resolvido'] / $contagens['Todos']) * 100) : 0; ?>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-[#161b22] rounded-xl p-3.5 border border-white/8 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Total</span>
                                <div class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center">
                                    <i data-lucide="inbox" class="w-3 h-3 text-slate-400"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-white leading-none"><?php echo $contagens['Todos']; ?></h3>
                                <p class="text-[9px] text-slate-600 mt-0.5">chamados</p>
                            </div>
                        </div>

                        <div class="bg-[#161b22] rounded-xl p-3.5 border border-emerald-500/20 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black text-emerald-500/80 uppercase tracking-widest">Resolvidos</span>
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-3 h-3 text-emerald-400"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-emerald-400 leading-none"><?php echo $taxa; ?>%</h3>
                                <p class="text-[9px] text-slate-600 mt-0.5"><?php echo $contagens['Resolvido']; ?> de <?php echo $contagens['Todos']; ?></p>
                            </div>
                            <div class="h-1 bg-white/5 rounded-full overflow-hidden">
                                <div class="h-1 bg-emerald-500 rounded-full" style="width:<?php echo $taxa; ?>%"></div>
                            </div>
                        </div>

                        <div class="bg-[#161b22] rounded-xl p-3.5 border border-blue-500/20 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black text-blue-500/80 uppercase tracking-widest">Tempo Médio</span>
                                <div class="w-6 h-6 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                    <i data-lucide="clock" class="w-3 h-3 text-blue-400"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-blue-400 leading-none"><?php echo $tempo_medio_h !== null ? $tempo_medio_h : '–'; ?></h3>
                                <p class="text-[9px] text-slate-600 mt-0.5"><?php echo $tempo_medio_h !== null ? 'horas p/ resolver' : 'sem dados'; ?></p>
                            </div>
                        </div>

                        <div class="bg-[#161b22] rounded-xl p-3.5 border border-amber-500/20 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black text-amber-500/80 uppercase tracking-widest">Satisfação</span>
                                <div class="w-6 h-6 rounded-lg bg-amber-500/10 flex items-center justify-center">
                                    <i data-lucide="star" class="w-3 h-3 text-amber-400"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-amber-400 leading-none"><?php echo $satisfacao_media !== null ? $satisfacao_media : '–'; ?><?php if ($satisfacao_media !== null): ?><span class="text-base font-bold opacity-40">/5</span><?php endif; ?></h3>
                                <p class="text-[9px] text-slate-600 mt-0.5"><?php echo $satisfacao_total > 0 ? $satisfacao_total . ' avaliações' : 'sem avaliações'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Status pills -->
                    <div class="grid grid-cols-4 gap-1.5">
                        <?php
                        $pill_cfg_row = [
                            'Aberto'          => ['dot'=>'bg-blue-500',   'text'=>'text-blue-400',   'bg'=>'bg-blue-500/10',   'border'=>'border-blue-500/20'],
                            'Em Atendimento'  => ['dot'=>'bg-amber-500',  'text'=>'text-amber-400',  'bg'=>'bg-amber-500/10',  'border'=>'border-amber-500/20'],
                            'Aguardando Peça' => ['dot'=>'bg-violet-500', 'text'=>'text-violet-400', 'bg'=>'bg-violet-500/10', 'border'=>'border-violet-500/20'],
                            'Resolvido'       => ['dot'=>'bg-emerald-500','text'=>'text-emerald-400','bg'=>'bg-emerald-500/10','border'=>'border-emerald-500/20'],
                        ];
                        foreach ($pill_cfg_row as $label => $cfg):
                            $cnt = $contagens[$label] ?? 0;
                        ?>
                        <div class="flex flex-col items-center gap-1 px-2 py-2 rounded-lg border <?php echo $cfg['bg'].' '.$cfg['border']; ?>">
                            <span class="text-lg font-black text-white leading-none"><?php echo $cnt; ?></span>
                            <span class="w-1.5 h-1.5 rounded-full <?php echo $cfg['dot']; ?>"></span>
                            <span class="text-[9px] font-bold <?php echo $cfg['text']; ?> text-center leading-tight"><?php echo $label; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($contagens['Cancelado'] ?? 0) > 0 || $sem_tecnico > 0): ?>
                    <div class="flex flex-wrap gap-1.5">
                        <?php if (($contagens['Cancelado'] ?? 0) > 0): ?>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border bg-slate-500/10 border-slate-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500 shrink-0"></span>
                            <span class="text-[10px] font-bold text-slate-400">Cancelado</span>
                            <span class="text-[10px] font-black text-white ml-0.5"><?php echo $contagens['Cancelado']; ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($sem_tecnico > 0): ?>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border bg-rose-500/10 border-rose-500/30">
                            <i data-lucide="alert-triangle" class="w-3 h-3 text-rose-400 shrink-0"></i>
                            <span class="text-[10px] font-bold text-rose-400"><?php echo $sem_tecnico; ?> sem técnico</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Ranking -->
                    <?php if (!empty($ranking_tecnicos)): ?>
                    <div class="bg-[#161b22] border border-white/8 rounded-xl p-4 flex-1">
                        <div class="flex items-center gap-1.5 mb-3">
                            <i data-lucide="trophy" class="w-3.5 h-3.5 text-slate-500"></i>
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Ranking de Resoluções</p>
                        </div>
                        <div class="space-y-2.5">
                            <?php
                            $medals = ['🥇','🥈','🥉'];
                            foreach ($ranking_tecnicos as $i => $tec):
                                $pct = $max_resolvidos > 0 ? round(($tec['total_resolvidos'] / $max_resolvidos) * 100) : 0;
                            ?>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm leading-none shrink-0 w-5"><?php echo isset($medals[$i]) ? $medals[$i] : '<span class="text-[10px] font-black text-slate-600">'.($i+1).'</span>'; ?></span>
                                        <span class="text-xs font-bold text-slate-300 truncate max-w-[130px]"><?php echo htmlspecialchars($tec['nome']); ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <?php if ($tec['media_nota'] !== null): ?>
                                        <span class="flex items-center gap-0.5 text-[10px] font-bold text-amber-400">
                                            <i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i><?php echo $tec['media_nota']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="text-xs font-black text-emerald-400"><?php echo $tec['total_resolvidos']; ?></span>
                                    </div>
                                </div>
                                <div class="h-1.5 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-1.5 rounded-full <?php echo $i === 0 ? 'bg-gradient-to-r from-amber-500 to-emerald-400' : ($i === 1 ? 'bg-emerald-500/80' : 'bg-emerald-500/50'); ?>" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end left panel -->

                <!-- ── RIGHT PANEL ── Prioridade + Categoria + Mensal ──── -->
                <div class="flex-1 flex flex-col gap-4 p-5 overflow-y-auto" style="scrollbar-width:thin;scrollbar-color:#1e2330 transparent">

                    <div class="grid grid-cols-2 gap-4">

                        <!-- Por Prioridade -->
                        <?php if (!empty($stats_prioridade)): ?>
                        <div class="bg-[#161b22] border border-white/8 rounded-xl p-4">
                            <div class="flex items-center gap-1.5 mb-3">
                                <i data-lucide="alert-circle" class="w-3.5 h-3.5 text-slate-500"></i>
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Por Prioridade</p>
                            </div>
                            <?php
                            $pri_bar  = ['Urgente'=>'bg-gradient-to-r from-rose-600 to-rose-400','Alta'=>'bg-gradient-to-r from-orange-500 to-orange-300','Média'=>'bg-gradient-to-r from-blue-600 to-blue-400','Baixa'=>'bg-slate-600'];
                            $pri_text = ['Urgente'=>'text-rose-400','Alta'=>'text-orange-400','Média'=>'text-blue-400','Baixa'=>'text-slate-400'];
                            foreach ($stats_prioridade as $p):
                                $pct    = $max_pri > 0 ? round(($p['total'] / $max_pri) * 100) : 0;
                                $bar    = $pri_bar[$p['prioridade']]  ?? 'bg-slate-600';
                                $tcolor = $pri_text[$p['prioridade']] ?? 'text-slate-400';
                            ?>
                            <div class="mb-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold <?php echo $tcolor; ?>"><?php echo htmlspecialchars($p['prioridade']); ?></span>
                                    <span class="text-xs font-black text-white"><?php echo $p['total']; ?> <span class="text-slate-600 font-normal text-[10px]">(<?php echo $pct; ?>%)</span></span>
                                </div>
                                <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-2 rounded-full <?php echo $bar; ?>" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Por Categoria -->
                        <?php if (!empty($stats_categoria)): ?>
                        <div class="bg-[#161b22] border border-white/8 rounded-xl p-4">
                            <div class="flex items-center gap-1.5 mb-3">
                                <i data-lucide="tag" class="w-3.5 h-3.5 text-slate-500"></i>
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Por Categoria</p>
                            </div>
                            <?php foreach ($stats_categoria as $idx => $c):
                                $pct = $max_cat > 0 ? round(($c['total'] / $max_cat) * 100) : 0;
                                $opacities = ['opacity-100','opacity-90','opacity-80','opacity-70','opacity-60','opacity-55','opacity-50','opacity-45'];
                                $op = $opacities[$idx] ?? 'opacity-40';
                            ?>
                            <div class="mb-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold text-slate-300 truncate max-w-[55%]"><?php echo htmlspecialchars($c['categoria']); ?></span>
                                    <span class="text-xs font-black text-white"><?php echo $c['total']; ?> <span class="text-slate-600 font-normal text-[10px]">(<?php echo $pct; ?>%)</span></span>
                                </div>
                                <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-2 rounded-full bg-violet-500 <?php echo $op; ?>" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                    </div>

                    <!-- Evolução Mensal -->
                    <?php if (!empty($stats_mensal)): ?>
                    <div class="bg-[#161b22] border border-white/8 rounded-xl p-4 flex-1">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="trending-up" class="w-3.5 h-3.5 text-slate-500"></i>
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Evolução Mensal</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5 text-[10px] font-bold text-blue-400"><span class="w-2 h-2 rounded-sm bg-blue-500/70 inline-block"></span>Abertos</span>
                                <span class="flex items-center gap-1.5 text-[10px] font-bold text-emerald-400"><span class="w-2 h-2 rounded-sm bg-emerald-500/70 inline-block"></span>Resolvidos</span>
                            </div>
                        </div>
                        <?php $max_men_val = max(array_column($stats_mensal, 'abertos')); $max_men_val = $max_men_val > 0 ? $max_men_val : 1; ?>
                        <div class="relative">
                            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none" style="padding-bottom:1.6rem">
                                <?php for ($gl = 0; $gl < 4; $gl++): ?><div class="border-t border-white/5 w-full"></div><?php endfor; ?>
                            </div>
                            <div class="flex items-end gap-2 relative z-10" style="height:9rem">
                                <?php foreach ($stats_mensal as $m):
                                    $h_ab  = max(4, round(($m['abertos']   / $max_men_val) * 100));
                                    $h_res = max(0, round(($m['resolvidos'] / $max_men_val) * 100));
                                ?>
                                <div class="flex-1 flex flex-col items-center gap-1.5">
                                    <div class="w-full flex items-end gap-0.5 justify-center" style="height:7.5rem">
                                        <div class="flex-1 rounded-t-sm bg-blue-500/60 hover:bg-blue-500/90 transition-colors cursor-default" style="height:<?php echo $h_ab; ?>%" title="Abertos: <?php echo $m['abertos']; ?>"></div>
                                        <div class="flex-1 rounded-t-sm bg-emerald-500/60 hover:bg-emerald-500/90 transition-colors cursor-default" style="height:<?php echo $h_res; ?>%" title="Resolvidos: <?php echo $m['resolvidos']; ?>"></div>
                                    </div>
                                    <span class="text-[9px] font-bold text-slate-600 uppercase"><?php echo htmlspecialchars($m['mes']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end right panel -->

            </div><!-- end landscape body -->
        </div>
    </div>

    <!-- Modal Mensagens Rápidas -->
    <div id="modalMsgsRapidas" class="modal <?php echo isset($_GET['mr']) ? 'active' : ''; ?>">
        <div class="bg-white w-full max-w-2xl mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150 flex flex-col max-h-[90vh]">
            <div class="bg-amber-500 px-5 py-4 text-white flex justify-between items-center shrink-0">
                <div>
                    <h2 class="text-base font-bold flex items-center gap-2">
                        <i data-lucide="zap" class="w-4 h-4"></i> Mensagens Rápidas
                    </h2>
                    <p class="text-white/80 text-[10px] uppercase font-bold tracking-widest mt-0.5">Textos prontos para resolução / observações</p>
                </div>
                <button onclick="fecharModalMsgsRapidas()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-grow">
                <div class="p-4 space-y-2">
                    <?php if (empty($msgs_rapidas_lista)): ?>
                    <p class="text-center text-xs text-text-secondary py-6 italic">Nenhuma mensagem cadastrada.</p>
                    <?php endif; ?>
                    <?php foreach ($msgs_rapidas_lista as $mr): ?>
                    <div class="p-3 bg-background rounded-xl border border-border <?php echo !$mr['ativo'] ? 'opacity-50' : ''; ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2.5 flex-1 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-text"><?php echo htmlspecialchars($mr['titulo']); ?></span>
                                        <?php if (!$mr['ativo']): ?>
                                            <span class="text-[8px] font-black text-red-400 uppercase tracking-wider">(inativa)</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[10px] text-text-secondary mt-0.5 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($mr['texto']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="acao" value="toggle_msg_rapida">
                                    <input type="hidden" name="mr_id" value="<?php echo $mr['id']; ?>">
                                    <input type="hidden" name="ativo_atual" value="<?php echo $mr['ativo']; ?>">
                                    <button type="submit" title="<?php echo $mr['ativo'] ? 'Desativar' : 'Ativar'; ?>" class="p-1.5 rounded-lg transition-all <?php echo $mr['ativo'] ? 'text-amber-500 hover:bg-amber-50' : 'text-emerald-500 hover:bg-emerald-50'; ?>">
                                        <i data-lucide="<?php echo $mr['ativo'] ? 'eye-off' : 'eye'; ?>" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                                <button onclick="abrirEdicaoMsgRapida(<?php echo $mr['id']; ?>, '<?php echo htmlspecialchars(addslashes($mr['titulo'])); ?>', '<?php echo htmlspecialchars(addslashes($mr['texto'])); ?>')"
                                        class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition-all">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir a mensagem rápida \"<?php echo htmlspecialchars(addslashes($mr['titulo'])); ?>\"?')">
                                    <input type="hidden" name="acao" value="excluir_msg_rapida">
                                    <input type="hidden" name="mr_id" value="<?php echo $mr['id']; ?>">
                                    <button type="submit" class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulário de edição inline -->
                <div id="formEdicaoMR" class="hidden px-4 pb-3">
                    <form method="POST" class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-2">
                        <input type="hidden" name="acao" value="editar_msg_rapida">
                        <input type="hidden" name="mr_id" id="editMRId">
                        <label class="block text-[10px] font-black text-blue-700 uppercase tracking-widest">Editando mensagem</label>
                        <input type="text" name="titulo_mr" id="editMRTitulo" required placeholder="Título..."
                               class="w-full p-2 bg-white border border-blue-200 rounded-lg text-xs font-bold focus:outline-none focus:border-blue-400">
                        <textarea name="texto_mr" id="editMRTexto" required rows="3" placeholder="Texto completo..."
                                  class="w-full p-2 bg-white border border-blue-200 rounded-lg text-xs font-bold focus:outline-none focus:border-blue-400 resize-none leading-relaxed"></textarea>
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-[10px] font-black uppercase hover:bg-blue-700 transition-all">Salvar</button>
                            <button type="button" onclick="cancelarEdicaoMsgRapida()" class="px-3 py-2 bg-white border border-border text-text-secondary rounded-lg text-[10px] font-black uppercase hover:bg-gray-50 transition-all">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Adicionar nova mensagem -->
            <div class="p-4 border-t border-border bg-white shrink-0">
                <form method="POST" class="space-y-2">
                    <input type="hidden" name="acao" value="criar_msg_rapida">
                    <input type="text" name="titulo_mr" placeholder="Título da mensagem rápida..." required
                           class="w-full p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    <div class="flex gap-2">
                        <textarea name="texto_mr" placeholder="Texto completo que será inserido no campo de resolução..." required rows="2"
                                  class="flex-grow p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:outline-none focus:border-primary transition-all resize-none leading-relaxed"></textarea>
                        <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 transition-all active:scale-95 flex items-center gap-1.5 shrink-0 self-end">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Adicionar
                        </button>
                    </div>
                </form>
            </div>
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
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[9px] font-black text-text-secondary/40 uppercase tracking-widest">
                            <span id="view_solicitante">---</span>
                            <span id="view_data">---</span>
                            <span id="view_ramais" class="hidden items-center gap-1 text-blue-500/70">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.5 12a19.79 19.79 0 0 1-3-8.63A2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <span id="view_ramais_txt"></span>
                            </span>
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

                    <form method="POST" action="" enctype="multipart/form-data" class="p-5 flex flex-col flex-grow">
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
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[9px] font-black text-text-secondary uppercase tracking-widest">Resolução / Observações Técnicas</label>
                                <button type="button" onclick="toggleMsgsRapidasPicker()" id="btnMsgRapida"
                                        class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 hover:bg-amber-500/20 transition-all text-[9px] font-black uppercase tracking-wider">
                                    <i data-lucide="zap" class="w-3 h-3"></i> Msgs. Rápidas
                                </button>
                            </div>
                            <!-- Picker de mensagens rápidas -->
                            <div id="mrPickerPanel" class="hidden mb-2 bg-amber-50 border border-amber-200 rounded-xl overflow-hidden">
                                <div class="flex items-center gap-2 px-3 py-2 border-b border-amber-200 bg-amber-100">
                                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-600 shrink-0"></i>
                                    <input type="text" id="mrPickerSearch" oninput="filtrarMsgsRapidas()" placeholder="Filtrar mensagens..." 
                                           class="flex-grow bg-transparent text-xs font-bold text-amber-900 placeholder-amber-400 focus:outline-none">
                                </div>
                                <div id="mrPickerList" class="max-h-48 overflow-y-auto divide-y divide-amber-100">
                                    <p class="text-center text-xs text-amber-400 py-3 italic" id="mrPickerLoading">Carregando...</p>
                                </div>
                            </div>
                            <textarea name="resolucao" id="form_resolucao" placeholder="Documente o atendimento ou a solução aplicada..."
                                      class="flex-grow w-full p-3 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all min-h-[150px]"></textarea>
                        </div>

                        <!-- Anexo do Técnico -->
                        <div class="mt-4 shrink-0">
                            <label class="block text-[9px] font-black text-text-secondary mb-1 uppercase tracking-widest flex items-center gap-1.5">
                                <i data-lucide="paperclip" class="w-3 h-3"></i>
                                Anexar Arquivo (opcional)
                            </label>
                            <label class="flex items-center gap-2 w-full cursor-pointer border border-dashed border-border rounded-lg px-3 py-2 bg-background hover:border-primary hover:bg-primary/5 transition-all group">
                                <i data-lucide="upload" class="w-3.5 h-3.5 text-text-secondary group-hover:text-primary transition-colors shrink-0"></i>
                                <span id="anexo_tecnico_label" class="text-[10px] font-bold text-text-secondary group-hover:text-primary transition-colors truncate">Clique para selecionar...</span>
                                <input type="file" name="anexo_tecnico" id="anexo_tecnico" class="hidden"
                                       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar"
                                       onchange="document.getElementById('anexo_tecnico_label').textContent = this.files[0] ? this.files[0].name : 'Clique para selecionar...'">
                            </label>
                            <p class="text-[9px] text-text-secondary/50 mt-1">Máx. 10 MB &bull; JPG, PNG, PDF, DOC, XLS, TXT, ZIP, RAR</p>
                        </div>

                        <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-border shrink-0">
                            <button type="button" onclick="fecharModal()" class="px-4 py-2 text-[10px] font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-8 py-2 rounded-lg text-[10px] font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle ranking de técnicos
        (function () {
            const STORAGE_KEY = 'suporte_ranking_aberto';
            const body    = document.getElementById('ranking-body');
            const chevron = document.getElementById('ranking-chevron');
            if (!body) return;

            function aplicarEstado(aberto, animar) {
                if (aberto) {
                    body.style.display = '';
                    chevron.style.transform = 'rotate(0deg)';
                } else {
                    body.style.display = 'none';
                    chevron.style.transform = 'rotate(180deg)';
                }
                if (animar) chevron.style.transition = 'transform 0.2s';
            }

            // Restaurar estado salvo (padrão: aberto)
            const salvo = localStorage.getItem(STORAGE_KEY);
            aplicarEstado(salvo !== '0', false);

            window.toggleRanking = function () {
                const aberto = body.style.display === 'none';
                aplicarEstado(aberto, true);
                localStorage.setItem(STORAGE_KEY, aberto ? '1' : '0');
            };
        })();

        function abrirAtendimento(chamado) {
            // Fechar picker de mensagens rápidas se estiver aberto
            document.getElementById('mrPickerPanel').classList.add('hidden');
            document.getElementById('mrPickerSearch').value = '';
            document.getElementById('view_id').textContent = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('view_titulo').textContent = chamado.titulo;
            document.getElementById('view_descricao').textContent = chamado.descricao;
            document.getElementById('view_solicitante').textContent = 'Solicitante: ' + chamado.solicitante + ' (' + chamado.setor_solicitante + ')';
            document.getElementById('view_data').textContent = 'Aberto em: ' + chamado.data_abertura;
            const ramaisEl = document.getElementById('view_ramais');
            const ramaisTxt = document.getElementById('view_ramais_txt');
            if (chamado.ramais_setor) {
                ramaisTxt.textContent = 'Ramal: ' + chamado.ramais_setor;
                ramaisEl.classList.remove('hidden');
                ramaisEl.classList.add('flex');
            } else {
                ramaisEl.classList.add('hidden');
                ramaisEl.classList.remove('flex');
            }
            
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
                    
                    const wrapper = document.createElement('div');
                    wrapper.className = 'flex items-center gap-1 group/anexo';
                    wrapper.dataset.anexoId = anexo.id;

                    item.innerHTML = `
                        <i data-lucide="${isImg ? 'image' : 'file-text'}" class="w-3.5 h-3.5 shrink-0"></i>
                        <span class="max-w-[140px] truncate">${anexo.nome_original}</span>
                        <i data-lucide="download" class="w-3 h-3 ml-0.5 opacity-0 group-hover:opacity-60 transition-opacity shrink-0"></i>
                    `;

                    const btnDel = document.createElement('button');
                    btnDel.type = 'button';
                    btnDel.title = 'Excluir anexo';
                    btnDel.className = 'flex items-center justify-center w-5 h-5 rounded-md bg-rose-50 border border-rose-200 text-rose-400 hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all opacity-0 group-hover/anexo:opacity-100 shrink-0';
                    btnDel.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
                    btnDel.addEventListener('click', (e) => { e.preventDefault(); excluirAnexo(anexo.id, wrapper); });

                    wrapper.appendChild(item);
                    wrapper.appendChild(btnDel);
                    anexoList.appendChild(wrapper);
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
        function fecharModal() {
            document.getElementById('modalAtender').classList.remove('active');
            const inputAnexo = document.getElementById('anexo_tecnico');
            if (inputAnexo) { inputAnexo.value = ''; document.getElementById('anexo_tecnico_label').textContent = 'Clique para selecionar...'; }
        }

        function excluirAnexo(anexoId, wrapperEl) {
            if (!confirm('Excluir este anexo permanentemente?')) return;
            const fd = new FormData();
            fd.append('acao', 'excluir_anexo');
            fd.append('anexo_id', anexoId);
            fetch('suporte_gerenciar.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        wrapperEl.remove();
                        const lista = document.getElementById('view_anexos_list');
                        if (!lista.children.length) document.getElementById('container_anexos_view').classList.add('hidden');
                        lucide.createIcons();
                    } else {
                        alert('Erro ao excluir: ' + (data.erro || 'desconhecido'));
                    }
                });
        }

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
                    const params = new URLSearchParams(window.location.search);
                    const _pollStatus = params.get('status') || '';
                    const _pollBusca  = params.get('busca')  || '';
                    let pollUrl = 'suporte_gerenciar.php?action=poll';
                    if (_pollStatus) pollUrl += '&status=' + encodeURIComponent(_pollStatus);
                    if (_pollBusca)  pollUrl += '&busca='  + encodeURIComponent(_pollBusca);
                    const res = await fetch(pollUrl, { cache: 'no-store' });
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

        function abrirDashboard() {
            document.getElementById('modalDashboard').classList.add('active');
            lucide.createIcons();
        }
        function fecharDashboard() {
            document.getElementById('modalDashboard').classList.remove('active');
        }

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

        // ── Mensagens Rápidas ─────────────────────────────────────────────
        let _mrCache = null;

        function abrirModalMsgsRapidas() {
            document.getElementById('modalMsgsRapidas').classList.add('active');
            lucide.createIcons();
        }
        function fecharModalMsgsRapidas() {
            document.getElementById('modalMsgsRapidas').classList.remove('active');
            const url = new URL(window.location);
            url.searchParams.delete('mr');
            window.history.replaceState({}, '', url);
        }
        function abrirEdicaoMsgRapida(id, titulo, texto) {
            document.getElementById('editMRId').value = id;
            document.getElementById('editMRTitulo').value = titulo;
            document.getElementById('editMRTexto').value = texto;
            document.getElementById('formEdicaoMR').classList.remove('hidden');
            document.getElementById('editMRTitulo').focus();
        }
        function cancelarEdicaoMsgRapida() {
            document.getElementById('formEdicaoMR').classList.add('hidden');
        }

        // Picker dentro do modalAtender
        function toggleMsgsRapidasPicker() {
            const panel = document.getElementById('mrPickerPanel');
            const isHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !isHidden);
            if (isHidden) {
                carregarMsgsRapidasPicker();
                document.getElementById('mrPickerSearch').focus();
            }
        }

        function carregarMsgsRapidasPicker() {
            if (_mrCache !== null) { renderizarMsgsPicker(_mrCache); return; }
            fetch('suporte_gerenciar.php?action=get_msgs_rapidas')
                .then(r => r.json())
                .then(data => { _mrCache = data; renderizarMsgsPicker(data); })
                .catch(() => {
                    document.getElementById('mrPickerList').innerHTML =
                        '<p class="text-center text-xs text-red-400 py-3">Erro ao carregar mensagens.</p>';
                });
        }

        function renderizarMsgsPicker(msgs, filtro) {
            const list = document.getElementById('mrPickerList');
            const lower = (filtro || '').toLowerCase();
            const itens = msgs.reduce((acc, m, i) => {
                if (!lower || m.titulo.toLowerCase().includes(lower) || m.texto.toLowerCase().includes(lower)) {
                    acc.push({ ...m, _origIdx: i });
                }
                return acc;
            }, []);
            if (itens.length === 0) {
                list.innerHTML = '<p class="text-center text-xs text-amber-400 py-3 italic">Nenhuma mensagem encontrada.</p>';
                return;
            }
            list.innerHTML = itens.map(m => `
                <button type="button" data-mr-idx="${m._origIdx}"
                        class="mr-picker-item w-full text-left px-3 py-2.5 hover:bg-amber-100 transition-colors group">
                    <div class="flex items-start gap-2">
                        <div class="w-5 h-5 rounded bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-amber-800">${escHtml(m.titulo)}</p>
                            <p class="text-[9px] text-amber-600 leading-relaxed mt-0.5 line-clamp-2">${escHtml(m.texto)}</p>
                        </div>
                    </div>
                </button>
            `).join('');

            // Delegação de eventos — evita problemas com inline JS e caracteres especiais
            list.querySelectorAll('.mr-picker-item').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = parseInt(this.getAttribute('data-mr-idx'), 10);
                    inserirMsgRapida(_mrCache[idx].texto);
                });
            });
        }

        function filtrarMsgsRapidas() {
            if (_mrCache) renderizarMsgsPicker(_mrCache, document.getElementById('mrPickerSearch').value);
        }

        function inserirMsgRapida(texto) {
            const ta = document.getElementById('form_resolucao');
            const atual = ta.value.trim();
            ta.value = atual ? atual + '\n\n' + texto : texto;
            document.getElementById('mrPickerPanel').classList.add('hidden');
            ta.focus();
            ta.scrollTop = ta.scrollHeight;
        }

        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        // ────────────────────────────────────────────────────────────────────
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
