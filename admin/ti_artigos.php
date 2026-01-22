<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Exclusão
if (isset($_GET['excluir']) && !empty($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM ti_artigos WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mensagem = "Artigo excluído com sucesso!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu artigo de TI ID: $id");
    } else {
        $mensagem = "Erro ao excluir artigo: " . $conn->error;
        $tipo_mensagem = "error";
    }
    $stmt->close();
}

// Processar Troca de Status (Ativo/Inativo)
if (isset($_GET['toggle_status']) && !empty($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $stmt = $conn->prepare("UPDATE ti_artigos SET ativo = NOT ativo WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ti_artigos.php');
    exit;
}

// Processar Cadastro/Edição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $titulo = sanitize($_POST['titulo']);
    $categoria = sanitize($_POST['categoria']);
    $conteudo = $_POST['conteudo']; 
    $video_url = sanitize($_POST['video_url']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $autor_id = $_SESSION['usuario_id'];

    // Lógica de Upload de Imagem
    $imagem_path = $_POST['imagem_atual'] ?? '';
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $dir_img = '../uploads/ti_imagens/';
        if (!is_dir($dir_img)) mkdir($dir_img, 0777, true);
        
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome_imagem = uniqid('ti_img_') . '.' . $ext;
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $dir_img . $novo_nome_imagem)) {
            $imagem_path = $novo_nome_imagem;
        }
    }

    // Lógica de Upload de Anexo
    $anexo_path = $_POST['anexo_atual'] ?? '';
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $dir_anexo = '../uploads/ti_anexos/';
        if (!is_dir($dir_anexo)) mkdir($dir_anexo, 0777, true);

        $ext = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
        $novo_nome_anexo = uniqid('ti_doc_') . '.' . $ext;
        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $dir_anexo . $novo_nome_anexo)) {
            $anexo_path = $novo_nome_anexo;
        }
    }

    if ($id > 0) {
        // Editar
        $stmt = $conn->prepare("UPDATE ti_artigos SET titulo = ?, categoria = ?, conteudo = ?, video_url = ?, anexo_path = ?, imagem_path = ?, ativo = ? WHERE id = ?");
        $stmt->bind_param("ssssssii", $titulo, $categoria, $conteudo, $video_url, $anexo_path, $imagem_path, $ativo, $id);
        if ($stmt->execute()) {
            $mensagem = "Artigo atualizado com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Editou artigo de TI: $titulo");
        } else {
            $mensagem = "Erro ao atualizar artigo: " . $conn->error;
            $tipo_mensagem = "error";
        }
    } else {
        // Novo
        $stmt = $conn->prepare("INSERT INTO ti_artigos (titulo, categoria, conteudo, video_url, anexo_path, imagem_path, ativo, autor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssii", $titulo, $categoria, $conteudo, $video_url, $anexo_path, $imagem_path, $ativo, $autor_id);
        if ($stmt->execute()) {
            $mensagem = "Artigo cadastrado com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Criou novo artigo de TI: $titulo");
        } else {
            $mensagem = "Erro ao cadastrar artigo: " . $conn->error;
            $tipo_mensagem = "error";
        }
    }
    if (isset($stmt)) $stmt->close();
}

// Buscar dados para edição
$artigo_edit = null;
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $id_edit = (int)$_GET['editar'];
    $res = $conn->query("SELECT * FROM ti_artigos WHERE id = $id_edit");
    $artigo_edit = $res->fetch_assoc();
}

