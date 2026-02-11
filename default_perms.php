<?php
include 'config.php';
$modules = ['dashboard', 'mural', 'agenda', 'aniversariantes', 'biblioteca', 'telefones'];
$setores_res = $conn->query("SELECT id FROM setores WHERE ativo = 1");

while ($setor = $setores_res->fetch_assoc()) {
    $sid = $setor['id'];
    foreach ($modules as $slug) {
        $mod_res = $conn->query("SELECT id FROM modulos WHERE slug = '$slug'");
        if ($mod_res->num_rows > 0) {
            $mid = $mod_res->fetch_assoc()['id'];
            // Check if already exists
            $check = $conn->query("SELECT id FROM permissoes WHERE setor_id = $sid AND modulo_id = $mid");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO permissoes (setor_id, modulo_id, pode_visualizar) VALUES ($sid, $mid, 1)");
            }
        }
    }
}
unlink(__FILE__);
