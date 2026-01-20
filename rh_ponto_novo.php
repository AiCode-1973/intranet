<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Carregar Dados se for Edição de Rascunho
$edit_id = intval($_GET['id'] ?? 0);
$edit_data = null;
$edit_itens = [];

if ($edit_id) {
    $stmt = $conn->prepare("SELECT * FROM rh_ponto_ocorrencias WHERE id = ? AND usuario_id = ? AND (status = 'RASCUNHO' OR status = 'REJEITADO')");
    $stmt->bind_param("ii", $edit_id, $usuario_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    
    if ($edit_data) {
        $res_itens = $conn->query("SELECT * FROM rh_ponto_itens WHERE ocorrencia_id = $edit_id ORDER BY data_ponto ASC");
        while($row = $res_itens->fetch_assoc()) $edit_itens[] = $row;
    } else {
        header("Location: rh_ponto_novo.php");
        exit();
    }
}

// Processar Envio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao']; // 'rascunho' ou 'enviar'
    $status = ($acao == 'rascunho') ? 'RASCUNHO' : 'PENDENTE';
    
    $supervisor_id = intval($_POST['supervisor_id']);
    $tipo = $_POST['tipo'];
    $mes = intval($_POST['mes']);
    $ano = intval($_POST['ano']);
    $id_atual = intval($_POST['id_atual'] ?? 0);
    
    $conn->begin_transaction();
    try {
        if ($id_atual > 0) {
            // Atualizar Ocorrência Existente
            $stmt = $conn->prepare("UPDATE rh_ponto_ocorrencias SET supervisor_id = ?, tipo = ?, mes = ?, ano = ?, status = ? WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("issisii", $supervisor_id, $tipo, $mes, $ano, $status, $id_atual, $usuario_id);
            $stmt->execute();
            $ocorrencia_id = $id_atual;
            
            // Limpar itens antigos para reinserir
            $conn->query("DELETE FROM rh_ponto_itens WHERE ocorrencia_id = $ocorrencia_id");
        } else {
            // Inserir Nova
            $stmt = $conn->prepare("INSERT INTO rh_ponto_ocorrencias (usuario_id, supervisor_id, tipo, mes, ano, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissis", $usuario_id, $supervisor_id, $tipo, $mes, $ano, $status);
            $stmt->execute();
            $ocorrencia_id = $conn->insert_id;
        }

        $stmt_item = $conn->prepare("INSERT INTO rh_ponto_itens (ocorrencia_id, data_ponto, entrada, saida_almoco, volta_almoco, saida, descricao) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($_POST['data_ponto'] as $index => $data) {
            if (empty($data)) continue;
            
            $entrada = $_POST['entrada'][$index] ?? '';
            $saida_almoco = $_POST['saida_almoco'][$index] ?? '';
            $volta_almoco = $_POST['volta_almoco'][$index] ?? '';
            $saida = $_POST['saida'][$index] ?? '';
            $descricao = $_POST['descricao'][$index] ?? '';
            
            $stmt_item->bind_param("issssss", $ocorrencia_id, $data, $entrada, $saida_almoco, $volta_almoco, $saida, $descricao);
            $stmt_item->execute();
        }
        
        $conn->commit();
        $msg_final = ($status == 'RASCUNHO') ? "Rascunho salvo com sucesso!" : "Enviado para validação!";
        header("Location: rh_ponto.php?msg=" . urlencode($msg_final));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem = "Erro ao processar: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Buscar Superior Direto do Usuário
$meu_superior_id = $conn->query("SELECT superior_id FROM usuarios WHERE id = $usuario_id")->fetch_assoc()['superior_id'] ?? 0;

// Buscar Supervisores (Administradores, o superior direto do usuário ou qualquer um que seja superior de alguém)
$supervisores = $conn->query("
    SELECT DISTINCT id, nome 
    FROM usuarios 
    WHERE (is_admin = 1 OR id = $meu_superior_id OR id IN (SELECT DISTINCT superior_id FROM usuarios WHERE superior_id IS NOT NULL)) 
    AND ativo = 1 
    ORDER BY nome
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
    <title>Nova Ocorrência de Ponto - APAS Intranet</title>
    <?php include 'tailwind_setup.php'; ?>
    <style>
        .input-slim { @apply w-full p-2 bg-background border border-border rounded-lg text-xs focus:ring-1 focus:ring-primary outline-none transition-all; }
        .label-slim { @apply block text-[10px] font-black uppercase text-text-secondary mb-1 tracking-wider; }
    </style>
</head>
<body class="bg-background text-text font-sans selection:bg-primary/20 bg-pattern">
    <?php include 'header.php'; ?>
    
    <div class="p-6 w-full max-w-7xl mx-auto min-h-screen">
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-black text-primary tracking-tight">Ocorrência de Ponto</h1>
                <p class="text-text-secondary text-xs font-medium">Preencha os dados abaixo para validação do supervisor e RH.</p>
            </div>
            <a href="rh.php" class="px-4 py-2 bg-white border border-border text-text-secondary hover:text-text rounded-xl text-xs font-bold transition-all shadow-sm">Cancelar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-4 rounded-2xl border mb-6 text-sm font-bold flex items-center gap-2 <?php echo $tipo_mensagem == 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="id_atual" value="<?php echo $edit_id; ?>">
            
            <div class="bg-white p-4 md:p-8 rounded-3xl border border-border shadow-xl shadow-gray-200/50">
                <!-- Cabeçalho do Formulário -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 pb-8 border-b border-dashed border-border">
                    <div>
                        <label class="label-slim">Supervisor / Gerente</label>
                        <select name="supervisor_id" required class="input-slim">
                            <option value="">Selecione...</option>
                            <?php while($s = $supervisores->fetch_assoc()): ?>
                                <option value="<?php echo $s['id']; ?>" <?php 
                                    $selected_sup = $edit_data ? $edit_data['supervisor_id'] : $meu_superior_id;
                                    echo ($s['id'] == $selected_sup) ? 'selected' : ''; 
                                ?>>
                                    <?php echo $s['nome']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="label-slim">Mês de Referência</label>
                        <select name="mes" required class="input-slim">
                            <?php foreach($meses as $num => $nome): ?>
                                <option value="<?php echo $num; ?>" <?php 
                                    $selected_mes = $edit_data ? $edit_data['mes'] : date('n');
                                    echo $num == $selected_mes ? 'selected' : ''; 
                                ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="label-slim">Ano</label>
                        <select name="ano" required class="input-slim">
                            <?php $current_ano = $edit_data ? $edit_data['ano'] : date('Y'); ?>
                            <option value="2025" <?php echo $current_ano == '2025' ? 'selected' : ''; ?>>2025</option>
                            <option value="2026" <?php echo $current_ano == '2026' ? 'selected' : ''; ?>>2026</option>
                        </select>
                    </div>
                    <div>
                        <label class="label-slim">Tipo de Ocorrência</label>
                        <div class="flex gap-4 mt-2">
                            <?php $tipo_sel = $edit_data ? $edit_data['tipo'] : 'BANCO'; ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="tipo" value="BANCO" <?php echo $tipo_sel == 'BANCO' ? 'checked' : ''; ?> class="w-4 h-4 text-primary focus:ring-primary">
                                <span class="text-[10px] font-black uppercase text-text-secondary">Banco de Horas</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="tipo" value="EXTRA" <?php echo $tipo_sel == 'EXTRA' ? 'checked' : ''; ?> class="w-4 h-4 text-primary focus:ring-primary">
                                <span class="text-[10px] font-black uppercase text-text-secondary">Horas Extras</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Ocorrências -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left" id="pontoTable">
                        <thead>
                            <tr class="text-[10px] font-black text-text-secondary uppercase tracking-widest">
                                <th class="p-2 w-32 text-center">Data</th>
                                <th class="p-2 w-24 text-center">Entrada</th>
                                <th class="p-2 w-24 text-center">S. Almoço</th>
                                <th class="p-2 w-24 text-center">V. Almoço</th>
                                <th class="p-2 w-24 text-center">Saída</th>
                                <th class="p-2">Descrição / Justificativa</th>
                                <th class="p-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="pontoBody">
                            <?php if (empty($edit_itens)): ?>
                                <tr class="ponto-row transition-all hover:bg-gray-50/50">
                                    <td class="p-1"><input type="date" name="data_ponto[]" required class="input-slim text-center"></td>
                                    <td class="p-1"><input type="text" name="entrada[]" placeholder="00:00" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="saida_almoco[]" placeholder="00:00" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="volta_almoco[]" placeholder="00:00" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="saida[]" placeholder="00:00" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="descricao[]" required class="input-slim"></td>
                                    <td class="p-1 text-right">
                                        <button type="button" onclick="removeRow(this)" class="p-1.5 text-red-500 hover:bg-red-50 rounded hidden delete-btn"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($edit_itens as $index => $i): ?>
                                <tr class="ponto-row transition-all hover:bg-gray-50/50">
                                    <td class="p-1"><input type="date" name="data_ponto[]" value="<?php echo $i['data_ponto']; ?>" required class="input-slim text-center"></td>
                                    <td class="p-1"><input type="text" name="entrada[]" placeholder="00:00" value="<?php echo $i['entrada']; ?>" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="saida_almoco[]" placeholder="00:00" value="<?php echo $i['saida_almoco']; ?>" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="volta_almoco[]" placeholder="00:00" value="<?php echo $i['volta_almoco']; ?>" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="saida[]" placeholder="00:00" value="<?php echo $i['saida']; ?>" class="input-slim text-center mask-time px-1"></td>
                                    <td class="p-1"><input type="text" name="descricao[]" value="<?php echo $i['descricao']; ?>" required class="input-slim"></td>
                                    <td class="p-1 text-right">
                                        <button type="button" onclick="removeRow(this)" class="p-1.5 text-red-500 hover:bg-red-50 rounded <?php echo $index == 0 ? 'hidden' : ''; ?> delete-btn"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <button type="button" onclick="addRow()" class="px-4 py-2 bg-gray-50 text-text-secondary hover:bg-gray-100 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 transition-all">
                        <i data-lucide="plus" class="w-4 h-4"></i> Adicionar Outra Data
                    </button>
                    <p class="text-[9px] text-text-secondary font-bold italic">Este documento tem validade para ocorrências do dia 01 até o dia 30 do mês.</p>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-end gap-3 pt-4">
                <button type="reset" class="px-6 py-2 text-xs font-bold text-text-secondary hidden md:block">Limpar Formulário</button>
                <div class="flex gap-2">
                    <button type="submit" name="acao" value="rascunho" class="px-6 py-3 bg-white border border-border text-text-secondary rounded-2xl text-xs font-black uppercase tracking-widest hover:border-primary transition-all">
                        Salvar Rascunho
                    </button>
                    <button type="submit" name="acao" value="enviar" class="px-8 py-3 bg-primary text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl shadow-primary/30 hover:scale-[1.02] active:scale-95 transition-all">
                        Enviar para Validação
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function addRow() {
            const body = document.getElementById('pontoBody');
            const newRow = body.children[0].cloneNode(true);
            
            // Limpar valores
            newRow.querySelectorAll('input').forEach(input => {
                input.value = '';
            });
            
            // Mostrar botão delete
            newRow.querySelector('.delete-btn').classList.remove('hidden');
            
            body.appendChild(newRow);
            lucide.createIcons();
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
        }

        // Máscara simples para horários
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('mask-time')) {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 4) v = v.substring(0, 4);
                if (v.length > 2) v = v.substring(0, 2) + ':' + v.substring(2);
                e.target.value = v;
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
