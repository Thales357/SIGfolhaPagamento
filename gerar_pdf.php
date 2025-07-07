<?php
require_once 'fpdf.php';

$colaborador = $_POST['colaborador'] ?? '';
$salario_base = floatval(str_replace([',','.'],['','.'], $_POST['salario_base'] ?? 0));
$horas_extras = floatval(str_replace([',','.'],['','.'], $_POST['horas_extras'] ?? 0));
$inss = floatval(str_replace([',','.'],['','.'], $_POST['inss'] ?? 0));
$irrf = floatval(str_replace([',','.'],['','.'], $_POST['irrf'] ?? 0));
$outros = floatval(str_replace([',','.'],['','.'], $_POST['outros_descontos'] ?? 0));
$salario_liquido = floatval(str_replace([',','.'],['','.'], $_POST['salario_liquido'] ?? 0));

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Sushi House's",0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'CNPJ 28458251000133',0,1,'C');
$pdf->Ln(10);

$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Folha de Pagamento',0,1,'C');
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(50,8,'Colaborador:');
$pdf->Cell(0,8,$colaborador,0,1);
$pdf->Cell(50,8,'Salario Base:');
$pdf->Cell(0,8,number_format($salario_base,2,',','.'),0,1);
$pdf->Cell(50,8,'Horas Extras:');
$pdf->Cell(0,8,number_format($horas_extras,2,',','.'),0,1);
$pdf->Cell(50,8,'Desconto INSS:');
$pdf->Cell(0,8,number_format($inss,2,',','.'),0,1);
$pdf->Cell(50,8,'Desconto IRRF:');
$pdf->Cell(0,8,number_format($irrf,2,',','.'),0,1);
$pdf->Cell(50,8,'Outros Descontos:');
$pdf->Cell(0,8,number_format($outros,2,',','.'),0,1);
$pdf->Cell(50,8,'Salario Liquido:');
$pdf->Cell(0,8,number_format($salario_liquido,2,',','.'),0,1);

$pdf->Output('I','folha_pagamento.pdf');

