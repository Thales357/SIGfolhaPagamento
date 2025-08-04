<?php
// folha-ponto.php

// evita cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'conexao.php';

// Conexão com o banco
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

// Filtros recebidos via GET
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));
$colabFilter = intval($_GET['colaborador_id'] ?? 0);

// Obtém todos colaboradores para autocomplete
$allCols = [];
$resAll = $db->query("SELECT id, nome FROM colaboradores WHERE status='Ativo' ORDER BY nome");
while ($row = $resAll->fetch_assoc()) {
    $allCols[] = ['id' => $row['id'], 'label' => $row['nome']];
}

// Seleciona quais colaboradores exibir na folha
if ($colabFilter > 0) {
    $stmt = $db->prepare("SELECT id, nome, horas_mes FROM colaboradores WHERE id=? AND status='Ativo'");
    $stmt->bind_param('i', $colabFilter);
    $stmt->execute();
    $cols = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $cols = [];
    $res = $db->query("SELECT id, nome, horas_mes FROM colaboradores WHERE status='Ativo' ORDER BY nome");
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r;
    }
}

// Processa registros de ponto
$diasUteis = count_business_days($year, $month);
foreach ($cols as &$col) {
    $stmt = $db->prepare(
        "SELECT data_registro, horario_entrada, horario_saida
         FROM registro_ponto
         WHERE colaborador_id=? AND YEAR(data_registro)=? AND MONTH(data_registro)=?
         ORDER BY data_registro, horario_entrada"
    );
    $stmt->bind_param('iii', $col['id'], $year, $month);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Agrupa por data
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['data_registro']][] = $r;
    }

    // Calcula totais e saldos
    $contractSec = intval($col['horas_mes']) * 3600;
    $jornadaSec = $diasUteis ? intdiv($contractSec, $diasUteis) : 0;
    $sumWorked = 0;
    $sumSaldo = 0;
    foreach ($grouped as $ents) {
        $daySec = 0;
        foreach ($ents as $e) {
            if (!empty($e['horario_entrada']) && !empty($e['horario_saida'])) {
                $daySec += max(0, strtotime($e['horario_saida']) - strtotime($e['horario_entrada']));
            }
        }
        $sumWorked += $daySec;
        $sumSaldo += $daySec - $jornadaSec;
    }

    $col['rows'] = $grouped;
    $col['totalSec'] = $sumWorked;
    $col['sumSaldo'] = $sumSaldo;
    $col['jornadaSec'] = $jornadaSec;
}
unset($col);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    $fname = sprintf('folha_ponto_%02d_%04d.csv', $month, $year);
    header("Content-Disposition: attachment; filename={$fname}");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Colaborador', 'Dia', 'Ent 1', 'Saí 1', 'Ent 2', 'Saí 2', 'Total', 'Jornada Esp.', 'Saldo'], ';');

    $days = (int) (new DateTime("{$year}-{$month}-01"))->format('t');
    $fmt = fn($s) => sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));

    foreach ($cols as $col) {
        for ($d = 1; $d <= $days; $d++) {
            $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $ents = $col['rows'][$key] ?? [];
            $daySec = 0;
            $e1 = $s1 = $e2 = $s2 = '--';
            for ($i = 0; $i < 2; $i++) {
                if (!empty($ents[$i]['horario_entrada']) && !empty($ents[$i]['horario_saida'])) {
                    $st = $ents[$i]['horario_entrada'];
                    $ed = $ents[$i]['horario_saida'];
                    $daySec += max(0, strtotime($ed) - strtotime($st));
                    if ($i === 0) {
                        $e1 = date('H:i', strtotime($st));
                        $s1 = date('H:i', strtotime($ed));
                    } else {
                        $e2 = date('H:i', strtotime($st));
                        $s2 = date('H:i', strtotime($ed));
                    }
                }
            }
            $saldo = $daySec - $col['jornadaSec'];
            fputcsv($out, [
                $col['nome'],
                str_pad($d, 2, '0', STR_PAD_LEFT),
                $e1,
                $s1,
                $e2,
                $s2,
                $fmt($daySec),
                $fmt($col['jornadaSec']),
                ($saldo >= 0 ? '+' : '-') . $fmt(abs($saldo))
            ], ';');
        }
    }

    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folha de Ponto Mensal | SIG</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
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
            background: var(--bg);
            color: var(--fg);
            font-family: 'Segoe UI', sans-serif;
            padding: 1rem;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .controls button.back {
            padding: .5rem 1rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .controls select,
        .controls input {
            padding: .5rem;
            border: 1px solid var(--muted);
            border-radius: 4px;
            font-size: .9rem;
        }

        .controls button.filter {
            padding: .5rem 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .print-btn {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: .5rem 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .excel-btn {
            position: fixed;
            top: 1rem;
            right: 7rem;
            padding: .5rem 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px var(--shadow);
            margin-bottom: 1.5rem;
        }

        .card h2 {
            font-size: 1.2rem;
            margin-bottom: .25rem;
        }

        .card .subhead {
            font-size: .9rem;
            color: var(--muted);
            margin-bottom: .75rem;
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

        .totals {
            text-align: right;
            font-weight: 600;
            margin-top: .5rem;
        }

        @media print {

            .controls,
            .print-btn {
                display: none !important;
            }

            .card {
                page-break-after: always;
            }

            .card:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>

<body>
    <div class="controls">
        <button class="back" onclick="location.href='dashboard.html'">← Voltar</button>
        <select id="monthSel" onchange="applyFilter()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $month ? ' selected' : '' ?>><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" id="yearInp" value="<?= $year ?>" min="2000" max="2100">
        <input type="text" id="colabLabel" placeholder="Colaborador" class="autocomplete">
        <button class="filter" onclick="applyFilter()">Filtrar</button>
    </div>
    <button class="print-btn" onclick="window.print()">Imprimir</button>
    <button class="excel-btn" onclick="exportExcel()">Exportar Excel</button>

    <div class="container">
        <?php foreach ($cols as $col): ?>
            <div class="card">
                <h2><?= htmlspecialchars($col['nome']) ?></h2>
                <div class="subhead">Folha de Ponto Mensal — Mês <?= str_pad($month, 2, '0', STR_PAD_LEFT) ?> / Ano
                    <?= $year ?></div>
                <table>
                    <thead>
                        <tr>
                            <th>Dia</th>
                            <th>Ent 1</th>
                            <th>Saí 1</th>
                            <th>Ent 2</th>
                            <th>Saí 2</th>
                            <th>Total</th>
                            <th>Jornada Esp.</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days = (int) (new DateTime("{$year}-{$month}-01"))->format('t');
                        $fmt = fn($s) => sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));
                        for ($d = 1; $d <= $days; $d++):
                            $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $ents = $col['rows'][$key] ?? [];
                            $daySec = 0;
                            echo '<tr><td>' . str_pad($d, 2, '0', STR_PAD_LEFT) . '</td>';
                            for ($i = 0; $i < 2; $i++) {
                                if (!empty($ents[$i]['horario_entrada']) && !empty($ents[$i]['horario_saida'])) {
                                    $st = strtotime($ents[$i]['horario_entrada']);
                                    $ed = strtotime($ents[$i]['horario_saida']);
                                    $daySec += max(0, $ed - $st);
                                    echo '<td>' . date('H:i', $st) . '</td><td>' . date('H:i', $ed) . '</td>';
                                } else {
                                    echo '<td>--</td><td>--</td>';
                                }
                            }
                            $saldo = $daySec - $col['jornadaSec'];
                            echo '<td>' . $fmt($daySec) . '</td><td>' . $fmt($col['jornadaSec']) . '</td><td>' . ($saldo >= 0 ? '+' : '-') . $fmt(abs($saldo)) . '</td></tr>';
                        endfor;
                        ?>
                    </tbody>
                </table>
                <div class="totals">
                    <?php
                    $totStr = $fmt($col['totalSec']);
                    $extra = max(0, $col['sumSaldo']);
                    $falt = max(0, -$col['sumSaldo']);
                    echo 'Contrato: ' . sprintf('%02d:00', $col['horas_mes']) . "h/mês — Trabalhadas: $totStr";
                    echo " — Extras: " . ($extra ? '+' : '') . $fmt($extra);
                    echo " — Faltas: " . ($falt ? '-' : '') . $fmt($falt);
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(function () {
            $('#colabLabel').autocomplete({ source: <?= json_encode($allCols) ?>, minLength: 2, select: function (e, ui) { $(this).data('id', ui.item.id); } });
        });
        function applyFilter() {
            const m = document.getElementById('monthSel').value;
            const y = document.getElementById('yearInp').value;
            const c = $('#colabLabel').data('id') || 0;
            window.location.href = `?month=${m}&year=${y}&colaborador_id=${c}`;
        }

        function exportExcel() {
            const m = document.getElementById('monthSel').value;
            const y = document.getElementById('yearInp').value;
            const c = $('#colabLabel').data('id') || 0;
            window.location.href = `?month=${m}&year=${y}&colaborador_id=${c}&export=1`;
        }
    </script>
</body>

</html>