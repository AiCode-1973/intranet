<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!temPermissao($conn, $_SESSION['setor_id'], 'seguranca_trabalho')) {
    header("Location: dashboard.php");
    exit;
}

// Filtro por mês (Padrão: mês atual)
$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Buscar usuários admitidos no mês selecionado
$query = "
    SELECT u.*, s.nome as setor_nome 
    FROM usuarios u
    LEFT JOIN setores s ON u.setor_id = s.id
    WHERE MONTH(u.data_admissao) = ? 
    ORDER BY DAY(u.data_admissao) ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $mes_selecionado);
$stmt->execute();
$usuarios = $stmt->get_result();

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segurança do Trabalho - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="hard-hat" class="w-6 h-6"></i>
                    Segurança do Trabalho
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gestão de novos colaboradores e integrações</p>
            </div>

            <div class="flex items-center gap-2">
                <form action="" method="GET" class="flex items-center gap-2">
                    <select name="mes" onchange="this.form.submit()" class="bg-white border border-border px-3 py-2 rounded-lg text-xs font-bold focus:outline-none focus:border-primary">
                        <?php foreach($meses as $num => $nome): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mes_selecionado == $num ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Info Stats Card -->
        <div class="bg-primary/5 rounded-3xl p-8 mb-8 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl shadow-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="users-2" class="w-10 h-10"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-primary">Integração de Colaboradores</h2>
                    <p class="text-xs font-bold text-primary/60 uppercase tracking-widest mt-1">Mês de <?php echo $meses[$mes_selecionado]; ?></p>
                </div>
            </div>
            <div class="bg-white px-6 py-4 rounded-2xl shadow-sm border border-border flex flex-col items-center min-w-[150px]">
                <span class="text-3xl font-black text-primary"><?php echo $usuarios->num_rows; ?></span>
                <span class="text-[9px] font-black text-text-secondary uppercase tracking-widest leading-none">Admissões</span>
            </div>
        </div>

        <!-- Colaboradores Grid -->
        <h3 class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4"></i>
            Lista de Colaboradores Admitidos
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if ($usuarios->num_rows > 0): ?>
                <?php while ($u = $usuarios->fetch_assoc()): ?>
                <div class="bg-white p-5 rounded-2xl border border-border shadow-sm hover:border-primary/50 hover:shadow-md transition-all group relative overflow-hidden">
                    <!-- Badge de Data -->
                    <div class="absolute right-0 top-0 bg-primary/10 text-primary px-3 py-2 rounded-bl-2xl">
                        <span class="block text-center text-[10px] font-black leading-none uppercase tracking-tighter"><?php echo date('d', strtotime($u['data_admissao'])); ?></span>
                        <span class="block text-center text-[8px] font-bold leading-none opacity-60 uppercase"><?php echo substr($meses[(int)date('m', strtotime($u['data_admissao']))], 0, 3); ?></span>
                    </div>

                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gray-100 border-2 border-white shadow-sm flex items-center justify-center text-primary font-black text-sm overflow-hidden">
                            <?php if ($u['foto_path']): ?>
                                <img src="uploads/perfil/<?php echo $u['foto_path']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo substr($u['nome'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 pr-12">
                            <h4 class="text-xs font-bold text-text truncate group-hover:text-primary transition-colors"><?php echo $u['nome']; ?></h4>
                            <p class="text-[9px] font-medium text-text-secondary/60 flex items-center gap-1">
                                <i data-lucide="briefcase" class="w-3 h-3"></i>
                                <?php echo $u['setor_nome'] ?: 'Setor não informado'; ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-auto">
                        <div class="bg-background rounded-lg p-2 border border-border/40">
                            <span class="block text-[7px] font-black text-text-secondary uppercase tracking-widest mb-0.5 opacity-50">Admissão</span>
                            <span class="block text-[9px] font-bold text-text"><?php echo date('d/m/Y', strtotime($u['data_admissao'])); ?></span>
                        </div>
                        <div class="bg-background rounded-lg p-2 border border-border/40">
                            <span class="block text-[7px] font-black text-text-secondary uppercase tracking-widest mb-0.5 opacity-50">Status</span>
                            <span class="block text-[9px] font-black text-emerald-500 uppercase">Pendente Integração</span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-dashed border-border">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="user-plus" class="w-8 h-8 text-text-secondary opacity-20"></i>
                    </div>
                    <p class="text-xs font-bold text-text-secondary uppercase tracking-widest">Nenhuma admissão registrada para este mês.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>