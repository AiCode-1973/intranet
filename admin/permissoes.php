<?php
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] == 'salvar_permissoes') {
        $setor_id = intval($_POST['setor_id']);
        
        $conn->query("DELETE FROM permissoes WHERE setor_id = $setor_id");
        
        if (isset($_POST['permissoes']) && is_array($_POST['permissoes'])) {
            foreach ($_POST['permissoes'] as $modulo_id => $perms) {
                $visualizar = isset($perms['visualizar']) ? 1 : 0;
                $criar = isset($perms['criar']) ? 1 : 0;
                $editar = isset($perms['editar']) ? 1 : 0;
                $excluir = isset($perms['excluir']) ? 1 : 0;
                
                if ($visualizar || $criar || $editar || $excluir) {
                    $stmt = $conn->prepare("
                        INSERT INTO permissoes (setor_id, modulo_id, pode_visualizar, pode_criar, pode_editar, pode_excluir) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiiiii", $setor_id, $modulo_id, $visualizar, $criar, $editar, $excluir);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        $mensagem = 'Permissões atualizadas com sucesso!';
        $tipo_mensagem = 'success';
        registrarLog($conn, 'Configurou permissões do setor ID: ' . $setor_id);
    }
}

$setor_selecionado = isset($_GET['setor_id']) ? intval($_GET['setor_id']) : 0;

$setores = $conn->query("SELECT * FROM setores WHERE ativo = 1 ORDER BY nome");
$modulos = $conn->query("SELECT * FROM modulos WHERE ativo = 1 ORDER BY ordem, nome");

$permissoes_atuais = [];
if ($setor_selecionado > 0) {
    $result = $conn->query("
        SELECT modulo_id, pode_visualizar, pode_criar, pode_editar, pode_excluir 
        FROM permissoes 
        WHERE setor_id = $setor_selecionado
    ");
    while ($perm = $result->fetch_assoc()) {
        $permissoes_atuais[$perm['modulo_id']] = $perm;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Permissões - APAS Intranet</title>
    <?php include '../tailwind_setup.php'; ?>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20">
    <?php include '../header.php'; ?>
    
    <div class="p-6 w-full max-w-6xl mx-auto flex-grow">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-primary flex items-center gap-2 tracking-tight">
                    <i data-lucide="lock" class="w-6 h-6"></i>
                    Controle de Permissões
                </h1>
                <p class="text-text-secondary text-[11px] mt-0.5 uppercase tracking-wider font-semibold">Níveis de Acesso por Setor</p>
            </div>
            
            <a href="index.php" class="px-3 py-1.5 bg-white border border-border text-text-secondary hover:text-text rounded-lg text-xs font-bold transition-all flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                Voltar
            </a>
        </div>

        <!-- Section Selection -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-border mb-6">
            <form method="GET" action="" id="formSetor">
                <label for="setor_id" class="block text-[10px] font-black text-text-secondary mb-2 uppercase tracking-widest">Selecione o Departamento</label>
                <div class="flex flex-col md:flex-row gap-3 items-center">
                    <div class="relative w-full md:max-w-xs">
                        <select id="setor_id" name="setor_id" onchange="document.getElementById('formSetor').submit()" required 
                                class="w-full pl-8 pr-4 py-2 bg-background border border-border rounded-lg text-xs font-bold text-text appearance-none focus:outline-none focus:border-primary transition-all">
                            <option value="">Escolha um setor...</option>
                            <?php while ($setor = $setores->fetch_assoc()): ?>
                                <option value="<?php echo $setor['id']; ?>" <?php echo $setor_selecionado == $setor['id'] ? 'selected' : ''; ?>>
                                    <?php echo $setor['nome']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i data-lucide="briefcase" class="absolute left-2.5 top-2.5 w-3.5 h-3.5 text-text-secondary"></i>
                        <i data-lucide="chevron-down" class="absolute right-2.5 top-2.5 w-3.5 h-3.5 text-text-secondary pointer-events-none"></i>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="p-3 rounded-lg border mb-4 flex items-center gap-2 bg-green-50 border-green-100 text-green-700">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span class="text-xs font-semibold"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($setor_selecionado > 0): ?>
        <form method="POST" action="">
            <input type="hidden" name="acao" value="salvar_permissoes">
            <input type="hidden" name="setor_id" value="<?php echo $setor_selecionado; ?>">
            
            <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background/50 border-b border-border">
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest w-1/3">Módulo de Sistema</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Ver</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Add</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Edit</th>
                            <th class="p-3 text-[10px] font-black text-text-secondary uppercase tracking-widest text-center">Del</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php 
                        $modulos->data_seek(0);
                        while ($modulo = $modulos->fetch_assoc()): 
                            $mod_id = $modulo['id'];
                            $perms = isset($permissoes_atuais[$mod_id]) ? $permissoes_atuais[$mod_id] : [
                                'pode_visualizar' => 0,
                                'pode_criar' => 0,
                                'pode_editar' => 0,
                                'pode_excluir' => 0
                            ];
                        ?>
                        <tr class="hover:bg-background/20 transition-colors group">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded bg-gray-50 flex items-center justify-center text-text-secondary group-hover:text-primary transition-colors">
                                        <i data-lucide="<?php echo $modulo['icone'] ?: 'box'; ?>" class="w-3.5 h-3.5"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-text"><?php echo $modulo['nome']; ?></p>
                                        <p class="text-[9px] text-text-secondary opacity-70"><?php echo $modulo['descricao']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <?php foreach(['visualizar', 'criar', 'editar', 'excluir'] as $perm_type): ?>
                            <td class="p-3">
                                <div class="flex justify-center">
                                    <label class="relative inline-flex items-center cursor-pointer scale-75">
                                        <input type="checkbox" 
                                               name="permissoes[<?php echo $mod_id; ?>][<?php echo $perm_type; ?>]" 
                                               <?php echo $perms['pode_'.$perm_type] ? 'checked' : ''; ?>
                                               class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="p-4 bg-background/30 border-t border-border flex justify-end">
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-8 py-2 rounded-lg text-xs font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        SALVAR PERMISSÕES
                    </button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border-2 border-dashed border-border p-12 text-center">
            <i data-lucide="mouse-pointer-click" class="w-8 h-8 text-primary opacity-20 mx-auto mb-3"></i>
            <p class="text-[11px] font-bold text-text-secondary">Selecione um departamento para gerenciar os acessos.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../footer.php'; ?>
    </div> <!-- Close Main Content Wrapper -->
</body>
</html>
