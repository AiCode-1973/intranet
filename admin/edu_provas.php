<?php
require_once '../config.php';
require_once '../functions.php';

requireEduAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Ações (Provas e Questões)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'config_prova') {
        $curso_id = intval($_POST['curso_id']);
        $nota_minima = intval($_POST['nota_minima']);
        $tentativas = intval($_POST['tentativas_max']);

        $check = $conn->query("SELECT id FROM edu_provas WHERE curso_id = $curso_id");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE edu_provas SET nota_minima = $nota_minima, tentativas_max = $tentativas WHERE curso_id = $curso_id");
        } else {
            $conn->query("INSERT INTO edu_provas (curso_id, nota_minima, tentativas_max) VALUES ($curso_id, $nota_minima, $tentativas)");
        }
        $mensagem = "Configurações da prova salvas!";
        $tipo_mensagem = "success";
    } elseif ($_POST['acao'] == 'add_questao') {
        $prova_id = intval($_POST['prova_id']);
        $enunciado = sanitize($_POST['enunciado']);
        
        $stmt = $conn->prepare("INSERT INTO edu_questoes (prova_id, enunciado) VALUES (?, ?)");
        $stmt->bind_param("is", $prova_id, $enunciado);
        if ($stmt->execute()) {
            $questao_id = $conn->insert_id;
            // Adicionar respostas
            foreach ($_POST['resp_texto'] as $k => $texto) {
                if (trim($texto) == '') continue;
                $correta = ($k == $_POST['resp_correta']) ? 1 : 0;
                $stmt_r = $conn->prepare("INSERT INTO edu_respostas (questao_id, texto, correta) VALUES (?, ?, ?)");
                $stmt_r->bind_param("isi", $questao_id, $texto, $correta);
                $stmt_r->execute();
            }
            $mensagem = "Questão adicionada!";
            $tipo_mensagem = "success";
        }
    } elseif ($_POST['acao'] == 'excluir_questao') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM edu_questoes WHERE id = $id");
        $mensagem = "Questão removida!";
        $tipo_mensagem = "success";
    }
}

