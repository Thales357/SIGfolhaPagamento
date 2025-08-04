<?php
// registro-ponto-manual.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    die("Erro interno: conexão inválida.");
}

// Tratamento de formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $colaborador_id = intval($_POST['colaborador_id'] ?? 0);
    $data = $_POST['data'] ?? date('Y-m-d');
    $horario_entrada = $_POST['horario_entrada'] ?? '';
    $horario_saida = $_POST['horario_saida'] ?? '';
    $total = '';
    if ($horario_entrada && $horario_saida) {
        $diff = strtotime($horario_saida) - strtotime($horario_entrada);
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        $total = sprintf('%02d:%02d', $h, $m);
    }
    $atividade = trim($_POST['atividade'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');


    if ($action === 'save') {
        $stmt = $db->prepare(
            "INSERT INTO registro_ponto
             (colaborador_id, data_registro, horario_entrada, horario_saida, total, atividade, observacoes)

    if ($action === 'save') {
        $stmt = $db->prepare(
            "INSERT INTO registro_ponto
             (id, data_registro, horario_entrada, horario_saida, total, atividade, observacoes)

             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issssss',
            $colaborador_id,
            $data,
            $horario_entrada,
            $horario_saida,
            $total,
            $atividade,
            $observacoes
        );

        $stmt->execute();
        $_SESSION['flash'] = 'Apontamento cadastrado com sucesso!';
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $db->prepare(
            "UPDATE registro_ponto SET colaborador_id=?, data_registro=?, horario_entrada=?, horario_saida=?, total=?, atividade=?, observacoes=? WHERE id=?"
        );
        $stmt->bind_param(
            'issssssi',
            $colaborador_id,
=======
        $stmt->execute();
        $_SESSION['flash'] = 'Apontamento cadastrado com sucesso!';
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $db->prepare(
            "UPDATE registro_ponto SET data_registro=?, horario_entrada=?, horario_saida=?, total=?, atividade=?, observacoes=?
             WHERE id=?"
        );
        $stmt->bind_param(
            'ssssssi',

            $data,
            $horario_entrada,
            $horario_saida,
            $total,
            $atividade,
            $observacoes,
            $id
        );

        $stmt->execute();
        $_SESSION['flash'] = 'Apontamento atualizado com sucesso!';
=======
        $stmt->execute();
        $_SESSION['flash'] = 'Apontamento atualizado com sucesso!';

    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM registro_ponto WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $_SESSION['flash'] = 'Apontamento excluído com sucesso!';
    }
    // Preserva filtros de data ao redirecionar
    $qs = '';
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $qs = '?start_date=' . urlencode($_GET['start_date']) . '&end_date=' . urlencode($_GET['end_date']);
    }
    header('Location: registro-ponto-manual.php' . $qs);
    exit;
}

// Edição
$edit = null;
if (!empty($_GET['id'])) {
    $eid = intval($_GET['id']);
    $stmt = $db->prepare("SELECT rp.*, c.nome AS colaborador_nome
        FROM registro_ponto rp

        JOIN colaboradores c ON rp.colaborador_id = c.id

        JOIN colaboradores c ON rp.id = c.id

        WHERE rp.id = ?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc() ?: null;
}

// Filtros de data
$start = $_GET['start_date'] ?? '';
$end = $_GET['end_date'] ?? '';

// Listagem com join para nome do colaborador e filtro por data
$sql = "SELECT rp.*, c.nome AS colaborador_nome
        FROM registro_ponto rp

        JOIN colaboradores c ON rp.colaborador_id = c.id";

        JOIN colaboradores c ON rp.id = c.id";

$params = [];
if ($start && $end) {
    $sql .= " WHERE rp.data_registro BETWEEN ? AND ?";
    $params = [$start, $end];
}
$sql .= " ORDER BY rp.data_registro DESC, rp.horario_entrada DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param('ss', $params[0], $params[1]);
}
$stmt->execute();
$res = $stmt->get_result();
$lista = $res->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Registro de Ponto Manual | SIG</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        :root {
            --primary: #ffffff;
            --secondary: #444444;
            --accent: #d4af37;
            --background: #f0f0f0;
            --shadow: rgba(0, 0, 0, 0.05);
            --muted: #999999;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem;
            background: var(--background);
            font-family: 'Segoe UI', sans-serif;
            color: var(--secondary);
        }

        h1 {
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .flash {
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            background: #44bd32;
            color: #fff;
            border-radius: 4px;
        }

        .card {
            background: var(--primary);
            border-radius: 6px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px var(--shadow);
            margin-bottom: 2rem;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .grid-filter {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: .25rem;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: .5rem;
            border: 1px solid var(--muted);
            border-radius: 4px;
            background: #fff;
            color: var(--secondary);
        }

        textarea {
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        button {
            padding: .6rem 1.2rem;
            border: none;
            border-radius: 4px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font-weight: 500;
            transition: background .2s;
        }

        button.cancel {
            background: var(--muted);
        }

        table.dataTable {
            width: 100% !important;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px var(--shadow);
        }

        table.dataTable thead {
            background: var(--primary);
            color: var(--secondary);
        }

        table.dataTable th,
        table.dataTable td {
            padding: .6rem 1rem;
            border-bottom: 1px solid var(--background);
            white-space: nowrap;
        }

        a.edit {
            color: var(--accent);
            text-decoration: none;
        }

        button.delete {
            background: transparent;
            color: #e84118;
            padding: 0;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
        }

        button.back {
            background: #dc3545;
        }
    </style>
</head>

<body>
    <h1>Registro de Ponto Manual</h1>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= htmlspecialchars($_SESSION['flash'] ?? '') ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

        <!-- Formulário de Apontamento -->
    <div class="card">
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'save' ?>">

            <div class="grid">
                <div>
                    <label>Colaborador</label>
                    <input type="hidden" name="colaborador_id" id="colaborador_id"

                        value="<?= $edit['colaborador_id'] ?? '' ?>">

                        value="<?= $edit['id'] ?? '' ?>">

                    <input type="text" id="colaborador_label" class="autocomplete"
                        value="<?= htmlspecialchars($edit['colaborador_nome'] ?? '') ?>" required>
                </div>
                <div><label>Data</label><input type="date" name="data"
                        value="<?= htmlspecialchars($edit['data_registro'] ?? date('Y-m-d')) ?>" required></div>
                <div><label>Hora Início</label><input type="time" name="horario_entrada"
                        value="<?= htmlspecialchars($edit['horario_entrada'] ?? '') ?>" required></div>
                <div><label>Hora Fim</label><input type="time" name="horario_saida"
                        value="<?= htmlspecialchars($edit['horario_saida'] ?? '') ?>" required></div>
                <div style="grid-column:1/-1;"><label>Atividade Realizada</label><textarea name="atividade"
                        required><?= htmlspecialchars($edit['atividade'] ?? '') ?></textarea></div>
                <div style="grid-column:1/-1;"><label>Observações</label><textarea
                        name="observacoes"><?= htmlspecialchars($edit['observacoes'] ?? '') ?></textarea></div>
            </div>

            <div class="actions">
                <button type="button" class="back" onclick="location.href='dashboard.html'">⬅ Voltar</button>
                <button type="button" class="cancel"
                    onclick="location.href='registro-ponto-manual.php'">Cancelar</button>
                <button type="submit"><?= $edit ? 'Atualizar' : 'Salvar' ?></button>
            </div>
        </form>
    </div>

    <!-- Tabela de Registros -->
    <div class="card">
        <!-- Filtro de Data -->
        <div class="card">
            <form method="get">
                <div class="grid-filter">
                    <div><label>Data Início</label><input type="date" name="start_date"
                            value="<?= htmlspecialchars($start ?? '') ?>"></div>
                    <div><label>Data Fim</label><input type="date" name="end_date"
                            value="<?= htmlspecialchars($end ?? '') ?>"></div>
                    <div><button type="submit">Filtrar</button></div>
                </div>
            </form>
        </div>
        <table id="pontoTable" class="display">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Colaborador</th>
                    <th>Data</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Total</th>
                    <th>Atividade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $a): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['colaborador_nome']) ?></td>
                        <td><?= date('d/m/Y', strtotime($a['data_registro'])) ?></td>
                        <td><?= htmlspecialchars($a['horario_entrada'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['horario_saida'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['total'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['atividade'] ?? '') ?></td>
                        <td>
                            <a href="?id=<?= $a['id'] ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>"
                                class="edit">✏️</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="delete" onclick="return confirm('Excluir apontamento?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(function () {
            $('#pontoTable').DataTable({
                paging: true, searching: true, ordering: true,
                columnDefs: [{ orderable: false, targets: 7 }]
            });
            $('#colaborador_label').autocomplete({
                source: function (req, res) {
                    $.getJSON('buscar_colaboradores.php', { term: req.term }, res);
                },
                minLength: 2,
                select: function (event, ui) {
                    $('#colaborador_id').val(ui.item.id);
                    $(this).val(ui.item.label);
                    return false;
                }
            });
        });
    </script>
</body>

</html>