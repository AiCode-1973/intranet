<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        
        if ($acao == 'criar' || $acao == 'editar') {
            $titulo = sanitize($_POST['titulo']);
            $descricao = $_POST['descricao'];
            $data_evento = $_POST['data_evento'];
            $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
            $hora_fim = !empty($_POST['hora_fim']) ? $_POST['hora_fim'] : null;
            $local_evento = sanitize($_POST['local_evento']);
            $categoria = sanitize($_POST['categoria']);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $autor_id = $_SESSION['usuario_id'];

            if ($acao == 'criar') {
                $stmt = $conn->prepare("INSERT INTO agenda (titulo, descricao, data_evento, hora_inicio, hora_fim, local_evento, categoria, autor_id, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssii", $titulo, $descricao, $data_evento, $hora_inicio, $hora_fim, $local_evento, $categoria, $autor_id, $ativo);
            } else {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE agenda SET titulo = ?, descricao = ?, data_evento = ?, hora_inicio = ?, hora_fim = ?, local_evento = ?, categoria = ?, ativo = ? WHERE id = ?");
                $stmt->bind_param("sssssssid", $titulo, $descricao, $data_evento, $hora_inicio, $hora_fim, $local_evento, $categoria, $ativo, $id);
            }

            if ($stmt->execute()) {
                $mensagem = 'Evento salvo com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, ($acao == 'criar' ? 'Criou' : 'Editou') . ' evento na agenda: ' . $titulo);
            } else {
                $mensagem = 'Erro ao salvar evento: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        } elseif ($acao == 'excluir') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM agenda WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = 'Evento excluído com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Excluiu evento ID: ' . $id);
            }
            $stmt->close();
        }
    }
}

// Fetch events
$eventos = $conn->query("
    SELECT a.*, u.nome as autor_nome 
    FROM agenda a
    LEFT JOIN usuarios u ON a.autor_id = u.id
    ORDER BY a.data_evento DESC, a.hora_inicio DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Agenda - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header (Slim Style) -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="calendar" class="w-6 h-6"></i>
                    Gerenciar Agenda de Eventos
                </h1>
                <p class="text-text-secondary text-xs mt-1">Controle do calendário institucional</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
                <button onclick="abrirModal('criar')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    Novo Evento
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 animate-in slide-in-from-top-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-tighter"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Table (Slim Style) -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Data / Título</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Horário</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Categoria</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Local</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php while ($evento = $eventos->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group <?php echo !$evento['ativo'] ? 'opacity-40' : ''; ?>">
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-black text-primary text-[9px] tracking-widest uppercase mb-0.5"><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></span>
                                    <span class="font-bold text-text leading-tight group-hover:text-primary transition-colors"><?php echo $evento['titulo']; ?></span>
                                    <span class="text-[9px] text-text-secondary truncate max-w-[200px]"><?php echo strip_tags($evento['descricao']); ?></span>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="bg-gray-50 border border-border px-1.5 py-0.5 rounded text-[10px] font-mono font-black text-text-secondary">
                                        <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?>
                                    </span>
                                    <?php if($evento['hora_fim']): ?>
                                        <span class="text-[8px] text-text-secondary/50 mt-1 uppercase font-black">até <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-gray-50 text-text-secondary border border-border group-hover:bg-white">
                                    <?php echo $evento['categoria']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-text-secondary italic text-[11px]">
                                <?php echo $evento['local_evento'] ?: '-'; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-1.5 opacity-40 group-hover:opacity-100 transition-opacity">
                                    <button onclick='editarEvento(<?php echo json_encode($evento); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded-lg transition-all" title="Editar">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <button onclick="excluirEvento(<?php echo $evento['id']; ?>, '<?php echo $evento['titulo']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir">
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
    <div id="modalEvento" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 id="modalTitulo" class="text-base font-bold text-white">Novo Evento</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Ficha de Agendamento</p>
                </div>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" id="acao" value="criar">
                <input type="hidden" name="id" id="evento_id">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título do Evento</label>
                        <input type="text" name="titulo" id="titulo" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Data</label>
                        <input type="date" name="data_evento" id="data_evento" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Início</label>
                        <input type="time" name="hora_inicio" id="hora_inicio" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Término</label>
                        <input type="time" name="hora_fim" id="hora_fim" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                        <select name="categoria" id="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="Reunião">Reunião</option>
                            <option value="Treinamento">Treinamento</option>
                            <option value="Celebração">Celebração</option>
                            <option value="Importante">Importante</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Local</label>
                        <input type="text" name="local_evento" id="local_evento" placeholder="Ex: Sala A" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição detalhada</label>
                        <textarea name="descricao" id="descricao" rows="4" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                    </div>

                    <div class="md:col-span-3 pt-2">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="ativo" id="ativo" checked class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-black text-text-secondary uppercase tracking-tighter">Evento Ativo/Visível</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Gravar Evento</button>
                </div>
            </form>
        </div>
    </div>

    <form id="formExcluir" method="POST" style="display:none;"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" id="excluir_id"></form>

    <script>
        function abrirModal(acao) {
            document.getElementById('modalTitulo').textContent = 'Novo Evento';
            document.getElementById('acao').value = 'criar';
            document.getElementById('evento_id').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('descricao').value = '';
            document.getElementById('data_evento').value = '';
            document.getElementById('hora_inicio').value = '';
            document.getElementById('hora_fim').value = '';
            document.getElementById('local_evento').value = '';
            document.getElementById('categoria').value = 'Reunião';
            document.getElementById('ativo').checked = true;
            document.getElementById('modalEvento').classList.add('active');
        }
        function editarEvento(evento) {
            document.getElementById('modalTitulo').textContent = 'Editar Evento';
            document.getElementById('acao').value = 'editar';
            document.getElementById('evento_id').value = evento.id;
            document.getElementById('titulo').value = evento.titulo;
            document.getElementById('descricao').value = evento.descricao;
            document.getElementById('data_evento').value = evento.data_evento;
            document.getElementById('hora_inicio').value = evento.hora_inicio;
            document.getElementById('hora_fim').value = evento.hora_fim;
            document.getElementById('local_evento').value = evento.local_evento;
            document.getElementById('categoria').value = evento.categoria;
            document.getElementById('ativo').checked = evento.ativo == 1;
            document.getElementById('modalEvento').classList.add('active');
        }
        function fecharModal() { document.getElementById('modalEvento').classList.remove('active'); }
        function excluirEvento(id, titulo) { if(confirm('Excluir evento "'+titulo+'" permanentemente?')) { document.getElementById('excluir_id').value = id; document.getElementById('formExcluir').submit(); } }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
