<?php
// folha-pagamento.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

session_start();
require 'conexao.php';

// valida conexão
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

// conta dias úteis do mês
function count_business_days($year, $month) {
    $start = new DateTime("$year-$month-01");
    $end   = (clone $start)->modify('last day of this month');
    $count = 0;
    while ($start <= $end) {
        if ((int)$start->format('N') < 6) {
            $count++;
        }
        $start->modify('+1 day');
    }
    return $count;
}

// cálculos simples
function calc_inss($valor) { return round($valor * 0.08, 2); }
function calc_irrf($base)  { return round(($base) * 0.075, 2); }

function pdf_escape($str){
    return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $str);
}

function simple_pdf($title, $lines, $footer){
    $y = 760;
    $txt = "BT\n";
    $txt .= "/F1 16 Tf 50 $y Td (".pdf_escape($title).") Tj\n";
    $y -= 30;
    foreach($lines as $line){
        $txt .= "/F1 12 Tf 50 $y Td (".pdf_escape($line).") Tj\n";
        $y -= 15;
    }
    $y -= 10;
    $txt .= "/F1 10 Tf 50 $y Td (".pdf_escape($footer).") Tj\nET";
    $len = strlen($txt);

    $obj1 = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
    $obj2 = "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj";
    $obj3 = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj";
    $obj4 = "4 0 obj << /Length $len >> stream\n$txt\nendstream endobj";
    $obj5 = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";

    $objects = [$obj1,$obj2,$obj3,$obj4,$obj5];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach($objects as $obj){
        $offsets[] = strlen($pdf);
        $pdf .= $obj."\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach($offsets as $o){
        $pdf .= str_pad($o,10,'0',STR_PAD_LEFT)." 00000 n \n";
    }
    $pdf .= "trailer << /Root 1 0 R /Size ".(count($objects)+1)." >>\nstartxref\n$xrefPos\n%%EOF";
    return $pdf;
}

function generate_payslip_pdf(array $d){
    $title = 'Folha de Pagamento';
    $lines = [
        'Colaborador: '.$d['colaborador'],
        'Referência: '.str_pad($d['mes'],2,'0',STR_PAD_LEFT).'/'.$d['ano'],
        'Salário Base: R$ '.number_format($d['salario_base'],2,',','.'),
        'Horas Trabalhadas: '.number_format($d['horas_trabalhadas'],2,',','.'),
        'Horas Extras: '.number_format($d['horas_extras'],2,',','.'),
        'Valor Extras: R$ '.number_format($d['valor_extras'],2,',','.'),
        'INSS: R$ '.number_format($d['inss'],2,',','.'),
        'IRRF: R$ '.number_format($d['irrf'],2,',','.'),
        'Outros Descontos: R$ '.number_format($d['outros_descontos'],2,',','.'),
        'Salário Líquido: R$ '.number_format($d['salario_liquido'],2,',','.')
    ];
    $footer = 'Gerado em '.date('d/m/Y H:i');
    $pdf = simple_pdf($title,$lines,$footer);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=folha_pagamento.pdf');
    echo $pdf;
}

// filtros vindo de GET
$month       = intval($_GET['month'] ?? date('m'));
$year        = intval($_GET['year']  ?? date('Y'));
$colabFilter = intval($_GET['colaborador_id'] ?? 0);

// lista para dropdown de colaboradores
$allCols = [];
$resAll = $db->query("SELECT id, nome FROM colaboradores WHERE status='Ativo' ORDER BY nome");
while ($r = $resAll->fetch_assoc()) {
    $allCols[] = $r;
}

// carrega colaboradores para gerar folha
$cols = [];
if ($colabFilter > 0) {
    $stmt = $db->prepare("SELECT id, nome, horas_mes, salario FROM colaboradores WHERE id=? AND status='Ativo'");
    $stmt->bind_param('i', $colabFilter);
    $stmt->execute();
    $cols = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $res = $db->query("SELECT id, nome, horas_mes, salario FROM colaboradores WHERE status='Ativo' ORDER BY nome");
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r;
    }
}

