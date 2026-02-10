<?php
require_once '../config.php';
require_once '../functions.php';

requireTecnico();

$mensagem = '';
$tipo_mensagem = '';

// Processar atualização do chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_chamado') {
    $id = intval($_POST['id']);
    $status = sanitize($_POST['status']);
    $resolucao = $_POST['resolucao'];
    $tecnico_id = intval($_POST['tecnico_id']);
    $data_fechamento = ($status == 'Resolvido' || $status == 'Cancelado') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE chamados SET status = ?, resolucao = ?, tecnico_id = ?, data_fechamento = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $status, $resolucao, $tecnico_id, $data_fechamento, $id);

    if ($stmt->execute()) {
        $mensagem = "Chamado #$id atualizado!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Atualizou chamado #$id para status: $status");
    } else {
        $mensagem = "Erro ao atualizar: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Processar exclusão do chamado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'excluir_chamado' && isAdmin()) {
    $id = intval($_POST['id']);
    
    $stmt = $conn->prepare("DELETE FROM chamados WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $mensagem = "Chamado #$id removido com sucesso!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu chamado #$id");
    } else {
        $mensagem = "Erro ao excluir: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Buscar todos os chamados com detalhes
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico_nome, s.nome as setor_solicitante
        FROM chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN setores s ON u.setor_id = s.id
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        ORDER BY 
            CASE 
                WHEN c.status = 'Aberto' THEN 1 
                WHEN c.status = 'Em Atendimento' THEN 2 
                WHEN c.status = 'Aguardando Peça' THEN 3 
                ELSE 4 
            END, 
            c.prioridade DESC, 
            c.data_abertura ASC";
$chamados = $conn->query($sql);

// Buscar lista de técnicos (Filtra especificamente pelo atributo is_tecnico = TI)
$tecnicos = $conn->query("SELECT id, nome FROM usuarios WHERE is_tecnico = 1 AND ativo = 1 ORDER BY nome ASC");

$status_styles = [
    'Aberto' => 'bg-blue-50 text-blue-600 border-blue-100',
    'Em Atendimento' => 'bg-amber-50 text-amber-600 border-amber-100',
    'Aguardando Peça' => 'bg-purple-50 text-purple-600 border-purple-100',
    'Resolvido' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
    'Cancelado' => 'bg-gray-50 text-gray-400 border-gray-100'
];

$prioridade_styles = [
    'Baixa' => 'text-gray-400',
    'Média' => 'text-primary font-bold',
    'Alta' => 'text-orange-500 font-bold',
    'Urgente' => 'text-rose-600 font-black'
];

// Stats para o dashboard superior
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Total' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as total FROM chamados WHERE status IN ('Aberto', 'Em Atendimento') GROUP BY status");
while($row = $res_stats->fetch_assoc()) {
    $stats[$row['status']] = $row['total'];
}
$stats['Total'] = $conn->query("SELECT COUNT(*) FROM chamados")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Suporte - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header (Slim Style) -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                    Gerencial de Chamados
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão técnica e operacional de TI</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="../suporte.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    Visão Usuário
                </a>
                <a href="../suporte.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Dashboard Superior (Slim) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500">
                    <i data-lucide="inbox" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Aberto']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Abertos</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-500">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Em Atendimento']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Em Curso</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats['Total']; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Total Histórico</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo date('H:i'); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Hora Atual</p>
                </div>
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
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Assunto</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Solicitante</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php while ($chamado = $chamados->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group <?php echo in_array($chamado['status'], ['Resolvido', 'Cancelado']) ? 'opacity-40' : ''; ?>">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[9px] bg-gray-50 border border-border px-1 rounded text-text-secondary/50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                    <div>
                                        <p class="font-bold text-text leading-tight group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></p>
                                        <p class="text-[9px] text-text-secondary uppercase font-black opacity-50"><?php echo $chamado['categoria']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <p class="font-bold text-text leading-tight"><?php echo $chamado['solicitante']; ?></p>
                                <p class="text-[9px] text-text-secondary uppercase font-black opacity-50"><?php echo $chamado['setor_solicitante']; ?></p>
                            </td>
                            <td class="p-3 text-center">
                                <span class="<?php echo $prioridade_styles[$chamado['prioridade']]; ?> uppercase tracking-tighter text-[10px]">
                                    <?php echo $chamado['prioridade']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase border <?php echo $status_styles[$chamado['status']]; ?>">
                                    <?php echo $chamado['status']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <?php if ($chamado['tecnico_nome']): ?>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded bg-primary/10 flex items-center justify-center text-[9px] font-bold text-primary">
                                            <?php echo substr($chamado['tecnico_nome'], 0, 1); ?>
                                        </div>
                                        <span class="text-[11px] font-bold text-text-secondary"><?php echo $chamado['tecnico_nome']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-300 italic text-[10px]">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='abrirAtendimento(<?php echo json_encode($chamado); ?>)' class="px-3 py-1 bg-primary text-white rounded-lg font-black uppercase tracking-widest text-[9px] transition-all hover:bg-primary-hover shadow-md shadow-primary/10 active:scale-95">
                                        Gerenciar
                                    </button>
                                    <?php if (isAdmin()): ?>
                                    <button onclick="excluirChamado(<?php echo $chamado['id']; ?>)" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition-all active:scale-90" title="Excluir Registro">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Atendimento (Slim Pattern) -->
    <div id="modalAtender" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold text-white uppercase flex items-center gap-2">
                        <span id="view_id" class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">#000</span>
                        Atendimento Técnico
                    </h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest mt-0.5">Gestão da Ocorrência</p>
                </div>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="p-4 bg-gray-50 border-b border-border">
                <h3 class="text-sm font-bold text-text mb-1" id="view_titulo">---</h3>
                <p class="text-xs text-text-secondary leading-relaxed bg-white p-3 rounded-lg border border-border/50" id="view_descricao">---</p>
                <div class="mt-3 flex gap-4 text-[9px] font-black text-text-secondary/40 uppercase tracking-widest">
                    <span id="view_solicitante">---</span>
                    <span id="view_data">---</span>
                </div>
            </div>

            <form method="POST" action="" class="p-5 space-y-4">
                <input type="hidden" name="acao" value="atualizar_chamado">
                <input type="hidden" name="id" id="form_id">
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Avançar Status</label>
                        <select name="status" id="form_status" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="Aberto">Aberto</option>
                            <option value="Em Atendimento">Em Atendimento</option>
                            <option value="Aguardando Peça">Aguardando Peça</option>
                            <option value="Resolvido">Resolvido ✅</option>
                            <option value="Cancelado">Cancelado ❌</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Técnico Atribuído</label>
                        <select name="tecnico_id" id="form_tecnico" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="">Selecione o Técnico</option>
                            <?php 
                            $tecnicos->data_seek(0);
                            while($t = $tecnicos->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Resolução / Observações Técnicas</label>
                    <textarea name="resolucao" id="form_resolucao" rows="5" placeholder="Documente o atendimento ou a solução aplicada..."
                              class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                </div>

                <div class="flex justify-end gap-2 mt-6 pt-2">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirAtendimento(chamado) {
            document.getElementById('view_id').textContent = '#' + chamado.id.toString().padStart(3, '0');
            document.getElementById('view_titulo').textContent = chamado.titulo;
            document.getElementById('view_descricao').textContent = chamado.descricao;
            document.getElementById('view_solicitante').textContent = 'Solicitante: ' + chamado.solicitante + ' (' + chamado.setor_solicitante + ')';
            document.getElementById('view_data').textContent = 'Aberto em: ' + chamado.data_abertura;
            
            document.getElementById('form_id').value = chamado.id;
            document.getElementById('form_status').value = chamado.status;
            document.getElementById('form_tecnico').value = chamado.tecnico_id || '';
            document.getElementById('form_resolucao').value = chamado.resolucao || '';
            
            document.getElementById('modalAtender').classList.add('active');
        }
        function fecharModal() { document.getElementById('modalAtender').classList.remove('active'); }

        function excluirChamado(id) {
            if (confirm('Tem certeza que deseja excluir permanentemente este chamado? Esta ação não pode ser desfeita.')) {
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
