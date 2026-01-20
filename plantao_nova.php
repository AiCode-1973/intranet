<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Buscar colaboradores do mesmo setor (exceto o próprio usuário)
$setor_id = isset($_SESSION['setor_id']) ? $_SESSION['setor_id'] : null;

if ($setor_id) {
    $colaboradores = $conn->query("SELECT id, nome FROM usuarios WHERE setor_id = $setor_id AND id != $usuario_id AND ativo = 1 ORDER BY nome ASC");
} else {
    // Se for um admin sem setor, permitir escolher qualquer um
    $colaboradores = $conn->query("SELECT id, nome FROM usuarios WHERE id != $usuario_id AND ativo = 1 ORDER BY nome ASC");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'solicitar') {
    $colaborador_id = intval($_POST['colaborador_id']);
    $data_plantao_sol = sanitize($_POST['data_plantao_sol']);
    $data_troca_sol = sanitize($_POST['data_troca_sol']);
    $data_plantao_col = sanitize($_POST['data_plantao_col']);
    $data_troca_col = sanitize($_POST['data_troca_col']);
    $observacoes = sanitize($_POST['observacoes']);

    $stmt = $conn->prepare("INSERT INTO trocas_plantao 
        (solicitante_id, data_plantao_solicitante, data_troca_solicitante, colaborador_id, data_plantao_colaborador, data_troca_colaborador, observacoes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $usuario_id, $data_plantao_sol, $data_troca_sol, $colaborador_id, $data_plantao_col, $data_troca_col, $observacoes);

    if ($stmt->execute()) {
        $mensagem = "Solicitação de troca enviada com sucesso! Aguardando aceite do colaborador.";
        $tipo_mensagem = "success";
        
        // Registrar log
        registrarLog($conn, "Solicitou troca de plantão com colaborador ID $colaborador_id");
    } else {
        $mensagem = "Erro ao solicitar troca: " . $conn->error;
        $tipo_mensagem = "danger";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Troca de Plantão - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include 'header.php'; ?>
    
    <div class="p-4 md:p-6 w-full max-w-4xl mx-auto flex-grow">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-primary flex items-center gap-3">
                    <i data-lucide="refresh-cw" class="w-8 h-8"></i>
                    Nova Troca de Plantão
                </h1>
                <p class="text-text-secondary text-sm mt-1">Preencha os dados abaixo para solicitar uma troca com um colega.</p>
            </div>
            <a href="plantao_trocas.php" class="flex items-center gap-2 px-4 py-2 bg-white border border-border rounded-xl text-xs font-bold text-text-secondary hover:text-primary hover:border-primary transition-all shadow-sm">
                <i data-lucide="list" class="w-4 h-4"></i>
                Minhas Trocas
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-2xl border flex items-start gap-3 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> animate-in fade-in slide-in-from-top-2">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span class="text-sm font-bold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl border border-border shadow-xl overflow-hidden">
            <form method="POST" action="" class="p-6 md:p-8 space-y-8">
                <input type="hidden" name="acao" value="solicitar">

                <!-- Seção: Colaborador -->
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-text-secondary mb-4 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                        Colaborador para Troca
                    </h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Selecione o Colega</label>
                            <select name="colaborador_id" required class="w-full px-4 py-3.5 bg-gray-50 border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all appearance-none cursor-pointer">
                                <option value="">Escolha um colaborador...</option>
                                <?php while($c = $colaboradores->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Bloco: Solicitante (Eu) -->
                    <div class="space-y-6 p-6 bg-primary/[0.02] rounded-3xl border border-primary/10">
                        <h3 class="text-xs font-black uppercase tracking-widest text-primary flex items-center gap-2">
                            <i data-lucide="user-check" class="w-4 h-4"></i>
                            Minha Parte (Solicitante)
                        </h3>
                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Data do meu Plantão</label>
                                <input type="date" name="data_plantao_sol" required class="w-full px-4 py-3 bg-white border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary transition-all">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Data da minha Troca</label>
                                <input type="date" name="data_troca_sol" required class="w-full px-4 py-3 bg-white border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Bloco: Colaborador (Ele) -->
                    <div class="space-y-6 p-6 bg-amber-500/[0.02] rounded-3xl border border-amber-500/10">
                        <h3 class="text-xs font-black uppercase tracking-widest text-amber-600 flex items-center gap-2">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            Parte do Colega (Colaborador)
                        </h3>
                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Data do Plantão dele</label>
                                <input type="date" name="data_plantao_col" required class="w-full px-4 py-3 bg-white border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary transition-all">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Data da Troca dele</label>
                                <input type="date" name="data_troca_col" required class="w-full px-4 py-3 bg-white border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary transition-all">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observações -->
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Observações / Motivo (Opcional)</label>
                    <textarea name="observacoes" rows="3" class="w-full px-4 py-3 bg-gray-50 border border-border rounded-xl text-sm font-bold text-text focus:outline-none focus:border-primary transition-all placeholder:font-normal" placeholder="Descreva brevemente o motivo da troca..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-4 pt-4 border-t border-border">
                    <button type="reset" class="px-6 py-3 text-xs font-black uppercase tracking-widest text-text-secondary hover:text-red-500 transition-colors">
                        Limpar Dados
                    </button>
                    <button type="submit" class="px-8 py-4 bg-primary hover:bg-primary-hover text-white font-black rounded-2xl shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 text-xs uppercase tracking-[0.2em]">
                        Enviar Solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
