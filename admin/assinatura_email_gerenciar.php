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
    $campos = ['empresa_nome','empresa_site','empresa_cor',
               'empresa_endereco','empresa_telefone','disclaimer'];

    $stmt = $conn->prepare("INSERT INTO assinatura_config (chave, valor)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    foreach ($campos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        if ($campo === 'empresa_cor' && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            $valor = '#0d9488';
        }
        $stmt->bind_param("ss", $campo, $valor);
        $stmt->execute();
    }

    // ── Upload da logo ────────────────────────────────────────────────────────
    if (!empty($_FILES['logo_upload']['name'])) {
        $file     = $_FILES['logo_upload'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','svg','webp'];
        $max_size = 10 * 1024 * 1024; // 10 MB

        if (!in_array($ext, $allowed)) {
            $erro = 'Formato inválido. Use JPG, PNG, GIF, SVG ou WebP.';
        } elseif ($file['size'] > $max_size) {
            $erro = 'Arquivo muito grande. Máximo permitido: 10 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $erro = 'Erro no upload do arquivo.';
        } else {
            $dir = dirname(__DIR__) . '/uploads/logos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            // Remove logo anterior se for arquivo local
            $old_row = $conn->query("SELECT valor FROM assinatura_config WHERE chave = 'empresa_logo_url' LIMIT 1");
            if ($old_row && $old_row->num_rows > 0) {
                $old_val = $old_row->fetch_assoc()['valor'];
                if (strpos($old_val, 'uploads/logos/') === 0) {
                    $old_path = dirname(__DIR__) . '/' . $old_val;
                    if (file_exists($old_path)) unlink($old_path);
                }
            }

            $filename = 'logo_assinatura_' . time() . '.' . $ext;
            $dest     = $dir . $filename;
            $logo_url = 'uploads/logos/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $chave = 'empresa_logo_url';
                $stmt->bind_param("ss", $chave, $logo_url);
                $stmt->execute();
            } else {
                $erro = 'Falha ao salvar o arquivo no servidor.';
            }
        }
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

            <form method="POST" enctype="multipart/form-data">
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
                            <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Logo da Empresa (opcional)</label>

                            <?php if (!empty($cfg['empresa_logo_url'])): ?>
                            <div class="mb-3 flex items-center gap-4 p-3 bg-gray-50 border border-border rounded-xl">
                                <img src="<?php echo htmlspecialchars('../' . $cfg['empresa_logo_url'], ENT_QUOTES); ?>"
                                     alt="Logo atual" class="h-12 object-contain rounded">
                                <div>
                                    <p class="text-[10px] font-black text-text-muted uppercase tracking-widest">Logo atual</p>
                                    <p class="text-[11px] text-text-muted mt-0.5 break-all"><?php echo htmlspecialchars($cfg['empresa_logo_url'], ENT_QUOTES); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <label class="flex flex-col items-center justify-center w-full border-2 border-dashed border-border rounded-xl p-6 cursor-pointer hover:border-primary hover:bg-primary/[0.02] transition-all group" id="drop-zone">
                                <img id="logo-preview" src="#" alt="Pré-visualização" class="hidden h-16 object-contain mb-3 rounded">
                                <i data-lucide="upload-cloud" class="w-8 h-8 text-text-muted group-hover:text-primary transition-colors mb-2" id="upload-icon"></i>
                                <span class="text-sm font-bold text-text-muted group-hover:text-primary transition-colors" id="file-label">Clique ou arraste a imagem aqui</span>
                                <span class="text-[10px] text-text-muted mt-1">JPG, PNG, SVG, WebP — máx. 10 MB</span>
                                <input type="file" name="logo_upload" id="logo_upload" accept="image/*" class="hidden">
                            </label>

                            <p class="text-[10px] text-text-muted mt-1.5">Se nenhuma imagem for enviada, o nome da empresa será exibido no lugar do logotipo.</p>
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

        // ── Cor ──────────────────────────────────────────────────────────────
        const colorInput = document.getElementById('cor-input');
        const hexInput   = document.getElementById('cor-hex');
        colorInput.addEventListener('input', () => { hexInput.value = colorInput.value; });
        hexInput.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                colorInput.value = hexInput.value;
            }
        });
        hexInput.name = '';

        // ── Upload / Drag-and-drop da logo ───────────────────────────────────
        const fileInput = document.getElementById('logo_upload');
        const dropZone  = document.getElementById('drop-zone');
        const fileLabel = document.getElementById('file-label');
        const preview   = document.getElementById('logo-preview');

        function handleFile(file) {
            if (!file || !file.type.startsWith('image/')) return;
            fileLabel.textContent = file.name;
            const reader = new FileReader();
            reader.onload = e => {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
            };
            reader.readAsDataURL(file);
        }

        fileInput.addEventListener('change', () => handleFile(fileInput.files[0]));

        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('border-primary','bg-primary/[0.03]');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-primary','bg-primary/[0.03]');
        });
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('border-primary','bg-primary/[0.03]');
            const file = e.dataTransfer.files[0];
            if (file) {
                // Transfere o arquivo para o input
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                handleFile(file);
            }
        });
    });
    </script>
</body>
</html>
