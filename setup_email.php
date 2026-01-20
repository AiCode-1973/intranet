<?php
require_once 'config.php';

$sql = file_get_contents('admin/email_setup.sql');
$commands = explode(';', $sql);

echo "<h2>Configurando Módulo de E-mail...</h2>";

foreach ($commands as $command) {
    $command = trim($command);
    if (!empty($command)) {
        if ($conn->query($command)) {
            echo "<p style='color: green;'>Sucesso: " . substr($command, 0, 50) . "...</p>";
        } else {
            echo "<p style='color: red;'>Erro: " . $conn->error . " no comando: " . substr($command, 0, 50) . "</p>";
        }
    }
}

// Registrar o módulo na tabela de módulos se não existir
$conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
              SELECT 'Comunicação', 'Envio de comunicados e e-mails', 'comunicacao', 'mail', 9
              WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'comunicacao')");

echo "<h3>Configuração finalizada!</h3>";
$conn->close();
?>
