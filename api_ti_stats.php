<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Estatísticas em tempo real
$stats = [
    'abertos' => 0,
    'em_atendimento' => 0,
    'aguardando_peca' => 0,
    'resolvidos_hoje' => 0,
    'total_ativos' => 0
];

$res = $conn->query("
    SELECT status, COUNT(*) as total 
    FROM chamados 
    WHERE status IN ('Aberto', 'Em Atendimento', 'Aguardando Peça') 
    GROUP BY status
");

if ($res) {
    while($row = $res->fetch_assoc()) {
        if ($row['status'] == 'Aberto') $stats['abertos'] = (int)$row['total'];
        if ($row['status'] == 'Em Atendimento') $stats['em_atendimento'] = (int)$row['total'];
        if ($row['status'] == 'Aguardando Peça') $stats['aguardando_peca'] = (int)$row['total'];
    }
}

// Buscar resolvidos hoje
$hoje = date('Y-m-d');
$res_resolvidos = $conn->query("SELECT COUNT(*) as total FROM chamados WHERE status = 'Resolvido' AND DATE(data_fechamento) = '$hoje'");
if ($res_resolvidos) {
    $stats['resolvidos_hoje'] = (int)$res_resolvidos->fetch_assoc()['total'];
}

$stats['total_ativos'] = $stats['abertos'] + $stats['em_atendimento'] + $stats['aguardando_peca'];

echo json_encode($stats);
?>