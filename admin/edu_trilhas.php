<?php
require_once '../config.php';
require_once '../functions.php';

requireEduAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Ações (Trilhas)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'salvar_trilha') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $titulo = sanitize($_POST['titulo']);
        $descricao = $_POST['descricao'];
        $status = intval($_POST['status']);

        if ($id) {
            $stmt = $conn->prepare("UPDATE edu_trilhas SET titulo = ?, descricao = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssii", $titulo, $descricao, $status, $id);
        } else {
            $usuario_id = $_SESSION['usuario_id'];
            $stmt = $conn->prepare("INSERT INTO edu_trilhas (titulo, descricao, status, usuario_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $titulo, $descricao, $status, $usuario_id);
        }

        if ($stmt->execute()) {
            $mensagem = "Trilha salva!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'vincular_curso') {
        $trilha_id = intval($_POST['trilha_id']);
        $curso_id = intval($_POST['curso_id']);
        $ordem = intval($_POST['ordem']);

        $stmt = $conn->prepare("INSERT INTO edu_trilha_curso (trilha_id, curso_id, ordem) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $trilha_id, $curso_id, $ordem);
        $stmt->execute();
    } elseif ($_POST['acao'] == 'remover_curso') {
        $id_vinculo = intval($_POST['id_vinculo']);
        $conn->query("DELETE FROM edu_trilha_curso WHERE id = $id_vinculo");
    }
}

// Buscar trilhas e cursos disponíveis
$where_owner = "";
if (!isAdmin()) {
    $uid = $_SESSION['usuario_id'];
    $where_owner = " WHERE usuario_id = $uid";
}
$trilhas = $conn->query("SELECT * FROM edu_trilhas $where_owner ORDER BY created_at DESC");
$cursos_disp = $conn->query("SELECT id, titulo FROM edu_cursos WHERE status = 1 ORDER BY titulo");
$cursos_arr = [];
while($c = $cursos_disp->fetch_assoc()) $cursos_arr[] = $c;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Trilhas - APAS Intranet</title>
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
                    <i data-lucide="map" class="w-6 h-6"></i>
                    Gestão de Trilhas de Aprendizado
                </h1>
                <p class="text-text-secondary text-xs mt-1">Organize cursos em trilhas temáticas por função ou setor</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Painel Geral
                </a>
                <button onclick="abrirModalTrilha()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Nova Trilha
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php while ($t = $trilhas->fetch_assoc()): ?>
            <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-gray-50/50 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-text"><?php echo $t['titulo']; ?></h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick='abrirModalVinculo(<?php echo $t['id']; ?>)' class="p-1.5 hover:bg-white text-primary rounded-lg border border-border" title="Vincular Curso"><i data-lucide="plus" class="w-4 h-4"></i></button>
                        <button onclick='abrirModalTrilha(<?php echo json_encode($t); ?>)' class="p-1.5 hover:bg-white text-text-secondary rounded-lg border border-border"><i data-lucide="edit" class="w-4 h-4"></i></button>
                    </div>
                </div>
                
                <div class="p-5 flex-grow">
                    <p class="text-[11px] text-text-secondary mb-4 italic"><?php echo $t['descricao']; ?></p>
                    <h4 class="text-[9px] font-black text-text-secondary uppercase tracking-widest mb-3">Cursos na Trilha</h4>
                    <div class="space-y-2">
                        <?php 
                        $cursos_trilha = $conn->query("SELECT tc.*, c.titulo FROM edu_trilha_curso tc JOIN edu_cursos c ON tc.curso_id = c.id WHERE tc.trilha_id = {$t['id']} ORDER BY tc.ordem ASC");
                        if ($cursos_trilha->num_rows > 0):
                            while ($ct = $cursos_trilha->fetch_assoc()):
                        ?>
                            <div class="flex items-center justify-between p-2 bg-background rounded-lg border border-border/50 text-xs">
                                <span class="font-bold text-text-secondary">#<?php echo $ct['ordem']; ?> - <?php echo $ct['titulo']; ?></span>
                                <button onclick="removerVinculo(<?php echo $ct['id']; ?>)" class="text-red-400 hover:text-red-600"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <p class="text-[10px] italic text-text-secondary">Nenhum curso associado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Trilha -->
    <div id="modalTrilha" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold" id="modal-titulo">Nova Trilha</h2>
                <button onclick="fecharModalTrilha()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="salvar_trilha">
                <input type="hidden" name="id" id="trilha_id">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Título</label>
                    <input type="text" name="titulo" id="trilha_titulo" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Descrição</label>
                    <textarea name="descricao" id="trilha_descricao" rows="3" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary"></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Status</label>
                    <select name="status" id="trilha_status" class="w-full p-2 border border-border rounded-lg text-xs font-bold">
                        <option value="1">Ativa</option>
                        <option value="0">Inativa</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="fecharModalTrilha()" class="text-xs font-bold text-text-secondary uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary text-white px-6 py-1.5 rounded-lg text-xs font-bold uppercase">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Vincular -->
    <div id="modalVinculo" class="modal">
        <div class="bg-white w-full max-w-sm mx-4 rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-indigo-600 px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold">Vincular Curso</h2>
                <button onclick="fecharModalVinculo()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="vincular_curso">
                <input type="hidden" name="trilha_id" id="vinc_trilha_id">
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Curso</label>
                    <select name="curso_id" required class="w-full p-2 border border-border rounded-lg text-xs font-bold">
                        <option value="">Selecione...</option>
                        <?php foreach($cursos_arr as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['titulo']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Ordem na Trilha</label>
                    <input type="number" name="ordem" value="1" class="w-full p-2 border border-border rounded-lg text-xs font-bold">
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="fecharModalVinculo()" class="text-xs font-bold text-text-secondary uppercase">Cancelar</button>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-1.5 rounded-lg text-xs font-bold uppercase">Vincular</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalTrilha(dados = null) {
            if (dados) {
                document.getElementById('trilha_id').value = dados.id;
                document.getElementById('trilha_titulo').value = dados.titulo;
                document.getElementById('trilha_descricao').value = dados.descricao;
                document.getElementById('trilha_status').value = dados.status;
                document.getElementById('modal-titulo').innerText = 'Editar Trilha';
            } else {
                document.getElementById('trilha_id').value = '';
                document.getElementById('trilha_titulo').value = '';
                document.getElementById('trilha_descricao').value = '';
                document.getElementById('modal-titulo').innerText = 'Nova Trilha';
            }
            document.getElementById('modalTrilha').classList.add('active');
        }
        function fecharModalTrilha() { document.getElementById('modalTrilha').classList.remove('active'); }
        
        function abrirModalVinculo(trilhaId) {
            document.getElementById('vinc_trilha_id').value = trilhaId;
            document.getElementById('modalVinculo').classList.add('active');
        }
        function fecharModalVinculo() { document.getElementById('modalVinculo').classList.remove('active'); }
        
        function removerVinculo(id) {
            if (confirm('Remover curso desta trilha?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="remover_curso"><input type="hidden" name="id_vinculo" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
