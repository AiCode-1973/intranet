<?php
require_once 'config.php';
require_once 'functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cpf = preg_replace('/[^0-9]/', '', sanitize($_POST['cpf']));
    $senha = $_POST['senha'];
    
    $stmt = $conn->prepare("SELECT id, nome, email, cpf, senha, setor_id, is_admin, is_tecnico, is_manutencao, is_educacao FROM usuarios WHERE cpf = ? AND ativo = 1");
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($senha, $row['senha'])) {
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['usuario_nome'] = $row['nome'];
            $_SESSION['usuario_email'] = $row['email'];
            $_SESSION['usuario_cpf'] = $row['cpf'];
            $_SESSION['setor_id'] = $row['setor_id'];
            $_SESSION['is_admin'] = $row['is_admin'];
            $_SESSION['is_tecnico'] = $row['is_tecnico'];
            $_SESSION['is_manutencao'] = $row['is_manutencao'];
            $_SESSION['is_educacao'] = $row['is_educacao'];
            
            $stmt_update = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt_update->bind_param("i", $row['id']);
            $stmt_update->execute();
            $stmt_update->close();
            
            registrarLog($conn, 'Login realizado');
            
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'Credenciais inválidas.';
        }
    } else {
        $erro = 'Credenciais inválidas.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .split-bg {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        }
        @media (min-height: 800px) {
            body { overflow: hidden; }
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans selection:bg-primary/20 flex flex-col">
    
    <div class="flex flex-grow flex-col md:flex-row h-full">
        <!-- Left Side: Information & Branding -->
        <div class="flex flex-col md:w-1/2 split-bg p-8 md:p-12 justify-between text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-80 h-80 bg-white/10 rounded-full -mr-40 -mt-40 blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-8 md:mb-14">
                    <div class="bg-white p-2 rounded-xl shadow-xl">
                        <i data-lucide="hospital" class="w-7 h-7 md:w-9 md:h-9 text-teal-600"></i>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-black tracking-tighter leading-none text-white">APAS</h1>
                        <p class="text-[9px] md:text-[10px] font-bold uppercase tracking-[0.3em] text-white/80">Baixada Santista</p>
                    </div>
                </div>

                <div class="space-y-5 md:space-y-6 max-w-lg">
                    <h2 class="text-3xl md:text-5xl font-black leading-tight tracking-tight text-white">
                        Conectando nossa <br>saúde com <span class="text-teal-200">inteligência.</span>
                    </h2>
                    <p class="text-white/90 text-sm md:text-base leading-relaxed opacity-80">
                        Acesse protocolos clínicos, gestão de pessoal e comunicados oficiais em uma plataforma integrada.
                    </p>
                </div>
            </div>

            <div class="relative z-10 mt-8 md:mt-0">
                <div class="grid grid-cols-2 gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/20">
                            <i data-lucide="shield-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">Ambiente Seguro</p>
                            <p class="text-[9px] text-white/50 uppercase tracking-widest font-semibold">Criptografado</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/20">
                            <i data-lucide="zap" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">Acesso Rápido</p>
                            <p class="text-[9px] text-white/50 uppercase tracking-widest font-semibold">Alta performance</p>
                        </div>
                    </div>
                </div>
                <div class="mt-8 md:mt-12 pt-6 border-t border-white/10 flex justify-between items-center text-[9px] md:text-[10px] font-bold uppercase tracking-widest text-white/60">
                    <span class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                        Sistema de Auditoria Ativo
                    </span>
                    <span>v2.0.4</span>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="w-full md:w-1/2 flex items-center justify-center p-8 bg-white md:bg-gray-50/30">
        <div class="w-full max-w-[350px] animate-in fade-in duration-500">
                <div class="mb-8 md:mb-10">
                    <h2 class="text-2xl md:text-3xl font-black text-text tracking-tight">Login corporativo</h2>
                    <p class="text-xs md:text-sm text-text-secondary mt-1.5">Identifique-se para acessar o painel.</p>
                </div>

                <?php if ($erro): ?>
                    <div class="bg-red-50 border border-red-100 text-red-600 p-3 rounded-xl mb-5 flex items-center gap-2 animate-in shake duration-300">
                        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                        <span class="text-xs font-bold"><?php echo $erro; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5 md:space-y-6">
                    <div class="space-y-1.5">
                        <label for="cpf" class="text-[10px] font-black text-text-secondary uppercase tracking-widest ml-1">Seu CPF</label>
                        <div class="relative group">
                            <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required 
                                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-border rounded-xl text-sm md:text-base font-bold text-text focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all outline-none shadow-sm">
                            <i data-lucide="user" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center ml-1">
                            <label for="senha" class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Sua Senha</label>
                            <a href="#" class="text-[10px] font-bold text-primary hover:underline">Recuperar?</a>
                        </div>
                        <div class="relative group">
                            <input type="password" id="senha" name="senha" required placeholder="••••••••"
                                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-border rounded-xl text-sm md:text-base font-bold text-text focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all outline-none shadow-sm">
                            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary group-focus-within:text-primary transition-colors"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-4 bg-primary hover:bg-primary-hover text-white font-black rounded-xl shadow-lg shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 text-xs md:text-sm uppercase tracking-[0.2em] mt-2">
                        Entrar no Sistema
                    </button>
                </form>

                <div class="mt-10 p-4 bg-white rounded-2xl border border-border flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center text-primary">
                            <i data-lucide="headphones" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-[10px] md:text-xs font-black text-text uppercase leading-none mb-1">Suporte Técnico</p>
                            <p class="text-[10px] text-text-secondary font-bold">Email: suporte@hsesantos.com.br</p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('dev-info').classList.toggle('hidden')" class="p-2 hover:bg-primary/5 rounded-lg transition-colors">
                        <i data-lucide="info" class="w-4 h-4 text-text-secondary"></i>
                    </button>
                </div>

                <div id="dev-info" class="hidden mt-3 p-4 bg-white border border-border rounded-xl text-left animate-in fade-in duration-300">
                     <p class="text-[10px] font-mono text-text-secondary italic">CPF: 000.000.000-00 | Senha: admin123</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-50 py-4 border-t border-border hidden md:block">
        <div class="max-w-7xl mx-auto px-12 flex justify-between items-center text-[9px] md:text-[10px] font-bold text-text-secondary uppercase tracking-[0.2em]">
            <span>&copy; <?php echo date('Y'); ?> APAS Baixada Santista</span>
            <span class="text-primary/60 group relative cursor-help">
                Desenvolvido por Equipe de TI
                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-auto whitespace-nowrap bg-sidebar text-white text-[10px] py-2 px-3 rounded-lg shadow-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none z-50">
                    Demetrius, Matheus, Vinicius, Gabriel e Erasmo
                    <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-sidebar"></span>
                </span>
            </span>
            <div class="flex gap-6">
                <a href="#" class="hover:text-primary transition-colors">Termos</a>
                <a href="#" class="hover:text-primary transition-colors">Segurança</a>
            </div>
        </div>
    </footer>

    <script>
        // CPF Mask logic
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
        });
    </script>
</body>
</html>
