<?php
require_once 'fpdf.php';

$colaborador = $_POST['colaborador'] ?? '';


$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"Sushi House's",0,1,'C');
$pdf->SetFont('Arial','',12);

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

