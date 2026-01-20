<?php
require_once '../config.php';
require_once '../functions.php';

requireRHAdmin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        
        if ($acao == 'criar') {
            $nome = sanitize($_POST['nome']);
            $cpf = preg_replace('/[^0-9]/', '', sanitize($_POST['cpf']));
            $email = sanitize($_POST['email']);
            $funcao = sanitize($_POST['funcao']);
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            $setor_id = $_POST['setor_id'] ? intval($_POST['setor_id']) : null;
            $superior_id = $_POST['superior_id'] ? intval($_POST['superior_id']) : null;
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_tecnico = isset($_POST['is_tecnico']) ? 1 : 0;
            $is_manutencao = isset($_POST['is_manutencao']) ? 1 : 0;
            $is_educacao = isset($_POST['is_educacao']) ? 1 : 0;
            
            // Upload e Processamento da foto
            $foto_nome = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $foto_nome = time() . '_' . $cpf . '.jpg'; // Convertemos sempre para JPG de alta qualidade
                
                if (processarFoto3x4($_FILES['foto']['tmp_name'], '../uploads/fotos/' . $foto_nome)) {
                    // Sucesso
                } else {
                    $foto_nome = null; // Falha no processamento
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, cpf, email, foto, funcao, senha, setor_id, superior_id, is_admin, is_tecnico, is_manutencao, is_educacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssiiiiii", $nome, $cpf, $email, $foto_nome, $funcao, $senha, $setor_id, $superior_id, $is_admin, $is_tecnico, $is_manutencao, $is_educacao);
            
            if ($stmt->execute()) {
                $mensagem = 'Usuário criado com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Criou usuário: ' . $nome);
            } else {
                $mensagem = 'Erro ao criar usuário: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        elseif ($acao == 'editar') {
            $id = intval($_POST['id']);
            $nome = sanitize($_POST['nome']);
            $cpf = preg_replace('/[^0-9]/', '', sanitize($_POST['cpf']));
            $email = sanitize($_POST['email']);
            $funcao = sanitize($_POST['funcao']);
            $setor_id = $_POST['setor_id'] ? intval($_POST['setor_id']) : null;
            $superior_id = $_POST['superior_id'] ? intval($_POST['superior_id']) : null;
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_tecnico = isset($_POST['is_tecnico']) ? 1 : 0;
            $is_manutencao = isset($_POST['is_manutencao']) ? 1 : 0;
            $is_educacao = isset($_POST['is_educacao']) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $foto_sql = "";
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $foto_nome = time() . '_' . $cpf . '.jpg';
                if (processarFoto3x4($_FILES['foto']['tmp_name'], '../uploads/fotos/' . $foto_nome)) {
                    $foto_sql = ", foto = '$foto_nome'";
                } else {
                    error_log("Erro ao processar imagem para ../uploads/fotos/" . $foto_nome);
                }
            } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] != 0 && $_FILES['foto']['error'] != 4) {
                error_log("Erro no upload do arquivo: " . $_FILES['foto']['error']);
            }
            
            if (!empty($_POST['senha'])) {
                $p_senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, cpf = ?, email = ?, funcao = ?, senha = ?, setor_id = ?, superior_id = ?, is_admin = ?, is_tecnico = ?, is_manutencao = ?, is_educacao = ?, ativo = ? $foto_sql WHERE id = ?");
                $stmt->bind_param("sssssiiiiiiii", $nome, $cpf, $email, $funcao, $p_senha, $setor_id, $superior_id, $is_admin, $is_tecnico, $is_manutencao, $is_educacao, $ativo, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, cpf = ?, email = ?, funcao = ?, setor_id = ?, superior_id = ?, is_admin = ?, is_tecnico = ?, is_manutencao = ?, is_educacao = ?, ativo = ? $foto_sql WHERE id = ?");
                $stmt->bind_param("ssssiiiiiiii", $nome, $cpf, $email, $funcao, $setor_id, $superior_id, $is_admin, $is_tecnico, $is_manutencao, $is_educacao, $ativo, $id);
            }
            
            if ($stmt->execute()) {
                $mensagem = 'Usuário atualizado com sucesso!';
                $tipo_mensagem = 'success';
                registrarLog($conn, 'Editou usuário: ' . $nome);
            } else {
                $mensagem = 'Erro ao atualizar usuário: ' . $conn->error;
                $tipo_mensagem = 'danger';
            }
            $stmt->close();
        }
        elseif ($acao == 'excluir') {
            $id = intval($_POST['id']);
            
            if ($id != $_SESSION['usuario_id']) {
                $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $mensagem = 'Usuário excluído com sucesso!';
                    $tipo_mensagem = 'success';
                    registrarLog($conn, 'Excluiu usuário ID: ' . $id);
                } else {
                    $mensagem = 'Erro ao excluir usuário: ' . $conn->error;
                    $tipo_mensagem = 'danger';
                }
                $stmt->close();
            } else {
                $mensagem = 'Você não pode excluir seu próprio usuário!';
                $tipo_mensagem = 'warning';
            }
        }
    }
}

