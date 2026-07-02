<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$msg = '';
$erro = '';

// ── Garante que a tabela existe ─────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS assinatura_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Salvar configurações ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $campos = ['empresa_nome','empresa_site','empresa_logo_url','empresa_cor',
               'empresa_endereco','empresa_telefone','disclaimer'];

    $stmt = $conn->prepare("INSERT INTO assinatura_config (chave, valor)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    foreach ($campos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        // Valida cor hex
        if ($campo === 'empresa_cor' && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            $valor = '#0d9488';
        }
        // Sanitiza URL de logo
        if ($campo === 'empresa_logo_url' && $valor !== '') {
            $parsed = parse_url($valor);
            if (!in_array($parsed['scheme'] ?? '', ['http','https'])) {
                $erro = 'URL da logo inválida. Use http:// ou https://.';
                $valor = '';
            }
        }
        $stmt->bind_param("ss", $campo, $valor);
        $stmt->execute();
    }
    $stmt->close();

    if (!$erro) {
        registrarLog($conn, 'Configurações de assinatura de e-mail atualizadas');
        $msg = 'Configurações salvas com sucesso!';
    }
}

// ── Carregar configurações atuais ────────────────────────────────────────────
$rows = $conn->query("SELECT chave, valor FROM assinatura_config");
$cfg  = [];
if ($rows) {
    while ($r = $rows->fetch_assoc()) $cfg[$r['chave']] = $r['valor'];
}
$cfg = array_merge([
    'empresa_nome'     => 'APAS Baixada Santista',
    'empresa_site'     => 'www.apassantos.com.br',
    'empresa_logo_url' => '',
    'empresa_cor'      => '#0d9488',
    'empresa_endereco' => '',
    'empresa_telefone' => '',
    'disclaimer'       => '',
], $cfg);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Assinatura de E-mail - Admin</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 flex flex-col min-h-screen">
    <?php include '../header.php'; ?>

    <div class="lg:ml-64 pt-16 flex-grow flex flex-col">
        <div class="p-6 w-full max-w-4xl mx-auto flex-grow">

            <!-- Cabeçalho -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <i data-lucide="mail-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-text">Configurar Template de Assinatura</h1>
                        <p class="text-xs text-text-muted">Defina as informações institucionais exibidas nas assinaturas dos colaboradores.</p>
                    </div>
                </div>
                <a href="../assinatura_email.php"
                   class="flex items-center gap-2 px-4 py-2 bg-white border border-border rounded-xl text-xs font-bold text-text hover:border-primary hover:text-primary transition-all">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    Ver Gerador
                </a>
            </div>

            <?php if ($msg): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-3 text-sm font-bold flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
            <?php endif; ?>
            <?php if ($erro): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-3 text-sm font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Identidade da Empresa -->
                <div class="bg-white rounded-2xl border border-border p-6 shadow-sm mb-6">
                    <h2 class="text-sm font-black text-text uppercase tracking-widest mb-5 flex items-center gap-2">
                        <i data-lucide="building-2" class="w-4 h-4 text-primary"></i>
                        Identidade da Organização
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Nome da Empresa *</label>
                            <input type="text" name="empresa_nome"
                                value="<?php echo htmlspecialchars($cfg['empresa_nome'], ENT_QUOTES); ?>"
                                class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Site (sem https://)</label>
                            <input type="text" name="empresa_site"
                                value="<?php echo htmlspecialchars($cfg['empresa_site'], ENT_QUOTES); ?>"
                                class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                placeholder="www.empresa.com.br">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">URL da Logo (opcional)</label>
                            <input type="url" name="empresa_logo_url"
                                value="<?php echo htmlspecialchars($cfg['empresa_logo_url'], ENT_QUOTES); ?>"
                                class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                placeholder="https://exemplo.com/logo.png">
                            <p class="text-[10px] text-text-muted mt-1">Se vazio, o nome da empresa será exibido no lugar do logotipo.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Telefone Institucional</label>
                            <input type="text" name="empresa_telefone"
                                value="<?php echo htmlspecialchars($cfg['empresa_telefone'], ENT_QUOTES); ?>"
                                class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                placeholder="(13) 3000-0000">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Cor Primária *</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="empresa_cor" id="cor-input"
                                    value="<?php echo htmlspecialchars($cfg['empresa_cor'], ENT_QUOTES); ?>"
                                    class="w-10 h-10 rounded-lg border border-border cursor-pointer p-0.5">
                                <input type="text" id="cor-hex"
                                    value="<?php echo htmlspecialchars($cfg['empresa_cor'], ENT_QUOTES); ?>"
                                    class="flex-1 border border-border rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:border-primary transition-colors"
                                    maxlength="7" placeholder="#0d9488">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Endereço</label>
                            <input type="text" name="empresa_endereco"
                                value="<?php echo htmlspecialchars($cfg['empresa_endereco'], ENT_QUOTES); ?>"
                                class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                placeholder="Rua Exemplo, 123 – Santos/SP">
                        </div>
                    </div>
                </div>

                <!-- Disclaimer / Aviso Legal -->
                <div class="bg-white rounded-2xl border border-border p-6 shadow-sm mb-6">
                    <h2 class="text-sm font-black text-text uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="scroll-text" class="w-4 h-4 text-primary"></i>
                        Aviso Legal (Disclaimer)
                    </h2>
                    <p class="text-[10px] text-text-muted mb-4">Texto exibido no rodapé de todas as assinaturas. Deixe em branco para não exibir.</p>
                    <textarea name="disclaimer" rows="3"
                        class="w-full border border-border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-primary transition-colors resize-none"
                        placeholder="Esta mensagem e seus anexos são confidenciais..."><?php echo htmlspecialchars($cfg['disclaimer'], ENT_QUOTES); ?></textarea>
                </div>

                <!-- Botão salvar -->
                <div class="flex justify-end">
                    <button type="submit" name="salvar"
                        class="flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl text-sm font-black hover:bg-primary-hover transition-all shadow-lg shadow-primary/20">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Salvar Configurações
                    </button>
                </div>
            </form>
        </div>

        <?php include '../footer.php'; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();

        const colorInput = document.getElementById('cor-input');
        const hexInput   = document.getElementById('cor-hex');

        // Sincroniza color picker → campo texto
        colorInput.addEventListener('input', () => {
            hexInput.value = colorInput.value;
        });

        // Sincroniza campo texto → color picker
        hexInput.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                colorInput.value = hexInput.value;
                // Propaga para o campo hidden do form
                colorInput.name = 'empresa_cor';
                hexInput.name   = '';
            }
        });
        // Garante que o campo correto é submetido
        hexInput.name = '';
    });
    </script>
</body>
</html>
