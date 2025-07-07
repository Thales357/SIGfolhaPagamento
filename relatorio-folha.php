<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
// somente carrega a conexão se for necessário buscar dados do banco
$db = null;

// valores padrão para evitar avisos quando dados não forem enviados
$data = [
    'nome'             => '',
    'salario_base'     => 0,
    'horas_trabalhadas'=> 0,
    'horas_extras'     => 0,
    'valor_extras'     => 0,
    'desconto_inss'    => 0,
    'desconto_irrf'    => 0,
    'outros_descontos' => 0,
    'salario_liquido'  => 0,
    'mes'              => 0,
    'ano'              => 0
];
// id da folha para buscar dados
$id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido';
    exit;
}

// carrega conexão
if (!file_exists('conexao.php')) {
    http_response_code(500);
    echo 'Arquivo de conexão ausente';
    exit;
}
require 'conexao.php';

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

// busca valores da folha de pagamento
$stmt = $db->prepare("SELECT f.*, c.nome
                      FROM folha_pagamento f
                      JOIN colaboradores c ON c.id=f.colaborador_id
                      WHERE f.id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if(!$data){
    echo 'Registro não encontrado';
    exit;
}

$mes  = isset($data['mes']) ? str_pad((string)$data['mes'],2,'0',STR_PAD_LEFT) : '';
$ano  = $data['ano'] ?? '';
$mesAno = $mes . '/' . $ano;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório Folha de Pagamento</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:0;color:#333;}
.header{background:#f4f4f4;padding:20px;text-align:center;border-bottom:2px solid #ccc;}
.footer{background:#f4f4f4;padding:10px;text-align:center;color:#777;font-size:0.9rem;position:fixed;bottom:0;width:100%;}
.container{width:800px;margin:40px auto 80px auto;}
.info{margin-bottom:20px;}
.info strong{display:inline-block;width:140px;}
.table{width:100%;border-collapse:collapse;margin-top:20px;}
.table th,.table td{border:1px solid #ccc;padding:8px;text-align:right;}
.table th{text-align:left;background:#e9e9e9;}
.print-btn{margin-top:20px;padding:8px 16px;border:none;background:#0275d8;color:#fff;cursor:pointer;border-radius:4px;}
</style>
</head>
<body>
<div class="header"><h2>Folha de Pagamento</h2></div>
<div class="container">
  <div class="info">
    <div><strong>Colaborador:</strong> <?=htmlspecialchars($data['nome'])?></div>
    <div><strong>Referência:</strong> <?=$mesAno?></div>
  </div>
  <table class="table">
    <tr><th>Descrição</th><th>Valor</th></tr>
    <tr><td>Salário Base</td><td>R$ <?=number_format($data['salario_base'],2,',','.')?></td></tr>
    <tr><td>Horas Trabalhadas</td><td><?=number_format($data['horas_trabalhadas'],2,',','.')?></td></tr>
    <tr><td>Horas Extras</td><td><?=number_format($data['horas_extras'],2,',','.')?></td></tr>
    <tr><td>Valor Horas Extras</td><td>R$ <?=number_format($data['valor_extras'],2,',','.')?></td></tr>
    <tr><td>Desconto INSS</td><td>R$ <?=number_format($data['desconto_inss'],2,',','.')?></td></tr>
    <tr><td>Desconto IRRF</td><td>R$ <?=number_format($data['desconto_irrf'],2,',','.')?></td></tr>
    <tr><td>Outros Descontos</td><td>R$ <?=number_format($data['outros_descontos'],2,',','.')?></td></tr>
    <tr><td><strong>Salário Líquido</strong></td><td><strong>R$ <?=number_format($data['salario_liquido'],2,',','.')?></strong></td></tr>
  </table>
  <button class="print-btn" onclick="window.print()">Imprimir</button>
</div>
<div class="footer">Gerado em <?=date('d/m/Y H:i')?> </div>
</body>
</html>
