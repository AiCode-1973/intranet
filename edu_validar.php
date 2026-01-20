<?php
require_once 'config.php';
require_once 'functions.php';

$codigo = isset($_GET['codigo']) ? sanitize($_GET['codigo']) : '';

$cert = null;
if ($codigo) {
    $res = $conn->query("SELECT cert.*, c.titulo as curso_titulo, u.nome as aluno_nome 
                        FROM edu_certificados cert
                        JOIN edu_cursos c ON cert.curso_id = c.id
                        JOIN usuarios u ON cert.usuario_id = u.id
                        WHERE cert.codigo_unico = '$codigo'");
    $cert = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Certificado - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 flex flex-col min-h-screen">
    <div class="max-w-md mx-auto p-12 flex-grow flex flex-col items-center justify-center text-center">
        
        <div class="mb-8">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center text-primary mx-auto">
                <i data-lucide="shield-check" class="w-8 h-8"></i>
            </div>
        </div>

        <?php if ($cert): ?>
            <div class="bg-white p-8 rounded-3xl border border-border shadow-2xl w-full">
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="check" class="w-8 h-8"></i>
                </div>
                <h1 class="text-xl font-bold text-text mb-2">Certificado Autêntico</h1>
                <p class="text-xs text-text-secondary mb-6 italic">Validado com sucesso pelo sistema de registro interno.</p>
                
                <div class="space-y-4 text-left border-t border-border pt-6">
                    <div>
                        <span class="text-[10px] font-black text-text-secondary uppercase opacity-40">Aluno</span>
                        <p class="text-sm font-bold text-text"><?php echo $cert['aluno_nome']; ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-text-secondary uppercase opacity-40">Curso</span>
                        <p class="text-sm font-bold text-text"><?php echo $cert['curso_titulo']; ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-text-secondary uppercase opacity-40">Código de Registro</span>
                        <p class="text-xs font-mono font-bold text-primary"><?php echo $cert['codigo_unico']; ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-text-secondary uppercase opacity-40">Data de Emissão</span>
                        <p class="text-sm font-bold text-text"><?php echo date('d/m/Y', strtotime($cert['data_emissao'])); ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white p-8 rounded-3xl border border-red-100 shadow-xl w-full">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="x" class="w-8 h-8"></i>
                </div>
                <h1 class="text-xl font-bold text-text mb-2">Código Inválido</h1>
                <p class="text-xs text-text-secondary">Não encontramos nenhum registro correspondente ao código informado.</p>
                
                <form action="" method="GET" class="mt-8">
                    <input type="text" name="codigo" placeholder="Digite o código do certificado" class="w-full p-3 bg-background border border-border rounded-xl text-xs font-bold focus:outline-none focus:border-red-500 mb-4 uppercase">
                    <button class="w-full bg-primary text-white py-3 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg">Verificar Novo Código</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="mt-12">
            <p class="text-[10px] text-text-secondary opacity-40 font-bold uppercase tracking-widest">© <?php echo date('Y'); ?> APAS Intranet - Sistema de Gestão de Conhecimento</p>
        </div>
    </div>
</body>
</html>
