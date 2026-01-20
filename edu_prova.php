<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$curso_id = intval($_GET['id']);
$user_id = $_SESSION['usuario_id'];

// Buscar Prova
$res_prova = $conn->query("SELECT p.*, c.titulo as curso_titulo FROM edu_provas p JOIN edu_cursos c ON p.curso_id = c.id WHERE p.curso_id = $curso_id");
if ($res_prova->num_rows == 0) header("Location: educacao.php");
$prova = $res_prova->fetch_assoc();

// Verificar tentativas
$tentativas = $conn->query("SELECT COUNT(*) as total FROM edu_resultados_provas WHERE usuario_id = $user_id AND prova_id = {$prova['id']}")->fetch_assoc()['total'];
$bloqueado = ($tentativas >= $prova['tentativas_max']);

$resultado_passado = $conn->query("SELECT * FROM edu_resultados_provas WHERE usuario_id = $user_id AND prova_id = {$prova['id']} AND nota >= {$prova['nota_minima']} LIMIT 1")->fetch_assoc();

$mensagem = '';
$nota_final = null;

// Processar Envio da Prova
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_prova']) && !$bloqueado && !$resultado_passado) {
    $questoes = $conn->query("SELECT id FROM edu_questoes WHERE prova_id = {$prova['id']}");
    $total_q = $questoes->num_rows;
    $acertos = 0;

    while($q = $questoes->fetch_assoc()) {
        $resp_aluno = isset($_POST['q_'.$q['id']]) ? intval($_POST['q_'.$q['id']]) : null;
        if ($resp_aluno) {
            $check = $conn->query("SELECT id FROM edu_respostas WHERE id = $resp_aluno AND correta = 1");
            if ($check->num_rows > 0) $acertos++;
        }
    }

    $nota_final = ($total_q > 0) ? round(($acertos / $total_q) * 100) : 0;
    
    // Salvar resultado
    $stmt = $conn->prepare("INSERT INTO edu_resultados_provas (usuario_id, prova_id, nota) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $prova['id'], $nota_final);
    $stmt->execute();

    if ($nota_final >= $prova['nota_minima']) {
        $mensagem = "Parabéns! Você foi aprovado com nota $nota_final%.";
        // Gerar Certificado
        $codigo_unico = strtoupper(uniqid('CERT-'));
        $stmt_c = $conn->prepare("INSERT INTO edu_certificados (usuario_id, curso_id, codigo_unico) VALUES (?, ?, ?)");
        $stmt_c->bind_param("iis", $user_id, $curso_id, $codigo_unico);
        $stmt_c->execute();
    } else {
        $mensagem = "Infelizmente você não atingiu a nota mínima. Sua nota foi $nota_final%.";
    }
    // Refresh para mostrar resultado
    header("Location: edu_prova.php?id=$curso_id&msg=".urlencode($mensagem)."&nota=$nota_final");
    exit;
}

$msg_url = isset($_GET['msg']) ? $_GET['msg'] : '';
$nota_url = isset($_GET['nota']) ? $_GET['nota'] : null;

// Buscar Questões
$res_questoes = $conn->query("SELECT * FROM edu_questoes WHERE prova_id = {$prova['id']} ORDER BY RAND()");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliação: <?php echo $prova['curso_titulo']; ?> - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans">
    <?php include 'header.php'; ?>
    
    <div class="max-w-3xl mx-auto p-6 flex-grow">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-primary mb-2">Avaliação de Conhecimento</h1>
            <p class="text-text-secondary text-sm">Curso: <span class="font-bold text-text"><?php echo $prova['curso_titulo']; ?></span></p>
        </div>

        <?php if ($msg_url): ?>
            <div class="p-8 rounded-3xl border <?php echo $nota_url >= $prova['nota_minima'] ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?> mb-8 text-center">
                <i data-lucide="<?php echo $nota_url >= $prova['nota_minima'] ? 'award' : 'alert-circle'; ?>" class="w-16 h-16 mx-auto mb-4"></i>
                <h2 class="text-2xl font-bold mb-2"><?php echo $msg_url; ?></h2>
                <?php if ($nota_url >= $prova['nota_minima']): ?>
                    <p class="text-sm mb-6">Seu certificado já foi gerado e está disponível no painel do curso.</p>
                    <a href="edu_curso.php?id=<?php echo $curso_id; ?>" class="bg-green-600 text-white px-8 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg">Voltar ao Curso</a>
                <?php else: ?>
                    <p class="text-sm mb-6">Revise o conteúdo e tente novamente. Tentativas restantes: <?php echo $prova['tentativas_max'] - $tentativas; ?></p>
                    <?php if (!$bloqueado): ?>
                        <a href="edu_prova.php?id=<?php echo $curso_id; ?>" class="bg-red-600 text-white px-8 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg">Tentar Novamente</a>
                    <?php else: ?>
                        <p class="text-xs font-bold text-red-700 uppercase">Limite de tentativas atingido.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($resultado_passado): ?>
            <div class="bg-indigo-50 border border-indigo-200 p-8 rounded-3xl text-center text-indigo-900 mb-8">
                <i data-lucide="check-check" class="w-12 h-12 mx-auto mb-4"></i>
                <h3 class="text-lg font-bold mb-2">Você já foi aprovado nesta avaliação!</h3>
                <p class="text-sm mb-6">Nota: <?php echo $resultado_passado['nota']; ?>%</p>
                <a href="edu_curso.php?id=<?php echo $curso_id; ?>" class="text-xs font-black uppercase text-indigo-600 border-b border-indigo-200">Voltar para o curso</a>
            </div>
        <?php elseif ($bloqueado): ?>
             <div class="bg-red-50 border border-red-100 p-8 rounded-3xl text-center text-red-900 mb-8">
                <i data-lucide="slash" class="w-12 h-12 mx-auto mb-4"></i>
                <h3 class="text-lg font-bold mb-2">Acesso Bloqueado</h3>
                <p class="text-sm">Você atingiu o limite máximo de <?php echo $prova['tentativas_max']; ?> tentativas.</p>
            </div>
        <?php else: ?>
            <!-- Form de Prova -->
            <form method="POST" class="space-y-8">
                <input type="hidden" name="enviar_prova" value="1">
                
                <?php 
                $num = 1;
                while($q = $res_questoes->fetch_assoc()): 
                ?>
                <div class="bg-white p-6 rounded-2xl border border-border shadow-sm">
                    <p class="text-sm font-bold text-text mb-6 flex gap-3">
                        <span class="text-primary opacity-30"><?php echo $num++; ?>.</span>
                        <?php echo $q['enunciado']; ?>
                    </p>
                    <div class="space-y-3">
                        <?php 
                        $respostas = $conn->query("SELECT * FROM edu_respostas WHERE questao_id = {$q['id']} ORDER BY RAND()");
                        while($r = $respostas->fetch_assoc()):
                        ?>
                        <label class="flex items-center gap-4 p-4 rounded-xl border border-border hover:border-primary hover:bg-primary/5 cursor-pointer transition-all group">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo $r['id']; ?>" class="w-4 h-4 accent-primary" required>
                            <span class="text-xs font-medium text-text-secondary group-hover:text-text"><?php echo $r['texto']; ?></span>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endwhile; ?>

                <div class="pt-8 flex justify-center">
                    <button type="submit" class="bg-primary text-white px-12 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-2xl hover:bg-primary-hover hover:-translate-y-1 active:scale-95 transition-all">
                        Finalizar Avaliação
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
