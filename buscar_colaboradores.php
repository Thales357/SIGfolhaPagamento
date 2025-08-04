<?php
// buscar_colaboradores.php

require 'conexao.php';

// Compatibilidade com diferentes variáveis de conexão
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conexao) && $conexao instanceof mysqli) {
    $db = $conexao;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    http_response_code(500);
    die('Erro interno: conexão inválida.');
}

$term = $_GET['term'] ?? '';
$like = "%{$term}%";

$stmt = $db->prepare('SELECT id, nome FROM colaboradores WHERE nome LIKE ? ORDER BY nome LIMIT 10');
$stmt->bind_param('s', $like);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = [
        'id'    => $row['id'],
        'label' => $row['nome'],
        'value' => $row['nome'],
    ];
}

header('Content-Type: application/json');
echo json_encode($dados);

