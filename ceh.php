<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar abertura de chamado CEH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'abrir_chamado_ceh') {
    $titulo = sanitize($_POST['titulo']);
    $descricao = $_POST['descricao'];
    $prioridade = isset($_POST['prioridade']) ? sanitize($_POST['prioridade']) : 'Média';
    $categoria = sanitize($_POST['categoria']);

    $stmt = $conn->prepare("INSERT INTO ceh_chamados (titulo, descricao, prioridade, categoria, usuario_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $titulo, $descricao, $prioridade, $categoria, $usuario_id);

    if ($stmt->execute()) {
        $mensagem = "Chamado CEH aberto com sucesso! A equipe técnica foi notificada.";
        $tipo_mensagem = "success";
        registrarLog($conn, "Abriu chamado CEH: " . $titulo);
    } else {
        $mensagem = "Erro ao abrir chamado: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Filtros
$filtro_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where_clauses = [];

if (!isAdmin()) {
    $where_clauses[] = "c.usuario_id = $usuario_id";
}

if ($filtro_status) {
    $where_clauses[] = "c.status = '$filtro_status'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Buscar chamados CEH
$sql = "SELECT c.*, u.nome as solicitante, t.nome as tecnico 
        FROM ceh_chamados c 
        JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN usuarios t ON c.tecnico_id = t.id 
        $where_sql
        ORDER BY c.data_abertura DESC";
$res = $conn->query($sql);
$chamados = [];
$stats = ['Aberto' => 0, 'Em Atendimento' => 0, 'Aguardando Peça' => 0, 'Resolvido' => 0, 'Cancelado' => 0];

while($row = $res->fetch_assoc()) {
    $chamados[] = $row;
    if (isset($stats[$row['status']])) $stats[$row['status']]++;
}

$status_styles = [
    'Aberto' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'dot' => 'bg-blue-500', 'icon' => 'clock'],
    'Em Atendimento' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'dot' => 'bg-amber-500', 'icon' => 'play-circle'],
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'dot' => 'bg-purple-500', 'icon' => 'component'],
    'Resolvido' => ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'dot' => 'bg-green-500', 'icon' => 'check-circle'],
    'Cancelado' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'dot' => 'bg-red-500', 'icon' => 'x-circle']
];

$prioridade_labels = [
    'Baixa' => ['text' => 'text-gray-400', 'icon' => 'arrow-down'],
    'Média' => ['text' => 'text-blue-500', 'icon' => 'minus'],
    'Alta' => ['text' => 'text-orange-500', 'icon' => 'arrow-up'],
    'Urgente' => ['text' => 'text-red-600 font-bold', 'icon' => 'alert-circle']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Equipamentos (CEH) - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                    Central de Equipamentos (CEH)
                </h1>
                <p class="text-text-secondary text-xs mt-1">Chamados técnicos para equipamentos hospitalares</p>
            </div>

            <div class="flex items-center gap-2">
                <?php if (isAdmin()): ?>
                <a href="admin/ceh_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Painel Gestor CEH
                </a>
                <?php endif; ?>
                <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-[11px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-wider">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Novo Chamado
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-6 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 animate-in slide-in-from-top-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <a href="ceh.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_status ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($chamados); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach(['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Resolvido'] as $st): 
                $active = ($filtro_status == $st);
                $style = $status_styles[$st];
                $icon = $style['icon'];
            ?>
            <a href="?status=<?php echo urlencode($st); ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$st]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $st; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Chamados List -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">ID / Equipamento</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Técnico CEH</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php if (count($chamados) > 0): ?>
                            <?php foreach ($chamados as $chamado): 
                                $style = $status_styles[$chamado['status']];
                                $prio = $prioridade_labels[$chamado['prioridade']];
                            ?>
                            <tr class="hover:bg-background/20 transition-colors group">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[9px] font-mono font-bold text-text-secondary opacity-50">#<?php echo str_pad($chamado['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-text group-hover:text-primary transition-colors"><?php echo $chamado['titulo']; ?></span>
                                            <span class="text-[9px] text-text-secondary uppercase font-bold tracking-tighter"><?php echo $chamado['categoria']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5 <?php echo $prio['text']; ?>">
                                        <i data-lucide="<?php echo $prio['icon']; ?>" class="w-3.5 h-3.5"></i>
                                        <span class="font-bold uppercase tracking-tighter text-[10px]"><?php echo $chamado['prioridade']; ?></span>
                                    </div>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?php echo $style['bg']; ?> <?php echo $style['text']; ?>">
                                        <?php echo $chamado['status']; ?>
                                    </span>
                                </td>
                                <td class="p-3 font-bold text-text-secondary">
                                    <?php if ($chamado['tecnico']): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 rounded bg-primary/10 flex items-center justify-center text-[9px] font-bold text-primary">
                                                <?php echo strtoupper(substr($chamado['tecnico'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo $chamado['tecnico']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-text-secondary/30 italic">Em triagem...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right font-mono text-text-secondary opacity-60 text-[10px]">
                                    <?php echo date('d/m H:i', strtotime($chamado['data_abertura'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-16 text-center">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhum chamado CEH encontrado.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado CEH -->
    <div id="modalChamado" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Solicitação CEH</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Equipamentos Hospitalares</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" value="abrir_chamado_ceh">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Identificação do Equipamento</label>
                        <input type="text" name="titulo" required placeholder="Ex: Monitor Multiparamétrico - Sala 04" 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Tipo de Serviço</label>
                        <select name="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all cursor-pointer">
                            <option value="Manutenção Corretiva">Manutenção Corretiva</option>
                            <option value="Manutenção Preventiva">Manutenção Preventiva</option>
                            <option value="Calibração">Calibração</option>
                            <option value="Treinamento de Uso">Treinamento de Uso</option>
                            <option value="Dúvida Técnica">Dúvida Técnica</option>
                            <option value="Equipamento Geral" selected>Equipamento Geral</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Descrição do Defeito / Solicitação</label>
                        <textarea name="descricao" required rows="4" placeholder="Detalhes do que está acontecendo com o equipamento..."
                                  class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Abrir Chamado</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalChamado').classList.add('active'); }
        function fecharModal() { document.getElementById('modalChamado').classList.remove('active'); }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
