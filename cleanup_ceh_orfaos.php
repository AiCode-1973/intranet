<?php
require_once 'config.php';
session_start();

// Somente admin pode executar
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Faça login primeiro.");
}

$result = $conn->query("SELECT COUNT(*) as total FROM ceh_comentarios WHERE chamado_id NOT IN (SELECT id FROM ceh_chamados)");
$row = $result->fetch_assoc();
$total_orfaos = $row['total'];

$deleted = 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    $res = $conn->query("DELETE FROM ceh_comentarios WHERE chamado_id NOT IN (SELECT id FROM ceh_chamados)");
    if ($res) {
        $deleted = $conn->affected_rows;
    } else {
        $error = $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Limpeza CEH — Comentários Órfãos</title>
    <style>
        body { font-family: sans-serif; max-width: 520px; margin: 60px auto; padding: 0 20px; background: #f5f5f5; }
        .card { background: #fff; border-radius: 10px; padding: 28px 32px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
        h2 { margin-top: 0; color: #1e293b; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 1.1rem; }
        .red   { background: #fee2e2; color: #b91c1c; }
        .green { background: #dcfce7; color: #15803d; }
        .info  { background: #eff6ff; color: #1d4ed8; padding: 12px 16px; border-radius: 8px; margin: 16px 0; font-size: .95rem; }
        button { background: #dc2626; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-size: 1rem; margin-top: 8px; }
        button:hover { background: #b91c1c; }
        a.btn-back { display: inline-block; margin-top: 16px; color: #2563eb; text-decoration: none; font-size: .9rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>🧹 Limpeza: Comentários Órfãos (CEH)</h2>

    <?php if ($deleted > 0): ?>
        <p>Limpeza concluída com sucesso!</p>
        <p>Comentários removidos: <span class="badge green"><?php echo $deleted; ?></span></p>
        <div class="info">O banco de dados está limpo. Você pode excluir este script do servidor.</div>
    <?php elseif ($error): ?>
        <p style="color:#b91c1c">Erro: <?php echo htmlspecialchars($error); ?></p>
    <?php else: ?>
        <p>Comentários órfãos encontrados (sem chamado correspondente):</p>
        <p><span class="badge <?php echo $total_orfaos > 0 ? 'red' : 'green'; ?>"><?php echo $total_orfaos; ?></span></p>

        <?php if ($total_orfaos > 0): ?>
            <div class="info">Esses comentários pertencem a chamados já excluídos e estão causando o problema de mensagens antigas em novos chamados.</div>
            <form method="POST">
                <button type="submit" name="confirmar" value="1"
                    onclick="return confirm('Confirma a exclusão de <?php echo $total_orfaos; ?> comentários órfãos?')">
                    Excluir todos os órfãos
                </button>
            </form>
        <?php else: ?>
            <div class="info">Nenhum comentário órfão encontrado. O banco está limpo.</div>
        <?php endif; ?>
    <?php endif; ?>

    <a class="btn-back" href="admin/ceh_gerenciar.php">← Voltar ao CEH</a>
</div>
</body>
</html>
