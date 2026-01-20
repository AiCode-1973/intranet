<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$filtro_usuario = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
$filtro_data = isset($_GET['data']) ? $_GET['data'] : '';

$where = [];
$params = [];
$types = '';

if ($filtro_usuario > 0) {
    $where[] = "l.usuario_id = ?";
    $params[] = $filtro_usuario;
    $types .= 'i';
}

if ($filtro_data) {
    $where[] = "DATE(l.created_at) = ?";
    $params[] = $filtro_data;
    $types .= 's';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT l.*, u.nome as usuario_nome, u.email as usuario_email, u.is_admin
    FROM logs_acesso l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    $where_clause
    ORDER BY l.created_at DESC
    LIMIT 100
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($sql);
}

$usuarios = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria de Logs - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="scroll-text" class="w-6 h-6"></i>
                    Logs de Auditoria
                </h1>
                <p class="text-text-secondary text-[11px] mt-0.5 uppercase tracking-wider font-semibold">Rastreabilidade do Sistema</p>
            </div>
            
            <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                Voltar
            </a>
        </div>

        <!-- Filters Section -->
        <div class="bg-white p-3 rounded-xl shadow-sm border border-border mb-6 flex flex-col md:flex-row gap-3 items-end">
            <form method="GET" action="" class="w-full flex flex-col md:flex-row gap-3 items-end">
                <div class="w-full md:w-56">
                    <label for="usuario_id" class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-[0.1em]">Usuário</label>
                    <div class="relative">
                        <select id="usuario_id" name="usuario_id" class="w-full pl-8 pr-4 py-1.5 bg-background border border-border rounded-lg text-xs font-bold text-text appearance-none focus:outline-none focus:border-primary transition-all">
                            <option value="">Todos</option>
                            <?php 
                            $usuarios->data_seek(0);
                            while ($usuario = $usuarios->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $usuario['id']; ?>" <?php echo $filtro_usuario == $usuario['id'] ? 'selected' : ''; ?>>
                                    <?php echo $usuario['nome']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i data-lucide="user" class="absolute left-2.5 top-2 w-3.5 h-3.5 text-text-secondary"></i>
                    </div>
                </div>

                <div class="w-full md:w-40">
                    <label for="data" class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-[0.1em]">Data</label>
                    <div class="relative">
                        <input type="date" id="data" name="data" value="<?php echo $filtro_data; ?>" 
                               class="w-full pl-8 pr-4 py-1.5 bg-background border border-border rounded-lg text-xs font-bold text-text focus:outline-none focus:border-primary transition-all">
                        <i data-lucide="calendar" class="absolute left-2.5 top-2 w-3.5 h-3.5 text-text-secondary"></i>
                    </div>
                </div>
                
                <div class="flex gap-2 w-full md:w-auto">
                    <button type="submit" class="flex-grow md:flex-none bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all flex items-center justify-center gap-1.5">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        FILTRAR
                    </button>
                    
                    <?php if ($filtro_usuario > 0 || $filtro_data): ?>
                        <a href="logs.php" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-all border border-red-100" title="Limpar">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Logs Table -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[9px] font-black text-text-secondary uppercase tracking-widest">Data / Hora</th>
                            <th class="p-3 text-[9px] font-black text-text-secondary uppercase tracking-widest">Agente</th>
                            <th class="p-3 text-[9px] font-black text-text-secondary uppercase tracking-widest">Ação Auditoria</th>
                            <th class="p-3 text-[9px] font-black text-text-secondary uppercase tracking-widest">Endereço IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php 
                        $total_logs = 0;
                        while ($log = $logs->fetch_assoc()): 
                            $total_logs++;
                            $is_critical = strpos(strtolower($log['acao']), 'excluiu') !== false || strpos(strtolower($log['acao']), 'editou') !== false;
                        ?>
                        <tr class="hover:bg-background/20 transition-colors group">
                            <td class="p-3 whitespace-nowrap">
                                <span class="text-[10px] font-bold text-text mono"><?php echo formatarData($log['created_at']); ?></span>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-[9px] font-black text-primary border border-primary/20 uppercase">
                                        <?php echo substr($log['usuario_nome'] ?? '?', 0, 1); ?>
                                    </div>
                                    <span class="text-xs font-bold text-text"><?php echo $log['usuario_nome'] ?? 'SISTEMA'; ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="text-[11px] font-medium <?php echo $is_critical ? 'text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded border border-orange-100' : 'text-text-secondary'; ?>">
                                    <?php echo $log['acao']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="text-[9px] font-mono text-text-secondary bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                    <?php echo $log['ip_address']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($total_logs == 0): ?>
                        <tr>
                            <td colspan="4" class="p-12 text-center">
                                <p class="text-[10px] font-bold text-text-secondary uppercase italic">Sem registros militares.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-2.5 bg-background/50 border-t border-border text-[9px] font-bold text-text-secondary uppercase tracking-widest text-right">
                Linhas Processadas: <?php echo $total_logs; ?>
            </div>
        </div>
    </div>    
    <?php include '../footer.php'; ?>
    </div> <!-- Close Main Content Wrapper -->
</body>
</html>
