<?php
require_once '../config.php';
require_once '../functions.php';

requireManutencao();

$mensagem = '';
$tipo_mensagem = '';

// Processar ações administrativas (Atribuir técnico, Mudar Status, Resolver)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $id = intval($_POST['id']);
    
    if ($_POST['acao'] == 'atualizar_chamado') {
        $status = sanitize($_POST['status']);
        $tecnico_id = $_POST['tecnico_id'] ? intval($_POST['tecnico_id']) : null;
        $resolucao = isset($_POST['resolucao']) ? $_POST['resolucao'] : null;
        $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare("UPDATE manutencao SET status = ?, tecnico_id = ?, resolucao = ?, data_fechamento = ? WHERE id = ?");
        $stmt->bind_param("sissi", $status, $tecnico_id, $resolucao, $data_fechamento, $id);

        if ($stmt->execute()) {
            $mensagem = "Chamado #$id atualizado com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Atualizou chamado de manutenção #$id para $status");
        } else {
            $mensagem = "Erro ao atualizar: " . $conn->error;
            $tipo_mensagem = "danger";
        }
        $stmt->close();
    }

    // Processar exclusão do chamado
    if ($_POST['acao'] == 'excluir_chamado' && isAdmin()) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM manutencao WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $mensagem = "Ordem de Serviço #$id removida com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Excluiu ordem de serviço #$id");
        } else {
            $mensagem = "Erro ao excluir: " . $conn->error;
            $tipo_mensagem = "danger";
        }
        $stmt->close();
    }
}

// Filtros
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_sql = $filtro_status ? "WHERE m.status = '$filtro_status'" : "";

// Buscar chamados
$sql = "SELECT m.*, u.nome as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante
        FROM manutencao m 
        JOIN usuarios u ON m.usuario_id = u.id 
        LEFT JOIN usuarios t ON m.tecnico_id = t.id 
        LEFT JOIN setores s ON u.setor_id = s.id
        $where_sql
        ORDER BY m.data_abertura DESC";
$res = $conn->query($sql);
$chamados = [];
while($row = $res->fetch_assoc()) $chamados[] = $row;

// Buscar técnicos (Marcados explicitamente como técnicos de manutenção)
$tecnicos_res = $conn->query("SELECT id, nome FROM usuarios WHERE is_manutencao = 1 AND ativo = 1 ORDER BY nome");
$tecnicos = [];
while($row = $tecnicos_res->fetch_assoc()) $tecnicos[] = $row;

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Manutenção - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="wrench" class="w-6 h-6"></i>
                    Gestão de Manutenção & Infraestrutura
                </h1>
                <p class="text-text-secondary text-xs mt-1">Painel administrativo para controle de ordens de serviço</p>
            </div>
            
            <a href="../manutencao.php" class="text-[11px] font-bold text-text-secondary hover:text-primary transition-colors flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar à lista
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-6 flex items-center gap-2 bg-green-50 border-green-100 text-green-700">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / OS</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Solicitante</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Local / Categ.</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Responsável</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php foreach ($chamados as $c): 
                            $style = $status_styles[$c['status']];
                        ?>
                        <tr class="hover:bg-background/20 transition-colors group">
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-text">#<?php echo str_pad($c['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                    <span class="text-[11px] text-text-secondary truncate max-w-[200px]"><?php echo $c['titulo']; ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-text"><?php echo $c['solicitante']; ?></span>
                                    <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $c['setor_solicitante']; ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-text-secondary"><?php echo $c['local']; ?></span>
                                    <span class="text-[9px] text-primary font-black uppercase tracking-widest"><?php echo $c['categoria']; ?></span>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?php echo $style['bg']; ?> <?php echo $style['text']; ?>">
                                    <?php echo $c['status']; ?>
                                </span>
                            </td>
                            <td class="p-3 font-bold text-text-secondary">
                                <?php echo $c['tecnico_nome'] ?: '<span class="italic opacity-30">Pendente</span>'; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick='abrirModal(<?php echo json_encode($c); ?>)' 
                                            class="p-2 hover:bg-primary/10 text-text-secondary hover:text-primary transition-all rounded-lg" title="Editar/Ver">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <?php if (isAdmin()): ?>
                                    <button onclick="excluirChamado(<?php echo $c['id']; ?>)" 
                                            class="p-2 hover:bg-rose-50 text-text-secondary hover:text-rose-500 transition-all rounded-lg" title="Excluir OS">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditar" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold">Gestão da Ordem #<span id="modal-id-display"></span></h2>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" value="atualizar_chamado">
                <input type="hidden" name="id" id="modal-id">
                
                <div class="mb-4 p-3 bg-background rounded-lg border border-border">
                    <p class="text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Solicitação Original</p>
                    <p id="modal-titulo" class="text-sm font-bold text-text mb-1"></p>
                    <p id="modal-descricao" class="text-xs text-text-secondary italic"></p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Status</label>
                        <select name="status" id="modal-status" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold">
                            <?php foreach($status_styles as $status => $st): ?>
                                <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Técnico Responsável</label>
                        <select name="tecnico_id" id="modal-tecnico" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold">
                            <option value="">Selecione...</option>
                            <?php foreach($tecnicos as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Resolução / Observações Técnicas</label>
                        <textarea name="resolucao" id="modal-resolucao" rows="3" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(dados) {
            document.getElementById('modal-id').value = dados.id;
            document.getElementById('modal-id-display').innerText = dados.id.toString().padStart(3, '0');
            document.getElementById('modal-titulo').innerText = dados.titulo;
            document.getElementById('modal-descricao').innerText = dados.descricao;
            document.getElementById('modal-status').value = dados.status;
            document.getElementById('modal-tecnico').value = dados.tecnico_id || "";
            document.getElementById('modal-resolucao').value = dados.resolucao || "";
            
            document.getElementById('modalEditar').classList.add('active');
        }
        function fecharModal() { document.getElementById('modalEditar').classList.remove('active'); }

        function excluirChamado(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente esta ordem de serviço? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_chamado">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
