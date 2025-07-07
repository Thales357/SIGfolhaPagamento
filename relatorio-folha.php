<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();

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
