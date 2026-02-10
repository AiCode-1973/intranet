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
    <title>Central de Ajuda - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 overflow-x-hidden">
    <?php include 'header.php'; ?>
    
    <div class="max-w-5xl mx-auto p-6 md:p-12">
        <!-- Header da Página -->
        <div class="mb-12 text-center">
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-primary/20">
                <i data-lucide="help-circle" class="w-8 h-8 text-primary"></i>
            </div>
            <h1 class="text-3xl font-black text-text tracking-tight italic">Como podemos ajudar?</h1>
            <p class="text-xs text-text-secondary mt-2 font-bold uppercase tracking-[0.2em] opacity-70">Central de Suporte e FAQ Interno</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <!-- Card: Suporte TI -->
            <a href="suporte.php" class="bg-white p-6 rounded-[2rem] border border-border shadow-sm hover:border-primary transition-all group">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-500 mb-4 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="monitor" class="w-6 h-6"></i>
                </div>
                <h3 class="font-bold text-text mb-2">Suporte de TI</h3>
                <p class="text-[11px] text-text-secondary leading-relaxed">Problemas com computador, rede, e-mail ou sistemas internos.</p>
            </a>

            <!-- Card: CEH -->
            <a href="ceh.php" class="bg-white p-6 rounded-[2rem] border border-border shadow-sm hover:border-primary transition-all group">
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-500 mb-4 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                </div>
                <h3 class="font-bold text-text mb-2">Equipamentos</h3>
                <p class="text-[11px] text-text-secondary leading-relaxed">Manutenção e calibração de equipamentos hospitalares.</p>
            </a>

            <!-- Card: Manutenção -->
            <a href="manutencao.php" class="bg-white p-6 rounded-[2rem] border border-border shadow-sm hover:border-primary transition-all group">
                <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center text-orange-500 mb-4 group-hover:bg-primary group-hover:text-white transition-all">
                    <i data-lucide="wrench" class="w-6 h-6"></i>
                </div>
                <h3 class="font-bold text-text mb-2">Infraestrutura</h3>
                <p class="text-[11px] text-text-secondary leading-relaxed">Reparos prediais, elétrica, hidráulica e mobiliário.</p>
            </a>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-[2.5rem] border border-border shadow-xl p-8 md:p-12">
            <h2 class="text-xl font-black text-text italic mb-8 flex items-center gap-3">
                <i data-lucide="message-circle" class="w-6 h-6 text-primary"></i>
                Perguntas Frequentes
            </h2>

            <div class="space-y-6">
                <div class="p-6 rounded-2xl bg-gray-50 border border-border hover:bg-white transition-all group cursor-pointer">
                    <h4 class="text-sm font-bold text-text mb-2 group-hover:text-primary transition-colors">Como alterar minha senha de acesso?</h4>
                    <p class="text-xs text-text-secondary leading-relaxed">No canto superior direito, clique na sua foto de perfil e escolha a opção <strong>"Alterar Senha"</strong>. Siga as instruções na tela para atualizar sua credencial.</p>
                </div>

                <div class="p-6 rounded-2xl bg-gray-50 border border-border hover:bg-white transition-all group cursor-pointer">
                    <h4 class="text-sm font-bold text-text mb-2 group-hover:text-primary transition-colors">Esqueci minha senha, o que fazer?</h4>
                    <p class="text-xs text-text-secondary leading-relaxed">Na tela de login, utilize o link "Esqueci minha senha" ou entre em contato com o setor de TI através do ramal interno para reset de credenciais.</p>
                </div>

                <div class="p-6 rounded-2xl bg-gray-50 border border-border hover:bg-white transition-all group cursor-pointer">
                    <h4 class="text-sm font-bold text-text mb-2 group-hover:text-primary transition-colors">Como abrir um chamado para TI ou Manutenção?</h4>
                    <p class="text-xs text-text-secondary leading-relaxed">Acesse o dashboard principal e clique no card correspondente (Suporte TI ou Manutenção). Clique em <strong>"Novo Chamado"</strong>, preencha as informações e aguarde o atendimento.</p>
                </div>

                <div class="p-6 rounded-2xl bg-gray-50 border border-border hover:bg-white transition-all group cursor-pointer">
                    <h4 class="text-sm font-bold text-text mb-2 group-hover:text-primary transition-colors">Onde encontro manuais e protocolos?</h4>
                    <p class="text-xs text-text-secondary leading-relaxed">Todos os documentos oficiais estão disponíveis no módulo <strong>"Documentos & Biblioteca"</strong> no menu lateral ou através do card no dashboard.</p>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-dashed border-border flex flex-col items-center">
                <p class="text-[11px] text-text-secondary font-bold mb-4">Ainda precisa de ajuda?</p>
                <div class="flex gap-4">
                    <a href="telefones.php" class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest hover:underline">
                        <i data-lucide="phone" class="w-4 h-4"></i>
                        Ramais Internos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    </div> <!-- Fecha o wrapper do header.php -->
</body>
</html>
