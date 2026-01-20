<?php
require_once '../config.php';
require_once '../functions.php';

requireRHAdmin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        
        if ($acao == 'criar') {
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao']);
            
            $stmt = $conn->prepare("INSERT INTO setores (nome, descricao) VALUES (?, ?)");
            $stmt->bind_param("ss", $nome, $descricao);
            
            if ($stmt->execute()) {
                $mensagem = 'Setor criado com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Criou setor: ' . $nome);
            } else {
                $mensagem = 'Erro ao criar setor: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        elseif ($acao == 'editar') {
            $id = intval($_POST['id']);
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao']);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE setores SET nome = ?, descricao = ?, ativo = ? WHERE id = ?");
            $stmt->bind_param("ssii", $nome, $descricao, $ativo, $id);
            
            if ($stmt->execute()) {
                $mensagem = 'Setor atualizado com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Editou setor: ' . $nome);
            } else {
                $mensagem = 'Erro ao atualizar setor: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        elseif ($acao == 'excluir') {
            $id = intval($_POST['id']);
            
            $check = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE setor_id = $id");
            $total_usuarios = $check->fetch_assoc()['total'];
            
            if ($total_usuarios > 0) {
                $mensagem = 'Não é possível excluir este setor pois existem ' . $total_usuarios . ' usuário(s) vinculado(s) a ele.';
                $tipo_mensagem = 'warning';
            } else {
                $stmt = $conn->prepare("DELETE FROM setores WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $mensagem = 'Setor excluído com sucesso!';
                    $tipo_mensagem = 'success';
                    registrarLog($conn, 'Excluiu setor ID: ' . $id);
                } else {
                    $mensagem = 'Erro ao excluir setor: ' . $conn->error;
                    $tipo_mensagem = 'danger';
                }
                $stmt->close();
            }
        }
    }
}

$setores = $conn->query("
    SELECT s.*, 
    (SELECT COUNT(*) FROM usuarios WHERE setor_id = s.id) as total_usuarios,
    (SELECT COUNT(*) FROM permissoes WHERE setor_id = s.id) as total_permissoes
    FROM setores s 
    ORDER BY s.nome
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Setores - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="layers" class="w-6 h-6"></i>
                    Gerenciar Setores
                </h1>
                <p class="text-text-secondary text-[11px] mt-0.5 uppercase tracking-wider font-semibold">Estrutura de Departamentos</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
                <button onclick="abrirModal('criar')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    Novo Setor
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 animate-in fade-in slide-in-from-top-2 duration-300 <?php 
                echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 
                    ($tipo_mensagem == 'warning' ? 'bg-yellow-50 border-yellow-100 text-yellow-700' : 'bg-red-50 border-red-100 text-red-700'); ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <span class="text-xs font-semibold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Setor / ID</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Descrição</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Usuários</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Permissões</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php while ($setor = $setores->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                        <i data-lucide="building-2" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-text leading-tight"><?php echo $setor['nome']; ?></p>
                                        <p class="text-[9px] text-text-secondary font-mono">ID: #<?php echo str_pad($setor['id'], 3, '0', STR_PAD_LEFT); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <p class="text-xs text-text max-w-xs truncate" title="<?php echo $setor['descricao']; ?>">
                                    <?php echo $setor['descricao'] ? $setor['descricao'] : '<span class="text-text-secondary italic">Sem descrição</span>'; ?>
                                </p>
                            </td>
                            <td class="p-3 text-center">
                                <span class="text-xs font-bold text-text"><?php echo $setor['total_usuarios']; ?></span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="text-xs font-bold text-text"><?php echo $setor['total_permissoes']; ?></span>
                            </td>
                            <td class="p-3">
                                <?php if ($setor['ativo']): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-green-50 text-green-600 border border-green-100">
                                        Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-red-50 text-red-600 border border-red-100">
                                        Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-1.5 opacity-50 group-hover:opacity-100 transition-opacity">
                                    <button onclick='editarSetor(<?php echo json_encode($setor); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all" title="Editar">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <button onclick="excluirSetor(<?php echo $setor['id']; ?>, '<?php echo $setor['nome']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all" title="Excluir">
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
    
    <!-- Modal Setor -->
    <div id="modalSetor" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 id="modalTitulo" class="text-base font-bold">Novo Setor</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Unidade / Departamento</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" id="acao" value="criar">
                <input type="hidden" name="id" id="setor_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="nome" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Nome do Setor</label>
                        <input type="text" id="nome" name="nome" required class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div>
                        <label for="descricao" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Descrição / Notas</label>
                        <textarea id="descricao" name="descricao" rows="3" class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all" placeholder="Informações adicionais..."></textarea>
                    </div>
                    
                    <div id="ativoGroup" style="display: none;" class="pt-2 border-t border-border/50">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="ativo" name="ativo" checked class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-bold text-text-secondary">Setor Ativo</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Salvar Setor</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="formExcluir" method="POST" action="" style="display: none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="excluir_id">
    </form>
    
    <script>
        function abrirModal(acao) {
            document.getElementById('modalTitulo').textContent = 'Novo Setor';
            document.getElementById('acao').value = 'criar';
            document.getElementById('setor_id').value = '';
            document.getElementById('nome').value = '';
            document.getElementById('descricao').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('ativoGroup').style.display = 'none';
            document.getElementById('modalSetor').classList.add('active');
        }
        
        function editarSetor(setor) {
            document.getElementById('modalTitulo').textContent = 'Editar Setor';
            document.getElementById('acao').value = 'editar';
            document.getElementById('setor_id').value = setor.id;
            document.getElementById('nome').value = setor.nome;
            document.getElementById('descricao').value = setor.descricao || '';
            document.getElementById('ativo').checked = setor.ativo == 1;
            document.getElementById('ativoGroup').style.display = 'block';
            document.getElementById('modalSetor').classList.add('active');
        }
        
        function fecharModal() {
            document.getElementById('modalSetor').classList.remove('active');
        }
        
        function excluirSetor(id, nome) {
            if (confirm('Deseja excluir o setor ' + nome + '?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('modalSetor');
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
    
    <?php include '../footer.php'; ?>
    </div> <!-- Close Main Content Wrapper -->
</body>
</html>
