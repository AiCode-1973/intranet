<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../email_para_chamado.php'; // engine (não executa em modo web)

requireAdmin();

// Garante que as tabelas existem
email_setup_tables($conn);

$mensagem     = '';
$tipo_mensagem = '';

// ── Salvar configuração IMAP ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_config') {
    $host        = sanitize($_POST['imap_host'] ?? '');
    $port        = max(1, intval($_POST['imap_port'] ?? 993));
    $user        = sanitize($_POST['imap_user'] ?? '');
    $pass        = $_POST['imap_pass'] ?? '';   // senha não sanitizada
    $ssl         = isset($_POST['imap_ssl'])          ? 1 : 0;
    $novalidate  = isset($_POST['novalidate_cert'])   ? 1 : 0;
    $inbox       = sanitize($_POST['pasta_inbox']      ?? 'INBOX');
    $processado  = sanitize($_POST['pasta_processado'] ?? '');
    $ativo       = isset($_POST['ativo']) ? 1 : 0;

    $existe = $conn->query("SELECT id FROM email_imap_config LIMIT 1")->num_rows > 0;

    if ($existe) {
        $stmt = $conn->prepare("UPDATE email_imap_config SET imap_host=?, imap_port=?, imap_user=?, imap_pass=?, imap_ssl=?, novalidate_cert=?, pasta_inbox=?, pasta_processado=?, ativo=?");
        $stmt->bind_param("sissiissl", $host, $port, $user, $pass, $ssl, $novalidate, $inbox, $processado, $ativo);
    } else {
        $stmt = $conn->prepare("INSERT INTO email_imap_config (imap_host,imap_port,imap_user,imap_pass,imap_ssl,novalidate_cert,pasta_inbox,pasta_processado,ativo) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sissiissl", $host, $port, $user, $pass, $ssl, $novalidate, $inbox, $processado, $ativo);
    }

    if ($stmt->execute()) {
        $mensagem      = 'Configurações salvas com sucesso!';
        $tipo_mensagem = 'success';
        registrarLog($conn, "Configuração IMAP de e-mail salva.");
    } else {
        $mensagem      = 'Erro ao salvar: ' . $conn->error;
        $tipo_mensagem = 'danger';
    }
    $stmt->close();
}

// ── Testar conexão IMAP ──────────────────────────────────────────────────────
$teste_resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'testar_conexao') {
    $host       = sanitize($_POST['imap_host'] ?? '');
    $port       = max(1, intval($_POST['imap_port'] ?? 993));
    $user       = sanitize($_POST['imap_user'] ?? '');
    $pass       = $_POST['imap_pass'] ?? '';
    $ssl        = isset($_POST['imap_ssl'])        ? 1 : 0;
    $novalidate = isset($_POST['novalidate_cert']) ? 1 : 0;

    $flags   = '/imap';
    if ($ssl)        $flags .= '/ssl';
    if ($novalidate) $flags .= '/novalidate-cert';
    $mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';

    $imap = @imap_open($mailbox, $user, $pass, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
    if ($imap) {
        $count = imap_num_msg($imap);
        imap_close($imap);
        $teste_resultado = ['ok' => true, 'msg' => "Conexão OK! {$count} mensagem(ns) na caixa."];
    } else {
        $teste_resultado = ['ok' => false, 'msg' => imap_last_error() ?: 'Falha desconhecida.'];
    }
}

// ── Verificar e-mails agora ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'verificar_agora') {
    $result = processarEmailsChamados($conn);
    if ($result['erro']) {
        $mensagem      = 'Erro na verificação: ' . $result['erro'];
        $tipo_mensagem = 'danger';
    } else {
        $criados    = $result['criados'];
        $dup        = $result['duplicados'];
        $erros      = $result['erros'];
        $mensagem      = "Verificação concluída: {$criados} chamado(s) criado(s), {$dup} duplicado(s) ignorado(s)" . ($erros ? ", {$erros} erro(s)." : ".");
        $tipo_mensagem = $criados > 0 ? 'success' : 'info';
        if ($result['erros']) $tipo_mensagem = 'warning';
        registrarLog($conn, "Verificação manual de e-mails: {$criados} criados.");
    }
}

// ── Limpar log ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'limpar_log') {
    $conn->query("DELETE FROM email_chamados_log WHERE processado_em < NOW() - INTERVAL 30 DAY");
    $mensagem      = 'Log de e-mails antigos (>30 dias) removido.';
    $tipo_mensagem = 'success';
}

// ── Carregar dados para exibição ─────────────────────────────────────────────
$cfg  = $conn->query("SELECT * FROM email_imap_config LIMIT 1")->fetch_assoc();
$logs = $conn->query("SELECT * FROM email_chamados_log ORDER BY processado_em DESC LIMIT 60");

