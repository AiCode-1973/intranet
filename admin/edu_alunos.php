<?php
require_once '../config.php';
require_once '../functions.php';

requireEduAdmin();

$mensagem = '';
$tipo_mensagem = '';

$curso_id = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : null;

if (!$curso_id) {
    header("Location: edu_cursos.php");
    exit;
}

// Processar Reset de Progresso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'zerar_progresso') {
    $aluno_id = intval($_POST['aluno_id']);
    
    $conn->begin_transaction();
    try {
        // 1. Apagar progresso de aulas
        $stmt1 = $conn->prepare("DELETE FROM edu_progresso WHERE usuario_id = ? AND aula_id IN (SELECT id FROM edu_aulas WHERE curso_id = ?)");
        $stmt1->bind_param("ii", $aluno_id, $curso_id);
        $stmt1->execute();
        
        // 2. Apagar resultados de provas
        $stmt2 = $conn->prepare("DELETE FROM edu_resultados_provas WHERE usuario_id = ? AND prova_id IN (SELECT id FROM edu_provas WHERE curso_id = ?)");
        $stmt2->bind_param("ii", $aluno_id, $curso_id);
        $stmt2->execute();
        
        // 3. Apagar certificados
        $stmt3 = $conn->prepare("DELETE FROM edu_certificados WHERE usuario_id = ? AND curso_id = ?");
        $stmt3->bind_param("ii", $aluno_id, $curso_id);
        $stmt3->execute();
        
        $conn->commit();
        $mensagem = "Progresso do aluno reiniciado com sucesso!";
        $tipo_mensagem = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem = "Erro ao reiniciar progresso: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Buscar Curso e Verificar Posse
$curso_res = $conn->query("SELECT * FROM edu_cursos WHERE id = $curso_id");
if ($curso_res->num_rows == 0) {
    header("Location: edu_cursos.php");
    exit;
}
$curso = $curso_res->fetch_assoc();

if (!isAdmin() && $curso['usuario_id'] != $_SESSION['usuario_id']) {
    // Redirecionar se não for o dono nem admin
    header("Location: edu_cursos.php");
    exit;
}

// Buscar Alunos com Progresso neste curso
$sql = "SELECT DISTINCT u.id, u.nome, u.email, s.nome as setor_nome,
        (SELECT COUNT(*) FROM edu_progresso p JOIN edu_aulas a ON p.aula_id = a.id WHERE p.usuario_id = u.id AND a.curso_id = $curso_id AND p.concluido = 1) as aulas_concluidas,
        (SELECT MAX(nota) FROM edu_resultados_provas rp JOIN edu_provas pr ON rp.prova_id = pr.id WHERE rp.usuario_id = u.id AND pr.curso_id = $curso_id) as melhor_nota,
        (SELECT COUNT(*) FROM edu_certificados c WHERE c.usuario_id = u.id AND c.curso_id = $curso_id) as tem_certificado
        FROM usuarios u
        LEFT JOIN setores s ON u.setor_id = s.id
        WHERE u.id IN (
            SELECT p.usuario_id FROM edu_progresso p JOIN edu_aulas a ON p.aula_id = a.id WHERE a.curso_id = $curso_id
            UNION
            SELECT rp.usuario_id FROM edu_resultados_provas rp JOIN edu_provas pr ON rp.prova_id = pr.id WHERE pr.curso_id = $curso_id
        )
        ORDER BY u.nome ASC";

$alunos = $conn->query($sql);

// Total de aulas do curso para cálculo de %
$total_aulas = $conn->query("SELECT COUNT(*) as t FROM edu_aulas WHERE curso_id = $curso_id")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Alunos - <?php echo $curso['titulo']; ?></title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="users" class="w-6 h-6"></i>
                    Gerenciar Alunos: <?php echo $curso['titulo']; ?>
                </h1>
                <p class="text-text-secondary text-xs mt-1">Acompanhe e gerencie o progresso individual dos colaboradores</p>
            </div>
            
            <a href="edu_cursos.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar para Cursos
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-triangle'; ?>" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest">Aluno</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Progresso</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Melhor Nota</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Certificado</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if ($alunos->num_rows > 0): ?>
                            <?php while ($al = $alunos->fetch_assoc()): 
                                $perc = ($total_aulas > 0) ? round(($al['aulas_concluidas'] / $total_aulas) * 100) : 0;
                            ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-text"><?php echo $al['nome']; ?></span>
                                        <span class="text-[10px] text-text-secondary opacity-60 uppercase"><?php echo $al['setor_nome'] ?: 'Sem Setor'; ?></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col items-center gap-1.5 min-w-[120px]">
                                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-primary" style="width: <?php echo $perc; ?>%"></div>
                                        </div>
                                        <span class="text-[9px] font-black text-primary"><?php echo $al['aulas_concluidas']; ?> / <?php echo $total_aulas; ?> Aulas (<?php echo $perc; ?>%)</span>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if ($al['melhor_nota'] !== null): ?>
                                        <span class="inline-block px-2 py-1 rounded bg-indigo-50 text-indigo-600 text-[10px] font-black"><?php echo $al['melhor_nota']; ?>%</span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-text-secondary italic opacity-40">Nenhuma tentativa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if ($al['tem_certificado']): ?>
                                        <div class="flex items-center justify-center text-green-600 gap-1.5">
                                            <i data-lucide="award" class="w-4 h-4"></i>
                                            <span class="text-[9px] font-black uppercase">Emitido</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-text-secondary opacity-20">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <button onclick="confirmarReset(<?php echo $al['id']; ?>, '<?php echo addslashes($al['nome']); ?>')" class="px-3 py-1.5 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white rounded-lg text-[9px] font-black uppercase tracking-widest border border-red-100 transition-all">
                                        Zerar Tudo
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 opacity-20">
                                        <i data-lucide="user-minus" class="w-12 h-12"></i>
                                        <p class="text-sm font-bold uppercase tracking-widest">Nenhum aluno com progresso neste curso.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div id="modalReset" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl border border-border overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="alert-triangle" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Confirmar Reinício?</h3>
                <p class="text-xs text-text-secondary mb-6 leading-relaxed">
                    Você está prestes a apagar permanentemente todo o progresso de <span id="nomeAlunoReset" class="font-bold text-text"></span> neste curso. <br><br>
                    <strong>Isso inclui aulas concluídas, notas de provas e certificados já emitidos.</strong>
                </p>
                
                <form method="POST" id="formReset">
                    <input type="hidden" name="acao" value="zerar_progresso">
                    <input type="hidden" name="aluno_id" id="alunoIdReset">
                    <div class="flex flex-col gap-2">
                        <button type="submit" class="w-full py-3 bg-red-600 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-lg shadow-red-200 hover:bg-red-700 transition-all">
                            Sim, Zerar Agora
                        </button>
                        <button type="button" onclick="fecharModalReset()" class="w-full py-3 bg-gray-100 text-text-secondary rounded-xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmarReset(id, nome) {
            document.getElementById('alunoIdReset').value = id;
            document.getElementById('nomeAlunoReset').innerText = nome;
            document.getElementById('modalReset').classList.remove('hidden');
            document.getElementById('modalReset').classList.add('flex');
        }
        function fecharModalReset() {
            document.getElementById('modalReset').classList.add('hidden');
            document.getElementById('modalReset').classList.remove('flex');
        }
    </script>

    <?php include '../footer.php'; ?>
</body>
</html>
