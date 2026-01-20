<?php
require_once '../config.php';
require_once '../functions.php';

requireEduAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Ações do Curso (Adicionar, Editar, Excluir)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'salvar_curso') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $titulo = sanitize($_POST['titulo']);
        $instrutor = sanitize($_POST['instrutor']);
        $descricao = $_POST['descricao'];
        $capa = sanitize($_POST['capa_atual'] ?? '');
        
        // Processar Upload de Capa
        if (isset($_FILES['capa_arquivo']) && $_FILES['capa_arquivo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['capa_arquivo']['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'capa_' . time() . '_' . uniqid() . '.' . $ext;
            $destino = '../uploads/educacao/' . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['capa_arquivo']['tmp_name'], $destino)) {
                $capa = 'uploads/educacao/' . $nome_arquivo;
            }
        }

        $carga_horaria = sanitize($_POST['carga_horaria']);
        $status = intval($_POST['status']);

        if ($id) {
            $stmt = $conn->prepare("UPDATE edu_cursos SET titulo = ?, instrutor = ?, descricao = ?, capa = ?, carga_horaria = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssii", $titulo, $instrutor, $descricao, $capa, $carga_horaria, $status, $id);
        } else {
            $usuario_id = $_SESSION['usuario_id'];
            $stmt = $conn->prepare("INSERT INTO edu_cursos (titulo, instrutor, descricao, capa, carga_horaria, status, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssii", $titulo, $instrutor, $descricao, $capa, $carga_horaria, $status, $usuario_id);
        }

        if ($stmt->execute()) {
            $mensagem = "Curso salvo com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao salvar curso: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'excluir_curso') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM edu_cursos WHERE id = $id");
        $mensagem = "Curso excluído!";
        $tipo_mensagem = "success";
    } elseif ($_POST['acao'] == 'salvar_aula') {
        $id = isset($_POST['aula_id']) && !empty($_POST['aula_id']) ? intval($_POST['aula_id']) : null;
        $curso_id = intval($_POST['curso_id']);
        $titulo = sanitize($_POST['aula_titulo']);
        $tipo = $_POST['aula_tipo'];
        $conteudo = $_POST['aula_conteudo']; // Sem sanitize para permitir HTML do TinyMCE
        $ordem = intval($_POST['aula_ordem']);

        if ($id) {
            $stmt = $conn->prepare("UPDATE edu_aulas SET titulo = ?, tipo = ?, conteudo = ?, ordem = ? WHERE id = ?");
            $stmt->bind_param("sssii", $titulo, $tipo, $conteudo, $ordem, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO edu_aulas (curso_id, titulo, tipo, conteudo, ordem) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $curso_id, $titulo, $tipo, $conteudo, $ordem);
        }
        
        if ($stmt->execute()) {
            $mensagem = "Aula salva!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao salvar aula: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'excluir_aula') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM edu_aulas WHERE id = $id");
        $mensagem = "Aula removida!";
        $tipo_mensagem = "success";
    }
}

