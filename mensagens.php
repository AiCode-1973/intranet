<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$user_id = $_SESSION['usuario_id'];
$msg_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$selected_msg = null;

if ($msg_id) {
    // Marcar como lida e buscar conteúdo
    $conn->query("UPDATE email_logs SET lido = 1 WHERE id = $msg_id AND usuario_id = $user_id");
    $res = $conn->query("SELECT * FROM email_logs WHERE id = $msg_id AND usuario_id = $user_id");
    $selected_msg = $res->fetch_assoc();
}

// Buscar todas as mensagens do usuário
$mensagens = $conn->query("SELECT * FROM email_logs WHERE usuario_id = $user_id ORDER BY data_envio DESC");

// Se não tiver mensagem selecionada mas houver mensagens, pegar a primeira? 
// Não, melhor deixar a lista se não houver ID.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Mensagens - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-primary flex items-center gap-3">
                <i data-lucide="mail-open" class="w-8 h-8"></i>
                Minhas Mensagens
            </h1>
            <p class="text-text-secondary text-sm mt-1">Comunicados internos enviados pela administração.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Message List -->
            <div class="bg-white rounded-3xl border border-border overflow-hidden flex flex-col h-[600px] shadow-sm">
                <div class="p-4 border-b border-border bg-gray-50">
                    <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary">Caixa de Entrada</h3>
                </div>
                <div class="flex-grow overflow-y-auto">
                    <?php if ($mensagens->num_rows > 0): ?>
                        <?php while($m = $mensagens->fetch_assoc()): ?>
                            <a href="mensagens.php?id=<?php echo $m['id']; ?>" class="block p-5 border-b border-border/50 hover:bg-primary/[0.02] transition-colors <?php echo ($msg_id == $m['id']) ? 'bg-primary/5 border-l-4 border-l-primary' : ($m['lido'] == 0 ? 'bg-primary/[0.03]' : ''); ?>">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="text-xs font-bold text-text truncate pr-2"><?php echo $m['assunto']; ?></h4>
                                    <span class="text-[9px] text-text-secondary opacity-50 shrink-0"><?php echo date('d/m H:i', strtotime($m['data_envio'])); ?></span>
                                </div>
                                <p class="text-[10px] text-text-secondary line-clamp-2 leading-relaxed"><?php echo strip_tags($m['mensagem']); ?></p>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="h-full flex flex-col items-center justify-center opacity-30 italic p-8 text-center">
                            <i data-lucide="inbox" class="w-12 h-12 mb-3"></i>
                            <p class="text-xs">Nenhuma mensagem encontrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message Detail -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-border shadow-xl min-h-[600px] flex flex-col overflow-hidden">
                <?php if ($selected_msg): ?>
                    <div class="p-8 border-b border-border">
                        <div class="flex justify-between items-center mb-6">
                            <span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-black uppercase tracking-widest rounded-full">Comunicado Interno</span>
                            <span class="text-[11px] text-text-secondary font-bold"><?php echo date('d \d\e F \d\e Y, H:i', strtotime($selected_msg['data_envio'])); ?></span>
                        </div>
                        <h2 class="text-2xl font-bold text-text mb-2"><?php echo $selected_msg['assunto']; ?></h2>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xs">A</div>
                            <div>
                                <p class="text-[11px] font-bold text-text">Administração APAS</p>
                                <p class="text-[10px] text-text-secondary">Para: <?php echo $_SESSION['usuario_nome']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="p-8 flex-grow prose prose-sm max-w-none text-text leading-relaxed">
                        <?php echo nl2br($selected_msg['mensagem']); ?>
                    </div>
                    <div class="p-6 bg-gray-50 border-t border-border flex justify-between items-center">
                        <p class="text-[10px] text-text-secondary font-bold italic">Esta é uma mensagem automática gerada pelo sistema de intranet.</p>
                        <button onclick="window.print()" class="p-2 text-text-secondary hover:text-primary transition-colors">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center p-12 text-center">
                        <div class="w-20 h-20 bg-primary/5 rounded-full flex items-center justify-center text-primary mb-6 animate-pulse">
                            <i data-lucide="mail" class="w-10 h-10"></i>
                        </div>
                        <h3 class="text-base font-bold text-text mb-2">Selecione uma mensagem</h3>
                        <p class="text-xs text-text-secondary max-w-xs">Escolha uma mensagem na lista ao lado para visualizar seu conteúdo completo.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