$total_criados   = $conn->query("SELECT COUNT(*) FROM email_chamados_log WHERE status='criado'")->fetch_row()[0] ?? 0;
$total_duplicados= $conn->query("SELECT COUNT(*) FROM email_chamados_log WHERE status='duplicado'")->fetch_row()[0] ?? 0;
$total_erros     = $conn->query("SELECT COUNT(*) FROM email_chamados_log WHERE status='erro'")->fetch_row()[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-mail → Chamado - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display:none; position:fixed; inset:0; z-index:50; background:rgba(0,0,0,.5); align-items:center; justify-content:center; }
        .modal.active { display:flex; }
    </style>
</head>
<body class="bg-background text-text font-sans">
    <?php include '../header.php'; ?>

    <div class="p-6 w-full max-w-5xl mx-auto flex-grow">

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="mail" class="w-6 h-6"></i>
                    E-mail → Chamado
                </h1>
                <p class="text-text-secondary text-xs mt-1">
                    E-mails recebidos em <strong>suporte@hsesantos.com.br</strong> viram chamados automaticamente
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="suporte_gerenciar.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Mensagem de feedback -->
        <?php if ($mensagem): ?>
            <?php
                $cor = match($tipo_mensagem) {
                    'success' => 'bg-green-50 border-green-200 text-green-700',
                    'danger'  => 'bg-red-50 border-red-200 text-red-700',
                    'warning' => 'bg-amber-50 border-amber-200 text-amber-700',
                    default   => 'bg-blue-50 border-blue-200 text-blue-700',
                };
                $icon = match($tipo_mensagem) {
                    'success' => 'check-circle',
                    'danger'  => 'alert-circle',
                    'warning' => 'alert-triangle',
                    default   => 'info',
                };
            ?>
            <div id="email-msg" class="p-3 rounded-lg border mb-6 flex items-center gap-2 <?php echo $cor; ?> transition-opacity duration-500">
                <i data-lucide="<?php echo $icon; ?>" class="w-4 h-4 flex-shrink-0"></i>
                <span class="text-xs font-bold"><?php echo htmlspecialchars($mensagem); ?></span>
            </div>
            <script>setTimeout(function(){var m=document.getElementById('email-msg');if(m){m.style.opacity='0';setTimeout(function(){m.remove();},500);}},5000);</script>
        <?php endif; ?>

        <!-- Resultado do teste de conexão -->
        <?php if ($teste_resultado): ?>
            <div class="p-3 rounded-lg border mb-6 flex items-center gap-2 <?php echo $teste_resultado['ok'] ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <i data-lucide="<?php echo $teste_resultado['ok'] ? 'check-circle' : 'x-circle'; ?>" class="w-4 h-4 flex-shrink-0"></i>
                <span class="text-xs font-bold"><?php echo htmlspecialchars($teste_resultado['msg']); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Coluna esquerda: Config ────────────────────────────────── -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Card: Configuração IMAP -->
                <div class="bg-white rounded-xl border border-border shadow-sm">
                    <div class="p-4 border-b border-border flex items-center justify-between">
                        <h2 class="text-sm font-bold text-text flex items-center gap-2">
                            <i data-lucide="settings" class="w-4 h-4 text-primary"></i>
                            Configuração IMAP
                        </h2>
                        <?php if ($cfg && $cfg['ativo']): ?>
                            <span class="flex items-center gap-1.5 text-[10px] font-black text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Ativo
                            </span>
                        <?php elseif ($cfg): ?>
                            <span class="text-[10px] font-black text-text-secondary bg-gray-100 border border-border px-2 py-0.5 rounded-full uppercase tracking-wide">Desativado</span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="p-4 space-y-4">
                        <input type="hidden" name="acao" value="salvar_config">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Servidor IMAP</label>
                                <input type="text" name="imap_host" value="<?php echo htmlspecialchars($cfg['imap_host'] ?? 'mail.hsesantos.com.br'); ?>"
                                    placeholder="mail.hsesantos.com.br"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Porta</label>
                                <input type="number" name="imap_port" value="<?php echo htmlspecialchars($cfg['imap_port'] ?? '993'); ?>"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Usuário (e-mail completo)</label>
                                <input type="text" name="imap_user" value="<?php echo htmlspecialchars($cfg['imap_user'] ?? 'suporte@hsesantos.com.br'); ?>"
                                    placeholder="suporte@hsesantos.com.br"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Senha</label>
                                <input type="password" name="imap_pass" value="<?php echo htmlspecialchars($cfg['imap_pass'] ?? ''); ?>"
                                    placeholder="••••••••"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Pasta de entrada</label>
                                <input type="text" name="pasta_inbox" value="<?php echo htmlspecialchars($cfg['pasta_inbox'] ?? 'INBOX'); ?>"
                                    placeholder="INBOX"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Pasta após processamento <span class="font-normal normal-case text-text-secondary/50">(opcional)</span></label>
                                <input type="text" name="pasta_processado" value="<?php echo htmlspecialchars($cfg['pasta_processado'] ?? ''); ?>"
                                    placeholder="INBOX.Processados"
                                    class="w-full bg-background border border-border rounded-lg px-3 py-2 text-xs font-bold focus:outline-none focus:border-primary transition-all">
                                <p class="text-[9px] text-text-secondary mt-0.5">Se preenchida, o e-mail é movido após virar chamado</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="imap_ssl" value="1" <?php echo (!$cfg || $cfg['imap_ssl']) ? 'checked' : ''; ?>
                                    class="w-4 h-4 rounded accent-primary">
                                <span class="text-xs font-bold text-text">Usar SSL/TLS</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="novalidate_cert" value="1" <?php echo ($cfg && $cfg['novalidate_cert']) ? 'checked' : ''; ?>
                                    class="w-4 h-4 rounded accent-primary">
                                <span class="text-xs font-bold text-text">Ignorar certificado SSL</span>
                                <span class="text-[9px] text-text-secondary">(servidores compartilhados)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="ativo" value="1" <?php echo (!$cfg || $cfg['ativo']) ? 'checked' : ''; ?>
                                    class="w-4 h-4 rounded accent-primary">
                                <span class="text-xs font-bold text-text">Recepção ativa</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2 pt-2 border-t border-border">
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-2">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i>
                                Salvar Configuração
                            </button>
                            <button type="submit" form="form-teste" class="bg-white border border-border hover:bg-gray-50 text-text px-4 py-2 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-2">
                                <i data-lucide="wifi" class="w-3.5 h-3.5 text-blue-500"></i>
                                Testar Conexão
                            </button>
                        </div>
                    </form>

                    <!-- Formulário oculto para teste (reutiliza os valores digitados) -->
                    <form id="form-teste" method="POST" class="hidden">
                        <input type="hidden" name="acao" value="testar_conexao">
                        <input type="hidden" name="imap_host"     id="t_host">
                        <input type="hidden" name="imap_port"     id="t_port">
                        <input type="hidden" name="imap_user"     id="t_user">
                        <input type="hidden" name="imap_pass"     id="t_pass">
                        <input type="hidden" name="imap_ssl"      id="t_ssl">
                        <input type="hidden" name="novalidate_cert" id="t_novalidate">
                    </form>
                    <script>
                    document.querySelector('button[form="form-teste"]').addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('t_host').value     = document.querySelector('[name="imap_host"]').value;
                        document.getElementById('t_port').value     = document.querySelector('[name="imap_port"]').value;
                        document.getElementById('t_user').value     = document.querySelector('[name="imap_user"]').value;
                        document.getElementById('t_pass').value     = document.querySelector('[name="imap_pass"]').value;
                        document.getElementById('t_ssl').value      = document.querySelector('[name="imap_ssl"]').checked ? '1' : '';
                        document.getElementById('t_novalidate').value = document.querySelector('[name="novalidate_cert"]').checked ? '1' : '';
                        document.getElementById('form-teste').submit();
                    });
                    </script>
                </div>

                <!-- Card: Como configurar no Windows Task Scheduler -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <h3 class="text-xs font-black text-blue-700 uppercase tracking-widest flex items-center gap-2 mb-3">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        Automação — Windows Task Scheduler
                    </h3>
                    <p class="text-xs text-blue-700 mb-3">
                        Para verificar e-mails automaticamente a cada 5 minutos, crie uma tarefa no <strong>Agendador de Tarefas do Windows</strong>:
                    </p>
                    <div class="space-y-2">
                        <div>
                            <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5">Programa / Script:</p>
                            <code class="block bg-white border border-blue-200 rounded px-3 py-2 text-xs text-blue-900 font-mono select-all">C:\xampp1\php\php.exe</code>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5">Argumentos:</p>
                            <code class="block bg-white border border-blue-200 rounded px-3 py-2 text-xs text-blue-900 font-mono select-all">C:\xampp1\htdocs\intranet\email_para_chamado.php</code>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5">Disparar:</p>
                            <p class="text-xs text-blue-700">Repetir a cada <strong>5 minutos</strong>, por <strong>indefinidamente</strong>, diariamente.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Coluna direita: Ações + Stats ─────────────────────────── -->
            <div class="space-y-4">

                <!-- Verificar agora -->
                <div class="bg-white rounded-xl border border-border shadow-sm p-4">
                    <h2 class="text-sm font-bold text-text flex items-center gap-2 mb-3">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-primary"></i>
                        Verificar Agora
                    </h2>
                    <p class="text-[10px] text-text-secondary mb-3 leading-relaxed">
                        Busca e-mails não lidos em <strong>suporte@hsesantos.com.br</strong> e converte em chamados imediatamente.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="acao" value="verificar_agora">
                        <button type="submit" <?php echo !$cfg || !$cfg['ativo'] ? 'disabled title="Configure e ative o IMAP primeiro"' : ''; ?>
                            class="w-full bg-primary hover:bg-primary-hover disabled:opacity-40 disabled:cursor-not-allowed text-white py-2.5 rounded-lg text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2">
                            <i data-lucide="mail-check" class="w-4 h-4"></i>
                            Verificar E-mails
                        </button>
                    </form>
                </div>

                <!-- Stats -->
                <div class="bg-white rounded-xl border border-border shadow-sm p-4">
                    <h2 class="text-sm font-bold text-text flex items-center gap-2 mb-3">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 text-primary"></i>
                        Estatísticas
                    </h2>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2 bg-green-50 rounded-lg border border-green-100">
                            <span class="text-[10px] font-black text-green-700 uppercase tracking-widest">Chamados Criados</span>
                            <span class="text-lg font-black text-green-700"><?php echo $total_criados; ?></span>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border border-border">
                            <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Duplicados Ignorados</span>
                            <span class="text-lg font-black text-text-secondary"><?php echo $total_duplicados; ?></span>
                        </div>
                        <?php if ($total_erros > 0): ?>
                        <div class="flex items-center justify-between p-2 bg-red-50 rounded-lg border border-red-100">
                            <span class="text-[10px] font-black text-red-700 uppercase tracking-widest">Erros</span>
                            <span class="text-lg font-black text-red-700"><?php echo $total_erros; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Limpar log -->
                <form method="POST">
                    <input type="hidden" name="acao" value="limpar_log">
                    <button type="submit" onclick="return confirm('Remover logs com mais de 30 dias?')"
                        class="w-full bg-white border border-border hover:bg-red-50 hover:border-red-200 text-text-secondary hover:text-red-600 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        Limpar Log Antigo
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Log de e-mails processados ──────────────────────────────────── -->
        <div class="mt-6 bg-white rounded-xl border border-border shadow-sm">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <h2 class="text-sm font-bold text-text flex items-center gap-2">
                    <i data-lucide="list" class="w-4 h-4 text-primary"></i>
                    Log de E-mails Processados
                    <span class="text-[10px] font-black text-text-secondary/50">(últimos 60)</span>
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-background border-b border-border">
                        <tr>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-left">Data</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-left">De</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-left">Assunto</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Chamado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if ($logs && $logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr class="hover:bg-background/30 transition-colors">
                                    <td class="p-3 text-text-secondary whitespace-nowrap">
                                        <?php echo date('d/m/y H:i', strtotime($log['processado_em'])); ?>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-bold text-text leading-tight"><?php echo htmlspecialchars($log['de_nome']); ?></p>
                                        <p class="text-[9px] text-text-secondary"><?php echo htmlspecialchars($log['de_email']); ?></p>
                                    </td>
                                    <td class="p-3 max-w-xs">
                                        <p class="font-bold text-text leading-tight truncate"><?php echo htmlspecialchars($log['assunto']); ?></p>
                                        <?php if ($log['erro_msg']): ?>
                                            <p class="text-[9px] text-red-600"><?php echo htmlspecialchars($log['erro_msg']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center">
                                        <?php
                                        $badge = match($log['status']) {
                                            'criado'    => 'bg-green-100 text-green-700 border-green-200',
                                            'duplicado' => 'bg-gray-100 text-gray-600 border-gray-200',
                                            'erro'      => 'bg-red-100 text-red-700 border-red-200',
                                            default     => 'bg-gray-100 text-gray-600 border-gray-200',
                                        };
                                        $label = match($log['status']) {
                                            'criado'    => 'Criado',
                                            'duplicado' => 'Duplicado',
                                            'erro'      => 'Erro',
                                            default     => $log['status'],
                                        };
                                        ?>
                                        <span class="<?php echo $badge; ?> border text-[9px] font-black uppercase tracking-tighter px-2 py-0.5 rounded-full">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <?php if ($log['chamado_id']): ?>
                                            <a href="suporte_gerenciar.php" class="text-primary font-black hover:underline">
                                                #<?php echo str_pad($log['chamado_id'], 3, '0', STR_PAD_LEFT); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-text-secondary/40">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-text-secondary">
                                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                                    <p class="text-xs font-bold">Nenhum e-mail processado ainda.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php include '../footer.php'; ?>
    <script>lucide.createIcons();</script>
</body>
</html>
