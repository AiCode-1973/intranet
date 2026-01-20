<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Buscar Usuários para o seletor individual
$usuarios = $conn->query("SELECT id, nome, email, setor_id FROM usuarios WHERE ativo = 1 ORDER BY nome ASC");
$usuarios_arr = [];
while($u = $usuarios->fetch_assoc()) $usuarios_arr[] = $u;

// Buscar Logs recentes
$logs = $conn->query("SELECT l.*, u.nome as usuario_nome 
                      FROM email_logs l 
                      LEFT JOIN usuarios u ON l.usuario_id = u.id 
                      ORDER BY l.data_envio DESC LIMIT 10");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'enviar') {
    $assunto = sanitize($_POST['assunto']);
    $corpo = $_POST['mensagem']; // Permitir HTML
    $tipo_destinatario = $_POST['tipo_destinatario']; // 'todos' ou 'individual'
    
    // Obter config de e-mail
    $res_config = $conn->query("SELECT * FROM email_config LIMIT 1");
    $config = $res_config->fetch_assoc();
    
    if (!$config) {
        $mensagem = "Configure o servidor SMTP antes de enviar!";
        $tipo_mensagem = "danger";
    } else {
        $enviados = 0;
        $falhas = 0;
        
        $lista_envio = [];
        if ($tipo_destinatario == 'todos') {
            $lista_envio = $usuarios_arr;
        } else {
            $user_id = intval($_POST['usuario_id']);
            foreach($usuarios_arr as $u) {
                if ($u['id'] == $user_id) {
                    $lista_envio[] = $u;
                    break;
                }
            }
        }
        
        foreach ($lista_envio as $dest) {
            // Aqui chamamos a função de envio (que implementaremos em functions.php)
            // Por enquanto, simulamos e logamos no banco
            if (enviarEmail($dest['email'], $assunto, $corpo, $config)) {
                $enviados++;
                $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status) VALUES (?, ?, ?, ?, 'Sucesso')");
                $stmt->bind_param("isss", $dest['id'], $dest['email'], $assunto, $corpo);
                $stmt->execute();
            } else {
                $falhas++;
                $stmt = $conn->prepare("INSERT INTO email_logs (usuario_id, destinatario_email, assunto, mensagem, status, erro_mensagem) VALUES (?, ?, ?, ?, 'Falha', 'Erro no servidor de envio')");
                $stmt->bind_param("isss", $dest['id'], $dest['email'], $assunto, $corpo);
                $stmt->execute();
            }
        }
        
        $mensagem = "Log de Envio: $enviados sucesso(s), $falhas falha(s).";
        $tipo_mensagem = $falhas == 0 ? "success" : "warning";
        registrarLog($conn, "Enviou e-mail ($tipo_destinatario): $assunto");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicação por E-mail - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-primary flex items-center gap-3">
                    <i data-lucide="mail" class="w-8 h-8"></i>
                    Central de Comunicação
                </h1>
                <p class="text-text-secondary text-sm mt-1">Envie comunicados e avisos diretamente para o e-mail dos colaboradores.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="config_email.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-primary rounded-xl text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm border-b-2 active:translate-y-0.5">
                    <i data-lucide="settings" class="w-4 h-4"></i> Configurar SMTP
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-xl border mb-6 flex items-center gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : ($tipo_mensagem == 'warning' ? 'bg-amber-50 border-amber-100 text-amber-700' : 'bg-red-50 border-red-100 text-red-700'); ?> animate-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                <span class="text-sm font-bold tracking-tight"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Composer -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-3xl shadow-xl border border-border overflow-hidden">
                    <div class="bg-primary px-6 py-4 flex items-center gap-3 text-white">
                        <i data-lucide="pen-tool" class="w-5 h-5"></i>
                        <h2 class="text-sm font-black uppercase tracking-widest">Nova Mensagem</h2>
                    </div>
                    
                    <form method="POST" class="p-8 space-y-6">
                        <input type="hidden" name="acao" value="enviar">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Destinatário</label>
                                <select name="tipo_destinatario" id="tipo_destinatario" onchange="toggleDestinatario()" class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                                    <option value="todos">Todos os Usuários Ativos</option>
                                    <option value="individual">Usuário Individual</option>
                                </select>
                            </div>

                            <div id="div_individual" class="hidden">
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Selecionar Usuário</label>
                                <select name="usuario_id" class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                                    <?php foreach($usuarios_arr as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo $u['nome']; ?> (<?php echo $u['email']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Assunto do E-mail</label>
                            <input type="text" name="assunto" required placeholder="Ex: Informativo - Reunião Geral"
                                   class="w-full p-3 bg-background border border-border rounded-xl text-sm font-bold focus:outline-none focus:border-primary transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-2 font-bold uppercase">Conteúdo da Mensagem</label>
                            <textarea name="mensagem" rows="10" required placeholder="Digite sua mensagem aqui... Você pode usar tags HTML básicas."
                                      class="w-full p-4 bg-background border border-border rounded-2xl text-sm font-medium focus:outline-none focus:border-primary transition-all leading-relaxed"></textarea>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-10 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 active:scale-95 transition-all flex items-center gap-3">
                                <i data-lucide="send" class="w-4 h-4"></i> Enviar Mensagem
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar Info & Logs -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl border border-border p-6 shadow-sm">
                    <h3 class="text-xs font-black text-text-secondary uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-primary"></i>
                        Envios Recentes
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while($l = $logs->fetch_assoc()): ?>
                                <div class="p-3 bg-background rounded-2xl border border-border/50 group hover:border-primary transition-colors cursor-default">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-[11px] font-bold text-text truncate pr-2"><?php echo $l['assunto']; ?></p>
                                        <span class="text-[8px] font-black px-1.5 py-0.5 rounded uppercase <?php echo $l['status'] == 'Sucesso' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                            <?php echo $l['status']; ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-[9px] text-text-secondary font-bold">Para: <?php echo $l['usuario_nome'] ?: 'Todos'; ?></p>
                                        <p class="text-[8px] text-text-secondary opacity-50"><?php echo date('d/m H:i', strtotime($l['data_envio'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-[10px] italic text-text-secondary py-4 text-center">Nenhum envio registrado.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-primary/5 rounded-3xl border border-primary/10 p-6">
                    <h4 class="text-[10px] font-black text-primary uppercase tracking-widest mb-3">Dicas de Envio</h4>
                    <ul class="space-y-3">
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">Você pode usar tags HTML como &lt;b&gt;, &lt;i&gt; e &lt;br&gt; para formatar o texto.</p>
                        </li>
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">Evite usar muitas imagens ou links suspeitos para não cair na caixa de spam.</p>
                        </li>
                        <li class="flex gap-2 items-start">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-primary mt-0.5"></i>
                            <p class="text-[10px] text-text-secondary font-bold">O envio em massa para muitos usuários pode levar alguns segundos para ser processado.</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDestinatario() {
            const tipo = document.getElementById('tipo_destinatario').value;
            const div = document.getElementById('div_individual');
            if (tipo === 'individual') {
                div.classList.remove('hidden');
            } else {
                div.classList.add('hidden');
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