// Buscar cursos
$where_owner = "";
if (!isAdmin()) {
    $uid = $_SESSION['usuario_id'];
    $where_owner = " WHERE usuario_id = $uid";
}
$cursos = $conn->query("SELECT * FROM edu_cursos $where_owner ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cursos - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
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
                    <i data-lucide="book-open" class="w-6 h-6"></i>
                    Gestão de Cursos & Aulas
                </h1>
                <p class="text-text-secondary text-xs mt-1">Configure o conteúdo programático e módulos dos cursos</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Painel Geral
                </a>
                <button onclick="abrirModalCurso()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Novo Curso
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6">
            <?php while ($c = $cursos->fetch_assoc()): ?>
            <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
                <div class="p-5 border-b border-border bg-gray-50/50 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <?php if ($c['capa']): ?>
                            <img src="../<?php echo $c['capa']; ?>" class="w-12 h-12 rounded-lg object-cover border border-border">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center border border-border border-dashed">
                                <i data-lucide="image" class="w-5 h-5 text-text-secondary opacity-30"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="text-base font-bold text-text"><?php echo $c['titulo']; ?></h3>
                            <p class="text-[10px] text-primary font-bold uppercase tracking-tight mb-1"><?php echo $c['instrutor'] ?: 'Autor não informado'; ?></p>
                            <div class="flex items-center gap-3 text-[10px] font-bold text-text-secondary uppercase">
                                <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> <?php echo $c['carga_horaria']; ?></span>
                                <span class="flex items-center gap-1 <?php echo $c['status'] ? 'text-green-600' : 'text-red-600'; ?>">
                                    <i data-lucide="<?php echo $c['status'] ? 'check-circle' : 'x-circle'; ?>" class="w-3 h-3"></i>
                                    <?php echo $c['status'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="edu_alunos.php?curso_id=<?php echo $c['id']; ?>" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-[10px] font-bold shadow-sm transition-all flex items-center gap-1.5">
                            <i data-lucide="users" class="w-3.5 h-3.5"></i> Alunos
                        </a>
                        <button onclick='abrirModalAula(<?php echo $c['id']; ?>)' class="px-3 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-[10px] font-bold shadow-sm transition-all flex items-center gap-1.5">
                            <i data-lucide="plus-square" class="w-3.5 h-3.5"></i> Add Aula
                        </button>
                        <button onclick='abrirModalCurso(<?php echo json_encode($c); ?>)' class="p-1.5 hover:bg-white text-text-secondary rounded-lg border border-border"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
                        <button onclick="excluirCurso(<?php echo $c['id']; ?>)" class="p-1.5 hover:bg-red-50 text-red-500 rounded-lg border border-border"><i data-lucide="trash" class="w-3.5 h-3.5"></i></button>
                    </div>
                </div>
                
                <div class="p-5">
                    <h4 class="text-[10px] font-black text-text-secondary uppercase tracking-widest mb-3">Conteúdo Programático</h4>
                    <div class="space-y-2">
                        <?php 
                        $aulas = $conn->query("SELECT * FROM edu_aulas WHERE curso_id = {$c['id']} ORDER BY ordem ASC");
                        if ($aulas->num_rows > 0):
                            while ($a = $aulas->fetch_assoc()):
                        ?>
                            <div class="flex items-center justify-between p-2.5 bg-background rounded-lg border border-border/50 text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="w-5 h-5 flex items-center justify-center bg-white border border-border rounded text-[10px] font-bold text-text-secondary"><?php echo $a['ordem']; ?></span>
                                    <i data-lucide="<?php echo $a['tipo'] == 'video' ? 'play-circle' : 'file-text'; ?>" class="w-4 h-4 text-primary"></i>
                                    <span class="font-bold text-text-secondary"><?php echo $a['titulo']; ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-gray-100 text-text-secondary uppercase"><?php echo $a['tipo']; ?></span>
                                    <button onclick='abrirModalAula(<?php echo $c['id']; ?>, <?php echo json_encode($a); ?>)' class="p-1 text-text-secondary hover:text-primary transition-colors"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                                    <button onclick="excluirAula(<?php echo $a['id']; ?>)" class="p-1 text-text-secondary hover:text-red-500 transition-colors"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <p class="text-[10px] italic text-text-secondary py-2">Nenhuma aula cadastrada ainda.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Curso -->
    <div id="modalCurso" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold" id="modal-curso-titulo">Novo Curso</h2>
                <button onclick="fecharModalCurso()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="salvar_curso">
                <input type="hidden" name="id" id="curso_id">
                <input type="hidden" name="capa_atual" id="curso_capa_atual">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título do Curso</label>
                    <input type="text" name="titulo" id="curso_titulo" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Autor / Instrutor</label>
                    <input type="text" name="instrutor" id="curso_instrutor" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Imagem de Capa (Opcional)</label>
                    <input type="file" name="capa_arquivo" id="curso_capa_arquivo" accept="image/*" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-primary file:text-white hover:file:bg-primary-hover">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição</label>
                    <textarea name="descricao" id="curso_descricao" rows="2" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Carga Horária</label>
                        <input type="text" name="carga_horaria" id="curso_carga" placeholder="Ex: 20h" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Status</label>
                        <select name="status" id="curso_status" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModalCurso()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all uppercase tracking-widest">Salvar Curso</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Aula -->
    <div id="modalAula" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden">
            <div class="bg-indigo-600 px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold" id="modal-aula-titulo">Adicionar Aula</h2>
                <button onclick="fecharModalAula()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" id="formAula" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="salvar_aula">
                <input type="hidden" name="curso_id" id="aula_curso_id">
                <input type="hidden" name="aula_id" id="aula_id">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título da Aula</label>
                    <input type="text" name="aula_titulo" id="aula_titulo" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Tipo</label>
                        <select name="aula_tipo" id="aula_tipo" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                            <option value="video">Vídeo (YouTube/Vimeo)</option>
                            <option value="pdf">Documento PDF</option>
                            <option value="texto">Texto / Artigo</option>
                            <option value="slide">Slideshow</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Ordem</label>
                        <input type="number" name="aula_ordem" id="aula_ordem" value="1" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Conteúdo (URL ou Texto)</label>
                    <div id="editor-container" class="bg-background border border-border rounded-lg text-xs" style="height: 200px;"></div>
                    <textarea name="aula_conteudo" id="aula_conteudo" class="hidden"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModalAula()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" id="btn-aula-salvar" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all uppercase tracking-widest">Salvar Aula</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalCurso(dados = null) {
            if (dados) {
                document.getElementById('curso_id').value = dados.id;
                document.getElementById('curso_titulo').value = dados.titulo;
                document.getElementById('curso_instrutor').value = dados.instrutor || '';
                document.getElementById('curso_capa_atual').value = dados.capa || '';
                document.getElementById('curso_descricao').value = dados.descricao;
                document.getElementById('curso_carga').value = dados.carga_horaria;
                document.getElementById('curso_status').value = dados.status;
                document.getElementById('modal-curso-titulo').innerText = 'Editar Curso';
            } else {
                document.getElementById('curso_id').value = '';
                document.getElementById('curso_titulo').value = '';
                document.getElementById('curso_instrutor').value = '';
                document.getElementById('curso_capa_atual').value = '';
                document.getElementById('curso_descricao').value = '';
                document.getElementById('curso_carga').value = '';
                document.getElementById('modal-curso-titulo').innerText = 'Novo Curso';
            }
            document.getElementById('modalCurso').classList.add('active');
        }
        function fecharModalCurso() { document.getElementById('modalCurso').classList.remove('active'); }
        
        // Inicializar Quill
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        // Sincronizar Quill com Textarea antes do envio
        document.getElementById('formAula').addEventListener('submit', function() {
            document.getElementById('aula_conteudo').value = quill.root.innerHTML;
        });

        function abrirModalAula(cursoId, dados = null) {
            document.getElementById('aula_curso_id').value = cursoId;
            if (dados) {
                document.getElementById('aula_id').value = dados.id;
                document.getElementById('aula_titulo').value = dados.titulo;
                document.getElementById('aula_tipo').value = dados.tipo;
                document.getElementById('aula_ordem').value = dados.ordem;
                document.getElementById('aula_conteudo').value = dados.conteudo;
                quill.root.innerHTML = dados.conteudo;
                document.getElementById('modal-aula-titulo').innerText = 'Editar Aula';
                document.getElementById('btn-aula-salvar').innerText = 'Salvar Aula';
            } else {
                document.getElementById('aula_id').value = '';
                document.getElementById('aula_titulo').value = '';
                document.getElementById('aula_tipo').value = 'video';
                document.getElementById('aula_ordem').value = '1';
                document.getElementById('aula_conteudo').value = '';
                quill.root.innerHTML = '';
                document.getElementById('modal-aula-titulo').innerText = 'Adicionar Aula';
                document.getElementById('btn-aula-salvar').innerText = 'Adicionar Aula';
            }
            document.getElementById('modalAula').classList.add('active');
        }
        function fecharModalAula() { document.getElementById('modalAula').classList.remove('active'); }

        function excluirAula(id) {
            if (confirm('Deseja excluir esta aula permanentemente?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_aula"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
        
        function excluirCurso(id) {
            if (confirm('Deseja excluir este curso e todas as suas aulas?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_curso"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
