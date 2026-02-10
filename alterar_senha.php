<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        $mensagem = "Todos os campos são obrigatórios.";
        $tipo_mensagem = "error";
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = "A nova senha e a confirmação não conferem.";
        $tipo_mensagem = "error";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = "A nova senha deve ter pelo menos 6 caracteres.";
        $tipo_mensagem = "error";
    } else {
        // Buscar senha atual no banco
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($senha_atual, $user['senha'])) {
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $update->bind_param("si", $nova_senha_hash, $usuario_id);
            
            if ($update->execute()) {
                registrarLog($conn, "Alterou a própria senha");
                $mensagem = "Senha alterada com sucesso! Você continuará logado.";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao alterar a senha. Tente novamente.";
                $tipo_mensagem = "error";
            }
        } else {
            $mensagem = "A senha atual está incorreta.";
            $tipo_mensagem = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 overflow-x-hidden">
    <?php include 'header.php'; ?>
    
    <div class="flex-grow flex items-center justify-center p-4 md:p-8">
        <div class="w-full max-w-md bg-white rounded-[2.5rem] border border-border shadow-2xl shadow-black/5 overflow-hidden transition-all duration-500 hover:shadow-primary/5">
            <!-- Header do Card -->
            <div class="p-8 pb-6 text-center bg-gray-50/50 border-b border-border relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></div>
                <div class="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-primary/20 shadow-inner group transition-all duration-500 hover:scale-110 hover:rotate-3">
                    <i data-lucide="lock" class="w-10 h-10 text-primary transition-transform duration-500 group-hover:scale-90"></i>
                </div>
                <h1 class="text-2xl font-black text-text tracking-tight italic">Alterar Senha</h1>
                <p class="text-[11px] text-text-secondary mt-2 font-bold uppercase tracking-widest opacity-70">Segurança da Conta</p>
            </div>

            <!-- Formulário -->
            <div class="p-8">
                <?php if ($mensagem): ?>
                    <div class="mb-8 p-4 rounded-2xl flex items-center gap-4 transition-all animate-in fade-in slide-in-from-top-4 duration-500 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 text-green-700 border border-green-100 shadow-sm shadow-green-100' : 'bg-red-50 text-red-700 border border-red-100 shadow-sm shadow-red-100'; ?>">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 <?php echo $tipo_mensagem == 'success' ? 'bg-green-100' : 'bg-red-100'; ?>">
                            <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check' : 'x'; ?>" class="w-5 h-5"></i>
                        </div>
                        <span class="text-xs font-black uppercase tracking-tight"><?php echo $mensagem; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1 opacity-70">Senha Atual</label>
                        <div class="relative group">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-white rounded-lg border border-border flex items-center justify-center text-text-secondary group-focus-within:border-primary group-focus-within:text-primary transition-all duration-300">
                                <i data-lucide="key" class="w-4 h-4"></i>
                            </div>
                            <input type="password" name="senha_atual" required 
                                class="w-full pl-14 pr-4 py-4 bg-gray-50/50 border border-border rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all text-sm font-bold text-text placeholder:text-text-secondary/30"
                                placeholder="Sua senha secreta">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 pt-4 border-t border-dashed border-border mt-8">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1 opacity-70">Nova Senha</label>
                            <div class="relative group">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-white rounded-lg border border-border flex items-center justify-center text-text-secondary group-focus-within:border-primary group-focus-within:text-primary transition-all duration-300">
                                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                                </div>
                                <input type="password" name="nova_senha" required 
                                    class="w-full pl-14 pr-4 py-4 bg-gray-50/50 border border-border rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all text-sm font-bold text-text placeholder:text-text-secondary/30"
                                    placeholder="Mínimo 6 caracteres">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1 opacity-70">Confirmar Nova Senha</label>
                            <div class="relative group">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-white rounded-lg border border-border flex items-center justify-center text-text-secondary group-focus-within:border-primary group-focus-within:text-primary transition-all duration-300">
                                    <i data-lucide="check-square" class="w-4 h-4"></i>
                                </div>
                                <input type="password" name="confirmar_senha" required 
                                    class="w-full pl-14 pr-4 py-4 bg-gray-50/50 border border-border rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all text-sm font-bold text-text placeholder:text-text-secondary/30"
                                    placeholder="Repita a nova senha">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" name="alterar_senha" 
                            class="w-full bg-primary hover:bg-primary-hover text-white font-black py-5 rounded-[1.25rem] shadow-xl shadow-primary/20 transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3 uppercase text-xs tracking-[0.15em]">
                            <i data-lucide="zap" class="w-5 h-5 fill-current"></i>
                            Atualizar Credenciais
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="p-6 bg-gray-50/50 border-t border-border flex justify-center">
                <a href="dashboard.php" class="group flex items-center gap-3 text-[10px] font-black text-text-secondary uppercase tracking-widest hover:text-primary transition-colors">
                    <div class="w-7 h-7 rounded-full bg-white border border-border flex items-center justify-center group-hover:border-primary group-hover:translate-x-[-2px] transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    </div>
                    Voltar para o Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    </div> <!-- Fecha o wrapper do header.php -->
</body>
</html>
