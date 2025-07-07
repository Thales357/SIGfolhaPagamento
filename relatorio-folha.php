<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

$d = [
    'colaborador'       => $_POST['colaborador']       ?? '',
    'salario_base'      => floatval($_POST['salario_base'] ?? 0),
    'horas_trabalhadas' => floatval($_POST['horas_trabalhadas'] ?? 0),
    'horas_extras'      => floatval($_POST['horas_extras'] ?? 0),
    'valor_extras'      => floatval($_POST['valor_extras'] ?? 0),
    'inss'              => floatval($_POST['inss'] ?? 0),
    'irrf'              => floatval($_POST['irrf'] ?? 0),
    'outros_descontos'  => floatval($_POST['outros_descontos'] ?? 0),
    'salario_liquido'   => floatval($_POST['salario_liquido'] ?? 0),
    'mes'               => intval($_POST['mes'] ?? date('m')),
    'ano'               => intval($_POST['ano'] ?? date('Y')),
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório da Folha</title>
<style>
body{font-family:'Segoe UI',sans-serif;margin:0;padding:0;color:#333;}
.page{width:210mm;min-height:297mm;padding:20mm;margin:auto;background:#fff;position:relative;}
.header{text-align:center;border-bottom:1px solid #ccc;padding-bottom:10px;margin-bottom:20px;}
.footer{position:absolute;bottom:20mm;left:20mm;right:20mm;text-align:center;border-top:1px solid #ccc;padding-top:10px;font-size:12px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #ccc;padding:6px 8px;font-size:14px;text-align:left;}
th{background:#f0f0f0;}
</style>
</head>
<body onload="window.print()">
<div class="page">
  <div class="header">
    <h2>Folha de Pagamento</h2>
    <h3><?=htmlspecialchars($d['colaborador'])?></h3>
    <p>Referência: <?=sprintf('%02d/%04d',$d['mes'],$d['ano'])?></p>
  </div>
  <table>
    <tr><th>Descrição</th><th>Valor</th></tr>
    <tr><td>Salário Base</td><td>R$ <?=number_format($d['salario_base'],2,',','.')?></td></tr>
    <tr><td>Horas Trabalhadas</td><td><?=number_format($d['horas_trabalhadas'],2,',','.')?></td></tr>
    <tr><td>Horas Extras</td><td><?=number_format($d['horas_extras'],2,',','.')?></td></tr>
    <tr><td>Valor Horas Extras</td><td>R$ <?=number_format($d['valor_extras'],2,',','.')?></td></tr>
    <tr><td>Desconto INSS</td><td>R$ <?=number_format($d['inss'],2,',','.')?></td></tr>
    <tr><td>Desconto IRRF</td><td>R$ <?=number_format($d['irrf'],2,',','.')?></td></tr>
    <tr><td>Outros Descontos</td><td>R$ <?=number_format($d['outros_descontos'],2,',','.')?></td></tr>
    <tr><th>Salário Líquido</th><th>R$ <?=number_format($d['salario_liquido'],2,',','.')?></th></tr>
  </table>
  <div class="footer">Gerado em <?=date('d/m/Y \a\s H:i')?>.</div>
</div>
</body>
</html>
