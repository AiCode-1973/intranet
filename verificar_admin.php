<?php
require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Verificar Usuário Admin</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f6f8f7; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo "table { width: 100%; border-collapse: collapse; margin: 20px 0; }";
echo "table th, table td { padding: 10px; border: 1px solid #ddd; text-align: left; }";
echo "table th { background: #f8f9fa; }";
echo "h1 { color: #102217; }";
echo ".btn { background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin: 10px 5px; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='card'>";
echo "<h1>🔍 Verificar Usuário Admin</h1>";

// Buscar usuário admin
$result = $conn->query("SELECT id, nome, cpf, email, is_admin, ativo FROM usuarios WHERE email = 'admin@intranet.com'");

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    echo "<div class='info'>";
    echo "<h3>Dados do Administrador:</h3>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td><strong>ID</strong></td><td>" . $admin['id'] . "</td></tr>";
    echo "<tr><td><strong>Nome</strong></td><td>" . $admin['nome'] . "</td></tr>";
    echo "<tr><td><strong>CPF (bruto)</strong></td><td>" . ($admin['cpf'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td><strong>CPF (formatado)</strong></td><td>" . ($admin['cpf'] ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $admin['cpf']) : 'NULL') . "</td></tr>";
    echo "<tr><td><strong>Email</strong></td><td>" . $admin['email'] . "</td></tr>";
    echo "<tr><td><strong>Admin</strong></td><td>" . ($admin['is_admin'] ? 'Sim' : 'Não') . "</td></tr>";
    echo "<tr><td><strong>Ativo</strong></td><td>" . ($admin['ativo'] ? 'Sim' : 'Não') . "</td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Verificar se CPF está NULL
    if ($admin['cpf'] === null || $admin['cpf'] === '') {
        echo "<div class='info'>";
        echo "<h3>⚠️ CPF não configurado!</h3>";
        echo "<p>O usuário admin não possui CPF. Vou configurar agora...</p>";
        
        $cpf = '00000000000';
        $stmt = $conn->prepare("UPDATE usuarios SET cpf = ? WHERE id = ?");
        $stmt->bind_param("si", $cpf, $admin['id']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'><strong>✅ CPF atualizado com sucesso!</strong></p>";
            echo "<p>CPF configurado: <strong>000.000.000-00</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Erro ao atualizar CPF:</strong> " . $conn->error . "</p>";
        }
        $stmt->close();
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<h3>✅ CPF Configurado</h3>";
        echo "<p>Use as credenciais abaixo para fazer login:</p>";
        echo "<p><strong>CPF:</strong> " . preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $admin['cpf']) . " (ou sem formatação: " . $admin['cpf'] . ")</p>";
        echo "<p><strong>Senha:</strong> admin123</p>";
        echo "</div>";
    }
    
} else {
    echo "<div class='info' style='background: #f8d7da; color: #721c24; border-color: #f5c6cb;'>";
    echo "<h3>❌ Usuário admin não encontrado!</h3>";
    echo "<p>Vou criar o usuário admin agora...</p>";
    
    $nome = 'Administrador';
    $cpf = '00000000000';
    $email = 'admin@intranet.com';
    $senha = password_hash('admin123', PASSWORD_DEFAULT);
    $is_admin = 1;
    $ativo = 1;
    
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, cpf, email, senha, is_admin, ativo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $nome, $cpf, $email, $senha, $is_admin, $ativo);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'><strong>✅ Usuário admin criado com sucesso!</strong></p>";
        echo "<p><strong>CPF:</strong> 000.000.000-00</p>";
        echo "<p><strong>Senha:</strong> admin123</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Erro ao criar usuário:</strong> " . $conn->error . "</p>";
    }
    $stmt->close();
    echo "</div>";
}

echo "<br>";
echo "<a href='login.php' class='btn'>Ir para Login</a>";
echo "<a href='test_connection.php' class='btn' style='background: #6c757d;'>Verificar Conexão</a>";

echo "</div>";
echo "</body>";
echo "</html>";

$conn->close();
?>
