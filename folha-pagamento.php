<?php
// folha-pagamento.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

session_start();
require 'conexao.php';

// Conexão
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

// Conta dias úteis no mês
function count_business_days(int $year, int $month): int
{
    $start = new DateTime("{$year}-{$month}-01");
    $end = (clone $start)->modify('last day of this month');
    $count = 0;
    while ($start <= $end) {
        if ((int) $start->format('N') < 6) {
            $count++;
        }
        $start->modify('+1 day');
    }
    return $count;
}

// Descontos simplificados
function calc_inss(float $valor): float
{
    return round($valor * 0.08, 2);
}
function calc_irrf(float $base): float
{
    return round($base * 0.075, 2);
}

// Recebe filtros
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));
$colabFilter = intval($_GET['colaborador_id'] ?? 0);

// Busca colaboradores ativos para filtro
$allCols = [];
$resAll = $db->query("SELECT id, nome FROM colaboradores WHERE status='Ativo' ORDER BY nome");
while ($r = $resAll->fetch_assoc()) {
    $allCols[] = ['id' => $r['id'], 'label' => $r['nome']];
}

// Seleção de colaboradores para geração
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

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'print') {
        $nome = $_POST['colaborador'] ?? 'Desconhecido';
        $salario_base = isset($_POST['salario_base']) ? floatval($_POST['salario_base']) : 0.0;
        $horas_extras = isset($_POST['horas_extras']) ? floatval($_POST['horas_extras']) : 0.0;
        $inss = isset($_POST['inss']) ? floatval($_POST['inss']) : 0.0;
        $irrf = isset($_POST['irrf']) ? floatval($_POST['irrf']) : 0.0;
        $outros = isset($_POST['outros_descontos']) ? floatval($_POST['outros_descontos']) : 0.0;
        $liquido = isset($_POST['salario_liquido']) ? floatval($_POST['salario_liquido']) : 0.0;
        $mes = isset($_POST['mes']) ? intval($_POST['mes']) : date('m');
        $ano = isset($_POST['ano']) ? intval($_POST['ano']) : date('Y');
        $periodo = str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' . $ano;
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
        <?php
        exit;
    } elseif ($action === 'generate') {
        // mesmo código de geração
        $diasUteis = count_business_days($year, $month);
        foreach ($cols as $col) {
            $stmt = $db->prepare(
                "SELECT IFNULL(SUM(TIME_TO_SEC(horario_saida)-TIME_TO_SEC(horario_entrada)),0) AS secs
                 FROM registro_ponto
                 WHERE colaborador_id=? AND YEAR(data_registro)=? AND MONTH(data_registro)=?"
            );
            $stmt->bind_param('iii', $col['id'], $year, $month);
            $stmt->execute();
            $tot = (int) $stmt->get_result()->fetch_assoc()['secs'];
            $horasTrab = $tot / 3600;
            $salBase = (float) $col['salario'];
            $horasContrato = (float) $col['horas_mes'];
            $valorHora = $horasContrato > 0 ? $salBase / $horasContrato : 0;
            $pagNormais = round(min($horasTrab, $horasContrato) * $valorHora, 2);
            $horasExt = max(0, $horasTrab - $horasContrato);
            $pagExtras = round($horasExt * $valorHora * 1.5, 2);
            $bruto = $pagNormais + $pagExtras;
            $desINSS = calc_inss($bruto);
            $desIRRF = calc_irrf($bruto - $desINSS);
            $outros = 0.00;
            $liq = round($bruto - ($desINSS + $desIRRF + $outros), 2);
            $db->query(
                "INSERT INTO folha_pagamento
                 (colaborador_id,mes,ano,salario_base,horas_trabalhadas,valor_hora,horas_extras,valor_extras,desconto_inss,desconto_irrf,outros_descontos,salario_liquido)
                 VALUES
                 ({$col['id']},{$month},{$year},{$salBase},{$horasTrab},{$valorHora},{$horasExt},{$pagExtras},{$desINSS},{$desIRRF},{$outros},{$liq})
                 ON DUPLICATE KEY UPDATE
                   salario_base={$salBase},horas_trabalhadas={$horasTrab},valor_hora={$valorHora},horas_extras={$horasExt},valor_extras={$pagExtras},desconto_inss={$desINSS},desconto_irrf={$desIRRF},outros_descontos={$outros},salario_liquido={$liq}"
            );
        }
        $_SESSION['flash'] = 'Folha gerada com sucesso!';
    } elseif ($action === 'clear') {
        $cond = "mes={$month} AND ano={$year}";
        if ($colabFilter > 0)
            $cond .= " AND colaborador_id={$colabFilter}";
        $db->query("DELETE FROM folha_pagamento WHERE {$cond}");
        $_SESSION['flash'] = 'Folha limpa!';
    } elseif ($action === 'save') {
        foreach ((array)($_POST['salario_base'] ?? []) as $id => $val) {
            $sb = floatval(str_replace(['.',','],['', '.'],$val));
            $ht = floatval(str_replace(['.',','],['', '.'],$_POST['horas_trabalhadas'][$id] ?? 0));
            $he = floatval(str_replace(['.',','],['', '.'],$_POST['horas_extras'][$id] ?? 0));
            $ve = floatval(str_replace(['.',','],['', '.'],$_POST['valor_extras'][$id] ?? 0));
            $inss = floatval(str_replace(['.',','],['', '.'],$_POST['desconto_inss'][$id] ?? 0));
            $irrf = floatval(str_replace(['.',','],['', '.'],$_POST['desconto_irrf'][$id] ?? 0));
            $outros = floatval(str_replace(['.',','],['', '.'],$_POST['outros_descontos'][$id] ?? 0));
            $liq = floatval(str_replace(['.',','],['', '.'],$_POST['salario_liquido'][$id] ?? 0));
            $db->query("UPDATE folha_pagamento SET salario_base={$sb},horas_trabalhadas={$ht},horas_extras={$he},valor_extras={$ve},desconto_inss={$inss},desconto_irrf={$irrf},outros_descontos={$outros},salario_liquido={$liq} WHERE id={$id}");
        }
        $_SESSION['flash'] = 'Alterações salvas!';
    }
    header("Location: folha-pagamento.php?month={$month}&year={$year}&colaborador_id={$colabFilter}");
    exit;
}

