<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$cert_id = intval($_GET['id']);
$user_id = $_SESSION['usuario_id'];

// Buscar Certificado
$res = $conn->query("SELECT cert.*, c.titulo as curso_titulo, c.carga_horaria, u.nome as aluno_nome 
                    FROM edu_certificados cert
                    JOIN edu_cursos c ON cert.curso_id = c.id
                    JOIN usuarios u ON cert.usuario_id = u.id
                    WHERE cert.id = $cert_id");

if ($res->num_rows == 0) die("Certificado não encontrado.");
$cert = $res->fetch_assoc();

// Segurança: Apenas o dono ou admin pode ver
if ($cert['usuario_id'] != $user_id && !isAdmin()) die("Acesso negado.");

// URL de validação para o QR Code (Simulado)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$valid_url = "$protocol://$host/intranet/edu_validar.php?codigo=" . $cert['codigo_unico'];

// Gerar QR Code via API pública (Google Charts ou similar)
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($valid_url);

// Tradução de Meses
$meses = [
    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
    'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
    'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
    'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
];
$mes_ingles = date('F', strtotime($cert['data_emissao']));
$mes_pt = $meses[$mes_ingles];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado - <?php echo $cert['curso_titulo']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { size: landscape; margin: 0; }
            body { margin: 0 !important; padding: 0 !important; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .cert-container { box-shadow: none !important; border: 20px solid #1e3a8a !important; margin: 0 !important; width: 29.7cm !important; height: 21cm !important; }
        }
        body { font-family: 'Montserrat', sans-serif; }
        .playfair { font-family: 'Playfair Display', serif; }
        .cert-border { border: 20px solid #1e3a8a; }
        .cert-inner-border { border: 2px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-100 p-8 flex flex-col items-center">
    
    <button onclick="window.print()" class="no-print mb-8 bg-primary text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest shadow-xl flex items-center gap-2 hover:bg-primary-hover transition-all">
        Imprimir Certificado
    </button>

    <div class="bg-white w-[1123px] h-[794px] cert-border cert-container relative shadow-2xl overflow-hidden">
        <div class="absolute inset-4 cert-inner-border p-12 flex flex-col items-center text-center">
            
            <!-- Marca D'água -->
            <div class="absolute inset-0 flex items-center justify-center opacity-[0.1] pointer-events-none z-0">
                <img src="imagens/logo_apas.png" class="w-[600px] grayscale">
            </div>

            <div class="relative z-10 w-full h-full flex flex-col items-center">
            
            <!-- Logo Decorativo -->
            <div class="mb-8">
                <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center border-4 border-amber-400">
                    <svg class="w-12 h-12 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" /></svg>
                </div>
            </div>

            <h1 class="text-5xl playfair text-primary mb-4">Certificado de Conclusão</h1>
            <p class="text-lg text-text-secondary uppercase tracking-[0.3em] mb-12">Pela Excelência no Aprendizado</p>

            <p class="text-xl mb-2">Certificamos para os devidos fins que</p>
            <h2 class="text-4xl font-bold text-gray-800 mb-8 border-b-2 border-amber-400 pb-2 inline-block"><?php echo $cert['aluno_nome']; ?></h2>

            <p class="text-xl mb-4 text-gray-600 max-w-2xl">
                concluiu com êxito o treinamento de capacitação profissional em
            </p>
            <h3 class="text-3xl font-bold text-primary mb-12"><?php echo $cert['curso_titulo']; ?></h3>

            <p class="text-sm text-gray-500 mb-12">
                Realizado através da plataforma de Educação Permanente da APAS Intranet, <br>
                com carga horária total de <strong><?php echo $cert['carga_horaria']; ?></strong>.
            </p>

            <!-- Rodapé com Assinatura e QR -->
            <div class="absolute bottom-2 left-10 right-10 flex justify-between items-end">
                <div class="text-left">
                    <div class="w-48 h-[1px] bg-gray-400 mb-2"></div>
                    <p class="text-[10px] font-bold text-gray-600 uppercase">Diretora Presidente</p>
                    <p class="text-[9px] text-gray-400 italic">Jane Moreira da Silva Reis</p>
                </div>

                <div class="flex flex-col items-center gap-1.5 opacity-80">
                    <img src="<?php echo $qr_url; ?>" class="w-10 h-10 border border-gray-200 p-1 bg-white rounded shadow-sm">
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest"><?php echo $cert['codigo_unico']; ?></p>
                </div>

                <div class="text-right">
                    <p class="text-xs text-gray-600 font-bold"><?php echo date('d', strtotime($cert['data_emissao'])); ?> de <?php echo $mes_pt; ?> de <?php echo date('Y', strtotime($cert['data_emissao'])); ?></p>
                    <p class="text-[10px] text-gray-400">Data de Emissão</p>
                </div>
            </div>

            <!-- Faixas decorativas nas quinas -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-400 rotate-45 opacity-20"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-amber-400 rotate-45 opacity-20"></div>
            </div>
        </div>
    </div>

</body>
</html>
