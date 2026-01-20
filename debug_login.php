<?php
require_once 'config.php';
require_once 'functions.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Debug Login</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f6f8f7; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }";
echo ".success { color: green; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }";
echo ".error { color: red; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "table th, table td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 12px; }";
echo "table th { background: #f8f9fa; font-weight: 600; }";
echo "pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; }";
echo "h1 { color: #102217; }";
echo "h3 { color: #13ec6a; margin-top: 20px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='card'>";
echo "<h1>🔍 Debug de Login - Diagnóstico Completo</h1>";

// Teste 1: Verificar usuário no banco
echo "<h3>1️⃣ Verificar Usuário Admin no Banco</h3>";
$result = $conn->query("SELECT id, nome, cpf, email, senha, is_admin, ativo FROM usuarios WHERE email = 'admin@intranet.com'");

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<div class='success'>✅ Usuário encontrado</div>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td>ID</td><td>" . $admin['id'] . "</td></tr>";
    echo "<tr><td>Nome</td><td>" . $admin['nome'] . "</td></tr>";
    echo "<tr><td>CPF (bruto)</td><td>" . $admin['cpf'] . "</td></tr>";
    echo "<tr><td>CPF (length)</td><td>" . strlen($admin['cpf']) . " caracteres</td></tr>";
    echo "<tr><td>Email</td><td>" . $admin['email'] . "</td></tr>";
    echo "<tr><td>Hash da Senha</td><td>" . substr($admin['senha'], 0, 30) . "...</td></tr>";
    echo "<tr><td>Is Admin</td><td>" . ($admin['is_admin'] ? 'Sim (1)' : 'Não (0)') . "</td></tr>";
    echo "<tr><td>Ativo</td><td>" . ($admin['ativo'] ? 'Sim (1)' : 'Não (0)') . "</td></tr>";
    echo "</table>";
} else {
    echo "<div class='error'>❌ Usuário admin não encontrado!</div>";
    exit;
}

// Teste 2: Testar busca por CPF
echo "<h3>2️⃣ Testar Busca por CPF</h3>";
$cpf_teste = '00000000000';
$stmt = $conn->prepare("SELECT id, nome, cpf FROM usuarios WHERE cpf = ? AND ativo = 1");
$stmt->bind_param("s", $cpf_teste);
$stmt->execute();
$result_cpf = $stmt->get_result();

if ($result_cpf->num_rows > 0) {
    $user_cpf = $result_cpf->fetch_assoc();
    echo "<div class='success'>✅ Usuário encontrado pelo CPF: $cpf_teste</div>";
    echo "<pre>ID: " . $user_cpf['id'] . " | Nome: " . $user_cpf['nome'] . " | CPF: " . $user_cpf['cpf'] . "</pre>";
} else {
    echo "<div class='error'>❌ Usuário NÃO encontrado pelo CPF: $cpf_teste</div>";
}
$stmt->close();

// Teste 3: Verificar hash da senha
echo "<h3>3️⃣ Testar Verificação de Senha</h3>";
$senha_teste = 'admin123';
$senha_hash = $admin['senha'];

echo "<div class='info'>";
echo "<p><strong>Senha testada:</strong> $senha_teste</p>";
echo "<p><strong>Hash no banco:</strong> " . substr($senha_hash, 0, 50) . "...</p>";
echo "</div>";

if (password_verify($senha_teste, $senha_hash)) {
    echo "<div class='success'>✅ Senha CORRETA! password_verify() retornou TRUE</div>";
} else {
    echo "<div class='error'>❌ Senha INCORRETA! password_verify() retornou FALSE</div>";
    echo "<div class='info'>";
    echo "<p>Vou criar uma nova senha hash e atualizar no banco...</p>";
    $nova_senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt_senha = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $stmt_senha->bind_param("si", $nova_senha_hash, $admin['id']);
    if ($stmt_senha->execute()) {
        echo "<p style='color: green;'><strong>✅ Senha atualizada com sucesso!</strong></p>";
        echo "<p>Nova hash: " . substr($nova_senha_hash, 0, 50) . "...</p>";
    }
    $stmt_senha->close();
    echo "</div>";
}

// Teste 4: Simular processo de login completo
echo "<h3>4️⃣ Simular Processo de Login Completo</h3>";
echo "<div class='info'>";
echo "<p>Testando com CPF: <strong>00000000000</strong> (sem formatação)</p>";
echo "<p>Testando com Senha: <strong>admin123</strong></p>";
echo "</div>";

$cpf_login = preg_replace('/[^0-9]/', '', '000.000.000-00');
echo "<p>CPF após preg_replace: <strong>$cpf_login</strong> (length: " . strlen($cpf_login) . ")</p>";

$stmt_login = $conn->prepare("SELECT id, nome, email, cpf, senha, setor_id, is_admin FROM usuarios WHERE cpf = ? AND ativo = 1");
$stmt_login->bind_param("s", $cpf_login);
$stmt_login->execute();
$result_login = $stmt_login->get_result();

if ($result_login->num_rows > 0) {
    $user_login = $result_login->fetch_assoc();
    echo "<div class='success'>✅ Passo 1: Usuário encontrado pelo CPF</div>";
    
    if (password_verify('admin123', $user_login['senha'])) {
        echo "<div class='success'>✅ Passo 2: Senha verificada com sucesso!</div>";
        echo "<div class='success'>";
        echo "<h3>🎉 LOGIN FUNCIONARIA!</h3>";
        echo "<p>Todos os testes passaram. O login deve funcionar com:</p>";
        echo "<p><strong>CPF:</strong> 000.000.000-00 (ou 00000000000)</p>";
        echo "<p><strong>Senha:</strong> admin123</p>";
        echo "</div>";
    } else {
        echo "<div class='error'>❌ Passo 2: Senha incorreta!</div>";
    }
} else {
    echo "<div class='error'>❌ Passo 1: Usuário NÃO encontrado pelo CPF</div>";
}
$stmt_login->close();

// Teste 5: Verificar tabela de usuários
echo "<h3>5️⃣ Listar Todos os Usuários</h3>";
$all_users = $conn->query("SELECT id, nome, cpf, email, ativo FROM usuarios");
echo "<table>";
echo "<tr><th>ID</th><th>Nome</th><th>CPF</th><th>Email</th><th>Ativo</th></tr>";
while ($u = $all_users->fetch_assoc()) {
    $status = $u['ativo'] ? 'style="background: #d4edda;"' : 'style="background: #f8d7da;"';
    echo "<tr $status>";
    echo "<td>" . $u['id'] . "</td>";
    echo "<td>" . $u['nome'] . "</td>";
    echo "<td>" . ($u['cpf'] ?? 'NULL') . " (" . strlen($u['cpf'] ?? '') . ")</td>";
    echo "<td>" . $u['email'] . "</td>";
    echo "<td>" . ($u['ativo'] ? 'Sim' : 'Não') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr style='margin: 30px 0;'>";
echo "<h3>🔧 Ações Disponíveis</h3>";
echo "<p><a href='login.php' style='background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px;'>Tentar Login Novamente</a></p>";

echo "</div>";
echo "</body>";
echo "</html>";

$conn->close();
?>
