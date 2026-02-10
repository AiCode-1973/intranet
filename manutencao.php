<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar abertura de chamado de manutenção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'abrir_manutencao') {
    $titulo = sanitize($_POST['titulo']);
    $descricao = $_POST['descricao'];
    $local = sanitize($_POST['local']);
    $prioridade = isset($_POST['prioridade']) ? sanitize($_POST['prioridade']) : 'Média';
    $categoria = sanitize($_POST['categoria']);

    $stmt = $conn->prepare("INSERT INTO manutencao (titulo, descricao, local, prioridade, categoria, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $titulo, $descricao, $local, $prioridade, $categoria, $usuario_id);

    if ($stmt->execute()) {
        $mensagem = "Chamado de manutenção aberto com sucesso! A equipe de infraestrutura já foi notificada.";
        $tipo_mensagem = "success";
        registrarLog($conn, "Abriu chamado de Manutenção: " . $titulo);
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
    $where_clauses[] = "m.usuario_id = $usuario_id";
}

if ($filtro_status) {
    $where_clauses[] = "m.status = '$filtro_status'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Buscar chamados de manutenção
$sql = "SELECT m.*, u.nome as solicitante, t.nome as tecnico 
        FROM manutencao m 
        JOIN usuarios u ON m.usuario_id = u.id 
        LEFT JOIN usuarios t ON m.tecnico_id = t.id 
        $where_sql
        ORDER BY m.data_abertura DESC";
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
    'Aguardando Peça' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'dot' => 'bg-purple-500', 'icon' => 'package'],
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
    <title>Infraestrutura & Manutenção - APAS Intranet</title>
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
                    <i data-lucide="wrench" class="w-6 h-6"></i>
                    Manutenção & Infraestrutura
                </h1>
                <p class="text-text-secondary text-xs mt-1">Reparos prediais, elétricos e hidráulicos</p>
            </div>

            <div class="flex items-center gap-2">
                <?php if (isAdmin()): ?>
                <a href="admin/manutencao_gerenciar.php" class="bg-white hover:bg-gray-50 text-text p-2 rounded-lg border border-border shadow-sm transition-all flex items-center gap-2 text-[11px] font-bold">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Gerenciar Demandas
                </a>
                <?php endif; ?>
                <button onclick="abrirModal()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-[11px] font-bold shadow-md transition-all flex items-center gap-2 uppercase tracking-wider">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Abrir Ordem de Serviço
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
            <a href="manutencao.php" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo !$filtro_status ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo count($chamados); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Todos</p>
                </div>
            </a>
            <?php foreach(['Aberto', 'Em Atendimento', 'Resolvido', 'Cancelado'] as $st): 
                $active = ($filtro_status == $st);
                $style = $status_styles[$st];
            ?>
            <a href="?status=<?php echo $st; ?>" class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3 group hover:border-primary transition-all <?php echo $active ? 'ring-1 ring-primary' : ''; ?>">
                <div class="w-10 h-10 rounded-lg <?php echo str_replace('text-', 'bg-', $style['text']); ?>/10 flex items-center justify-center <?php echo $style['text']; ?> group-hover:<?php echo str_replace('text-', 'bg-', $style['text']); ?> group-hover:text-white transition-all">
                    <i data-lucide="<?php echo $style['icon']; ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $stats[$st]; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider"><?php echo $st; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- List -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">OS / Descrição</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Local</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Prioridade</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php if (count($chamados) > 0): ?>
                            <?php foreach ($chamados as $chamado): 
                                $style = $status_styles[$chamado['status']];
                                $prio = $prioridade_labels[$chamado['prioridade']];
                            ?>
                            <tr class="hover:bg-background/20 transition-colors group cursor-pointer">
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
                                    <div class="flex items-center gap-1.5 text-text-secondary">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 opacity-50"></i>
                                        <span class="font-bold text-[10px]"><?php echo $chamado['local']; ?></span>
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
                                <td class="p-3 text-right font-mono text-text-secondary opacity-60 text-[10px]">
                                    <?php echo date('d/m H:i', strtotime($chamado['data_abertura'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-16 text-center">
                                    <i data-lucide="hard-hat" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhuma ordem de serviço encontrada.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado -->
    <div id="modalManutencao" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-orange-600 px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold">Nova Ordem de Serviço</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Infraestrutura</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5">
                <input type="hidden" name="acao" value="abrir_manutencao">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Assunto / Descrição Curta</label>
                        <input type="text" name="titulo" required placeholder="Ex: Lâmpada queimada, Vazamento..." 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Local exato</label>
                        <input type="text" name="local" required placeholder="Ex: Sala 02, Recepção, Banheiro 1º andar" 
                               class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Categoria</label>
                        <select name="categoria" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all cursor-pointer">
                            <option value="Elétrica">Elétrica</option>
                            <option value="Hidráulica">Hidráulica</option>
                            <option value="Pedreiro/Pintura">Pedreiro/Pintura</option>
                            <option value="Mobiliário">Mobiliário</option>
                            <option value="Ar Condicionado">Ar Condicionado</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Detalhes da Ocorrência</label>
                        <textarea name="descricao" required rows="4" placeholder="Descreva com detalhes o que está acontecendo..."
                                  class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-orange-600 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Abrir Ordem</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalManutencao').classList.add('active'); }
        function fecharModal() { document.getElementById('modalManutencao').classList.remove('active'); }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
