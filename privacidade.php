<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 overflow-x-hidden">
    <?php include 'header.php'; ?>
    
    <div class="max-w-4xl mx-auto p-6 md:p-12">
        <!-- Header da Página -->
        <div class="mb-12 text-center">
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-primary/20">
                <i data-lucide="shield-lock" class="w-8 h-8 text-primary"></i>
            </div>
            <h1 class="text-3xl font-black text-text tracking-tight italic">Privacidade & Proteção de Dados</h1>
            <p class="text-xs text-text-secondary mt-2 font-bold uppercase tracking-[0.2em] opacity-70">Termos de Uso Interno - v1.0.2</p>
        </div>

        <!-- Conteúdo -->
        <div class="bg-white rounded-[2.5rem] border border-border shadow-xl p-8 md:p-12 space-y-10">
            
            <section class="space-y-4">
                <h2 class="text-lg font-black text-primary flex items-center gap-3 italic">
                    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-xs not-italic">01</span>
                    Compromisso com a Segurança
                </h2>
                <div class="pl-11 text-sm text-text-secondary leading-relaxed space-y-3 font-medium">
                    <p>A <strong>APAS Intranet</strong> é uma ferramenta de uso exclusivo profissional. Nosso compromisso é garantir que todas as interações e dados trafegados dentro deste ambiente sejam protegidos por camadas rigorosas de segurança cibernética.</p>
                    <p>Todas as senhas são armazenadas utilizando algoritmos de criptografia de via única (<em>hashing</em>), garantindo que nem mesmo os administradores tenham acesso às credenciais originais dos colaboradores.</p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-black text-primary flex items-center gap-3 italic">
                    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-xs not-italic">02</span>
                    Coleta & Uso de Informações
                </h2>
                <div class="pl-11 text-sm text-text-secondary leading-relaxed space-y-3 font-medium">
                    <p>O sistema coleta dados essenciais para o funcionamento administrativo e operacional, incluindo:</p>
                    <ul class="list-disc pl-5 space-y-2 marker:text-primary">
                        <li>Dados cadastrais (Nome, CPF, Setor, E-mail corporativo);</li>
                        <li>Registros de acesso (IP, navegador e horários) para fins de auditoria e segurança;</li>
                        <li>Logs de ações (abertura de chamados, modificação de registros, etc.);</li>
                        <li>Dados de desempenho em cursos e treinamentos internos.</li>
                    </ul>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-black text-primary flex items-center gap-3 italic">
                    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-xs not-italic">03</span>
                    Finalidade Institucional
                </h2>
                <div class="pl-11 text-sm text-text-secondary leading-relaxed space-y-3 font-medium">
                    <p>As informações aqui contidas são utilizadas exclusivamente para:</p>
                    <ul class="list-disc pl-5 space-y-2 marker:text-primary">
                        <li>Facilitar a comunicação entre setores;</li>
                        <li>Gerenciar o fluxo de chamados técnicos e suporte;</li>
                        <li>Registrar e validar horas de treinamento (Educação Permanente);</li>
                        <li>Cumprir obrigações legais de registro de atividades profissionais.</li>
                    </ul>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-black text-primary flex items-center gap-3 italic">
                    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-xs not-italic">04</span>
                    Direitos do Colaborador
                </h2>
                <div class="pl-11 text-sm text-text-secondary leading-relaxed space-y-3 font-medium">
                    <p>Conforme as diretrizes da <strong>LGPD (Lei Geral de Proteção de Dados)</strong>, o colaborador tem o direito de solicitar a revisão de seus dados, transparência sobre como as informações são tratadas e a correção de dados inexatos através do setor de RH ou TI.</p>
                </div>
            </section>

            <div class="pt-10 border-t border-dashed border-border flex flex-col items-center">
                <p class="text-[10px] text-text-secondary font-black uppercase tracking-widest mb-6 italic">Última atualização: 10 de Fevereiro de 2026</p>
                <a href="dashboard.php" class="bg-primary hover:bg-primary-hover text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 transition-all hover:scale-105 active:scale-95 flex items-center gap-3">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Entendido e Voltar
                </a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    </div> <!-- Fecha o wrapper do header.php -->
</body>
</html>
