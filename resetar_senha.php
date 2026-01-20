<?php
require_once 'config.php';
require_once 'functions.php';

$mensagem = '';
$tipo_mensagem = '';
$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';
$valido = false;
$user_id = 0;

if ($token) {
    $stmt = $conn->prepare("SELECT usuario_id, expiracao FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['expiracao']) >= time()) {
            $valido = true;
            $user_id = $row['usuario_id'];
        } else {
            $mensagem = "Este link de recuperação expirou.";
            $tipo_mensagem = "danger";
        }
    } else {
        $mensagem = "Link de recuperação inválido.";
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valido) {
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];
    
    if ($senha !== $confirmar) {
        $mensagem = "As senhas não coincidem.";
        $tipo_mensagem = "danger";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres.";
        $tipo_mensagem = "danger";
    } else {
        $nova_senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        $stmt_update = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt_update->bind_param("si", $nova_senha_hash, $user_id);
        
        if ($stmt_update->execute()) {
            // Sucesso! Limpar tokens
            $conn->query("DELETE FROM password_resets WHERE usuario_id = $user_id");
            
            $mensagem = "Senha redefinida com sucesso! Você já pode fazer login.";
            $tipo_mensagem = "success";
            $valido = false; // Ocultar formulário
        } else {
            $mensagem = "Erro ao atualizar senha. Tente novamente.";
            $tipo_mensagem = "danger";
        }
        $stmt_update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir Nova Senha - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="min-h-screen bg-gray-50 font-sans selection:bg-primary/20 flex flex-col items-center justify-center p-6">
    
    <div class="w-full max-w-[400px] animate-in fade-in slide-in-from-bottom-4 duration-500">
        <div class="text-center mb-10">
            <div class="inline-flex bg-white p-3 rounded-2xl shadow-xl border border-border mb-6">
                <i data-lucide="shield-lock" class="w-8 h-8 text-primary"></i>
            </div>
            <h1 class="text-3xl font-black text-text tracking-tight">Nova Senha</h1>
            <p class="text-sm text-text-secondary mt-2 leading-relaxed">Defina sua nova credencial de acesso ao sistema.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-2xl border mb-6 flex items-start gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span class="text-xs font-bold leading-relaxed"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-3xl shadow-xl border border-border">
            <?php if ($valido): ?>
                <form method="POST" action="" class="space-y-6">
                    <div class="space-y-1.5">
                        <label for="senha" class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Nova Senha</label>
                        <div class="relative group">
                            <input type="password" id="senha" name="senha" placeholder="••••••••" required 
                                   class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-border rounded-2xl text-base font-bold text-text focus:outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all outline-none">
                            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="confirmar_senha" class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Confirmar Senha</label>
                        <div class="relative group">
                            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="••••••••" required 
                                   class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-border rounded-2xl text-base font-bold text-text focus:outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all outline-none">
                            <i data-lucide="check-circle-2" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-4 bg-primary hover:bg-primary-hover text-white font-black rounded-2xl shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 text-xs uppercase tracking-[0.2em]">
                        Alterar Senha
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="login.php" class="inline-block py-4 px-8 bg-primary hover:bg-primary-hover text-white font-black rounded-2xl shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 text-xs uppercase tracking-[0.2em]">
                        Ir para o Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
