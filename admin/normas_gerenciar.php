<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = "";
$tipo_mensagem = "";

// Processar Cadastro/Edição
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'salvar') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $titulo = sanitize($_POST['titulo']);
        $descricao = sanitize($_POST['descricao']);
        $data_publicacao = $_POST['data_publicacao'];
        $autor_id = $_SESSION['usuario_id'];
        
        $arquivo_path = $_POST['arquivo_atual'] ?? '';

        // Upload de Arquivo
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
            $diretorio = "../uploads/normas/";
            if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
            
            $extensao = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
            $nome_arquivo = time() . "_" . uniqid() . "." . $extensao;
            $caminho_completo = $diretorio . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminho_completo)) {
                $arquivo_path = "uploads/normas/" . $nome_arquivo;
            }
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE normas_procedimentos SET titulo = ?, descricao = ?, arquivo_path = ?, data_publicacao = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $titulo, $descricao, $arquivo_path, $data_publicacao, $id);
            $acao_txt = "Atualizou";
        } else {
            $stmt = $conn->prepare("INSERT INTO normas_procedimentos (titulo, descricao, arquivo_path, data_publicacao, autor_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $titulo, $descricao, $arquivo_path, $data_publicacao, $autor_id);
            $acao_txt = "Cadastrou";
        }

        if ($stmt->execute()) {
            $mensagem = "Norma $acao_txt com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "$acao_txt norma: $titulo");
        } else {
            $mensagem = "Erro ao salvar: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'excluir') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM normas_procedimentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = "Norma removida com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Excluiu norma ID: $id");
        }
    } elseif ($_POST['acao'] == 'alternar_status') {
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);
        $stmt = $conn->prepare("UPDATE normas_procedimentos SET ativo = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        $stmt->execute();
    }
}

$normas = $conn->query("SELECT n.*, u.nome as autor_nome FROM normas_procedimentos n LEFT JOIN usuarios u ON n.autor_id = u.id ORDER BY n.data_publicacao DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Normas - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="shield-check"></i>
                    Normas e Procedimentos (Diretoria)
                </h1>
                <p class="text-text-secondary text-xs">Gestão de documentos oficiais e diretrizes</p>
            </div>
            <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 shadow-md transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i> Nova Norma
            </button>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 bg-green-50 border-green-100 text-green-700 text-xs font-bold animate-in slide-in-from-top-2">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-background/50 border-b border-border">
                        <th class="p-3 font-black text-text-secondary uppercase tracking-widest">Título / Data</th>
                        <th class="p-3 font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                        <th class="p-3 font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php while ($n = $normas->fetch_assoc()): ?>
                    <tr class="hover:bg-background/30 transition-colors">
                        <td class="p-3">
                            <div class="font-bold text-text"><?php echo $n['titulo']; ?></div>
                            <div class="text-[10px] text-text-secondary opacity-70">Publicado em: <?php echo date('d/m/Y', strtotime($n['data_publicacao'])); ?></div>
                        </td>
                        <td class="p-3 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="acao" value="alternar_status">
                                <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $n['ativo'] ? 0 : 1; ?>">
                                <button type="submit" class="px-2 py-0.5 rounded text-[10px] font-black uppercase border <?php echo $n['ativo'] ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-gray-50 text-gray-400 border-gray-200'; ?>">
                                    <?php echo $n['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2">
                                <?php if ($n['arquivo_path']): ?>
                                    <a href="../<?php echo $n['arquivo_path']; ?>" target="_blank" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded" title="Ver Arquivo">
                                        <i data-lucide="file-text" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                                <button onclick='editarNorma(<?php echo json_encode($n); ?>)' class="p-1.5 text-amber-500 hover:bg-amber-50 rounded" title="Editar">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir esta norma permanentemente?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Cadastro/Edição -->
    <div id="modalNorma" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-200">
            <div class="p-4 border-b border-border flex justify-between items-center bg-background/50">
                <h3 id="modalTitle" class="font-bold text-primary flex items-center gap-2 text-sm uppercase tracking-tighter">Nova Norma</h3>
                <button onclick="fecharModal()" class="text-text-secondary hover:text-rose-500 transition-colors"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="form_id">
                <input type="hidden" name="arquivo_atual" id="form_arquivo_atual">
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Título da Norma</label>
                    <input type="text" name="titulo" id="form_titulo" required class="w-full p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:border-primary focus:outline-none transition-all">
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Data de Publicação</label>
                    <input type="date" name="data_publicacao" id="form_data" required class="w-full p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Descrição / Notas</label>
                    <textarea name="descricao" id="form_desc" rows="3" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs font-bold focus:border-primary focus:outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Arquivo (PDF/DOCX)</label>
                    <input type="file" name="arquivo" class="w-full text-xs text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                    <p id="label_arquivo_atual" class="text-[9px] text-emerald-600 mt-1 font-bold hidden italic"></p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border">
                    <button type="button" onclick="fecharModal()" class="px-4 py-2 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-xl text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Salvar Norma</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById('form_id').value = '';
            document.getElementById('form_titulo').value = '';
            document.getElementById('form_data').value = '<?php echo date("Y-m-d"); ?>';
            document.getElementById('form_desc').value = '';
            document.getElementById('modalTitle').textContent = 'Nova Norma';
            document.getElementById('label_arquivo_atual').classList.add('hidden');
            document.getElementById('modalNorma').classList.replace('hidden', 'flex');
        }

        function fecharModal() {
            document.getElementById('modalNorma').classList.replace('flex', 'hidden');
        }

        function editarNorma(n) {
            abrirModal();
            document.getElementById('form_id').value = n.id;
            document.getElementById('form_titulo').value = n.titulo;
            document.getElementById('form_data').value = n.data_publicacao;
            document.getElementById('form_desc').value = n.descricao;
            document.getElementById('form_arquivo_atual').value = n.arquivo_path;
            if (n.arquivo_path) {
                document.getElementById('label_arquivo_atual').textContent = 'Já possui arquivo anexado';
                document.getElementById('label_arquivo_atual').classList.remove('hidden');
            }
            document.getElementById('modalTitle').textContent = 'Editar Norma';
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>