<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aceitar') {
    $uid = intval($_SESSION['usuario_id']);
    $stmt = $conn->prepare("UPDATE usuarios SET aceite_termos = 1, data_aceite_termos = NOW() WHERE id = ?");
    $stmt->bind_param("i", $uid);
    if ($stmt->execute()) {
        $_SESSION['aceite_termos'] = 1;
        registrarLog($conn, 'Aceitou os termos de uso da intranet');
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'erro' => $conn->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Requisição inválida']);