// Listar artigos
$artigos = $conn->query("SELECT a.*, u.nome as autor_nome FROM ti_artigos a LEFT JOIN usuarios u ON a.autor_id = u.id ORDER BY a.created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar TI (Artigos) - Admin</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="max-w-6xl mx-auto">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
                            <i data-lucide="monitor" class="w-8 h-8"></i>
                            Gerenciar Artigos de TI
                        </h1>
                        <p class="text-text-secondary text-sm">Biblioteca de ajuda e procedimentos de Tecnologia da Informação</p>
                    </div>
                </div>

                <?php if ($mensagem): ?>
                    <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                        <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                        <span class="text-sm font-medium"><?php echo $mensagem; ?></span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Formulário -->
                    <div class="lg:col-span-1">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-border sticky top-8">
                            <h2 class="text-lg font-bold mb-6 flex items-center gap-2 text-text">
                                <i data-lucide="<?php echo $artigo_edit ? 'edit' : 'plus-circle'; ?>" class="w-5 h-5 text-primary"></i>
                                <?php echo $artigo_edit ? 'Editar Artigo' : 'Novo Artigo'; ?>
                            </h2>
                            
                            <form action="ti_artigos.php" method="POST" class="space-y-4" enctype="multipart/form-data">
                                <?php if ($artigo_edit): ?>
                                    <input type="hidden" name="id" value="<?php echo $artigo_edit['id']; ?>">
                                    <input type="hidden" name="imagem_atual" value="<?php echo $artigo_edit['imagem_path']; ?>">
                                    <input type="hidden" name="anexo_atual" value="<?php echo $artigo_edit['anexo_path']; ?>">
                                <?php endif; ?>
                                
                                <div>
                                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Título do Artigo</label>
                                    <input type="text" name="titulo" required value="<?php echo $artigo_edit ? $artigo_edit['titulo'] : ''; ?>" class="w-full px-4 py-2 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-sm" placeholder="Ex: Como configurar o e-mail">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Categoria</label>
                                        <select name="categoria" class="w-full px-4 py-2 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-sm">
                                            <option value="Sistemas" <?php echo ($artigo_edit && $artigo_edit['categoria'] == 'Sistemas') ? 'selected' : ''; ?>>Sistemas</option>
                                            <option value="Hardware" <?php echo ($artigo_edit && $artigo_edit['categoria'] == 'Hardware') ? 'selected' : ''; ?>>Hardware</option>
                                            <option value="Rede" <?php echo ($artigo_edit && $artigo_edit['categoria'] == 'Rede') ? 'selected' : ''; ?>>Rede</option>
                                            <option value="E-mail" <?php echo ($artigo_edit && $artigo_edit['categoria'] == 'E-mail') ? 'selected' : ''; ?>>E-mail</option>
                                            <option value="Geral" <?php echo ($artigo_edit && $artigo_edit['categoria'] == 'Geral') ? 'selected' : ''; ?>>Geral</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">URL do Vídeo</label>
                                        <input type="url" name="video_url" value="<?php echo $artigo_edit ? $artigo_edit['video_url'] : ''; ?>" class="w-full px-4 py-2 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-sm" placeholder="Ex: YouTube Link">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Imagem de Destaque</label>
                                        <input type="file" name="imagem" accept="image/*" class="w-full px-4 py-1.5 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-xs file:hidden">
                                        <?php if ($artigo_edit && $artigo_edit['imagem_path']): ?>
                                            <p class="text-[9px] text-primary font-bold mt-1">✓ Tem imagem salva</p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Anexo / Arquivo</label>
                                        <input type="file" name="anexo" class="w-full px-4 py-1.5 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-xs file:hidden">
                                        <?php if ($artigo_edit && $artigo_edit['anexo_path']): ?>
                                            <p class="text-[9px] text-primary font-bold mt-1">✓ Tem anexo salvo</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Conteúdo</label>
                                    <textarea name="conteudo" required rows="6" class="w-full px-4 py-2 bg-background border border-border rounded-lg focus:outline-none focus:border-primary text-sm" placeholder="Descreva o procedimento..."><?php echo $artigo_edit ? $artigo_edit['conteudo'] : ''; ?></textarea>
                                </div>

                                <div class="flex items-center gap-2 py-2">
                                    <input type="checkbox" name="ativo" id="ativo" <?php echo (!$artigo_edit || $artigo_edit['ativo']) ? 'checked' : ''; ?> class="w-4 h-4 text-primary border-border rounded focus:ring-primary">
                                    <label for="ativo" class="text-xs font-bold text-text-secondary cursor-pointer">Artigo Publicado (Ativo)</label>
                                </div>

                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="flex-1 bg-primary hover:bg-primary-dark text-white font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-2">
                                        <i data-lucide="save" class="w-4 h-4"></i>
                                        <?php echo $artigo_edit ? 'Salvar Alterações' : 'Publicar Artigo'; ?>
                                    </button>
                                    <?php if ($artigo_edit): ?>
                                        <a href="ti_artigos.php" class="bg-gray-100 hover:bg-gray-200 text-text-secondary font-bold py-2 px-4 rounded-lg text-xs transition-all">Cancelar</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Listagem -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-sm border border-border overflow-hidden">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 border-b border-border">
                                    <tr>
                                        <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Artigo</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Categoria</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Status</th>
                                        <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <?php if ($artigos->num_rows > 0): ?>
                                        <?php while($row = $artigos->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-bold text-text"><?php echo $row['titulo']; ?></span>
                                                        <span class="text-[10px] text-text-secondary mt-1">Por: <?php echo $row['autor_nome']; ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 bg-primary/5 text-primary text-[10px] font-black rounded uppercase"><?php echo $row['categoria']; ?></span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <a href="ti_artigos.php?toggle_status=<?php echo $row['id']; ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $row['ativo'] ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'; ?> hover:opacity-80 transition-opacity">
                                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['ativo'] ? 'bg-emerald-600' : 'bg-red-600'; ?>"></span>
                                                        <?php echo $row['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <a href="ti_artigos.php?editar=<?php echo $row['id']; ?>" class="p-1.5 text-text-secondary hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Editar">
                                                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                        </a>
                                                        <button onclick="confirmarExclusao(<?php echo $row['id']; ?>, '<?php echo $row['titulo']; ?>')" class="p-1.5 text-text-secondary hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-text-secondary text-sm italic">Nenhum artigo cadastrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <script>
    function confirmarExclusao(id, titulo) {
        if (confirm('Deseja realmente excluir o artigo "' + titulo + '"?')) {
            window.location.href = 'ti_artigos.php?excluir=' + id;
        }
    }
    </script>

    <?php include '../footer.php'; ?>
    </div> <!-- Fecha o div pl-64 do header.php -->
</body>
</html>
