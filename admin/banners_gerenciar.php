<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

// Garantir que a tabela existe
$conn->query("
    CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        imagem VARCHAR(255) NOT NULL,
        link_url VARCHAR(500) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$mensagem = '';
$tipo_mensagem = '';

// ──────────────────────────────────────────────
// PROCESSAR AÇÕES
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    // ── CRIAR ────────────────────────────────
    if ($acao == 'criar') {
        $titulo   = sanitize($_POST['titulo']);
        $link_url = !empty($_POST['link_url']) ? sanitize($_POST['link_url']) : null;
        $ativo    = isset($_POST['ativo']) ? 1 : 0;

        if (empty($titulo)) {
            $mensagem = 'O título / texto alternativo é obrigatório.';
            $tipo_mensagem = 'danger';
        } elseif (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            $mensagem = 'Selecione uma imagem para o banner.';
            $tipo_mensagem = 'danger';
        } else {
            $ext_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $ext_permitidas)) {
                $mensagem = 'Formato não permitido. Use JPG, PNG, GIF ou WEBP.';
                $tipo_mensagem = 'danger';
            } else {
                $dir = '../uploads/banners/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $nome_arquivo = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $destino = $dir . $nome_arquivo;

                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                    $stmt = $conn->prepare("INSERT INTO banners (titulo, imagem, link_url, ativo) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $titulo, $nome_arquivo, $link_url, $ativo);
                    if ($stmt->execute()) {
                        $mensagem = 'Banner criado com sucesso!';
                        $tipo_mensagem = 'success';
                        registrarLog($conn, 'Criou banner: ' . $titulo);
                    } else {
                        $mensagem = 'Erro ao salvar no banco: ' . $conn->error;
                        $tipo_mensagem = 'danger';
                    }
                    $stmt->close();
                } else {
                    $mensagem = 'Erro ao fazer upload da imagem.';
                    $tipo_mensagem = 'danger';
                }
            }
        }

    // ── EDITAR (só título, link e status) ────
    } elseif ($acao == 'editar') {
        $id       = intval($_POST['id']);
        $titulo   = sanitize($_POST['titulo']);
        $link_url = !empty($_POST['link_url']) ? sanitize($_POST['link_url']) : null;
        $ativo    = isset($_POST['ativo']) ? 1 : 0;

        // Verificar se enviou nova imagem
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $ext_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $ext_permitidas)) {
                $mensagem = 'Formato não permitido. Use JPG, PNG, GIF ou WEBP.';
                $tipo_mensagem = 'danger';
            } else {
                $dir = '../uploads/banners/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $nome_arquivo = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $destino = $dir . $nome_arquivo;

                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                    // Remover imagem antiga
                    $antiga = $conn->query("SELECT imagem FROM banners WHERE id = $id")->fetch_assoc();
                    if ($antiga && !empty($antiga['imagem'])) {
                        $caminho_antigo = $dir . $antiga['imagem'];
                        if (file_exists($caminho_antigo)) {
                            unlink($caminho_antigo);
                        }
                    }

                    $stmt = $conn->prepare("UPDATE banners SET titulo = ?, imagem = ?, link_url = ?, ativo = ? WHERE id = ?");
                    $stmt->bind_param("sssii", $titulo, $nome_arquivo, $link_url, $ativo, $id);
                } else {
                    $mensagem = 'Erro ao fazer upload da imagem.';
                    $tipo_mensagem = 'danger';
                    goto fim_edicao;
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE banners SET titulo = ?, link_url = ?, ativo = ? WHERE id = ?");
            $stmt->bind_param("ssii", $titulo, $link_url, $ativo, $id);
        }

        if (isset($stmt)) {
            if ($stmt->execute()) {
                $mensagem = 'Banner atualizado com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Editou banner ID: ' . $id);
            } else {
                $mensagem = 'Erro ao atualizar: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        fim_edicao:;

    // ── EXCLUIR ──────────────────────────────
    } elseif ($acao == 'excluir') {
        $id = intval($_POST['id']);
        $row = $conn->query("SELECT imagem FROM banners WHERE id = $id")->fetch_assoc();
        if ($row) {
            $caminho = '../uploads/banners/' . $row['imagem'];
            if (file_exists($caminho)) {
                unlink($caminho);
            }
        }
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = 'Banner excluído com sucesso!';
            $tipo_mensagem = 'success';
            registrarLog($conn, 'Excluiu banner ID: ' . $id);
        }
        $stmt->close();

    // ── TOGGLE ATIVO ─────────────────────────
    } elseif ($acao == 'toggle_ativo') {
        $id    = intval($_POST['id']);
        $atual = intval($_POST['ativo_atual']);
        $novo  = $atual == 1 ? 0 : 1;
        $conn->query("UPDATE banners SET ativo = $novo WHERE id = $id");
        $mensagem = $novo ? 'Banner ativado!' : 'Banner desativado!';
        $tipo_mensagem = 'success';
        registrarLog($conn, 'Alterou status do banner ID: ' . $id . ' para ' . ($novo ? 'ativo' : 'inativo'));
    }
}

