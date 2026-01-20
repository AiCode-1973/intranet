<?php
require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Adicionar Campo CPF</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f6f8f7; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: green; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }";
echo ".error { color: red; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo "h1 { color: #102217; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='card'>";
echo "<h1>🔧 Adicionar Campo CPF</h1>";

try {
    // Verificar se a coluna já existe
    $result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'cpf'");
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>ℹ️ O campo CPF já existe na tabela usuarios.</div>";
    } else {
        // Adicionar coluna CPF
        $sql = "ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) UNIQUE AFTER email";
        if ($conn->query($sql)) {
            echo "<div class='success'>✅ Campo CPF adicionado com sucesso!</div>";
        } else {
            throw new Exception($conn->error);
        }
    }
    
    // Atualizar usuário admin com CPF padrão
    $cpf_admin = '000.000.000-00';
    $stmt = $conn->prepare("UPDATE usuarios SET cpf = ? WHERE email = 'admin@intranet.com' AND cpf IS NULL");
    $stmt->bind_param("s", $cpf_admin);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<div class='success'>✅ CPF padrão definido para o administrador: $cpf_admin</div>";
        } else {
            echo "<div class='info'>ℹ️ Usuário administrador já possui CPF configurado.</div>";
        }
    }
    $stmt->close();
    
    echo "<div class='success'>";
    echo "<h2>✅ Atualização concluída!</h2>";
    echo "<p>✔️ O sistema agora está pronto para login via CPF</p>";
    echo "<p>✔️ CPF do admin: <strong>$cpf_admin</strong></p>";
    echo "<p>✔️ Senha do admin: <strong>admin123</strong></p>";
    echo "<br>";
    echo "<p><a href='login.php' style='background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Ir para o Login →</a></p>";
    echo "</div>";
    
    echo "<div class='info'>⚠️ <strong>IMPORTANTE:</strong> Exclua este arquivo (add_cpf_field.php) após a execução por segurança!</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erro na atualização</h2>";
    echo "<p>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</div>";
echo "</body>";
echo "</html>";

$conn->close();
?>
