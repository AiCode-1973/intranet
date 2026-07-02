<?php
/**
 * Setup do módulo Assinatura de E-mail
 * Execute uma vez para registrar o módulo e criar a tabela de configuração.
 */
require_once 'config.php';
require_once 'functions.php';

requireAdmin();

$erros = [];
$ok    = [];

// 1. Criar tabela de configuração
$sql = "CREATE TABLE IF NOT EXISTS assinatura_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    $ok[] = 'Tabela <code>assinatura_config</code> criada/verificada.';
} else {
    $erros[] = 'Erro ao criar tabela: ' . $conn->error;
}

// 2. Registrar módulo na tabela modulos (se não existir)
$check = $conn->query("SELECT id FROM modulos WHERE slug = 'assinatura_email' LIMIT 1");
if ($check && $check->num_rows === 0) {
    $ordem = (int)($conn->query("SELECT MAX(ordem) as m FROM modulos")->fetch_assoc()['m'] ?? 0) + 1;
    $stmt  = $conn->prepare("INSERT INTO modulos (nome, descricao, slug, icone, ordem, ativo) VALUES (?, ?, 'assinatura_email', 'mail-check', ?, 1)");
    $nome  = 'Assinatura de E-mail';
    $desc  = 'Gerador de assinatura profissional para e-mail corporativo.';
    $stmt->bind_param("ssi", $nome, $desc, $ordem);
    if ($stmt->execute()) {
        $ok[] = 'Módulo <code>assinatura_email</code> registrado.';
    } else {
        $erros[] = 'Erro ao registrar módulo: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $ok[] = 'Módulo <code>assinatura_email</code> já existia.';
}

// 3. Liberar acesso para TODOS os setores ativos
$mod_row = $conn->query("SELECT id FROM modulos WHERE slug = 'assinatura_email' LIMIT 1")->fetch_assoc();
if ($mod_row) {
    $mod_id  = (int)$mod_row['id'];
    $setores = $conn->query("SELECT id FROM setores WHERE ativo = 1");
    $liberados = 0;
    while ($s = $setores->fetch_assoc()) {
        $sid = (int)$s['id'];
        $stmt = $conn->prepare("INSERT IGNORE INTO permissoes (setor_id, modulo_id, pode_visualizar) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $sid, $mod_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) $liberados++;
        $stmt->close();
    }
    $ok[] = "Permissão de visualização liberada para <strong>$liberados</strong> setor(es).";
}

registrarLog($conn, 'Setup do módulo Assinatura de E-mail executado');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Setup - Assinatura de E-mail</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans p-8 max-w-xl mx-auto">
    <div class="bg-white rounded-2xl border border-border p-8 shadow-sm mt-12">
        <h1 class="text-lg font-black text-text mb-6 flex items-center gap-2">
            <i data-lucide="mail-check" class="w-5 h-5 text-primary"></i>
            Setup: Assinatura de E-mail
        </h1>

        <?php foreach ($ok as $msg): ?>
        <div class="flex items-start gap-2 mb-2 text-sm text-green-700 bg-green-50 rounded-lg px-4 py-2">
            <i data-lucide="check" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <span><?php echo $msg; ?></span>
        </div>
        <?php endforeach; ?>

        <?php foreach ($erros as $e): ?>
        <div class="flex items-start gap-2 mb-2 text-sm text-red-700 bg-red-50 rounded-lg px-4 py-2">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <span><?php echo $e; ?></span>
        </div>
        <?php endforeach; ?>

        <div class="mt-6 flex gap-3">
            <a href="assinatura_email.php"
               class="px-4 py-2 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary-hover transition-all">
                Abrir Gerador de Assinatura
            </a>
            <a href="admin/assinatura_email_gerenciar.php"
               class="px-4 py-2 bg-white border border-border rounded-xl text-sm font-bold hover:border-primary hover:text-primary transition-all">
                Configurar Template (Admin)
            </a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>
