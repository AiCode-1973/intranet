<?php
echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Teste de Conexão MySQL</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }";
echo ".card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: green; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }";
echo ".error { color: red; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }";
echo ".info { color: #004085; padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0; }";
echo ".code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; margin: 10px 0; }";
echo "h1 { color: #333; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='card'>";
echo "<h1>🔍 Teste de Conexão MySQL Remoto</h1>";

$host = '69.49.241.25';
$user = 'apassa73_intranet';
$pass = 'Dema@1973';
$db = 'apassa73_intranet';

echo "<div class='info'>";
echo "<strong>Configurações:</strong><br>";
echo "Host: $host<br>";
echo "Usuário: $user<br>";
echo "Banco: $db<br>";
echo "Seu IP: " . $_SERVER['REMOTE_ADDR'] . "<br>";
echo "IP do Servidor Web: " . gethostbyname(gethostname()) . "<br>";
echo "</div>";

// Teste 1: Verificar se a extensão MySQLi está habilitada
echo "<h3>✓ Teste 1: Extensão MySQLi</h3>";
if (extension_loaded('mysqli')) {
    echo "<div class='success'>✅ Extensão MySQLi está habilitada</div>";
} else {
    echo "<div class='error'>❌ Extensão MySQLi NÃO está habilitada</div>";
    echo "</div></body></html>";
    exit;
}

// Teste 2: Tentar conectar
echo "<h3>✓ Teste 2: Conexão ao Servidor</h3>";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo "<div class='error'>";
    echo "<strong>❌ Falha na conexão</strong><br><br>";
    echo "<strong>Erro MySQL:</strong> " . $conn->connect_error . "<br>";
    echo "<strong>Código de Erro:</strong> " . $conn->connect_errno . "<br><br>";
    
    if ($conn->connect_errno == 1045) {
        echo "<strong>Problema:</strong> Acesso negado (usuário/senha ou IP não autorizado)<br><br>";
        echo "<strong>Soluções:</strong><br>";
        echo "1. Verifique se o usuário '$user' tem permissão para conectar do seu IP<br>";
        echo "2. No servidor MySQL, execute:<br>";
        echo "<div class='code'>";
        echo "GRANT ALL PRIVILEGES ON $db.* TO '$user'@'%' IDENTIFIED BY 'sua_senha';<br>";
        echo "FLUSH PRIVILEGES;";
        echo "</div>";
        echo "3. Ou adicione seu IP nas permissões de MySQL remoto no cPanel<br>";
    } elseif ($conn->connect_errno == 2002 || $conn->connect_errno == 2003) {
        echo "<strong>Problema:</strong> Não foi possível alcançar o servidor<br><br>";
        echo "<strong>Soluções:</strong><br>";
        echo "1. Verifique se o servidor está online<br>";
        echo "2. Verifique se a porta 3306 está aberta no firewall<br>";
        echo "3. Verifique se o MySQL está configurado para aceitar conexões remotas<br>";
    }
    
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<strong>✅ Conexão estabelecida com sucesso!</strong><br><br>";
    echo "<strong>Informações do Servidor:</strong><br>";
    echo "Versão MySQL: " . $conn->server_info . "<br>";
    echo "Host Info: " . $conn->host_info . "<br>";
    echo "Protocolo: " . $conn->protocol_version . "<br>";
    echo "Charset: " . $conn->character_set_name() . "<br>";
    echo "</div>";
    
    // Teste 3: Verificar banco de dados
    echo "<h3>✓ Teste 3: Verificação do Banco de Dados</h3>";
    
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        if (count($tables) > 0) {
            echo "<div class='success'>";
            echo "✅ Banco de dados acessível<br>";
            echo "Total de tabelas: " . count($tables) . "<br><br>";
            echo "<strong>Tabelas encontradas:</strong><br>";
            foreach ($tables as $table) {
                echo "• $table<br>";
            }
            echo "</div>";
            
            echo "<div class='info'>";
            echo "✅ <strong>Tudo funcionando!</strong> Você pode usar o sistema normalmente.<br><br>";
            echo "<a href='login.php' style='background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Ir para o Login →</a>";
            echo "</div>";
        } else {
            echo "<div class='info'>";
            echo "⚠️ Banco de dados vazio (nenhuma tabela encontrada)<br>";
            echo "Execute o arquivo install.php para criar as tabelas.<br><br>";
            echo "<a href='install.php' style='background: linear-gradient(135deg, #13ec6a 0%, #0eb857 100%); color: #102217; font-weight: 600; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;'>Instalar Banco de Dados →</a>";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>❌ Erro ao acessar banco de dados: " . $conn->error . "</div>";
    }
    
    $conn->close();
}

echo "</div>";
echo "</body>";
echo "</html>";
?>
