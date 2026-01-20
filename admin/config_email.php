<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Buscar configuração atual
$res = $conn->query("SELECT * FROM email_config LIMIT 1");
$config = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $smtp_host = sanitize($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_user = sanitize($_POST['smtp_user']);
    $smtp_pass = $_POST['smtp_pass']; // Não sanitizar senha para preservar caracteres especiais
    $smtp_secure = sanitize($_POST['smtp_secure']);
    $from_email = sanitize($_POST['from_email']);
    $from_name = sanitize($_POST['from_name']);

    if ($config) {
        $stmt = $conn->prepare("UPDATE email_config SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_secure = ?, from_email = ?, from_name = ? WHERE id = ?");
        $stmt->bind_param("sisssssi", $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure, $from_email, $from_name, $config['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO email_config (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssss", $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure, $from_email, $from_name);
    }

    if ($stmt->execute()) {
        $mensagem = "Configurações de e-mail salvas!";
        $tipo_mensagem = "success";
        $config = ['smtp_host' => $smtp_host, 'smtp_port' => $smtp_port, 'smtp_user' => $smtp_user, 'smtp_pass' => $smtp_pass, 'smtp_secure' => $smtp_secure, 'from_email' => $from_email, 'from_name' => $from_name];
        registrarLog($conn, "Atualizou configurações de SMTP");
    } else {
        $mensagem = "Erro ao salvar: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de E-mail - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-4xl mx-auto flex-grow">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="settings-2" class="w-7 h-7"></i>
                    Servidor de E-mail (SMTP)
                </h1>
                <p class="text-text-secondary text-sm">Configure as credenciais para envio de notificações do sistema.</p>
            </div>
            <a href="comunicacao.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-xl border mb-6 flex items-center gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                <span class="text-sm font-bold tracking-tight"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl border border-border overflow-hidden">
            <form method="POST" class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Servidor SMTP (Host)</label>
                        <input type="text" name="smtp_host" value="<?php echo $config['smtp_host'] ?? ''; ?>" required placeholder="ex: smtp.gmail.com ou mail.suaempresa.com"
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Porta</label>
                        <input type="number" name="smtp_port" value="<?php echo $config['smtp_port'] ?? '587'; ?>" required
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Segurança</label>
                        <select name="smtp_secure" class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="tls" <?php echo ($config['smtp_secure'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS (Recomendado)</option>
                            <option value="ssl" <?php echo ($config['smtp_secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($config['smtp_secure'] ?? '') == 'none' ? 'selected' : ''; ?>>Nenhuma</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Usuário (E-mail)</label>
                        <input type="text" name="smtp_user" value="<?php echo $config['smtp_user'] ?? ''; ?>" required
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Senha</label>
                        <input type="password" name="smtp_pass" value="<?php echo $config['smtp_pass'] ?? ''; ?>" required
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <hr class="col-span-2 border-border border-dashed">

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">E-mail do Remetente</label>
                        <input type="email" name="from_email" value="<?php echo $config['from_email'] ?? ''; ?>" required
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2">Nome do Remetente</label>
                        <input type="text" name="from_name" value="<?php echo $config['from_name'] ?? 'APAS Intranet'; ?>" required
                               class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 active:scale-95 transition-all flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Salvar Configurações
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 bg-amber-50 rounded-2xl p-6 border border-amber-100">
            <div class="flex gap-4">
                <div class="shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                    <i data-lucide="info" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black text-amber-800 uppercase tracking-widest mb-1">Dica importante</h4>
                    <p class="text-[11px] text-amber-700 leading-relaxed font-bold">
                        Ao usar o Gmail, lembre-se de criar uma "Senha de App" nas configurações de segurança da sua conta Google. Senhas normais podem ser bloqueadas pelo serviço por segurança.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