// Buscar todos os banners
$banners = $conn->query("SELECT * FROM banners ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Banners - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>

    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">

        <!-- Header -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="image" class="w-6 h-6"></i>
                    Banners do Dashboard
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gerencie as imagens exibidas no painel principal dos colaboradores.</p>
            </div>
            <button onclick="abrirModalCriar()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Novo Banner
            </button>
        </div>

        <!-- Mensagem de feedback -->
        <?php if ($mensagem): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2
            <?php echo $tipo_mensagem == 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
            <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4 flex-shrink-0"></i>
            <?php echo $mensagem; ?>
        </div>
        <?php endif; ?>

        <!-- Info sobre comportamento -->
        <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-start gap-3 text-xs text-blue-700">
            <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
            <span>O <strong>banner ativo mais recente</strong> é exibido no dashboard, ao lado do card "RH & Holerites". Se nenhum banner estiver ativo, o espaço não aparece.</span>
        </div>

        <!-- Tabela de Banners -->
        <?php if ($banners->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php while ($b = $banners->fetch_assoc()): ?>
            <div class="bg-white rounded-2xl border <?php echo $b['ativo'] ? 'border-primary/30 ring-1 ring-primary/10' : 'border-border'; ?> shadow-sm overflow-hidden flex flex-col group hover:shadow-md transition-all">
                <!-- Preview da imagem -->
                <div class="relative h-40 bg-gray-100 overflow-hidden">
                    <img src="../uploads/banners/<?php echo htmlspecialchars($b['imagem']); ?>"
                         alt="<?php echo htmlspecialchars($b['titulo']); ?>"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <!-- Badge ativo/inativo -->
                    <div class="absolute top-2 left-2">
                        <?php if ($b['ativo']): ?>
                            <span class="px-2 py-0.5 bg-emerald-500 text-white text-[9px] font-black uppercase tracking-widest rounded-full shadow">Ativo</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 bg-gray-400 text-white text-[9px] font-black uppercase tracking-widest rounded-full shadow">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dados do banner -->
                <div class="p-4 flex flex-col flex-grow">
                    <h3 class="text-sm font-bold text-text mb-1 truncate"><?php echo htmlspecialchars($b['titulo']); ?></h3>
                    <?php if (!empty($b['link_url'])): ?>
                    <a href="<?php echo htmlspecialchars($b['link_url']); ?>" target="_blank"
                       class="text-[10px] text-primary underline truncate mb-2">
                        <?php echo htmlspecialchars($b['link_url']); ?>
                    </a>
                    <?php else: ?>
                    <span class="text-[10px] text-text-secondary mb-2 italic">Sem link</span>
                    <?php endif; ?>
                    <span class="text-[9px] text-text-secondary mt-auto">Criado em: <?php echo date('d/m/Y H:i', strtotime($b['created_at'])); ?></span>
                </div>

                <!-- Ações -->
                <div class="px-4 pb-4 flex items-center gap-2">
                    <!-- Toggle ativo -->
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="acao" value="toggle_ativo">
                        <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                        <input type="hidden" name="ativo_atual" value="<?php echo $b['ativo']; ?>">
                        <button type="submit" class="w-full py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all
                            <?php echo $b['ativo'] ? 'bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100'; ?>">
                            <?php echo $b['ativo'] ? 'Desativar' : 'Ativar'; ?>
                        </button>
                    </form>

                    <!-- Editar -->
                    <button onclick='abrirModalEditar(<?php echo json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'
                            class="px-3 py-1.5 bg-blue-50 text-blue-600 border border-blue-200 rounded-lg text-[10px] font-black uppercase hover:bg-blue-100 transition-all">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>

                    <!-- Excluir -->
                    <form method="POST" onsubmit="return confirm('Excluir este banner? A imagem também será apagada.');">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded-lg text-[10px] font-black uppercase hover:bg-red-100 transition-all">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl border border-border shadow-sm p-16 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
                <i data-lucide="image-off" class="w-8 h-8 text-text-secondary opacity-40"></i>
            </div>
            <h3 class="text-sm font-bold text-text mb-1">Nenhum banner cadastrado</h3>
            <p class="text-xs text-text-secondary mb-6">Clique em "Novo Banner" para adicionar a primeira imagem.</p>
            <button onclick="abrirModalCriar()" class="bg-primary text-white px-6 py-2 rounded-lg text-xs font-bold shadow-md hover:bg-primary-hover transition-all active:scale-95">
                Adicionar Banner
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../footer.php'; ?>

    <!-- ──────────────────────────────────────────
         MODAL: CRIAR BANNER
    ─────────────────────────────────────────── -->
    <div id="modalCriar" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4" onclick="fecharModalCriar(event)">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden" onclick="event.stopPropagation()">
            <div class="bg-primary p-6 text-white">
                <h2 class="text-lg font-black flex items-center gap-2">
                    <i data-lucide="image-plus" class="w-5 h-5"></i>
                    Novo Banner
                </h2>
                <p class="text-white/70 text-xs mt-1">Faça o upload de uma imagem para exibir no dashboard.</p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                <input type="hidden" name="acao" value="criar">

                <!-- Preview da imagem -->
                <div id="previewCriar" class="hidden h-40 bg-gray-100 rounded-xl overflow-hidden border border-border">
                    <img id="previewCriarImg" src="" alt="Preview" class="w-full h-full object-cover">
                </div>

                <!-- Upload -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Imagem do Banner <span class="text-red-500">*</span></label>
                    <input type="file" name="imagem" id="imagemCriar" accept="image/jpeg,image/png,image/gif,image/webp"
                           onchange="mostrarPreview(this, 'previewCriar', 'previewCriarImg')"
                           class="w-full text-xs text-text-secondary file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer border border-border rounded-lg p-2 bg-background" required>
                    <p class="text-[9px] text-text-secondary mt-1">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho recomendado: 800×300px.</p>
                </div>

                <!-- Título / Alt -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Título / Texto Alternativo <span class="text-red-500">*</span></label>
                    <input type="text" name="titulo" placeholder="Ex: Campanha de Vacinação Junho 2026"
                           class="w-full px-3 py-2 border border-border rounded-lg text-sm focus:outline-none focus:border-primary bg-background transition-all" required>
                </div>

                <!-- Link URL (opcional) -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Link ao Clicar (opcional)</label>
                    <input type="url" name="link_url" placeholder="https://exemplo.com.br"
                           class="w-full px-3 py-2 border border-border rounded-lg text-sm focus:outline-none focus:border-primary bg-background transition-all">
                    <p class="text-[9px] text-text-secondary mt-1">Se preenchido, o banner será clicável e abrirá o link em nova aba.</p>
                </div>

                <!-- Ativo -->
                <div class="flex items-center gap-3 p-3 bg-background rounded-xl border border-border">
                    <input type="checkbox" name="ativo" id="ativoCriar" checked class="w-4 h-4 accent-primary cursor-pointer">
                    <label for="ativoCriar" class="text-xs font-bold text-text cursor-pointer">Ativar este banner imediatamente</label>
                </div>

                <!-- Botões -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="fecharModalCriar()" class="flex-1 py-2.5 border border-border rounded-xl text-xs font-bold text-text-secondary hover:bg-gray-50 transition-all">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-md transition-all active:scale-95">Salvar Banner</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ──────────────────────────────────────────
         MODAL: EDITAR BANNER
    ─────────────────────────────────────────── -->
    <div id="modalEditar" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4" onclick="fecharModalEditar(event)">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden" onclick="event.stopPropagation()">
            <div class="bg-primary p-6 text-white">
                <h2 class="text-lg font-black flex items-center gap-2">
                    <i data-lucide="pencil" class="w-5 h-5"></i>
                    Editar Banner
                </h2>
                <p class="text-white/70 text-xs mt-1">Altere as informações ou substitua a imagem.</p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editarId">

                <!-- Preview atual -->
                <div id="previewEditarContainer" class="h-40 bg-gray-100 rounded-xl overflow-hidden border border-border">
                    <img id="previewEditarImg" src="" alt="Preview" class="w-full h-full object-cover">
                </div>

                <!-- Upload nova imagem (opcional) -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Nova Imagem (opcional)</label>
                    <input type="file" name="imagem" id="imagemEditar" accept="image/jpeg,image/png,image/gif,image/webp"
                           onchange="mostrarPreview(this, 'previewEditarContainer', 'previewEditarImg')"
                           class="w-full text-xs text-text-secondary file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer border border-border rounded-lg p-2 bg-background">
                    <p class="text-[9px] text-text-secondary mt-1">Deixe em branco para manter a imagem atual.</p>
                </div>

                <!-- Título -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Título / Texto Alternativo <span class="text-red-500">*</span></label>
                    <input type="text" name="titulo" id="editarTitulo"
                           class="w-full px-3 py-2 border border-border rounded-lg text-sm focus:outline-none focus:border-primary bg-background transition-all" required>
                </div>

                <!-- Link URL -->
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">Link ao Clicar (opcional)</label>
                    <input type="url" name="link_url" id="editarLinkUrl" placeholder="https://exemplo.com.br"
                           class="w-full px-3 py-2 border border-border rounded-lg text-sm focus:outline-none focus:border-primary bg-background transition-all">
                </div>

                <!-- Ativo -->
                <div class="flex items-center gap-3 p-3 bg-background rounded-xl border border-border">
                    <input type="checkbox" name="ativo" id="editarAtivo" class="w-4 h-4 accent-primary cursor-pointer">
                    <label for="editarAtivo" class="text-xs font-bold text-text cursor-pointer">Banner ativo (visível no dashboard)</label>
                </div>

                <!-- Botões -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="fecharModalEditar()" class="flex-1 py-2.5 border border-border rounded-xl text-xs font-bold text-text-secondary hover:bg-gray-50 transition-all">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-md transition-all active:scale-95">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── Modais ──────────────────────────────────
        function abrirModalCriar() {
            document.getElementById('modalCriar').classList.remove('hidden');
        }
        function fecharModalCriar(e) {
            if (!e || e.target === document.getElementById('modalCriar')) {
                document.getElementById('modalCriar').classList.add('hidden');
            }
        }

        function abrirModalEditar(banner) {
            document.getElementById('editarId').value         = banner.id;
            document.getElementById('editarTitulo').value     = banner.titulo;
            document.getElementById('editarLinkUrl').value    = banner.link_url || '';
            document.getElementById('editarAtivo').checked    = banner.ativo == 1;
            document.getElementById('previewEditarImg').src   = '../uploads/banners/' + banner.imagem;
            document.getElementById('modalEditar').classList.remove('hidden');
        }
        function fecharModalEditar(e) {
            if (!e || e.target === document.getElementById('modalEditar')) {
                document.getElementById('modalEditar').classList.add('hidden');
            }
        }

        // ── Preview de imagem ───────────────────────
        function mostrarPreview(input, containerId, imgId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(containerId).classList.remove('hidden');
                    document.getElementById(imgId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
