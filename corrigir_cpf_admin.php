<?php
require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Corrigir CPF Admin</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f6f8f7; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: green; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo "h1 { color: #102217; }";
echo ".btn { background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin: 10px 5px; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='card'>";
echo "<h1>🔧 Corrigir CPF do Admin</h1>";

// CPF correto (apenas números)
$cpf_correto = '00000000000';

// Atualizar CPF do admin
$stmt = $conn->prepare("UPDATE usuarios SET cpf = ? WHERE email = 'admin@intranet.com'");
$stmt->bind_param("s", $cpf_correto);

if ($stmt->execute()) {
    echo "<div class='success'>";
    echo "<h2>✅ CPF Corrigido com Sucesso!</h2>";
    echo "<p>O CPF do administrador foi atualizado para o formato correto (apenas números).</p>";
    echo "<br>";
    echo "<h3>Credenciais de Acesso:</h3>";
    echo "<p><strong>CPF:</strong> 000.000.000-00 (você pode digitar com ou sem formatação)</p>";
    echo "<p><strong>Senha:</strong> admin123</p>";
    echo "<br>";
    echo "<p>O sistema agora vai aceitar o login normalmente.</p>";
    echo "</div>";
} else {
    echo "<div class='info' style='background: #f8d7da; color: #721c24; border-color: #f5c6cb;'>";
    echo "<h3>❌ Erro ao atualizar CPF</h3>";
    echo "<p>Erro: " . $conn->error . "</p>";
    echo "</div>";
}

$stmt->close();

// Verificar o CPF atualizado
$result = $conn->query("SELECT cpf FROM usuarios WHERE email = 'admin@intranet.com'");
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<div class='info'>";
    echo "<h3>Verificação:</h3>";
    echo "<p>CPF no banco de dados: <strong>" . $admin['cpf'] . "</strong></p>";
    echo "<p>Comprimento: " . strlen($admin['cpf']) . " caracteres (deve ser 11)</p>";
    echo "</div>";
}

echo "<br>";
echo "<a href='login.php' class='btn'>Ir para o Login →</a>";

echo "</div>";
echo "</body>";
echo "</html>";

$conn->close();
?>