// Buscar cursos que têm prova ou podem ter
$where_owner = "";
if (!isAdmin()) {
    $uid = $_SESSION['usuario_id'];
    $where_owner = " AND c.usuario_id = $uid";
}
$cursos = $conn->query("SELECT c.id, c.titulo, p.id as prova_id, p.nota_minima, p.tentativas_max 
                        FROM edu_cursos c 
                        LEFT JOIN edu_provas p ON c.id = p.curso_id 
                        WHERE c.status = 1 $where_owner 
                        ORDER BY c.titulo");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Provas - APAS Intranet</title>
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
                    <i data-lucide="clipboard-check" class="w-6 h-6"></i>
                    Gestão de Provas & Avaliações
                </h1>
                <p class="text-text-secondary text-xs mt-1">Configure critérios de aprovação e banco de questões</p>
            </div>
            
            <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700">
                <i data-lucide="check-circle" class="w-4 h-4 text-xs font-bold"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6">
            <?php while ($c = $cursos->fetch_assoc()): ?>
            <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
                <div class="p-5 border-b border-border bg-gray-50/50 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-text"><?php echo $c['titulo']; ?></h3>
                        <?php if ($c['prova_id']): ?>
                            <span class="text-[9px] font-black text-primary uppercase">Nota Mín: <?php echo $c['nota_minima']; ?>% | Tentativas: <?php echo $c['tentativas_max']; ?></span>
                        <?php else: ?>
                            <span class="text-[9px] font-black text-text-secondary opacity-40 uppercase italic">Prova não configurada</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick='abrirModalConfig(<?php echo json_encode($c); ?>)' class="px-3 py-1.5 bg-white border border-border text-text-secondary text-[10px] font-bold rounded-lg hover:border-primary hover:text-primary transition-all flex items-center gap-1.5">
                            <i data-lucide="settings" class="w-3.5 h-3.5"></i> Configurar
                        </button>
                        <?php if ($c['prova_id']): ?>
                        <button onclick='abrirModalQuestao(<?php echo $c['prova_id']; ?>)' class="px-3 py-1.5 bg-primary text-white text-[10px] font-bold rounded-lg hover:bg-primary-hover shadow-sm transition-all flex items-center gap-1.5">
                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Add Questão
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($c['prova_id']): ?>
                <div class="p-5">
                    <div class="space-y-4">
                        <?php 
                        $questoes = $conn->query("SELECT * FROM edu_questoes WHERE prova_id = {$c['prova_id']}");
                        if ($questoes->num_rows > 0):
                            while ($q = $questoes->fetch_assoc()):
                        ?>
                            <div class="p-4 bg-background rounded-xl border border-border/50">
                                <div class="flex justify-between items-start gap-4 mb-3">
                                    <p class="text-xs font-bold text-text"><?php echo $q['enunciado']; ?></p>
                                    <button onclick="excluirQuestao(<?php echo $q['id']; ?>)" class="text-red-400 hover:text-red-600 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <?php 
                                    $respostas = $conn->query("SELECT * FROM edu_respostas WHERE questao_id = {$q['id']}");
                                    while ($r = $respostas->fetch_assoc()):
                                    ?>
                                        <div class="flex items-center gap-2 text-[10px] p-2 bg-white border border-border rounded-lg <?php echo $r['correta'] ? 'border-green-200 bg-green-50/50' : ''; ?>">
                                            <i data-lucide="<?php echo $r['correta'] ? 'check' : 'circle'; ?>" class="w-3 h-3 <?php echo $r['correta'] ? 'text-green-600' : 'text-text-secondary opacity-30'; ?>"></i>
                                            <span class="<?php echo $r['correta'] ? 'text-green-700 font-bold' : 'text-text-secondary'; ?>"><?php echo $r['texto']; ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <p class="text-[10px] italic text-text-secondary">Nenhuma questão cadastrada para este curso.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Config -->
    <div id="modalConfig" class="modal">
        <div class="bg-white w-full max-w-sm mx-4 rounded-xl shadow-2xl overflow-hidden border border-border">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold">Configurar Prova</h2>
                <button onclick="fecharModalConfig()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="config_prova">
                <input type="hidden" name="curso_id" id="config_curso_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Nota Mínima (%)</label>
                        <input type="number" name="nota_minima" id="config_nota" min="0" max="100" class="w-full p-2 border border-border rounded-lg text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Tentativas Máx</label>
                        <input type="number" name="tentativas_max" id="config_tentativas" min="1" class="w-full p-2 border border-border rounded-lg text-xs font-bold">
                    </div>
                </div>
                <button type="submit" class="w-full bg-primary text-white py-2 rounded-lg text-xs font-bold uppercase tracking-widest mt-4">Salvar Configurações</button>
            </form>
        </div>
    </div>

    <!-- Modal Questão -->
    <div id="modalQuestao" class="modal">
        <div class="bg-white w-full max-w-xl mx-4 rounded-2xl shadow-2xl overflow-hidden border border-border">
            <div class="bg-indigo-600 px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold">Nova Questão</h2>
                <button onclick="fecharModalQuestao()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="add_questao">
                <input type="hidden" name="prova_id" id="questao_prova_id">
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary uppercase mb-1">Enunciado</label>
                    <textarea name="enunciado" required rows="3" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="space-y-3">
                    <label class="block text-[10px] font-black text-text-secondary uppercase">Respostas (Marque a correta)</label>
                    <?php for($i=0; $i<4; $i++): ?>
                    <div class="flex items-center gap-3">
                        <input type="radio" name="resp_correta" value="<?php echo $i; ?>" <?php echo $i==0 ? 'checked' : ''; ?> class="accent-primary">
                        <input type="text" name="resp_texto[]" placeholder="Opção <?php echo $i+1; ?>..." class="flex-grow p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="fecharModalQuestao()" class="text-xs font-bold text-text-secondary uppercase">Cancelar</button>
                    <button type="submit" class="bg-indigo-600 text-white px-8 py-2 rounded-lg text-xs font-bold uppercase tracking-widest shadow-lg active:scale-95 transition-all">Adicionar Questão</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalConfig(c) {
            document.getElementById('config_curso_id').value = c.id;
            document.getElementById('config_nota').value = c.nota_minima || 70;
            document.getElementById('config_tentativas').value = c.tentativas_max || 3;
            document.getElementById('modalConfig').classList.add('active');
        }
        function fecharModalConfig() { document.getElementById('modalConfig').classList.remove('active'); }
        
        function abrirModalQuestao(provaId) {
            document.getElementById('questao_prova_id').value = provaId;
            document.getElementById('modalQuestao').classList.add('active');
        }
        function fecharModalQuestao() { document.getElementById('modalQuestao').classList.remove('active'); }
        
        function excluirQuestao(id) {
            if (confirm('Excluir esta questão permanentemente?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_questao"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
