<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Upload ou Exclusão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'upload') {
        $titulo = sanitize($_POST['titulo']);
        $descricao = $_POST['descricao'];
        $categoria = sanitize($_POST['categoria']);
        $usuario_id = $_SESSION['usuario_id'];

        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
            $extensao = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'txt'];

            if (in_array($extensao, $permitidos)) {
                $novo_nome = uniqid() . '.' . $extensao;
                $destino = '../uploads/biblioteca/' . $novo_nome;

                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                    $stmt = $conn->prepare("INSERT INTO biblioteca (titulo, descricao, arquivo_path, categoria, usuario_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $titulo, $descricao, $novo_nome, $categoria, $usuario_id);

                    if ($stmt->execute()) {
                        $mensagem = "Documento enviado com sucesso!";
                        $tipo_mensagem = "success";
                        registrarLog($conn, "Enviou documento: $titulo");
                    } else {
                        $mensagem = "Erro ao salvar no banco: " . $conn->error;
                        $tipo_mensagem = "danger";
                    }
                } else {
                    $mensagem = "Erro ao mover arquivo.";
                    $tipo_mensagem = "danger";
                }
            } else {
                $mensagem = "Extensão não permitida.";
                $tipo_mensagem = "danger";
            }
        }
    } elseif ($_POST['acao'] == 'editar') {
        $id = intval($_POST['id']);
        $titulo = sanitize($_POST['titulo']);
        $descricao = $_POST['descricao'];
        $categoria = sanitize($_POST['categoria']);

        // Se enviou novo arquivo
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
            $extensao = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'txt'];

            if (in_array($extensao, $permitidos)) {
                // Remover arquivo antigo
                $res = $conn->query("SELECT arquivo_path FROM biblioteca WHERE id = $id");
                if ($old = $res->fetch_assoc()) {
                    $arquivo_antigo = '../uploads/biblioteca/' . $old['arquivo_path'];
                    if (file_exists($arquivo_antigo)) unlink($arquivo_antigo);
                }

                $novo_nome = uniqid() . '.' . $extensao;
                $destino = '../uploads/biblioteca/' . $novo_nome;

                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                    $stmt = $conn->prepare("UPDATE biblioteca SET titulo = ?, descricao = ?, arquivo_path = ?, categoria = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $titulo, $descricao, $novo_nome, $categoria, $id);
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE biblioteca SET titulo = ?, descricao = ?, categoria = ? WHERE id = ?");
            $stmt->bind_param("sssi", $titulo, $descricao, $categoria, $id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $mensagem = "Documento atualizado com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Editou documento: $titulo");
        } else {
            $mensagem = "Erro ao atualizar: " . ($conn->error ?: "Erro no upload");
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'excluir') {
        $id = intval($_POST['id']);
        $res = $conn->query("SELECT arquivo_path, titulo FROM biblioteca WHERE id = $id");
        if ($doc = $res->fetch_assoc()) {
            $arquivo = '../uploads/biblioteca/' . $doc['arquivo_path'];
            if (file_exists($arquivo)) unlink($arquivo);

            $conn->query("DELETE FROM biblioteca WHERE id = $id");
            $mensagem = "Documento excluído!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Excluiu documento: " . $doc['titulo']);
        }
    }
}

// Buscar documentos
$documentos = $conn->query("SELECT b.*, u.nome as autor FROM biblioteca b JOIN usuarios u ON b.usuario_id = u.id ORDER BY b.data_upload DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Biblioteca - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="library" class="w-6 h-6"></i>
                    Gestão da Biblioteca
                </h1>
                <p class="text-text-secondary text-xs mt-1">Upload e organização de documentos institucionais</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar
                </a>
                <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Novo Documento
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-background/50 border-b border-border">
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Documento</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Categoria</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Enviado por</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Data</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    <?php while ($doc = $documentos->fetch_assoc()): ?>
                    <tr class="hover:bg-background/20 transition-colors group">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-primary border border-border">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-text"><?php echo $doc['titulo']; ?></span>
                                    <span class="text-[10px] text-text-secondary italic"><?php echo $doc['descricao']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 text-[9px] font-black uppercase tracking-widest border border-blue-100">
                                <?php echo $doc['categoria']; ?>
                            </span>
                        </td>
                        <td class="p-3 font-semibold text-text-secondary"><?php echo $doc['autor']; ?></td>
                        <td class="p-3 text-text-secondary opacity-60 font-mono"><?php echo date('d/m/Y', strtotime($doc['data_upload'])); ?></td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-1.5 opacity-40 group-hover:opacity-100 transition-opacity">
                                <a href="../uploads/biblioteca/<?php echo $doc['arquivo_path']; ?>" target="_blank" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition-all" title="Ver Arquivo">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <button onclick='editarDoc(<?php echo json_encode($doc); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all" title="Editar Metadados">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                <button onclick="excluirDoc(<?php echo $doc['id']; ?>, '<?php echo $doc['titulo']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all" title="Excluir">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Novo Documento -->
    <div id="modalNovo" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 id="modalTitulo" class="text-base font-bold">Enviar Documento</h2>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <form id="formDoc" method="POST" action="" enctype="multipart/form-data" class="p-5 space-y-4">
                <input type="hidden" name="acao" id="acao" value="upload">
                <input type="hidden" name="id" id="doc_id">
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título do Documento</label>
                    <input type="text" name="titulo" id="titulo" required placeholder="Ex: Manual de Conduta" 
                           class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                    <select name="categoria" id="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                        <option value="Manual">Manual</option>
                        <option value="POP">POP (Procedimento)</option>
                        <option value="Políticas">Políticas</option>
                        <option value="Formulários">Formulários</option>
                        <option value="Comunicados">Comunicados</option>
                        <option value="Outros">Outros</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição / Observações</label>
                    <textarea name="descricao" id="descricao" rows="2" placeholder="Breve resumo do conteúdo..."
                              class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Arquivo</label>
                    <input type="file" name="arquivo" id="inputArquivo"
                           class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-black file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                    <p id="labelArquivo" class="text-[9px] text-text-secondary mt-1">PDF, DOCX, XLSX, JPG, PNG (Max 10MB)</p>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Finalizar Envio</button>
                </div>
            </form>
        </div>
    </div>

    <form id="formExcluir" method="POST" action="" style="display:none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="excluir_id">
    </form>

    <script>
        function abrirModal() { 
            document.getElementById('modalTitulo').textContent = 'Enviar Documento';
            document.getElementById('acao').value = 'upload';
            document.getElementById('doc_id').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('descricao').value = '';
            document.getElementById('categoria').value = 'Manual';
            document.getElementById('inputArquivo').required = true;
            document.getElementById('labelArquivo').textContent = 'PDF, DOCX, XLSX, JPG, PNG (Max 10MB)';
            document.getElementById('modalNovo').classList.add('active'); 
        }

        function editarDoc(doc) {
            document.getElementById('modalTitulo').textContent = 'Editar Documento';
            document.getElementById('acao').value = 'editar';
            document.getElementById('doc_id').value = doc.id;
            document.getElementById('titulo').value = doc.titulo;
            document.getElementById('descricao').value = doc.descricao;
            document.getElementById('categoria').value = doc.categoria;
            document.getElementById('inputArquivo').required = false;
            document.getElementById('labelArquivo').textContent = 'Deixe em branco para manter o arquivo atual';
            document.getElementById('modalNovo').classList.add('active');
        }

        function fecharModal() { document.getElementById('modalNovo').classList.remove('active'); }
        
        function excluirDoc(id, titulo) {
            if (confirm('Deseja excluir o documento "' + titulo + '"?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