// Listagem
$lista = [];
$sql = "SELECT f.*,c.nome,c.horas_mes FROM folha_pagamento f
      JOIN colaboradores c ON c.id=f.colaborador_id
      WHERE f.mes={$month} AND f.ano={$year}";
if ($colabFilter > 0)
    $sql .= " AND f.colaborador_id={$colabFilter}";
$sql .= " ORDER BY c.nome";
$res = $db->query($sql);
while ($r = $res->fetch_assoc())
    $lista[] = $r;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Folha de Pagamento | SIG</title>
    <style>
        :root {
            --bg: #f9f9f9;
            --fg: #333;
            --accent: #d4af37;
            --card: #fff;
            --shadow: rgba(0, 0, 0, 0.1);
            --muted: #777;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--fg);
            padding: 1rem;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .controls select,
        .controls input {
            padding: .5rem;
            border: 1px solid var(--muted);
            border-radius: 4px;
            font-size: .9rem;
        }

        .controls button {
            padding: .5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .back {
            background: #dc3545;
            color: #fff;
        }

        .filter {
            background: var(--accent);
            color: #fff;
        }

        .generate {
            background: #27ae60;
            color: #fff;
        }

        .clear {
            background: #e67e22;
            color: #fff;
        }

        .flash {
            background: #44bd32;
            color: #fff;
            padding: .75rem;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .card {
            background: var(--card);
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px var(--shadow);
            margin-bottom: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: .5rem;
        }

        thead th {
            background: var(--accent);
            color: #fff;
            padding: .6rem;
            border: 1px solid #eee;
            text-align: center;
        }

        tbody td {
            padding: .6rem;
            border: 1px solid #eee;
            text-align: center;
            font-size: .85rem;
        }

        tbody tr:nth-child(even) {
            background: #fafafa;
        }
    </style>
</head>

<body>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= $_SESSION['flash'];
        unset($_SESSION['flash']); ?></div><?php endif; ?>
    <div class="controls">
        <button class="back" onclick="location.href='dashboard.html'">← Voltar</button>
        <select id="month" onchange="applyFilter()"><?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $month ? ' selected' : '' ?>><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" id="year" value="<?= $year ?>" min="2000" max="2100">
        <select id="colab_id"><?php foreach ($allCols as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $colabFilter ? ' selected' : '' ?>><?= htmlspecialchars($c['label']) ?>
                </option><?php endforeach; ?>
        </select>
        <button class="filter" onclick="applyFilter()">Filtrar</button>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="generate"><button
                type="submit" class="generate">Gerar Folha</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="clear"><button
                type="submit" class="clear">Limpar Folha</button></form>
    </div>
    <div class="card">
        <?php if (empty($lista)): ?>Nenhuma folha para este período.<?php else: ?>
            


<table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Colaborador</th>
                        <th>Sal. Base</th>
                        <th>Horas Trab.</th>
                        <th>Horas Não Trab.</th>
                        <th>Vlr Hrs Não Trab.</th>
                        <th>Horas Ext.</th>
                        <th>Vlr Ext.</th>
                        <th>Outros Desc.</th>
                        <th>Desc. Contrato</th>
                        <th>INSS</th>
                        <th>IRRF</th>
                        <th>Líquido</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $f):
                        $notWorked = max(0, $f['horas_mes'] - $f['horas_trabalhadas']);
                        $valorNot = round($notWorked * ($f['salario_base'] / $f['horas_mes']), 2);
                        $outrosDesc = isset($f['outros_descontos']) ? $f['outros_descontos'] : 0.00;
                        $descContrato = $valorNot;
                        $totalDesc = $descContrato + $f['desconto_inss'] + $f['desconto_irrf'] + $outrosDesc;
                        ?>
                        <tr>
                            <td><?= $f['id'] ?></td>
                            <td><?= htmlspecialchars($f['nome']) ?></td>
                            <td><input type="text" name="salario_base[<?= $f['id'] ?>]" value="<?= number_format($f['salario_base'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="horas_trabalhadas[<?= $f['id'] ?>]" value="<?= number_format($f['horas_trabalhadas'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="horas_nao_trabalhadas[<?= $f['id'] ?>]" value="<?= number_format($notWorked, 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="valor_horas_nao_trabalhadas[<?= $f['id'] ?>]" value="<?= number_format($valorNot, 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="horas_extras[<?= $f['id'] ?>]" value="<?= number_format($f['horas_extras'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="valor_extras[<?= $f['id'] ?>]" value="<?= number_format($f['valor_extras'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="outros_descontos[<?= $f['id'] ?>]" value="<?= number_format($outrosDesc, 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="desc_contrato[<?= $f['id'] ?>]" value="<?= number_format($descContrato, 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="desconto_inss[<?= $f['id'] ?>]" value="<?= number_format($f['desconto_inss'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="desconto_irrf[<?= $f['id'] ?>]" value="<?= number_format($f['desconto_irrf'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><input type="text" name="salario_liquido[<?= $f['id'] ?>]" value="<?= number_format($f['salario_liquido'], 2, ',', '.') ?>" style="width:6ch;"></td>
                            <td><?= $f['status'] ?></td>
                            <td>
                                <form method="post" action="folha-pagamento.php" target="_blank" style="display:inline;">
                                    <input type="hidden" name="action" value="print">
                                    <input type="hidden" name="colaborador" value="<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>">
                                    <input type="hidden" name="salario_base" value="<?= $f['salario_base'] ?>">
                                    <input type="hidden" name="horas_extras" value="<?= $f['horas_extras'] ?>">
                                    <input type="hidden" name="inss" value="<?= $f['desconto_inss'] ?>">
                                    <input type="hidden" name="irrf" value="<?= $f['desconto_irrf'] ?>">
                                    <input type="hidden" name="outros_descontos" value="<?= $outrosDesc ?>">
                                    <input type="hidden" name="salario_liquido" value="<?= $f['salario_liquido'] ?>">
                                    <input type="hidden" name="mes" value="<?= $month ?>">
                                    <input type="hidden" name="ano" value="<?= $year ?>">
                                    <button type="submit" style="padding:0 4px;">📄</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" style="margin-top:1rem;padding:.5rem 1rem;">Salvar alterações</button>
</form>
        <?php endif; ?>
    </div>
    <script>
        function applyFilter() { const m = document.getElementById('month').value, y = document.getElementById('year').value, c = document.getElementById('colab_id').value; window.location.search = `?month=${m}&year=${y}&colaborador_id=${c}`; }
    </script>
</body>

</html>
