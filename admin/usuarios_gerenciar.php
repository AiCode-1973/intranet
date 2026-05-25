<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';

// ── Ações POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    // Resetar aceite de termos
    if ($acao === 'resetar_aceite') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE usuarios SET aceite_termos = 0, data_aceite_termos = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = "Aceite de termos resetado. O usuário deverá aceitar novamente no próximo acesso.";
            registrarLog($conn, "Resetou aceite de termos do usuário ID: $id");
        }
        $stmt->close();
    }

    // Marcar aceite manualmente (pelo admin)
    if ($acao === 'marcar_aceite') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE usuarios SET aceite_termos = 1, data_aceite_termos = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = "Aceite de termos registrado manualmente para o usuário.";
            registrarLog($conn, "Marcou aceite de termos manualmente para usuário ID: $id");
        }
        $stmt->close();
    }
}

// ── Filtro ──────────────────────────────────────────────────────────────────
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos'; // todos | aceito | pendente

$where = 'WHERE u.ativo = 1';
if ($filtro === 'aceito')   $where .= ' AND u.aceite_termos = 1';
if ($filtro === 'pendente') $where .= ' AND u.aceite_termos = 0';

// Busca
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';
if ($busca) {
    $b = $conn->real_escape_string($busca);
    $where .= " AND (u.nome LIKE '%$b%' OR u.email LIKE '%$b%')";
}

$usuarios = $conn->query("
    SELECT u.id, u.nome, u.email, u.funcao, u.aceite_termos, u.data_aceite_termos, u.ultimo_acesso,
           s.nome as setor
    FROM usuarios u
    LEFT JOIN setores s ON u.setor_id = s.id
    $where
    ORDER BY u.aceite_termos ASC, u.nome ASC
");

// Totais
$total_aceito   = $conn->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND aceite_termos = 1")->fetch_row()[0];
$total_pendente = $conn->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND aceite_termos = 0")->fetch_row()[0];
$total_geral    = $total_aceito + $total_pendente;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários — Termos de Uso</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>

    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">

        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="users" class="w-6 h-6"></i>
                    Gestão de Usuários
                </h1>
                <p class="text-text-secondary text-[11px] mt-0.5 uppercase tracking-wider font-semibold">Aceite de Termos de Uso</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="politica_uso_intranet_colaborador_apas.pdf" target="_blank" rel="noopener"
                   class="flex items-center gap-2 px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-primary rounded-lg text-xs font-bold transition-all">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                    Ver Política
                </a>
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div id="adm-msg" class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700 transition-opacity duration-500">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-tighter"><?php echo $mensagem; ?></span>
            </div>
            <script>setTimeout(function(){var m=document.getElementById('adm-msg');if(m){m.style.opacity='0';setTimeout(function(){m.remove();},500);}},4000);</script>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_geral; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Total Ativos</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-green-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-green-600">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-green-700"><?php echo $total_aceito; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Termos Aceitos</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-amber-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-amber-700"><?php echo $total_pendente; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Pendentes</p>
                </div>
            </div>
        </div>

        <!-- Barra de filtros -->
        <div class="bg-white rounded-xl shadow-sm border border-border p-4 mb-4 flex flex-col md:flex-row gap-3 items-center justify-between">
            <!-- Abas -->
            <div class="flex gap-1">
                <?php foreach ([
                    'todos'    => ['label' => 'Todos', 'icon' => 'list'],
                    'aceito'   => ['label' => 'Aceitos', 'icon' => 'check-circle-2'],
                    'pendente' => ['label' => 'Pendentes', 'icon' => 'clock'],
                ] as $k => $v): ?>
                    <a href="?filtro=<?php echo $k; ?><?php echo $busca ? '&busca='.urlencode($busca) : ''; ?>"
                       class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all
                              <?php echo $filtro === $k ? 'bg-primary text-white shadow-md' : 'bg-background text-text-secondary hover:text-text'; ?>">
                        <i data-lucide="<?php echo $v['icon']; ?>" class="w-3 h-3"></i>
                        <?php echo $v['label']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <!-- Busca -->
            <form method="GET" action="" class="flex items-center gap-2">
                <input type="hidden" name="filtro" value="<?php echo htmlspecialchars($filtro); ?>">
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                       placeholder="Buscar por nome ou e-mail..."
                       class="pl-3 pr-3 py-1.5 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all w-56">
                <button type="submit" class="p-1.5 bg-primary text-white rounded-lg hover:bg-primary-hover transition-all">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                </button>
            </form>
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Colaborador</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Setor / Função</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status Termos</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Data do Aceite</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Último Acesso</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                            <?php while ($u = $usuarios->fetch_assoc()): ?>
                            <tr class="hover:bg-background/30 transition-colors">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-[11px] font-black text-primary">
                                            <?php echo strtoupper(substr($u['nome'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-text"><?php echo htmlspecialchars($u['nome']); ?></p>
                                            <p class="text-[9px] text-text-secondary"><?php echo htmlspecialchars($u['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <p class="font-bold text-text-secondary"><?php echo htmlspecialchars($u['setor'] ?? '—'); ?></p>
                                    <p class="text-[9px] text-text-secondary/60 uppercase tracking-tight"><?php echo htmlspecialchars($u['funcao'] ?? ''); ?></p>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if ($u['aceite_termos']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 border border-green-100 rounded text-[9px] font-black uppercase tracking-wider">
                                            <i data-lucide="check" class="w-2.5 h-2.5"></i>
                                            Aceito
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-100 rounded text-[9px] font-black uppercase tracking-wider">
                                            <i data-lucide="clock" class="w-2.5 h-2.5"></i>
                                            Pendente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-text-secondary font-mono text-[10px]">
                                    <?php echo $u['data_aceite_termos']
                                        ? date('d/m/Y H:i', strtotime($u['data_aceite_termos']))
                                        : '<span class="opacity-30 italic">—</span>'; ?>
                                </td>
                                <td class="p-3 text-text-secondary font-mono text-[10px]">
                                    <?php echo $u['ultimo_acesso']
                                        ? date('d/m/Y H:i', strtotime($u['ultimo_acesso']))
                                        : '<span class="opacity-30 italic">Nunca</span>'; ?>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if ($u['aceite_termos']): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Resetar o aceite fará o usuário ver o modal novamente no próximo acesso. Confirmar?');">
                                            <input type="hidden" name="acao" value="resetar_aceite">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="flex items-center gap-1.5 mx-auto px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-[9px] font-black uppercase tracking-wider hover:bg-amber-100 transition-all">
                                                <i data-lucide="rotate-ccw" class="w-2.5 h-2.5"></i>
                                                Resetar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="" onsubmit="return confirm('Marcar aceite manualmente para este usuário?');">
                                            <input type="hidden" name="acao" value="marcar_aceite">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="flex items-center gap-1.5 mx-auto px-2.5 py-1 bg-green-50 text-green-700 border border-green-200 rounded-lg text-[9px] font-black uppercase tracking-wider hover:bg-green-100 transition-all">
                                                <i data-lucide="check" class="w-2.5 h-2.5"></i>
                                                Marcar Aceite
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-16 text-center">
                                    <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 text-text-secondary opacity-20"></i>
                                    <p class="text-xs font-bold text-text-secondary">Nenhum usuário encontrado.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
