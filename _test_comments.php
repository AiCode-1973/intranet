<?php
require 'C:/xampp1/htdocs/intranet/config.php';
$id = 1;
$res = $conn->query("SELECT mc.*, u.nome as autor FROM manutencao_comentarios mc LEFT JOIN usuarios u ON mc.usuario_id = u.id WHERE mc.manutencao_id = $id ORDER BY mc.data_comentario ASC");
$comentarios = [];
while ($r = $res->fetch_assoc()) $comentarios[] = $r;
echo json_encode(['comentarios' => $comentarios], JSON_PRETTY_PRINT);
