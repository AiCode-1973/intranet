<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        
        if ($acao == 'salvar') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : null;
            $nome = sanitize($_POST['nome']);
            $ramal = sanitize($_POST['ramal']);
            $telefone = sanitize($_POST['telefone']);
            $setor_id = $_POST['setor_id'] ? intval($_POST['setor_id']) : null;
            $tipo = $_POST['tipo'];
            $ordem = intval($_POST['ordem']);
            
            if ($id) {
                $stmt = $conn->prepare("UPDATE telefones SET nome = ?, ramal = ?, telefone = ?, setor_id = ?, tipo = ?, ordem = ? WHERE id = ?");
                $stmt->bind_param("sssisii", $nome, $ramal, $telefone, $setor_id, $tipo, $ordem, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO telefones (nome, ramal, telefone, setor_id, tipo, ordem) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisi", $nome, $ramal, $telefone, $setor_id, $tipo, $ordem);
            }
            
            if ($stmt->execute()) {
                $mensagem = 'Registro salvo com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Salvou telefone/ramal: ' . $nome);
            } else {
                $mensagem = 'Erro ao salvar: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        elseif ($acao == 'excluir') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM telefones WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = 'Registro excluído com sucesso!';
                $tipo_mensagem = 'success';
            } else {
                $mensagem = 'Erro ao excluir: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
    }
}

$setores = $conn->query("SELECT id, nome FROM setores ORDER BY nome");
$telefones = $conn->query("
    SELECT t.*, s.nome as setor_nome 
    FROM telefones t 
    LEFT JOIN setores s ON t.setor_id = s.id 
    ORDER BY t.tipo, t.ordem, t.nome
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Ramais - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="phone" class="w-6 h-6"></i>
                    Gerenciar Ramais & Telefones
                </h1>
                <p class="text-text-secondary text-[11px] uppercase font-bold tracking-wider">Lista Telefônica Interna</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary rounded-lg text-xs font-bold shadow-sm">Voltar</a>
                <button onclick="abrirModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Novo Registro
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 text-xs font-bold <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-border">
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase">Nome/Setor</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase">Ramal</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase">Telefone</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase">Setor</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php while ($t = $telefones->fetch_assoc()): ?>
                    <tr class="hover:bg-background/30 transition-colors group">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                    <i data-lucide="<?php echo $t['tipo'] == 'externo' ? 'external-link' : ($t['tipo'] == 'colaborador' ? 'user' : 'building-2'); ?>" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-text leading-tight"><?php echo $t['nome']; ?></p>
                                    <p class="text-[9px] text-primary font-black uppercase tracking-tighter"><?php echo $t['tipo']; ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="text-xs font-mono font-bold text-primary bg-primary/5 px-2 py-0.5 rounded border border-primary/10 tracking-widest"><?php echo $t['ramal'] ?: '-'; ?></span>
                        </td>
                        <td class="p-3">
                            <span class="text-xs text-text-secondary font-medium"><?php echo $t['telefone'] ?: '-'; ?></span>
                        </td>
                        <td class="p-3">
                            <?php if ($t['setor_nome']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase bg-gray-50 text-text-secondary border border-border/50">
                                    <?php echo $t['setor_nome']; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-text-secondary opacity-30 italic">Nenhum</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-1.5 opacity-50 group-hover:opacity-100 transition-opacity">
                                <button onclick='editar(<?php echo json_encode($t); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </button>
                                <button onclick="excluir(<?php echo $t['id']; ?>)" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all">
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

    <!-- Modal -->
    <div id="modalTelefone" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-primary p-4 text-white flex justify-between items-center">
                <h2 id="modalTitulo" class="font-bold">Novo Registro</h2>
                <button onclick="fecharModal()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="id">
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Nome / Descrição</label>
                    <input type="text" name="nome" id="nome" required class="w-full p-2 bg-background border border-border rounded text-xs focus:outline-none focus:border-primary">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Ramal</label>
                        <input type="text" name="ramal" id="ramal" class="w-full p-2 bg-background border border-border rounded text-xs focus:outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Telefone Externo</label>
                        <input type="text" name="telefone" id="telefone" class="w-full p-2 bg-background border border-border rounded text-xs focus:outline-none focus:border-primary">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Setor Relacionado</label>
                    <select name="setor_id" id="setor_id" class="w-full p-2 bg-background border border-border rounded text-xs focus:outline-none focus:border-primary">
                        <option value="">Nenhum</option>
                        <?php $setores->data_seek(0); while($s = $setores->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['nome']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Tipo</label>
                        <select name="tipo" id="tipo" class="w-full p-2 bg-background border border-border rounded text-xs">
                            <option value="setor">Setor</option>
                            <option value="colaborador">Colaborador</option>
                            <option value="externo">Externo</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Ordem</label>
                        <input type="number" name="ordem" id="ordem" value="0" class="w-full p-2 bg-background border border-border rounded text-xs">
                    </div>
                </div>
                <div class="pt-4 flex justify-end gap-2">
                    <button type="button" onclick="fecharModal()" class="px-4 py-2 text-xs font-bold text-text-secondary">Cancelar</button>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg text-xs font-bold shadow-md">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById('modalTitulo').innerText = 'Novo Registro';
            document.getElementById('id').value = '';
            document.getElementById('nome').value = '';
            document.getElementById('ramal').value = '';
            document.getElementById('telefone').value = '';
            document.getElementById('setor_id').value = '';
            document.getElementById('tipo').value = 'setor';
            document.getElementById('ordem').value = '0';
            document.getElementById('modalTelefone').classList.add('active');
        }
        function fecharModal() { document.getElementById('modalTelefone').classList.remove('active'); }
        function editar(d) {
            document.getElementById('modalTitulo').innerText = 'Editar Registro';
            document.getElementById('id').value = d.id;
            document.getElementById('nome').value = d.nome;
            document.getElementById('ramal').value = d.ramal;
            document.getElementById('telefone').value = d.telefone;
            document.getElementById('setor_id').value = d.setor_id;
            document.getElementById('tipo').value = d.tipo;
            document.getElementById('ordem').value = d.ordem;
            document.getElementById('modalTelefone').classList.add('active');
        }
        function excluir(id) {
            if (confirm('Deseja excluir este registro?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