// Filtros e Pesquisa
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_setor = isset($_GET['setor']) ? intval($_GET['setor']) : 0;

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (u.nome LIKE '%$search%' OR u.email LIKE '%$search%' OR u.cpf LIKE '%$search%')";
}
if ($filter_setor) {
    $where .= " AND u.setor_id = $filter_setor";
}

// Lógica de Paginação
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Validar limites permitidos
$limit_options = [5, 10, 20, 30, 50];
if (!in_array($limit, $limit_options)) $limit = 10;

// Contar total de registros para a paginação
$total_query = $conn->query("
    SELECT COUNT(*) as total 
    FROM usuarios u
    $where
");
$total_rows = $total_query->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$usuarios = $conn->query("
    SELECT u.id, u.nome, u.cpf, u.email, u.foto, u.funcao, u.setor_id, u.superior_id, u.is_admin, u.is_tecnico, u.is_manutencao, u.is_educacao, u.ativo, u.ultimo_acesso, s.nome as setor_nome 
    FROM usuarios u
    LEFT JOIN setores s ON u.setor_id = s.id
    $where
    ORDER BY u.nome ASC
    LIMIT $limit OFFSET $offset
");

$setores = $conn->query("SELECT * FROM setores WHERE ativo = 1 ORDER BY nome");

/**
 * Função para processar a imagem: corta para 3x4 e redimensiona com alta qualidade
 */
function processarFoto3x4($caminho_origem, $caminho_destino) {
    if (!extension_loaded('gd')) return move_uploaded_file($caminho_origem, $caminho_destino);

    list($width, $height, $type) = getimagesize($caminho_origem);
    
    // Cria a imagem baseada no tipo
    switch ($type) {
        case IMAGETYPE_JPEG: $origem = imagecreatefromjpeg($caminho_origem); break;
        case IMAGETYPE_PNG:  $origem = imagecreatefrompng($caminho_origem); break;
        case IMAGETYPE_WEBP: $origem = imagecreatefromwebp($caminho_origem); break;
        default: return move_uploaded_file($caminho_origem, $caminho_destino);
    }

    if (!$origem) return false;

    // Alvo: 600x800 (Proporção 3x4 de alta resolução)
    $target_w = 600;
    $target_h = 800;
    $target_ratio = $target_w / $target_h;
    $current_ratio = $width / $height;

    $src_x = 0; $src_y = 0;
    $src_w = $width; $src_h = $height;

    if ($current_ratio > $target_ratio) {
        // Imagem muito larga -> corta as laterais
        $src_w = $height * $target_ratio;
        $src_x = ($width - $src_w) / 2;
    } else {
        // Imagem muito alta -> corta o topo/fundo
        $src_h = $width / $target_ratio;
        $src_y = ($height - $src_h) / 2;
    }

    // Cria nova imagem transparente
    $destino = imagecreatetruecolor($target_w, $target_h);
    
    // Preserva transparência (se for salvar como PNG, mas como salvaremos como JPG, preenchemos de branco)
    $branco = imagecolorallocate($destino, 255, 255, 255);
    imagefill($destino, 0, 0, $branco);

    // Redimensionamento com alta qualidade (bicubic)
    imagecopyresampled($destino, $origem, 0, 0, $src_x, $src_y, $target_w, $target_h, $src_w, $src_h);

    // Salva como JPEG com qualidade 95 (excelente equilíbrio)
    $resultado = imagejpeg($destino, $caminho_destino, 95);

    imagedestroy($origem);
    imagedestroy($destino);

    return $resultado;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .foto-3x4 {
            width: 58px;
            height: 77px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #fff;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 0 0 1px rgba(var(--color-primary), 0.05);
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            background-color: #f8fafc;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group:hover .foto-3x4 {
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: rgba(var(--color-primary), 0.2);
        }
        .foto-3x4-large {
            width: 140px;
            height: 186px;
            object-fit: cover;
            border-radius: 16px;
            border: 4px solid #fff;
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1), 
                0 10px 10px -5px rgba(0, 0, 0, 0.04);
            image-rendering: -webkit-optimize-contrast;
            background-color: #f8fafc;
            transition: transform 0.3s ease;
        }
        /* Efeito de brilho sutil para fotos */
        .avatar-container {
            position: relative;
            display: inline-block;
        }
        .avatar-container::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="users" class="w-6 h-6"></i>
                    Gerenciar Usuários
                </h1>
                <p class="text-text-secondary text-[11px] mt-0.5 uppercase tracking-wider font-semibold">Base de Colaboradores</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Voltar
                </a>
                <button onclick="abrirModal('criar')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Novo Usuário
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white p-3 rounded-xl shadow-sm border border-border mb-4 flex flex-col md:flex-row gap-3 items-end">
            <div class="flex-grow w-full">
                <label class="block text-[10px] font-bold text-text-secondary mb-1 uppercase tracking-wider">Pesquisar</label>
                <div class="relative">
                    <input type="text" id="searchInput" value="<?php echo $search; ?>" placeholder="Nome, E-mail ou CPF..." 
                           class="w-full pl-8 pr-4 py-1.5 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    <i data-lucide="search" class="absolute left-2.5 top-2 w-3.5 h-3.5 text-text-secondary"></i>
                </div>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-bold text-text-secondary mb-1 uppercase tracking-wider">Setor</label>
                <select id="setorFilter" class="w-full px-3 py-1.5 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    <option value="0">Todos</option>
                    <?php 
                    $setores->data_seek(0);
                    while ($setor = $setores->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $setor['id']; ?>" <?php echo $filter_setor == $setor['id'] ? 'selected' : ''; ?>>
                            <?php echo $setor['nome']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="w-full md:w-32">
                <label class="block text-[10px] font-bold text-text-secondary mb-1 uppercase tracking-wider">Registros</label>
                <select id="limitFilter" class="w-full px-3 py-1.5 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    <?php foreach ($limit_options as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $limit == $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button onclick="aplicarFiltros()" class="bg-card-blue/50 text-primary px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-primary/10 transition-all flex items-center gap-1.5 h-[34px]">
                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                Filtrar
            </button>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 animate-in fade-in slide-in-from-top-2 duration-300 <?php 
                echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 
                    ($tipo_mensagem == 'warning' ? 'bg-yellow-50 border-yellow-100 text-yellow-700' : 'bg-red-50 border-red-100 text-red-700'); ?>">
                <i data-lucide="<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4"></i>
                <span class="text-xs font-semibold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Usuário</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">E-mail / CPF</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Setor</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Perfil</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest">Status</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if ($usuarios->num_rows > 0): ?>
                            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                            <tr class="hover:bg-background/30 transition-colors group">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($usuario['foto'])): ?>
                                            <div class="avatar-container">
                                                <img src="../uploads/fotos/<?php echo $usuario['foto']; ?>" alt="Foto" class="foto-3x4">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center border border-primary/20 text-primary text-sm font-black shadow-inner">
                                                <?php echo substr($usuario['nome'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-xs font-bold text-text leading-tight"><?php echo $usuario['nome']; ?></p>
                                            <p class="text-[9px] text-primary font-bold uppercase tracking-tighter"><?php echo $usuario['funcao'] ?: 'Sem Função'; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-text"><?php echo $usuario['email']; ?></span>
                                        <span class="text-[10px] text-text-secondary font-mono mt-0.5 italic">
                                            <?php echo preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $usuario['cpf']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 bg-gray-50 border border-border rounded text-[10px] font-bold text-text-secondary group-hover:bg-white">
                                        <?php echo $usuario['setor_nome'] ?? '-'; ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php if ($usuario['is_admin']): ?>
                                        <span class="flex items-center gap-1 text-[10px] font-black text-orange-600 uppercase">
                                            <i data-lucide="shield" class="w-3 h-3"></i> Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-text-secondary font-bold uppercase">Padrão</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php if ($usuario['ativo']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase bg-green-50 text-green-600 border border-green-100">
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase bg-red-50 text-red-600 border border-red-100">
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-1.5 opacity-50 group-hover:opacity-100 transition-opacity">
                                        <button onclick='editarUsuario(<?php echo json_encode($usuario); ?>)' class="p-1.5 text-primary hover:bg-primary/10 rounded transition-all" title="Editar">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                        <button onclick="excluirUsuario(<?php echo $usuario['id']; ?>, '<?php echo $usuario['nome']; ?>')" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-all" title="Excluir">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-text-secondary">
                                    <i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 opacity-20"></i>
                                    <p class="text-[11px] font-bold">Nenhum resultado</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 bg-white border-t border-border flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="text-[10px] font-bold text-text-secondary uppercase tracking-widest">
                    Mostrando <?php echo min($offset + 1, $total_rows); ?> - <?php echo min($offset + $limit, $total_rows); ?> de <?php echo $total_rows; ?> usuários
                </div>
                
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <button onclick="mudarPagina(<?php echo $page - 1; ?>)" class="p-1.5 rounded-lg hover:bg-gray-100 text-text-secondary transition-all">
                            <i data-lucide="chevron-left" class="w-4 h-4 text-primary"></i>
                        </button>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<button onclick="mudarPagina(1)" class="w-8 h-8 rounded-lg text-xs font-bold text-text-secondary hover:bg-gray-100">1</button>';
                        if ($start_page > 2) echo '<span class="px-1 text-text-secondary">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = $i == $page ? 'bg-primary text-white scale-110 shadow-md' : 'text-text-secondary hover:bg-gray-100';
                        echo "<button onclick=\"mudarPagina($i)\" class=\"w-8 h-8 rounded-lg text-xs font-bold transition-all {$active_class}\">$i</button>";
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="px-1 text-text-secondary">...</span>';
                        echo "<button onclick=\"mudarPagina($total_pages)\" class=\"w-8 h-8 rounded-lg text-xs font-bold text-text-secondary hover:bg-gray-100\">$total_pages</button>";
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <button onclick="mudarPagina(<?php echo $page + 1; ?>)" class="p-1.5 rounded-lg hover:bg-gray-100 text-text-secondary transition-all">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-primary"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
                <div class="px-4 py-3 bg-white border-t border-border">
                    <div class="text-[10px] font-bold text-text-secondary uppercase tracking-widest">
                        Total de <?php echo $total_rows; ?> usuários encontrados
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Usuário -->
    <div id="modalUsuario" class="modal">
        <div class="bg-white w-full max-w-md mx-4 rounded-xl shadow-2xl border border-border overflow-hidden animate-in zoom-in duration-150">
            <div class="bg-primary px-5 py-4 text-white flex justify-between items-center">
                <div>
                    <h2 id="modalTitulo" class="text-base font-bold">Novo Usuário</h2>
                    <p class="text-white/70 text-[10px] uppercase font-bold tracking-widest">Ficha de Cadastro</p>
                </div>
                <button class="p-1.5 hover:bg-white/10 rounded-lg transition-colors" type="button" onclick="fecharModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="p-5" enctype="multipart/form-data">
                <input type="hidden" name="acao" id="acao" value="criar">
                <input type="hidden" name="id" id="usuario_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2 flex flex-col items-center mb-2">
                        <div id="fotoPreviewContainer" class="hidden mb-2">
                            <img id="fotoPreview" src="" alt="Preview" class="foto-3x4-large shadow-lg">
                        </div>
                        <div id="fotoDefault" class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center border-2 border-dashed border-gray-300 text-gray-400">
                            <i data-lucide="camera" class="w-8 h-8"></i>
                        </div>
                        <label class="mt-2 cursor-pointer bg-gray-100 hover:bg-gray-200 text-text-secondary px-3 py-1 rounded-full text-[10px] font-bold transition-all border border-border">
                            Escolher Foto (3x4)
                            <input type="file" name="foto" id="fotoInput" class="hidden" accept="image/*" onchange="previewImagem(this)">
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label for="nome" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Nome Completo</label>
                        <input type="text" id="nome" name="nome" required class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div>
                        <label for="cpf" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">CPF</label>
                        <input type="text" id="cpf" name="cpf" maxlength="14" required class="w-full p-2 bg-background border border-border rounded-lg text-xs font-mono focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">E-mail</label>
                        <input type="email" id="email" name="email" required class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    </div>

                    <div>
                        <label for="funcao" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Função / Cargo</label>
                        <input type="text" id="funcao" name="funcao" placeholder="Ex: Analista de RH" class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div>
                        <label for="setor_id" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Setor</label>
                        <select id="setor_id" name="setor_id" class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                            <option value="">Sem vínculo</option>
                            <?php 
                            $setores->data_seek(0);
                            while ($setor = $setores->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $setor['id']; ?>"><?php echo $setor['nome']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="superior_id" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Superior Direto</label>
                        <select id="superior_id" name="superior_id" class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                            <option value="">Sem superior</option>
                            <?php 
                            $usuarios_list = $conn->query("SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome");
                            while ($u = $usuarios_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo $u['nome']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="senha" class="block text-[10px] font-black text-text-secondary mb-1 uppercase">Senha <span id="senhaOpcional" class="font-normal normal-case opacity-60 italic"></span></label>
                        <input type="password" id="senha" name="senha" class="w-full p-2 bg-background border border-border rounded-lg text-xs focus:outline-none focus:border-primary transition-all">
                    </div>
                    
                    <div class="md:col-span-2 flex items-center gap-4 mt-1 border-t border-border/50 pt-3">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="is_admin" name="is_admin" class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-bold text-text-secondary">Administrador</span>
                        </label>

                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="is_tecnico" name="is_tecnico" class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-bold text-text-secondary">Técnico de TI</span>
                        </label>

                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="is_manutencao" name="is_manutencao" class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-bold text-text-secondary">Técnico Manutenção</span>
                        </label>

                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="is_educacao" name="is_educacao" class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                            <span class="text-[11px] font-bold text-text-secondary">Gestor Educação</span>
                        </label>
                        
                        <div id="ativoGroup" style="display: none;">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" id="ativo" name="ativo" checked class="w-3.5 h-3.5 rounded border-border text-primary focus:ring-primary">
                                <span class="text-[11px] font-bold text-text-secondary">Ativo</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" class="px-4 py-1.5 text-xs font-bold text-text-secondary hover:text-text transition-colors">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-1.5 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95">Gravar Dados</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="formExcluir" method="POST" action="" style="display: none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="excluir_id">
    </form>
    
    <script>
        function aplicarFiltros() {
            const search = document.getElementById('searchInput').value;
            const setor = document.getElementById('setorFilter').value;
            const limit = document.getElementById('limitFilter').value;
            window.location.href = `usuarios.php?search=${encodeURIComponent(search)}&setor=${setor}&limit=${limit}&page=1`;
        }

        function mudarPagina(p) {
            const search = document.getElementById('searchInput').value;
            const setor = document.getElementById('setorFilter').value;
            const limit = document.getElementById('limitFilter').value;
            window.location.href = `usuarios.php?search=${encodeURIComponent(search)}&setor=${setor}&limit=${limit}&page=${p}`;
        }

        function formatarCPF(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length <= 11) {
                cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
                cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
                cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            return cpf;
        }
        
        document.getElementById('cpf').addEventListener('input', function(e) {
            e.target.value = formatarCPF(e.target.value);
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') aplicarFiltros();
        });
        
        function abrirModal(acao) {
            document.getElementById('modalTitulo').textContent = 'Novo Usuário';
            document.getElementById('acao').value = 'criar';
            document.getElementById('usuario_id').value = '';
            document.getElementById('nome').value = '';
            document.getElementById('cpf').value = '';
            document.getElementById('email').value = '';
            document.getElementById('funcao').value = '';
            document.getElementById('senha').value = '';
            document.getElementById('senha').required = true;
            document.getElementById('senhaOpcional').textContent = '';
            document.getElementById('setor_id').value = '';
            document.getElementById('is_admin').checked = false;
            document.getElementById('is_tecnico').checked = false;
            document.getElementById('is_manutencao').checked = false;
            document.getElementById('is_educacao').checked = false;
            document.getElementById('ativo').checked = true;
            document.getElementById('ativoGroup').style.display = 'none';
            document.getElementById('superior_id').value = '';
            
            // Reset foto
            document.getElementById('fotoInput').value = '';
            document.getElementById('fotoPreviewContainer').classList.add('hidden');
            document.getElementById('fotoDefault').classList.remove('hidden');
            
            document.getElementById('modalUsuario').classList.add('active');
        }
        
        function editarUsuario(usuario) {
            document.getElementById('modalTitulo').textContent = 'Editar Usuário';
            document.getElementById('acao').value = 'editar';
            document.getElementById('usuario_id').value = usuario.id;
            document.getElementById('nome').value = usuario.nome;
            document.getElementById('cpf').value = formatarCPF(usuario.cpf);
            document.getElementById('email').value = usuario.email;
            document.getElementById('funcao').value = usuario.funcao || '';
            document.getElementById('senha').value = '';
            document.getElementById('senha').required = false;
            document.getElementById('senhaOpcional').textContent = '(manter)';
            document.getElementById('setor_id').value = usuario.setor_id || '';
            document.getElementById('superior_id').value = usuario.superior_id || '';
            document.getElementById('is_admin').checked = usuario.is_admin == 1;
            document.getElementById('is_tecnico').checked = usuario.is_tecnico == 1;
            document.getElementById('is_manutencao').checked = usuario.is_manutencao == 1;
            document.getElementById('is_educacao').checked = usuario.is_educacao == 1;
            document.getElementById('ativo').checked = usuario.ativo == 1;
            document.getElementById('ativoGroup').style.display = 'block';
            
            // Mostrar foto atual se existir
            document.getElementById('fotoInput').value = '';
            if (usuario.foto) {
                document.getElementById('fotoPreview').src = '../uploads/fotos/' + usuario.foto;
                document.getElementById('fotoPreviewContainer').classList.remove('hidden');
                document.getElementById('fotoDefault').classList.add('hidden');
            } else {
                document.getElementById('fotoPreviewContainer').classList.add('hidden');
                document.getElementById('fotoDefault').classList.remove('hidden');
            }
            
            document.getElementById('modalUsuario').classList.add('active');
        }
        
        function fecharModal() {
            document.getElementById('modalUsuario').classList.remove('active');
        }
        
        function excluirUsuario(id, nome) {
            if (confirm('Deseja excluir o usuário ' + nome + '?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
        
        function previewImagem(input) {
            const preview = document.getElementById('fotoPreview');
            const container = document.getElementById('fotoPreviewContainer');
            const def = document.getElementById('fotoDefault');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                    def.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalUsuario');
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
    
    <?php include '../footer.php'; ?>
    </div> <!-- Close Main Content Wrapper -->
</body>
</html>
