<?php
// gerar_pdf.php
// Recebe dados via POST e exibe uma página simples para impressão

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$nome = $_POST['colaborador'] ?? 'Desconhecido';
$salario_base = isset($_POST['salario_base']) ? floatval($_POST['salario_base']) : 0.0;
$horas_extras = isset($_POST['horas_extras']) ? floatval($_POST['horas_extras']) : 0.0;
$inss = isset($_POST['inss']) ? floatval($_POST['inss']) : 0.0;
$irrf = isset($_POST['irrf']) ? floatval($_POST['irrf']) : 0.0;
$outros = isset($_POST['outros_descontos']) ? floatval($_POST['outros_descontos']) : 0.0;
$liquido = isset($_POST['salario_liquido']) ? floatval($_POST['salario_liquido']) : 0.0;
$mes = isset($_POST['mes']) ? intval($_POST['mes']) : date('m');
$ano = isset($_POST['ano']) ? intval($_POST['ano']) : date('Y');

$periodo = str_pad($mes, 2, '0', STR_PAD_LEFT) . "/" . $ano;
$data_hoje = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de Pagamento</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            margin: 40px;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            border: 1px solid #ccc;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
            margin-bottom: 5px;
        }
        h1 { font-size: 22px; }
        h2 { font-size: 16px; color: #555; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        th { background-color: #f5f5f5; }
        .footer {
            text-align: center;
            margin-top: 50px;
            font-size: 12px;
            color: #888;
        }
        .titulo {
            margin-top: 30px;
            font-weight: bold;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .print-button {
            text-align: center;
            margin-top: 30px;
        }
        .print-button button {
            background-color: #333;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print { .print-button { display: none; } }
    </style>
</head>
<body>
<div class="container">
    <h1>Sushi House's - Folha de Pagamento</h1>
    <h2>CNPJ: 28.458.251/0001-33</h2>
    <h2>Período de Pagamento: <?php echo htmlspecialchars($periodo); ?></h2>

    <p class="titulo">Dados do Colaborador</p>
    <table>
        <tr><th>Nome</th><td><?php echo htmlspecialchars($nome); ?></td></tr>
    </table>

    <p class="titulo">Resumo Financeiro</p>
    <table>
        <tr><th>Salário Base</th><td><?php echo number_format($salario_base, 2, ',', '.'); ?></td></tr>
        <tr><th>Horas Extras</th><td><?php echo number_format($horas_extras, 2, ',', '.'); ?></td></tr>
        <tr><th>INSS</th><td><?php echo number_format($inss, 2, ',', '.'); ?></td></tr>
        <tr><th>IRRF</th><td><?php echo number_format($irrf, 2, ',', '.'); ?></td></tr>
        <tr><th>Outros Descontos</th><td><?php echo number_format($outros, 2, ',', '.'); ?></td></tr>
        <tr><th><strong>Salário Líquido</strong></th><td><strong><?php echo number_format($liquido, 2, ',', '.'); ?></strong></td></tr>
    </table>

    <div class="footer">
        Documento gerado automaticamente em <?php echo htmlspecialchars($data_hoje); ?>.
    </div>
</div>
<div class="print-button">
    <button onclick="window.print()">🖨️ Imprimir Folha de Pagamento</button>
</div>
</body>
</html>
