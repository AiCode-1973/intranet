<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $titulo = sanitize($_POST['titulo']);
    $url = $_POST['url']; // URL can contain special chars
    $conteudo = $_POST['conteudo']; // Novo campo de texto
    $tipo = sanitize($_POST['tipo']);
    $icone = sanitize($_POST['icone'] ?: 'link');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($_POST['acao'] == 'salvar') {
        $stmt = $conn->prepare("INSERT INTO informacoes (titulo, url, conteudo, tipo, icone, ativo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $titulo, $url, $conteudo, $tipo, $icone, $ativo);

        if ($stmt->execute()) {
            $mensagem = "Informação cadastrada com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Cadastrou informação: $titulo");
        } else {
            $mensagem = "Erro ao cadastrar: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'editar') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE informacoes SET titulo = ?, url = ?, conteudo = ?, tipo = ?, icone = ?, ativo = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $titulo, $url, $conteudo, $tipo, $icone, $ativo, $id);

        if ($stmt->execute()) {
            $mensagem = "Informação atualizada com sucesso!";
            $tipo_mensagem = "success";
            registrarLog($conn, "Editou informação: $titulo");
        } else {
            $mensagem = "Erro ao atualizar: " . $conn->error;
            $tipo_mensagem = "danger";
        }
    } elseif ($_POST['acao'] == 'excluir') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM informacoes WHERE id = $id");
        $mensagem = "Informação excluída!";
        $tipo_mensagem = "success";
        registrarLog($conn, "Excluiu informação ID: $id");
    }
}

// Buscar informações
$informacoes = $conn->query("SELECT * FROM informacoes ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Informações & Saúde - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i data-lucide="info" class="w-6 h-6"></i>
                    Gerenciar Informações & Saúde
                </h1>
                <p class="text-text-secondary text-xs mt-1">Gerenciamento de links externos e notícias para o dashboard</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar
                </a>
                <button onclick="abrirModal()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="plus" class="w-4 h-4"></i> Nova Informação
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase tracking-widest"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-background/50 border-b border-border">
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Título / URL</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Tipo</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                        <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    <?php while ($info = $informacoes->fetch_assoc()): ?>
                    <tr class="hover:bg-background/20 transition-colors group">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-primary border border-border">
                                    <i data-lucide="<?php echo $info['icone']; ?>" class="w-4 h-4"></i>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-bold text-text truncate max-w-xs md:max-w-md"><?php echo $info['titulo']; ?></span>
                                    <span class="text-[10px] text-text-secondary italic truncate max-w-xs md:max-w-md"><?php echo $info['url']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 text-[9px] font-black uppercase tracking-widest border border-blue-100">
                                <?php echo $info['tipo']; ?>
                            </span>
                        </td>
                        <td class="p-3 text-center">
                            <?php if ($info['ativo']): ?>
                                <span class="px-2 py-0.5 rounded bg-green-50 text-green-600 text-[9px] font-black uppercase border border-green-100">Ativo</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 text-[9px] font-black uppercase border border-red-100">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-1.5 opacity-40 group-hover:opacity-100 transition-opacity">
                                <a href="<?php echo $info['url']; ?>" target="_blank" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition-all" title="Ver Link">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                                <button onclick='editarInfo(<?php echo json_encode($info); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all" title="Editar">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                <button onclick="excluirInfo(<?php echo $info['id']; ?>, '<?php echo $info['titulo']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all" title="Excluir">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nova/Editar Informação -->
    <div id="modalNovo" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <h2 id="modalTitulo" class="text-base font-bold">Cadastrar Informação</h2>
                <button onclick="fecharModal()" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <form id="formInfo" method="POST" action="" class="p-5 space-y-4">
                <input type="hidden" name="acao" id="acao" value="salvar">
                <input type="hidden" name="id" id="info_id">
                
                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Título</label>
                    <input type="text" name="titulo" id="titulo" required placeholder="Ex: Portal da Saúde" 
                           class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">URL Externa</label>
                    <input type="url" name="url" id="url" placeholder="https://exemplo.com" 
                           class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Conteúdo / Informação Técnica</label>
                    <textarea name="conteudo" id="conteudo" rows="4" placeholder="Digite aqui os detalhes ou informações importantes..." 
                              class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Tipo</label>
                        <select name="tipo" id="tipo" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="Saúde">Saúde</option>
                            <option value="Link Externo">Link Externo</option>
                            <option value="Notícia">Notícia</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-text-secondary mb-1 uppercase tracking-widest">Ícone</label>
                        <select name="icone" id="icone" class="w-full p-2 bg-background border border-border rounded-lg text-xs font-bold focus:outline-none focus:border-primary transition-all">
                            <option value="info">Informação (Padrão)</option>
                            <option value="link">Link / Corrente</option>
                            <option value="heart-pulse">Saúde / Pulso</option>
                            <option value="stethoscop">Estetoscópio</option>
                            <option value="activity">Atividade / Gráfico</option>
                            <option value="alert-circle">Aviso / Alerta</option>
                            <option value="file-text">Documento / Texto</option>
                            <option value="newspaper">Notícia / Jornal</option>
                            <option value="help-circle">Ajuda / Dúvida</option>
                            <option value="shield-check">Segurança / Check</option>
                            <option value="megaphone">Comunicado</option>
                            <option value="calendar">Calendário</option>
                            <option value="users">Usuários / Equipe</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="ativo" id="ativo" checked class="w-4 h-4 text-primary border-border rounded focus:ring-primary">
                    <label for="ativo" class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Aparecer no Dashboard</label>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors uppercase">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 uppercase tracking-widest">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <form id="formExcluir" method="POST" action="" style="display:none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="excluir_id">
    </form>

    <script>
        function abrirModal() { 
            document.getElementById('modalTitulo').textContent = 'Cadastrar Informação';
            document.getElementById('acao').value = 'salvar';
            document.getElementById('info_id').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('url').value = '';
            document.getElementById('conteudo').value = '';
            document.getElementById('tipo').value = 'Saúde';
            document.getElementById('icone').value = 'link';
            document.getElementById('ativo').checked = true;
            document.getElementById('modalNovo').classList.add('active'); 
        }

        function editarInfo(info) {
            document.getElementById('modalTitulo').textContent = 'Editar Informação';
            document.getElementById('acao').value = 'editar';
            document.getElementById('info_id').value = info.id;
            document.getElementById('titulo').value = info.titulo;
            document.getElementById('url').value = info.url;
            document.getElementById('conteudo').value = info.conteudo || '';
            document.getElementById('tipo').value = info.tipo;
            document.getElementById('icone').value = info.icone;
            document.getElementById('ativo').checked = info.ativo == 1;
            document.getElementById('modalNovo').classList.add('active');
        }

        function fecharModal() { document.getElementById('modalNovo').classList.remove('active'); }
        
        function excluirInfo(id, titulo) {
            if (confirm('Deseja excluir a informação "' + titulo + '"?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>
