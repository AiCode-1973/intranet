<?php
require_once 'config.php';
require_once 'functions.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $token = bin2hex(random_bytes(32));
        $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $user_id = $user['id'];
        
        // Limpar tokens antigos do mesmo usuário
        $conn->query("DELETE FROM password_resets WHERE usuario_id = $user_id");
        
        $stmt_insert = $conn->prepare("INSERT INTO password_resets (usuario_id, token, expiracao) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iss", $user_id, $token, $expiracao);
        
        if ($stmt_insert->execute()) {
            // No XAMPP/Localhost, o host pode variar. Usamos server vars.
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $uri = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['REQUEST_URI']);
            $link = "$protocol://$host{$uri}resetar_senha.php?token=$token";
            
            $assunto = "Recuperação de Senha - APAS Intranet";
            $corpo = "<h1>Recuperação de Senha</h1>
                      <p>Olá <b>" . $user['nome'] . "</b>,</p>
                      <p>Recebemos uma solicitação para redefinir a sua senha da APAS Intranet.</p>
                      <p>Para prosseguir, clique no botão abaixo:</p>
                      <p><a href='$link' style='display:inline-block; padding: 12px 24px; background-color: #0d9488; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Redefinir Minha Senha</a></p>
                      <p>Ou copie e cole o link no seu navegador:</p>
                      <p>$link</p>
                      <hr>
                      <p><small>Este link expirará em 1 hora. Se você não solicitou esta alteração, ignore este e-mail.</small></p>";
            
            if (enviarEmail($email, $assunto, $corpo)) {
                $mensagem = "Instruções de recuperação enviadas para o seu e-mail!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao enviar e-mail. Verifique a configuração SMTP ou tente novamente.";
                $tipo_mensagem = "danger";
            }
        }
        $stmt_insert->close();
    } else {
        // Segurança: mesma mensagem mesmo que o e-mail não exista
        $mensagem = "Se o e-mail estiver cadastrado em nossa base, você receberá um link de recuperação em instantes.";
        $tipo_mensagem = "success";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="min-h-screen bg-gray-50 font-sans selection:bg-primary/20 flex flex-col items-center justify-center p-6">
    
    <div class="w-full max-w-[400px] animate-in fade-in slide-in-from-bottom-4 duration-500">
        <div class="text-center mb-10">
            <div class="inline-flex bg-white p-3 rounded-2xl shadow-xl border border-border mb-6">
                <i data-lucide="key-round" class="w-8 h-8 text-primary"></i>
            </div>
            <h1 class="text-3xl font-black text-text tracking-tight">Recuperar Senha</h1>
            <p class="text-sm text-text-secondary mt-2 leading-relaxed">Insira o e-mail associado à sua conta para receber as instruções.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-2xl border mb-6 flex items-start gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span class="text-xs font-bold leading-relaxed"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-3xl shadow-xl border border-border">
            <form method="POST" action="" class="space-y-6">
                <div class="space-y-1.5">
                    <label for="email" class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">E-mail Cadastrado</label>
                    <div class="relative group">
                        <input type="email" id="email" name="email" placeholder="seu@email.com.br" required 
                               class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-border rounded-2xl text-base font-bold text-text focus:outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all outline-none">
                        <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 bg-primary hover:bg-primary-hover text-white font-black rounded-2xl shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 text-xs uppercase tracking-[0.2em]">
                    Enviar Link
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-border/50 text-center">
                <a href="login.php" class="text-xs font-black text-text-secondary hover:text-primary transition-colors flex items-center justify-center gap-2 uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar para o Login
                </a>
            </div>
        </div>
    </div>

</body>
</html>
