<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function isRHAdmin() {
    return isAdmin() || (isset($_SESSION['setor_id']) && $_SESSION['setor_id'] == 2);
}

function isEduAdmin() {
    return isAdmin() || (isset($_SESSION['is_educacao']) && $_SESSION['is_educacao'] == 1);
}

function isTecnico() {
    return isAdmin() || (isset($_SESSION['is_tecnico']) && $_SESSION['is_tecnico'] == 1);
}

function isManutencao() {
    return isAdmin() || (isset($_SESSION['is_manutencao']) && $_SESSION['is_manutencao'] == 1);
}

function isAdminDashboardUser() {
    return isRHAdmin() || isEduAdmin() || isTecnico() || isManutencao();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function requireRHAdmin() {
    requireLogin();
    if (!isRHAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdminDashboard() {
    requireLogin();
    if (!isAdminDashboardUser()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireEduAdmin() {
    requireLogin();
    if (!isEduAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireTecnico() {
    requireLogin();
    if (!isTecnico()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireManutencao() {
    requireLogin();
    if (!isManutencao()) {
        header('Location: ../index.php');
        exit;
    }
}

function registrarLog($conn, $acao) {
    if (isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt = $conn->prepare("INSERT INTO logs_acesso (usuario_id, acao, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $usuario_id, $acao, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function temPermissao($conn, $setor_id, $modulo_slug, $tipo = 'visualizar') {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        return true;
    }
    
    $campo_permissao = 'pode_' . $tipo;
    
    $stmt = $conn->prepare("
        SELECT p.$campo_permissao 
        FROM permissoes p 
        INNER JOIN modulos m ON p.modulo_id = m.id 
        WHERE p.setor_id = ? AND m.slug = ?
    ");
    $stmt->bind_param("is", $setor_id, $modulo_slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row[$campo_permissao] == 1;
    }
    
    return false;
}

function formatarData($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