// ações de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        // salva apenas colunas existentes na tabela
        foreach ((array)($_POST['salario_base'] ?? []) as $id => $val) {
            $sb    = floatval(str_replace(['.', ','], ['', '.'], $val));
            $ht    = floatval(str_replace(['.', ','], ['', '.'], $_POST['horas_trabalhadas'][$id] ?? 0));
            $he    = floatval(str_replace(['.', ','], ['', '.'], $_POST['horas_extras'][$id] ?? 0));
            $ve    = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_extras'][$id] ?? 0));
            $inss  = floatval(str_replace(['.', ','], ['', '.'], $_POST['desconto_inss'][$id] ?? 0));
            $irrf  = floatval(str_replace(['.', ','], ['', '.'], $_POST['desconto_irrf'][$id] ?? 0));
            $outros= floatval(str_replace(['.', ','], ['', '.'], $_POST['outros_descontos'][$id] ?? 0));
            $liq   = floatval(str_replace(['.', ','], ['', '.'], $_POST['salario_liquido'][$id] ?? 0));

            $db->query("
                UPDATE folha_pagamento SET
                  salario_base      = {$sb},
                  horas_trabalhadas = {$ht},
                  horas_extras      = {$he},
                  valor_extras      = {$ve},
                  desconto_inss     = {$inss},
                  desconto_irrf     = {$irrf},
                  outros_descontos  = {$outros},
                  salario_liquido   = {$liq}
                WHERE id = {$id}
            ");
        }
        $_SESSION['flash'] = 'Alterações salvas com sucesso!';
    }
    elseif ($action === 'generate') {
        // gera folhas no banco
        foreach ($cols as $col) {
            $stmt = $db->prepare("
                SELECT IFNULL(SUM(TIME_TO_SEC(horario_saida)-TIME_TO_SEC(horario_entrada)),0) AS secs
                FROM registro_ponto
                WHERE colaborador_id=? AND YEAR(data_registro)=? AND MONTH(data_registro)=?
            ");
            $stmt->bind_param('iii', $col['id'], $year, $month);
            $stmt->execute();
            $tot = (int)$stmt->get_result()->fetch_assoc()['secs'];
            $horasTrab    = $tot/3600;
            $salBase      = (float)$col['salario'];
            $horasContrato= (float)$col['horas_mes'];
            $valorHora    = $horasContrato>0 ? $salBase/$horasContrato : 0;
            $pagNormais   = round(min($horasTrab,$horasContrato)*$valorHora,2);
            $horasExt     = max(0,$horasTrab-$horasContrato);
            $pagExtras    = round($horasExt*$valorHora*1.5,2);
            $bruto        = $pagNormais + $pagExtras;
            $desINSS      = calc_inss($bruto);
            $desIRRF      = calc_irrf($bruto-$desINSS);
            $outros       = 0.00;
            $liq          = round($bruto-($desINSS+$desIRRF+$outros),2);

            $db->query("
                INSERT INTO folha_pagamento
                  (colaborador_id,mes,ano,salario_base,horas_trabalhadas,valor_hora,
                   horas_extras,valor_extras,desconto_inss,desconto_irrf,outros_descontos,salario_liquido)
                VALUES
                  ({$col['id']},{$month},{$year},{$salBase},{$horasTrab},{$valorHora},
                   {$horasExt},{$pagExtras},{$desINSS},{$desIRRF},{$outros},{$liq})
                ON DUPLICATE KEY UPDATE
                  salario_base      = {$salBase},
                  horas_trabalhadas = {$horasTrab},
                  valor_hora        = {$valorHora},
                  horas_extras      = {$horasExt},
                  valor_extras      = {$pagExtras},
                  desconto_inss     = {$desINSS},
                  desconto_irrf     = {$desIRRF},
                  outros_descontos  = {$outros},
                  salario_liquido   = {$liq}
            ");
        }
        $_SESSION['flash'] = 'Folhas geradas com sucesso!';
    }
    elseif ($action === 'print') {
        $data = [
            'colaborador'       => $_POST['colaborador'] ?? '',
            'salario_base'      => floatval($_POST['salario_base'] ?? 0),
            'horas_trabalhadas' => floatval($_POST['horas_trabalhadas'] ?? 0),
            'horas_extras'      => floatval($_POST['horas_extras'] ?? 0),
            'valor_extras'      => floatval($_POST['valor_extras'] ?? 0),
            'inss'              => floatval($_POST['inss'] ?? 0),
            'irrf'              => floatval($_POST['irrf'] ?? 0),
            'outros_descontos'  => floatval($_POST['outros_descontos'] ?? 0),
            'salario_liquido'   => floatval($_POST['salario_liquido'] ?? 0),
            'mes'               => intval($_POST['mes'] ?? $month),
            'ano'               => intval($_POST['ano'] ?? $year)
        ];
        generate_payslip_pdf($data);
        exit;
    }
    header("Location: folha-pagamento.php?month={$month}&year={$year}&colaborador_id={$colabFilter}");
    exit;
}

// carrega lista para exibição
$lista = [];
$sql = "
    SELECT f.*, c.nome, c.horas_mes
    FROM folha_pagamento f
    JOIN colaboradores c ON c.id = f.colaborador_id
    WHERE f.mes={$month} AND f.ano={$year}
";
if ($colabFilter>0) {
    $sql .= " AND f.colaborador_id={$colabFilter}";
}
$sql .= " ORDER BY c.nome";
$res = $db->query($sql);
while ($r = $res->fetch_assoc()) {
    $lista[] = $r;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Folha de Pagamento | SIG</title>
  <style>
    :root {
      --bg:#f9f9f9;--fg:#333;--accent:#d4af37;
      --card:#fff;--shadow:rgba(0,0,0,0.1);--muted:#777;
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body {
      font-family:'Segoe UI',sans-serif;
      background:var(--bg);color:var(--fg);
      padding:1rem;
    }
    .controls {
      display:flex;flex-wrap:wrap;gap:.5rem;
      justify-content:center;margin-bottom:1rem;
    }
    .controls select,
    .controls input {
      padding:.5rem;border:1px solid var(--muted);
      border-radius:4px;font-size:.9rem;
    }
    .controls button {
      padding:.5rem 1rem;border:none;border-radius:4px;
      cursor:pointer;
    }
    .back{background:#dc3545;color:#fff;}
    .generate{background:#27ae60;color:#fff;}
    .clear{background:#e67e22;color:#fff;}
    .flash{
      background:#44bd32;color:#fff;padding:.75rem;
      border-radius:4px;text-align:center;
      margin-bottom:1rem;
    }
    .card{
      background:var(--card);padding:1rem;
      border-radius:6px;box-shadow:0 2px 4px var(--shadow);
      margin-bottom:1rem;
    }
    table{width:100%;border-collapse:collapse;margin-top:.5rem;}
    thead th{
      background:var(--accent);color:#fff;
      padding:.6rem;border:1px solid #eee;
      text-align:center;font-size:.9rem;
    }
    tbody td{
      padding:.6rem;border:1px solid #eee;
      text-align:center;font-size:.85rem;
    }
    tbody tr:nth-child(even){background:#fafafa;}
    input[type=text]{width:6ch;text-align:right;}
    button.save{
      margin-top:1rem;padding:.5rem 1rem;
      background:var(--accent);color:#fff;
      border:none;cursor:pointer;
    }
    .print-form{display:none;}
    .print-btn{
      background:none;border:none;
      cursor:pointer;font-size:1.1rem;
    }
  </style>
</head>
<body>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="flash"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="controls">
  <button class="back" onclick="location.href='dashboard.html'">← Voltar</button>
  <select id="month" onchange="applyFilter()">
    <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=str_pad($m,2,'0',STR_PAD_LEFT)?></option>
    <?php endfor;?>
  </select>
  <input type="number" id="year" min="2000" max="2100" value="<?=$year?>" onchange="applyFilter()">
  <select id="colab_id" onchange="applyFilter()">
    <option value="0">Todos</option>
    <?php foreach($allCols as $c): ?>
      <option value="<?=$c['id']?>" <?=$c['id']==$colabFilter?'selected':''?>><?=htmlspecialchars($c['nome'])?></option>
    <?php endforeach;?>
  </select>
  <form method="post" style="display:inline"><input type="hidden" name="action" value="generate"><button type="submit" class="generate">Gerar Folha</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="action" value="clear"><button type="submit" class="clear">Limpar Folha</button></form>
</div>

<div class="card">
  <?php if(empty($lista)): ?>
    Nenhuma folha para este período.
  <?php else: ?>
    <form id="saveForm" method="post" action="folha-pagamento.php?month=<?=$month?>&year=<?=$year?>&colaborador_id=<?=$colabFilter?>">
      <input type="hidden" name="action" value="save">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Colaborador</th><th>Sal. Base</th><th>Horas Trab.</th>
            <th>Horas Ext.</th><th>Vlr Ext.</th><th>INSS</th><th>IRRF</th>
            <th>Outros Desc.</th><th>Líquido</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($lista as $f): ?>
            <tr>
              <td><?=$f['id']?></td>
              <td><?=htmlspecialchars($f['nome'])?></td>
              <td><input type="text" name="salario_base[<?=$f['id']?>]"      value="<?=number_format($f['salario_base'],2,',','.')?>"></td>
              <td><input type="text" name="horas_trabalhadas[<?=$f['id']?>]" value="<?=number_format($f['horas_trabalhadas'],2,',','.')?>"></td>
              <td><input type="text" name="horas_extras[<?=$f['id']?>]"      value="<?=number_format($f['horas_extras'],2,',','.')?>"></td>
              <td><input type="text" name="valor_extras[<?=$f['id']?>]"      value="<?=number_format($f['valor_extras'],2,',','.')?>"></td>
              <td><input type="text" name="desconto_inss[<?=$f['id']?>]"    value="<?=number_format($f['desconto_inss'],2,',','.')?>"></td>
              <td><input type="text" name="desconto_irrf[<?=$f['id']?>]"   value="<?=number_format($f['desconto_irrf'],2,',','.')?>"></td>
              <td><input type="text" name="outros_descontos[<?=$f['id']?>]"  value="<?=number_format($f['outros_descontos']??0,2,',','.')?>"></td>
              <td><input type="text" name="salario_liquido[<?=$f['id']?>]"   value="<?=number_format($f['salario_liquido'],2,',','.')?>"></td>
              <td><button type="button" class="print-btn" onclick="updateAndPrint(<?=$f['id']?>)">📄</button></td>
            </tr>
          <?php endforeach;?>
        </tbody>
      </table>
      <button type="submit" class="save">Salvar alterações</button>
    </form>

    <?php foreach($lista as $f): ?>
      <form id="print<?=$f['id']?>" class="print-form" method="post" action="folha-pagamento.php" target="_blank">
        <input type="hidden" name="action"             value="print">
        <input type="hidden" name="colaborador"        value="<?=htmlspecialchars($f['nome'],ENT_QUOTES)?>">
        <input type="hidden" name="salario_base"       value="<?=$f['salario_base']?>">
        <input type="hidden" name="horas_trabalhadas"  value="<?=$f['horas_trabalhadas']?>">
        <input type="hidden" name="horas_extras"       value="<?=$f['horas_extras']?>">
        <input type="hidden" name="valor_extras"       value="<?=$f['valor_extras']?>">
        <input type="hidden" name="inss"               value="<?=$f['desconto_inss']?>">
        <input type="hidden" name="irrf"               value="<?=$f['desconto_irrf']?>">
        <input type="hidden" name="outros_descontos"   value="<?=$f['outros_descontos']??0?>">
        <input type="hidden" name="salario_liquido"    value="<?=$f['salario_liquido']?>">
        <input type="hidden" name="mes"                value="<?=$month?>">
        <input type="hidden" name="ano"                value="<?=$year?>">
      </form>
    <?php endforeach;?>
  <?php endif;?>
</div>

<script>
function applyFilter() {
  const m = document.getElementById('month').value,
        y = document.getElementById('year').value,
        c = document.getElementById('colab_id').value;
  window.location.search = `?month=${m}&year=${y}&colaborador_id=${c}`;
}
function updateAndPrint(id) {
  const form = document.getElementById('print' + id);
  form.querySelector('[name="salario_base"]').value       = document.querySelector(`[name="salario_base[${id}]"]`).value;
  form.querySelector('[name="horas_trabalhadas"]').value  = document.querySelector(`[name="horas_trabalhadas[${id}]"]`).value;
  form.querySelector('[name="horas_extras"]').value       = document.querySelector(`[name="horas_extras[${id}]"]`).value;
  form.querySelector('[name="valor_extras"]').value       = document.querySelector(`[name="valor_extras[${id}]"]`).value;
  form.querySelector('[name="inss"]').value               = document.querySelector(`[name="desconto_inss[${id}]"]`).value;
  form.querySelector('[name="irrf"]').value               = document.querySelector(`[name="desconto_irrf[${id}]"]`).value;
  form.querySelector('[name="outros_descontos"]').value   = document.querySelector(`[name="outros_descontos[${id}]"]`).value;
  form.querySelector('[name="salario_liquido"]').value    = document.querySelector(`[name="salario_liquido[${id}]"]`).value;
  form.submit();
}
</script>
</body>
</html>
