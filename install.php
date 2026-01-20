<?php
require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Instalação do Banco de Dados</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: green; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }";
echo ".error { color: red; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo "h1 { color: #333; }";
echo ".log { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='card'>";
echo "<h1>🗄️ Instalação do Banco de Dados</h1>";

$sql_file = __DIR__ . '/database.sql';

if (!file_exists($sql_file)) {
    echo "<div class='error'>❌ Arquivo database.sql não encontrado!</div>";
    exit;
}

$sql_content = file_get_contents($sql_file);

if ($sql_content === false) {
    echo "<div class='error'>❌ Erro ao ler o arquivo database.sql</div>";
    exit;
}

echo "<div class='info'>📝 Executando script SQL...</div>";
echo "<div class='log'>";

$conn->multi_query($sql_content);

$errors = [];
$success_count = 0;

do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
    
    if ($conn->errno) {
        $errors[] = "Erro: " . $conn->error;
        echo "❌ " . htmlspecialchars($conn->error) . "<br>";
    } else {
        $success_count++;
        echo "✅ Comando executado com sucesso<br>";
    }
} while ($conn->more_results() && $conn->next_result());

echo "</div>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h2>✅ Instalação concluída com sucesso!</h2>";
    echo "<p>✔️ Todas as tabelas foram criadas</p>";
    echo "<p>✔️ Dados iniciais foram inseridos</p>";
    echo "<p>✔️ Total de comandos executados: $success_count</p>";
    echo "<br>";
    echo "<p><strong>Credenciais de acesso:</strong></p>";
    echo "<p>Email: admin@intranet.com<br>Senha: admin123</p>";
    echo "<br>";
    echo "<p><a href='login.php' style='background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Ir para o Login →</a></p>";
    echo "</div>";
    echo "<div class='info'>⚠️ <strong>IMPORTANTE:</strong> Exclua este arquivo (install.php) após a instalação por segurança!</div>";
} else {
    echo "<div class='error'>";
    echo "<h2>⚠️ Instalação concluída com erros</h2>";
    echo "<p>Alguns comandos falharam. Verifique os erros acima.</p>";
    echo "<p>Total de erros: " . count($errors) . "</p>";
    echo "</div>";
}

echo "</div>";
echo "</body>";
echo "</html>";

$conn->close();
?>
