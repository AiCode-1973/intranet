<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Busca dados do usuário logado
$uid = intval($_SESSION['usuario_id']);
$stmt = $conn->prepare("
    SELECT u.nome, u.email, u.foto, s.nome AS setor_nome
    FROM usuarios u
    LEFT JOIN setores s ON s.id = u.setor_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Busca configurações globais da assinatura
$configs_raw = $conn->query("SELECT chave, valor FROM assinatura_config");
$cfg = [];
if ($configs_raw) {
    while ($row = $configs_raw->fetch_assoc()) {
        $cfg[$row['chave']] = $row['valor'];
    }
}
// Defaults
$cfg = array_merge([
    'empresa_nome'       => 'APAS Baixada Santista',
    'empresa_site'       => 'www.apassantos.com.br',
    'empresa_logo_url'   => '',
    'empresa_cor'        => '#0d9488',
    'empresa_endereco'   => '',
    'empresa_telefone'   => '',
    'disclaimer'         => '',
], $cfg);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Assinatura - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .sig-card { transition: all .25s cubic-bezier(.4,0,.2,1); }
        .sig-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px -10px rgba(0,0,0,.1); }
        .layout-btn.active { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 8%, white); color: var(--color-primary); }

        /* ---------- Assinatura HTML gerada ---------- */
        #sig-preview table { border-collapse: collapse; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 flex flex-col min-h-screen">
    <?php include 'header.php'; ?>

    <div class="lg:ml-64 pt-16 flex-grow flex flex-col">
        <div class="p-6 w-full max-w-7xl mx-auto flex-grow">

            <!-- Cabeçalho da página -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <i data-lucide="mail-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-text">Gerador de Assinatura de E-mail</h1>
                        <p class="text-xs text-text-muted">Crie sua assinatura profissional e copie para usar no seu cliente de e-mail.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">

                <!-- ======== COLUNA ESQUERDA: Formulário ======== -->
                <div class="space-y-6">

                    <!-- Dados Pessoais -->
                    <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
                        <h2 class="text-sm font-black text-text uppercase tracking-widest mb-5 flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                            Dados Pessoais
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Nome Completo *</label>
                                <input id="f-nome" type="text" value="<?php echo htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES); ?>"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="Seu nome">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Cargo / Função *</label>
                                <input id="f-cargo" type="text"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="Ex.: Enfermeira, Analista de TI...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Departamento / Setor</label>
                                <input id="f-setor" type="text" value="<?php echo htmlspecialchars($usuario['setor_nome'] ?? '', ENT_QUOTES); ?>"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="Ex.: Tecnologia da Informação">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">E-mail Profissional *</label>
                                <input id="f-email" type="email" value="<?php echo htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES); ?>"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="seu@email.com.br">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Ramal / Telefone Fixo</label>
                                <input id="f-ramal" type="text"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="(13) 3000-0000 | Ramal 217">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Celular / WhatsApp</label>
                                <input id="f-celular" type="text"
                                    class="w-full border border-border rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:border-primary transition-colors"
                                    placeholder="(13) 99000-0000">
                            </div>
                        </div>
                    </div>

                    <!-- Layout -->
                    <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
                        <h2 class="text-sm font-black text-text uppercase tracking-widest mb-5 flex items-center gap-2">
                            <i data-lucide="layout-template" class="w-4 h-4 text-primary"></i>
                            Modelo de Layout
                        </h2>
                        <div class="grid grid-cols-3 gap-3">
                            <button class="layout-btn active border-2 rounded-xl p-3 text-center cursor-pointer transition-all" data-layout="horizontal" onclick="setLayout(this)">
                                <div class="flex gap-1 justify-center mb-2">
                                    <div class="w-5 h-10 bg-current rounded opacity-20"></div>
                                    <div class="flex flex-col gap-1 justify-center">
                                        <div class="w-10 h-1.5 bg-current rounded opacity-30"></div>
                                        <div class="w-8 h-1 bg-current rounded opacity-20"></div>
                                        <div class="w-9 h-1 bg-current rounded opacity-20"></div>
                                    </div>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest">Horizontal</span>
                            </button>
                            <button class="layout-btn border-2 border-border rounded-xl p-3 text-center text-text-muted cursor-pointer transition-all" data-layout="vertical" onclick="setLayout(this)">
                                <div class="flex flex-col items-center gap-1 mb-2">
                                    <div class="w-6 h-6 bg-current rounded-full opacity-20"></div>
                                    <div class="w-12 h-1.5 bg-current rounded opacity-30"></div>
                                    <div class="w-10 h-1 bg-current rounded opacity-20"></div>
                                    <div class="w-11 h-1 bg-current rounded opacity-20"></div>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest">Vertical</span>
                            </button>
                            <button class="layout-btn border-2 border-border rounded-xl p-3 text-center text-text-muted cursor-pointer transition-all" data-layout="compacto" onclick="setLayout(this)">
                                <div class="flex flex-col gap-1 mb-2">
                                    <div class="w-12 h-1.5 bg-current rounded opacity-30 mx-auto"></div>
                                    <div class="w-10 h-1 bg-current rounded opacity-20 mx-auto"></div>
                                    <div class="h-px w-full bg-current opacity-20 my-0.5"></div>
                                    <div class="w-11 h-1 bg-current rounded opacity-20 mx-auto"></div>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest">Compacto</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cor de Destaque -->
                    <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
                        <h2 class="text-sm font-black text-text uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i data-lucide="palette" class="w-4 h-4 text-primary"></i>
                            Cor de Destaque
                        </h2>
                        <div class="flex items-center gap-4 flex-wrap">
                            <?php
                            $paleta = ['#0d9488','#0f766e','#1d4ed8','#7c3aed','#be123c','#b45309','#374151'];
                            foreach ($paleta as $i => $cor):
                            ?>
                            <button onclick="setCor('<?php echo $cor; ?>')"
                                class="color-swatch w-8 h-8 rounded-full border-2 border-transparent hover:scale-110 transition-all"
                                data-cor="<?php echo $cor; ?>"
                                style="background:<?php echo $cor; ?>">
                            </button>
                            <?php endforeach; ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="color" id="cor-custom" value="<?php echo htmlspecialchars($cfg['empresa_cor'], ENT_QUOTES); ?>"
                                    class="w-8 h-8 rounded-full cursor-pointer border border-border p-0.5"
                                    oninput="setCor(this.value)">
                                <span class="text-[10px] font-bold text-text-muted">Personalizar</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ======== COLUNA DIREITA: Pré-visualização ======== -->
                <div class="space-y-4">
                    <div class="bg-white rounded-2xl border border-border p-6 shadow-sm sticky top-20">
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-sm font-black text-text uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="eye" class="w-4 h-4 text-primary"></i>
                                Pré-visualização
                            </h2>
                            <div class="flex gap-2">
                                <button onclick="copiarHTML()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-primary hover:text-white transition-all">
                                    <i data-lucide="code-2" class="w-3 h-3"></i>
                                    Copiar HTML
                                </button>
                                <button onclick="copiarVisual()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-green-600 hover:text-white transition-all">
                                    <i data-lucide="clipboard-check" class="w-3 h-3"></i>
                                    Copiar Assinatura
                                </button>
                            </div>
                        </div>

                        <!-- Janela de e-mail simulada -->
                        <div class="border border-border rounded-xl overflow-hidden">
                            <div class="bg-gray-50 border-b border-border px-4 py-2 flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-400"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-400"></div>
                                <span class="text-[10px] text-text-muted ml-2 font-mono">Nova Mensagem</span>
                            </div>
                            <div class="p-4">
                                <div class="border-b border-dashed border-border pb-3 mb-4 space-y-1">
                                    <div class="flex items-center gap-2"><span class="text-[9px] text-text-muted w-6">Para</span><div class="h-2 bg-gray-100 rounded w-32"></div></div>
                                    <div class="flex items-center gap-2"><span class="text-[9px] text-text-muted w-6">Assunto</span><div class="h-2 bg-gray-100 rounded w-24"></div></div>
                                </div>
                                <div class="mb-6 space-y-1.5">
                                    <div class="h-2 bg-gray-100 rounded w-3/4"></div>
                                    <div class="h-2 bg-gray-100 rounded w-1/2"></div>
                                    <div class="h-2 bg-gray-100 rounded w-2/3"></div>
                                </div>
                                <!-- Separador -->
                                <div class="border-t border-gray-200 pt-4" id="sig-preview">
                                    <!-- gerado via JS -->
                                </div>
                            </div>
                        </div>

                        <!-- Instruções de uso -->
                        <div class="mt-4 bg-blue-50 border border-blue-100 rounded-xl p-4">
                            <h3 class="text-[10px] font-black text-blue-700 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                <i data-lucide="info" class="w-3 h-3"></i>
                                Como usar no seu e-mail
                            </h3>
                            <ul class="text-[11px] text-blue-700/80 space-y-1 list-none">
                                <li><strong class="font-black">Outlook:</strong> Configurações → Assinaturas → Nova → Cole a assinatura.</li>
                                <li><strong class="font-black">Gmail:</strong> Configurações ⚙ → Ver todas → Seção "Assinatura" → Cole.</li>
                                <li class="italic text-[10px] mt-1 opacity-70">Use "Copiar Assinatura" para manter a formatação ao colar diretamente no campo de assinatura do cliente de e-mail.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <!-- Toast Feedback -->
    <div id="toast" class="fixed bottom-6 right-6 bg-green-600 text-white px-5 py-3 rounded-xl shadow-2xl text-sm font-bold translate-y-16 opacity-0 transition-all duration-300 flex items-center gap-2 z-50">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        <span id="toast-msg">Copiado!</span>
    </div>

    <script>
    // ── Configurações globais vindas do servidor ──────────────────────────────
    const CFG = {
        empresa_nome:     <?php echo json_encode($cfg['empresa_nome']); ?>,
        empresa_site:     <?php echo json_encode($cfg['empresa_site']); ?>,
        empresa_logo_url: <?php echo json_encode($cfg['empresa_logo_url']); ?>,
        empresa_cor:      <?php echo json_encode($cfg['empresa_cor']); ?>,
        empresa_endereco: <?php echo json_encode($cfg['empresa_endereco']); ?>,
        empresa_telefone: <?php echo json_encode($cfg['empresa_telefone']); ?>,
        disclaimer:       <?php echo json_encode($cfg['disclaimer']); ?>,
    };

    let corAtual  = CFG.empresa_cor;
    let layoutAtual = 'horizontal';

    // ── Inputs que disparam atualização ──────────────────────────────────────
    ['f-nome','f-cargo','f-setor','f-email','f-ramal','f-celular'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', renderizar);
    });

    // ── Selecionar layout ────────────────────────────────────────────────────
    function setLayout(btn) {
        document.querySelectorAll('.layout-btn').forEach(b => {
            b.classList.remove('active');
            b.classList.add('border-border','text-text-muted');
        });
        btn.classList.add('active');
        btn.classList.remove('border-border','text-text-muted');
        layoutAtual = btn.dataset.layout;
        renderizar();
    }

    // ── Selecionar cor ───────────────────────────────────────────────────────
    function setCor(hex) {
        corAtual = hex;
        document.getElementById('cor-custom').value = hex;
        document.querySelectorAll('.color-swatch').forEach(b => {
            b.style.borderColor = (b.dataset.cor === hex) ? hex : 'transparent';
        });
        renderizar();
    }
    // Inicializa swatch ativo
    setCor(CFG.empresa_cor);

    // ── Geração do HTML da assinatura ────────────────────────────────────────
    function val(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function gerarHTML() {
        const nome   = val('f-nome');
        const cargo  = val('f-cargo');
        const setor  = val('f-setor');
        const email  = val('f-email');
        const ramal  = val('f-ramal');
        const cel    = val('f-celular');
        const cor    = corAtual;
        const layout = layoutAtual;

        if (!nome && !cargo) return '<p style="color:#999;font-size:12px;font-family:Arial,sans-serif;">Preencha os dados ao lado para visualizar a assinatura.</p>';

        const fontFamily = "Arial, Helvetica, sans-serif";
        const logoHTML = CFG.empresa_logo_url
            ? `<img src="${CFG.empresa_logo_url}" alt="${CFG.empresa_nome}" style="height:40px;display:block;margin-bottom:4px;">`
            : `<span style="font-size:15px;font-weight:900;color:${cor};font-family:${fontFamily};letter-spacing:-0.5px;">${CFG.empresa_nome}</span>`;

        const infoLines = [];
        if (cargo) infoLines.push(`<span style="font-size:12px;color:${cor};font-family:${fontFamily};font-weight:700;">${escHtml(cargo)}</span>`);
        if (setor) infoLines.push(`<span style="font-size:11px;color:#555;font-family:${fontFamily};">${escHtml(setor)}</span>`);

        const contactLines = [];
        if (email) contactLines.push(
            `<span style="font-family:${fontFamily};font-size:11px;color:#444;">&#9993;&nbsp;<a href="mailto:${escHtml(email)}" style="color:#444;text-decoration:none;">${escHtml(email)}</a></span>`
        );
        if (ramal) contactLines.push(
            `<span style="font-family:${fontFamily};font-size:11px;color:#444;">&#128222;&nbsp;${escHtml(ramal)}</span>`
        );
        if (cel) contactLines.push(
            `<span style="font-family:${fontFamily};font-size:11px;color:#444;">&#128241;&nbsp;${escHtml(cel)}</span>`
        );
        if (CFG.empresa_site) contactLines.push(
            `<span style="font-family:${fontFamily};font-size:11px;color:#444;">&#127760;&nbsp;<a href="https://${CFG.empresa_site}" style="color:${cor};text-decoration:none;">${CFG.empresa_site}</a></span>`
        );
        if (CFG.empresa_endereco) contactLines.push(
            `<span style="font-family:${fontFamily};font-size:11px;color:#666;">&#128205;&nbsp;${escHtml(CFG.empresa_endereco)}</span>`
        );

        let disclaimer = '';
        if (CFG.disclaimer) {
            disclaimer = `<tr><td colspan="3" style="padding-top:10px;">
                <p style="font-family:${fontFamily};font-size:9px;color:#aaa;line-height:1.4;max-width:480px;border-top:1px solid #eee;padding-top:6px;margin:0;">${escHtml(CFG.disclaimer)}</p>
            </td></tr>`;
        }

        if (layout === 'horizontal') {
            return `
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:${fontFamily};">
  <tr>
    <td style="vertical-align:top;padding-right:14px;border-right:3px solid ${cor};">
      ${logoHTML}
      <div style="margin-top:4px;">
        <span style="font-size:14px;font-weight:900;color:#222;font-family:${fontFamily};">${escHtml(nome)}</span>
      </div>
    </td>
    <td style="width:12px;"></td>
    <td style="vertical-align:top;">
      <div style="margin-bottom:4px;">${infoLines.join('<br>')}</div>
      <div style="display:flex;flex-direction:column;gap:2px;">${contactLines.join('<br>')}</div>
    </td>
  </tr>
  ${disclaimer}
</table>`;
        }

        if (layout === 'vertical') {
            return `
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:${fontFamily};text-align:center;">
  <tr>
    <td style="text-align:center;padding-bottom:8px;border-bottom:3px solid ${cor};">
      ${logoHTML}
      <div style="margin-top:6px;">
        <span style="font-size:15px;font-weight:900;color:#222;font-family:${fontFamily};">${escHtml(nome)}</span>
      </div>
      <div style="margin-top:3px;">${infoLines.join('<br>')}</div>
    </td>
  </tr>
  <tr>
    <td style="padding-top:8px;text-align:center;">
      <div>${contactLines.join('<br>')}</div>
    </td>
  </tr>
  ${disclaimer}
</table>`;
        }

        // Compacto
        return `
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:${fontFamily};">
  <tr>
    <td style="vertical-align:top;">
      <span style="font-size:13px;font-weight:900;color:#222;font-family:${fontFamily};">${escHtml(nome)}</span>
      ${infoLines.length ? ' &bull; ' + infoLines.join(' &bull; ') : ''}
      <br>
      <span style="font-size:10px;color:${cor};font-family:${fontFamily};font-weight:700;">${CFG.empresa_nome}</span>
      <hr style="border:none;border-top:2px solid ${cor};margin:5px 0;">
      <div>${contactLines.join('&nbsp;&nbsp;|&nbsp;&nbsp;')}</div>
    </td>
  </tr>
  ${disclaimer}
</table>`;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Renderizar preview ───────────────────────────────────────────────────
    function renderizar() {
        document.getElementById('sig-preview').innerHTML = gerarHTML();
        if (window.lucide) lucide.createIcons();
    }

    // ── Copiar HTML puro ────────────────────────────────────────────────────
    function copiarHTML() {
        const html = gerarHTML();
        navigator.clipboard.writeText(html).then(() => {
            showToast('HTML copiado para a área de transferência!');
        }).catch(() => {
            // fallback
            const ta = document.createElement('textarea');
            ta.value = html;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('HTML copiado para a área de transferência!');
        });
    }

    // ── Copiar assinatura visual (rich text) ────────────────────────────────
    async function copiarVisual() {
        const el = document.getElementById('sig-preview');
        try {
            const blob = new Blob([el.innerHTML], { type: 'text/html' });
            const item = new ClipboardItem({ 'text/html': blob });
            await navigator.clipboard.write([item]);
            showToast('Assinatura copiada! Cole diretamente no seu cliente de e-mail.');
        } catch {
            // Fallback: selecionar e copiar
            const range = document.createRange();
            range.selectNodeContents(el);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            sel.removeAllRanges();
            showToast('Assinatura copiada! Cole diretamente no seu cliente de e-mail.');
        }
    }

    // ── Toast ────────────────────────────────────────────────────────────────
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toast-msg').textContent = msg;
        t.classList.remove('translate-y-16','opacity-0');
        t.classList.add('translate-y-0','opacity-100');
        setTimeout(() => {
            t.classList.add('translate-y-16','opacity-0');
            t.classList.remove('translate-y-0','opacity-100');
        }, 3200);
    }

    // ── Inicializar ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        renderizar();
    });
    </script>
</body>
</html>
