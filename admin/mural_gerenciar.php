<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        
        if ($acao == 'criar' || $acao == 'editar') {
            $titulo = sanitize($_POST['titulo']);
            $conteudo = $_POST['conteudo']; 
            $categoria = sanitize($_POST['categoria']);
            $prioridade = sanitize($_POST['prioridade']);
            $data_expiracao = !empty($_POST['data_expiracao']) ? $_POST['data_expiracao'] : null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $autor_id = $_SESSION['usuario_id'];

            if ($acao == 'criar') {
                $stmt = $conn->prepare("INSERT INTO mural (titulo, conteudo, categoria, prioridade, data_expiracao, autor_id, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssii", $titulo, $conteudo, $categoria, $prioridade, $data_expiracao, $autor_id, $ativo);
            } else {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE mural SET titulo = ?, conteudo = ?, categoria = ?, prioridade = ?, data_expiracao = ?, ativo = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $titulo, $conteudo, $categoria, $prioridade, $data_expiracao, $ativo, $id);
            }

            if ($stmt->execute()) {
                $mensagem = 'Aviso salvo com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, ($acao == 'criar' ? 'Criou' : 'Editou') . ' aviso: ' . $titulo);
            } else {
                $mensagem = 'Erro ao salvar aviso: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        } elseif ($acao == 'excluir') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM mural WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = 'Aviso excluído com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Excluiu aviso ID: ' . $id);
            }
            $stmt->close();
        }
    }
}

// Fetch all notices
$avisos = $conn->query("
    SELECT m.*, u.nome as autor_nome 
    FROM mural m
    LEFT JOIN usuarios u ON m.autor_id = u.id
    ORDER BY m.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Mural - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="megaphone" class="w-6 h-6"></i>
                    Gerenciar Mural de Avisos
                </h1>
                <p class="text-text-secondary text-xs mt-1">Controle de comunicados e notícias internas</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
                <button onclick="abrirModal('criar')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    Novo Aviso
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 animate-in slide-in-from-top-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <span class="text-xs font-semibold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Aviso / Título</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Cat / Prio</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Autor</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Expiração</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php while ($aviso = $avisos->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group">
                            <td class="p-3">
                                <p class="font-bold text-text leading-tight"><?php echo $aviso['titulo']; ?></p>
                                <p class="text-[9px] text-text-secondary truncate max-w-[300px] mt-0.5"><?php echo strip_tags($aviso['conteudo']); ?></p>
                            </td>
                            <td class="p-3 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="px-1.5 py-0.5 bg-gray-50 border border-border rounded text-[9px] font-black text-text-secondary uppercase">
                                        <?php echo $aviso['categoria']; ?>
                                    </span>
                                    <?php if($aviso['prioridade'] == 'Alta'): ?>
                                        <span class="text-[8px] font-black text-red-600 uppercase tracking-tighter">ALTA PRIO</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-6 h-6 rounded bg-primary/10 flex items-center justify-center text-[10px] font-bold text-primary">
                                        <?php echo substr($aviso['autor_nome'], 0, 1); ?>
                                    </div>
                                    <span class="text-[11px] font-bold text-text-secondary"><?php echo $aviso['autor_nome']; ?></span>
                                </div>
                            </td>
                            <td class="p-3 text-text-secondary font-mono text-[10px]">
                                <?php echo $aviso['data_expiracao'] ? date('d/m/Y', strtotime($aviso['data_expiracao'])) : 'Perpétuo'; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($aviso['ativo']): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-green-50 text-green-600 border border-green-100">Visível</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-gray-50 text-gray-400 border border-gray-200">Oculto</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-1.5 opacity-40 group-hover:opacity-100 transition-opacity">
                                    <button onclick='editarAviso(<?php echo json_encode($aviso); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <button onclick="excluirAviso(<?php echo $aviso['id']; ?>, '<?php echo $aviso['titulo']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal (Slim Pattern) -->
    <div id="modalAviso" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 id="modalTitulo" class="text-base font-bold text-white">Novo Aviso</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Ficha de Publicação</p>
                </div>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" id="acao" value="criar">
                <input type="hidden" name="id" id="aviso_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título do Comunicado</label>
                        <input type="text" name="titulo" id="titulo" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Conteúdo</label>
                        <textarea name="conteudo" id="conteudo" rows="6" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                        <select name="categoria" id="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="Informativo">Informativo</option>
                            <option value="Evento">Evento</option>
                            <option value="RH">RH</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Prioridade</label>
                        <select name="prioridade" id="prioridade" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="Normal">Normal</option>
                            <option value="Alta">Alta (Destaque)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Expira em (Opcional)</label>
                        <input type="date" name="data_expiracao" id="data_expiracao" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="flex items-center gap-2 pt-5">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="ativo" id="ativo" checked class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary transition-all">
                            <span class="text-[11px] font-bold text-text-secondary uppercase">Aviso Público</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Gravar Aviso</button>
                </div>
            </form>
        </div>
    </div>

    <form id="formExcluir" method="POST" style="display:none;"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" id="excluir_id"></form>

    <script>
        function abrirModal(acao) {
            document.getElementById('modalTitulo').textContent = 'Novo Aviso';
            document.getElementById('acao').value = 'criar';
            document.getElementById('aviso_id').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('conteudo').value = '';
            document.getElementById('categoria').value = 'Informativo';
            document.getElementById('prioridade').value = 'Normal';
            document.getElementById('data_expiracao').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('modalAviso').classList.add('active');
        }
        function editarAviso(aviso) {
            document.getElementById('modalTitulo').textContent = 'Editar Aviso';
            document.getElementById('acao').value = 'editar';
            document.getElementById('aviso_id').value = aviso.id;
            document.getElementById('titulo').value = aviso.titulo;
            document.getElementById('conteudo').value = aviso.conteudo;
            document.getElementById('categoria').value = aviso.categoria;
            document.getElementById('prioridade').value = aviso.prioridade;
            document.getElementById('data_expiracao').value = aviso.data_expiracao || '';
            document.getElementById('ativo').checked = aviso.ativo == 1;
            document.getElementById('modalAviso').classList.add('active');
        }
        function fecharModal() { document.getElementById('modalAviso').classList.remove('active'); }
        function excluirAviso(id, titulo) { if(confirm('Excluir aviso "'+titulo+'"?')) { document.getElementById('excluir_id').value = id; document.getElementById('formExcluir').submit(); } }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
