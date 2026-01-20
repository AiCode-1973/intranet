<?php
require_once '../config.php';
require_once '../functions.php';

requireRHAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao == 'salvar_politica') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $titulo = sanitize($_POST['titulo']);
        $descricao = $_POST['descricao'];
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $arquivo_path = $_POST['arquivo_atual'] ?? '';

        if (!empty($_FILES['arquivo']['name'])) {
            $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'politica_' . time() . '.' . $ext;
            $destino = '../uploads/rh/politicas/' . $nome_arquivo;
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                $arquivo_path = 'uploads/rh/politicas/' . $nome_arquivo;
            }
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE rh_politicas SET titulo = ?, descricao = ?, arquivo_path = ?, ativo = ? WHERE id = ?");
            $stmt->bind_param("sssii", $titulo, $descricao, $arquivo_path, $ativo, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO rh_politicas (titulo, descricao, arquivo_path, ativo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $titulo, $descricao, $arquivo_path, $ativo);
        }
        
        if ($stmt->execute()) {
            $mensagem = "Política salva com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao salvar política: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    }

    if ($acao == 'salvar_documento') {
        $usuario_id = intval($_POST['usuario_id']);
        $titulo = sanitize($_POST['titulo']);
        $categoria = $_POST['categoria'];
        $mes = intval($_POST['mes']);
        $ano = intval($_POST['ano']);
        
        if (!empty($_FILES['arquivo']['name'])) {
            $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'doc_' . $usuario_id . '_' . time() . '.' . $ext;
            $destino = '../uploads/rh/documentos/' . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                $arquivo_path = 'uploads/rh/documentos/' . $nome_arquivo;
                
                $stmt = $conn->prepare("INSERT INTO rh_documentos (usuario_id, titulo, categoria, mes, ano, arquivo_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issiis", $usuario_id, $titulo, $categoria, $mes, $ano, $arquivo_path);
                
                if ($stmt->execute()) {
                    $mensagem = "Documento enviado para o colaborador!";
                    $tipo_mensagem = "success";
                } else {
                    $mensagem = "Erro ao salvar no banco: " . $conn->error;
                    $tipo_mensagem = "danger";
                }
            } else {
                $mensagem = "Erro ao fazer upload do arquivo.";
                $tipo_mensagem = "danger";
            }
        }
    }

    if ($acao == 'excluir_politica') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM rh_politicas WHERE id = $id");
        $mensagem = "Política excluída!";
        $tipo_mensagem = "success";
    }

    if ($acao == 'excluir_documento') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM rh_documentos WHERE id = $id");
        $mensagem = "Documento removido!";
        $tipo_mensagem = "success";
    }

    if ($acao == 'enviar_mensagem') {
        $usuario_id = intval($_POST['usuario_id']);
        $remetente_id = $_SESSION['usuario_id'];
        $assunto = sanitize($_POST['assunto']);
        $texto = $_POST['mensagem'];

        $stmt = $conn->prepare("INSERT INTO rh_mensagens (usuario_id, remetente_id, assunto, mensagem) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $usuario_id, $remetente_id, $assunto, $texto);
        
        if ($stmt->execute()) {
            $mensagem = "Mensagem enviada com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao enviar mensagem: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    }

    if ($acao == 'excluir_mensagem') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM rh_mensagens WHERE id = $id");
        $mensagem = "Mensagem removida!";
        $tipo_mensagem = "success";
    }
}

// Buscar Dados
$usuarios = $conn->query("SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome");
$politicas = $conn->query("SELECT * FROM rh_politicas ORDER BY created_at DESC");
$documentos = $conn->query("
    SELECT d.*, u.nome as usuario_nome 
    FROM rh_documentos d 
    JOIN usuarios u ON d.usuario_id = u.id 
    ORDER BY d.created_at DESC LIMIT 50
");
$mensagens = $conn->query("
    SELECT m.*, u.nome as destinatario_nome 
    FROM rh_mensagens m 
    JOIN usuarios u ON m.usuario_id = u.id 
    ORDER BY m.created_at DESC LIMIT 50
");

// Buscar Ocorrências Validadas (para o RH aprovar)
$ocorrencias_validadas = $conn->query("
    SELECT o.*, u.nome as colaborador_nome, s.nome as supervisor_nome 
    FROM rh_ponto_ocorrencias o 
    JOIN usuarios u ON o.usuario_id = u.id 
    JOIN usuarios s ON o.supervisor_id = s.id 
    WHERE o.status = 'VALIDADO'
    ORDER BY o.created_at ASC
");

// Buscar Equipes (Superiores e seus Subordinados)
$equipes = $conn->query("
    SELECT s.nome as superior_nome, GROUP_CONCAT(u.nome SEPARATOR ', ') as subordinados
    FROM usuarios u
    JOIN usuarios s ON u.superior_id = s.id
    WHERE u.superior_id IS NOT NULL
    GROUP BY u.superior_id
    ORDER BY s.nome
");

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de RH - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="users-2" class="w-6 h-6"></i>
                    Recursos Humanos (Gestão)
                </h1>
                <p class="text-text-secondary text-[11px] uppercase font-bold tracking-wider">Políticas, Manuais e Holerites</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all shadow-sm">Voltar</a>
                <button onclick="abrirModalPolitica()" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="file-plus" class="w-4 h-4"></i> Nova Política
                </button>
                <button onclick="abrirModalMensagem()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="mail-plus" class="w-4 h-4"></i> Enviar Mensagem
                </button>
                <a href="rh_ponto_relatorio.php" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Relatório de Ponto
                </a>
                <button onclick="abrirModalDocumento()" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Enviar Holerite/Doc
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-6 text-xs font-bold flex items-center gap-2 animate-in fade-in slide-in-from-top-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex gap-4 border-b border-border mb-6">
            <button onclick="switchTab('politicas')" id="tab-politicas" class="tab-btn px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-primary text-primary transition-all">Políticas e Manuais</button>
            <button onclick="switchTab('documentos')" id="tab-documentos" class="tab-btn px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-text-secondary hover:text-text transition-all">Documentos Enviados</button>
            <button onclick="switchTab('mensagens')" id="tab-mensagens" class="tab-btn px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-text-secondary hover:text-text transition-all">Comunicação Direta</button>
            <button onclick="switchTab('ponto')" id="tab-ponto" class="tab-btn px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-text-secondary hover:text-text transition-all flex items-center gap-2">
                Ocorrências de Ponto
                <?php if ($ocorrencias_validadas->num_rows > 0): ?>
                    <span class="w-4 h-4 rounded-full bg-red-500 text-white text-[9px] flex items-center justify-center font-bold animate-pulse"><?php echo $ocorrencias_validadas->num_rows; ?></span>
                <?php endif; ?>
            </button>
            <button onclick="switchTab('equipes')" id="tab-equipes" class="tab-btn px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-text-secondary hover:text-text transition-all">Gestão de Equipes</button>
        </div>

        <!-- Conteúdo Políticas -->
        <div id="content-politicas" class="tab-content active animate-in fade-in duration-300">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($p = $politicas->fetch_assoc()): ?>
                <div class="bg-white p-5 rounded-2xl border border-border shadow-sm group hover:border-indigo-400 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                        </div>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='editarPolitica(<?php echo json_encode($p); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                            <button onclick="excluirPolitica(<?php echo $p['id']; ?>)" class="p-1.5 text-red-500 hover:bg-red-50 rounded"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                    <h3 class="text-sm font-bold text-text mb-1"><?php echo $p['titulo']; ?></h3>
                    <p class="text-[11px] text-text-secondary line-clamp-2 mb-4"><?php echo $p['descricao']; ?></p>
                    <div class="flex items-center justify-between mt-auto">
                        <span class="text-[9px] font-black uppercase <?php echo $p['ativo'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $p['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                        <?php if ($p['arquivo_path']): 
                                $pol_nome = basename($p['arquivo_path']);
                                $pol_link = "../download.php?file=" . urlencode($pol_nome) . "&type=politica";
                            ?>
                            <a href="<?php echo $pol_link; ?>" target="_blank" class="text-[10px] font-bold text-primary flex items-center gap-1">
                                <i data-lucide="external-link" class="w-3 h-3"></i> Ver Arquivo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Conteúdo Documentos -->
        <div id="content-documentos" class="tab-content animate-in fade-in duration-300">
            <div class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Colaborador</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Documento</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Referência</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php while($d = $documentos->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary border border-primary/20 text-[10px] font-bold uppercase">
                                        <?php echo substr($d['usuario_nome'], 0, 2); ?>
                                    </div>
                                    <span class="text-xs font-bold text-text"><?php echo $d['usuario_nome']; ?></span>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-text"><?php echo $d['titulo']; ?></span>
                                    <span class="text-[9px] font-black uppercase text-indigo-500"><?php echo $d['categoria']; ?></span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="text-xs font-medium text-text-secondary">
                                    <?php echo $d['mes'] ? $meses[$d['mes']] . ' / ' . $d['ano'] : '-'; ?>
                                </span>
                            </td>
                             <td class="p-4 text-right">
                                <div class="flex justify-end gap-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                    <?php 
                                        $doc_nome = basename($d['arquivo_path']);
                                        $doc_link = "../download.php?file=" . urlencode($doc_nome) . "&type=documento";
                                    ?>
                                    <a href="<?php echo $doc_link; ?>" target="_blank" class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all"><i data-lucide="eye" class="w-4 h-4"></i></a>
                                    <button onclick="excluirDocumento(<?php echo $d['id']; ?>)" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conteúdo Mensagens -->
        <div id="content-mensagens" class="tab-content animate-in fade-in duration-300">
            <div class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Destinatário</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Assunto / Mensagem</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Status</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php while($m = $mensagens->fetch_assoc()): ?>
                        <tr class="hover:bg-background/30 transition-colors group">
                            <td class="p-4">
                                <span class="text-xs font-bold text-text"><?php echo $m['destinatario_nome']; ?></span>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-text"><?php echo $m['assunto']; ?></span>
                                    <span class="text-[10px] text-text-secondary line-clamp-1"><?php echo $m['mensagem']; ?></span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded <?php echo $m['lida'] ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600'; ?>">
                                    <?php echo $m['lida'] ? 'Lida' : 'Pendente'; ?>
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="excluirMensagem(<?php echo $m['id']; ?>)" class="p-1.5 text-red-500 hover:bg-red-50 opacity-50 group-hover:opacity-100 rounded transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conteúdo Ocorrências de Ponto -->
        <div id="content-ponto" class="tab-content animate-in fade-in duration-300">
            <div class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Colaborador</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Validador (Supervisor)</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Referência</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if ($ocorrencias_validadas->num_rows > 0): ?>
                            <?php while($o = $ocorrencias_validadas->fetch_assoc()): ?>
                            <tr class="hover:bg-background/30 transition-colors group">
                                <td class="p-4">
                                    <span class="text-xs font-bold text-text"><?php echo $o['colaborador_nome']; ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-medium text-text-secondary"><?php echo $o['supervisor_nome']; ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-bold text-primary"><?php echo $meses[$o['mes']] . '/' . $o['ano']; ?></span>
                                </td>
                                <td class="p-4 text-right">
                                    <a href="../rh_ponto_detalhes.php?id=<?php echo $o['id']; ?>" class="px-3 py-1.5 bg-primary text-white rounded-lg text-[10px] font-black uppercase tracking-widest shadow-md shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
                                        Revisar e Aprovar
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center">
                                    <p class="text-[10px] font-black uppercase text-text-secondary opacity-40 italic">Nenhuma ocorrência validada aguardando processamento.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conteúdo Equipes -->
        <div id="content-equipes" class="tab-content animate-in fade-in duration-300">
            <div class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-border">
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Superior (Encarregado/Gerente)</th>
                            <th class="p-4 text-[10px] font-black text-text-secondary uppercase">Integrantes da Equipe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if ($equipes->num_rows > 0): ?>
                            <?php while($e = $equipes->fetch_assoc()): ?>
                            <tr class="hover:bg-background/30 transition-colors group">
                                <td class="p-4 align-top w-64">
                                    <span class="text-xs font-bold text-primary"><?php echo $e['superior_nome']; ?></span>
                                </td>
                                <td class="p-4">
                                    <p class="text-[11px] text-text-secondary leading-relaxed"><?php echo $e['subordinados']; ?></p>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="p-8 text-center text-text-secondary italic text-xs">Ainda não há equipes mapeadas. Vincule os superiores no cadastro de usuários.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 p-4 bg-primary/5 rounded-xl border border-primary/10 flex items-center gap-3">
                <i data-lucide="info" class="w-5 h-5 text-primary"></i>
                <p class="text-[10px] font-bold text-primary uppercase">O mapeamento de equipes é feito diretamente no <a href="usuarios.php" class="underline">Cadastro de Usuários</a>.</p>
            </div>
        </div>
    </div>

    <!-- Modais -->
    <!-- Modal Política -->
    <div id="modalPolitica" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="bg-indigo-500 p-5 text-white flex justify-between items-center">
                <h2 id="tituloPolitica" class="font-bold">Nova Política / Manual</h2>
                <button onclick="fecharModal('modalPolitica')"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="acao" value="salvar_politica">
                <input type="hidden" name="id" id="pol_id">
                <input type="hidden" name="arquivo_atual" id="pol_arquivo_atual">
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Título</label>
                    <input type="text" name="titulo" id="pol_titulo" required class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Descrição</label>
                    <textarea name="descricao" id="pol_descricao" rows="4" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-indigo-500 outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Arquivo (PDF recomendado)</label>
                    <input type="file" name="arquivo" class="w-full text-xs text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="ativo" id="pol_ativo" checked class="w-4 h-4 rounded text-indigo-500 focus:ring-indigo-500">
                    <label for="pol_ativo" class="text-xs font-bold text-text-secondary">Público para todos os colaboradores</label>
                </div>
                
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="fecharModal('modalPolitica')" class="px-5 py-2 text-xs font-bold text-text-secondary">Cancelar</button>
                    <button type="submit" class="px-8 py-2 bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-500/20 active:scale-95 transition-all">Salvar Política</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Documento -->
    <div id="modalDocumento" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="bg-primary p-5 text-white flex justify-between items-center">
                <h2 class="font-bold">Enviar Documento ao Colaborador</h2>
                <button onclick="fecharModal('modalDocumento')"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="acao" value="salvar_documento">
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Selecionar Colaborador</label>
                    <select name="usuario_id" required class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Selecione...</option>
                        <?php $usuarios->data_seek(0); while($u = $usuarios->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo $u['nome']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Título do Documento</label>
                    <input type="text" name="titulo" required placeholder="Ex: Holerite de Dezembro" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-primary outline-none">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Categoria</label>
                        <select name="categoria" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs">
                            <option value="Holerite">Holerite</option>
                            <option value="Contrato">Contrato</option>
                            <option value="Comprovante">Comprovante</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Mês Ref.</label>
                        <select name="mes" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs">
                            <option value="">-</option>
                            <?php foreach($meses as $num => $nome): ?>
                                <option value="<?php echo $num; ?>" <?php echo $num == date('n') ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Ano Ref.</label>
                        <select name="ano" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs">
                            <option value="2023">2023</option>
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026" selected>2026</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Arquivo</label>
                    <input type="file" name="arquivo" required class="w-full text-xs text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-primary/5 file:text-primary hover:file:bg-primary/10">
                </div>
                
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="fecharModal('modalDocumento')" class="px-5 py-2 text-xs font-bold text-text-secondary">Cancelar</button>
                    <button type="submit" class="px-8 py-2 bg-primary text-white rounded-xl text-xs font-bold shadow-lg shadow-primary/20 active:scale-95 transition-all">Enviar Agora</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Mensagem -->
    <div id="modalMensagem" class="modal">
        <div class="bg-white w-full max-w-lg mx-4 rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="bg-emerald-500 p-5 text-white flex justify-between items-center">
                <h2 class="font-bold">Enviar Mensagem Individual</h2>
                <button onclick="fecharModal('modalMensagem')"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="acao" value="enviar_mensagem">
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Destinatário</label>
                    <select name="usuario_id" required class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                        <option value="">Selecione...</option>
                        <?php $usuarios->data_seek(0); while($u = $usuarios->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo $u['nome']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Assunto</label>
                    <input type="text" name="assunto" required placeholder="Ex: Aviso sobre Férias" class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-text-secondary mb-1">Mensagem</label>
                    <textarea name="mensagem" required rows="6" placeholder="Escreva sua mensagem aqui..." class="w-full p-2.5 bg-background border border-border rounded-xl text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></textarea>
                </div>
                
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="fecharModal('modalMensagem')" class="px-5 py-2 text-xs font-bold text-text-secondary">Cancelar</button>
                    <button type="submit" class="px-8 py-2 bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/20 active:scale-95 transition-all">Enviar Mensagem</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-primary', 'text-primary');
                btn.classList.add('border-transparent', 'text-text-secondary');
            });
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.getElementById('tab-' + tab).classList.add('border-primary', 'text-primary');
            document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-text-secondary');
            document.getElementById('content-' + tab).classList.add('active');
        }

        function abrirModalPolitica() {
            document.getElementById('tituloPolitica').innerText = 'Nova Política / Manual';
            document.getElementById('pol_id').value = '';
            document.getElementById('pol_titulo').value = '';
            document.getElementById('pol_descricao').value = '';
            document.getElementById('pol_arquivo_atual').value = '';
            document.getElementById('modalPolitica').classList.add('active');
        }

        function editarPolitica(p) {
            document.getElementById('tituloPolitica').innerText = 'Editar Política';
            document.getElementById('pol_id').value = p.id;
            document.getElementById('pol_titulo').value = p.titulo;
            document.getElementById('pol_descricao').value = p.descricao;
            document.getElementById('pol_arquivo_atual').value = p.arquivo_path;
            document.getElementById('modalPolitica').classList.add('active');
        }

        function abrirModalDocumento() {
            document.getElementById('modalDocumento').classList.add('active');
        }

        function abrirModalMensagem() {
            document.getElementById('modalMensagem').classList.add('active');
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function excluirPolitica(id) {
            if (confirm('Deseja excluir esta política? Esta ação é irreversível.')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_politica"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }

        function excluirDocumento(id) {
            if (confirm('Deseja remover este documento? O colaborador não terá mais acesso a ele.')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_documento"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }

        function excluirMensagem(id) {
            if (confirm('Deseja remover esta mensagem do registro?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="acao" value="excluir_mensagem"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>

    <?php include '../footer.php'; ?>
</body>
</html>
