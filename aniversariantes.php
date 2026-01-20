<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Buscar dados atuais do usuário
$stmt = $conn->prepare("SELECT data_nascimento FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data_nascimento = $_POST['data_nascimento'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET data_nascimento = ? WHERE id = ?");
    $stmt->bind_param("si", $data_nascimento, $usuario_id);
    
    if ($stmt->execute()) {
        $mensagem = "Data de nascimento atualizada!";
        $tipo_mensagem = "success";
        $user_data['data_nascimento'] = $data_nascimento;
        registrarLog($conn, "Atualizou data de nascimento");
    } else {
        $mensagem = "Erro ao atualizar: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}

// Buscar aniversariantes do mês atual
$mes_atual = date('m');
$aniversariantes = $conn->query("
    SELECT u.nome, u.data_nascimento, s.nome as setor_nome 
    FROM usuarios u
    LEFT JOIN setores s ON u.setor_id = s.id
    WHERE MONTH(u.data_nascimento) = '$mes_atual' AND u.ativo = 1
    ORDER BY DAY(u.data_nascimento) ASC
");

$total_mes = $aniversariantes->num_rows;
$hoje_dia = date('d');
$hoje_mes = date('m');
$aniv_hoje = 0;

$list_aniv = [];
while($row = $aniversariantes->fetch_assoc()) {
    $list_aniv[] = $row;
    if (date('d', strtotime($row['data_nascimento'])) == $hoje_dia) $aniv_hoje++;
}

// Nomes dos meses em português
$meses_pt = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aniversariantes - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section (Slim) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="cake" class="w-6 h-6"></i>
                    Aniversariantes do Mês
                </h1>
                <p class="text-text-secondary text-xs mt-1">Celebre o dia especial de nossos colaboradores</p>
            </div>
        </div>

        <!-- Quick Stats (Slim Style) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-pink-50 flex items-center justify-center text-pink-500">
                    <i data-lucide="party-popper" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $total_mes; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">No Mês de <?php echo $meses_pt[$mes_atual]; ?></p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-500">
                    <i data-lucide="gift" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo $aniv_hoje; ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Aniversariantes Hoje</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-border flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-500">
                    <i data-lucide="calendar" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text"><?php echo date('d/m/Y'); ?></h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider">Data Atual</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- My Date (Slim Card) -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden sticky top-20">
                    <div class="bg-primary/5 px-4 py-3 border-b border-border">
                        <h2 class="text-[11px] font-black text-primary uppercase tracking-widest flex items-center gap-2">
                            <i data-lucide="user-edit" class="w-3.5 h-3.5"></i>
                            Meu Cadastro
                        </h2>
                    </div>
                    <form method="POST" action="" class="p-4 space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Sua Data</label>
                            <input type="date" name="data_nascimento" required 
                                   value="<?php echo $user_data['data_nascimento']; ?>"
                                   class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                        </div>
                        <button type="submit" class="w-full py-2 bg-primary text-white text-[10px] font-black rounded-lg shadow-md hover:shadow-primary/20 transition-all uppercase tracking-widest">
                            Atualizar
                        </button>
                    </form>
                    <?php if ($mensagem): ?>
                        <div class="px-4 pb-4">
                            <div class="p-2 rounded-lg text-[10px] font-bold text-center <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> animate-in slide-in-from-top-2">
                                <?php echo $mensagem; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="p-4 bg-gray-50/50 border-t border-border">
                        <p class="text-[9px] text-text-secondary/60 italic leading-tight uppercase font-bold tracking-tighter">
                            <i data-lucide="shield-check" class="w-3 h-3 inline mr-1 opacity-40"></i>
                            Seu ano de nascimento não será exibido para os colegas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Mural (Slim Cards) -->
            <div class="lg:col-span-3">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php if (count($list_aniv) > 0): ?>
                        <?php foreach($list_aniv as $niver): 
                            $dia_niver = date('d', strtotime($niver['data_nascimento']));
                            $is_hoje = ($dia_niver == $hoje_dia);
                        ?>
                            <div class="bg-white p-3 rounded-xl border <?php echo $is_hoje ? 'border-primary ring-1 ring-primary/20 bg-primary/[0.02]' : 'border-border'; ?> flex items-center gap-3 group transition-all hover:shadow-md relative overflow-hidden">
                                <?php if($is_hoje): ?>
                                    <div class="absolute -top-1 -right-1">
                                        <div class="bg-primary text-white p-1 rounded-bl-lg animate-pulse">
                                            <i data-lucide="gift" class="w-3 h-3"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="w-10 h-10 rounded-lg bg-background border border-border flex flex-col items-center justify-center flex-shrink-0 group-hover:bg-primary/5 transition-colors">
                                    <span class="text-base font-black <?php echo $is_hoje ? 'text-primary' : 'text-text'; ?> leading-none"><?php echo $dia_niver; ?></span>
                                    <span class="text-[7px] font-black text-text-secondary uppercase opacity-50"><?php echo substr($meses_pt[$mes_atual], 0, 3); ?></span>
                                </div>
                                <div class="overflow-hidden">
                                    <h4 class="text-[11px] font-bold text-text truncate group-hover:text-primary transition-colors"><?php echo $niver['nome']; ?></h4>
                                    <p class="text-[9px] text-text-secondary/60 font-black uppercase tracking-tighter truncate"><?php echo $niver['setor_nome'] ?: 'Sem Setor'; ?></p>
                                    <?php if ($is_hoje): ?>
                                        <span class="text-[8px] font-black text-primary uppercase tracking-widest mt-0.5 block">Parabéns! 🎉</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full py-16 text-center border-2 border-dashed border-border rounded-xl">
                            <i data-lucide="users" class="w-8 h-8 mx-auto mb-3 text-text-secondary opacity-10"></i>
                            <p class="text-[11px] font-bold text-text-secondary uppercase tracking-widest">Nenhum registro no mês</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
