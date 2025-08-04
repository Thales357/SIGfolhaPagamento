<?php
// registro-ponto.php
// 0) indica ao navegador que não guarde nada em cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors',1);
error_reporting(E_ALL);
require 'conexao.php'; // expõe $mysqli

// 2) rota AJAX: busca logs **do dia**
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['colaborador_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $userId = intval($_GET['colaborador_id']);
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Colaborador inválido']);
        exit;
    }
    $stmt = $mysqli->prepare("
        SELECT
          DATE_FORMAT(data_registro, '%d/%m/%Y') AS data_registro,
          TIME_FORMAT(horario_entrada, '%H:%i:%s')    AS horario_entrada,
          TIME_FORMAT(horario_saida,   '%H:%i:%s')    AS horario_saida
        FROM registro_ponto
        WHERE colaborador_id = ?
          AND data_registro = CURDATE()
        ORDER BY id DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $logs = [];
    while ($r = $res->fetch_assoc()) {
        $logs[] = $r;
    }
    echo json_encode($logs);
    exit;
}

// 3) rota AJAX: registra entrada/saída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['colaborador_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $userId = intval($_POST['colaborador_id']);
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Colaborador inválido']);
        exit;
    }
    $today = date('Y-m-d');
    if ($action === 'entrada') {
        $stmt = $mysqli->prepare("
            INSERT INTO registro_ponto
              (colaborador_id, data_registro, horario_entrada)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param('is', $userId, $today);
        $stmt->execute();
        echo json_encode(['message' => 'Entrada registrada']);
        exit;
    }
    if ($action === 'saida') {
        $stmt = $mysqli->prepare("
            UPDATE registro_ponto
               SET horario_saida = NOW()
             WHERE colaborador_id = ?
               AND data_registro = ?
               AND horario_saida IS NULL
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->bind_param('is', $userId, $today);
        $stmt->execute();
        if ($stmt->affected_rows) {
            echo json_encode(['message' => 'Saída registrada']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhuma entrada pendente para saída']);
        }
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida']);
    exit;
}

// 4) página normal → renderiza HTML
header('Content-Type: text/html; charset=utf-8');

// 5) busca colaboradores para o <select>
$colabs = [];
$res = $mysqli->query("SELECT id, nome FROM colaboradores ORDER BY nome");
while ($r = $res->fetch_assoc()) {
    $colabs[] = $r;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Registro de Ponto Eletrônico | SIG</title>
  <style>
    :root {
      --primary: #fff;
      --secondary: #333;
      --accent: #d4af37;
      --bg: #f9f9f9;
      --shadow: rgba(0,0,0,0.1);
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--secondary);padding:1rem;}
    .container{max-width:600px;margin:auto;background:var(--primary);padding:1.5rem;border-radius:6px;
      box-shadow:0 2px 6px var(--shadow);}
    h1{text-align:center;margin-bottom:1rem;}
    select, input, button{width:100%;padding:.6rem;margin-bottom:1rem;border:1px solid #ccc;border-radius:4px;}
    button{background:var(--accent);color:#fff;border:none;cursor:pointer;transition:opacity .2s;}
    button:disabled{background:#aaa;cursor:not-allowed;}
    button:hover:not(:disabled){opacity:.9;}
    #clock{font-size:1.2rem;text-align:center;margin-bottom:1rem;}
    .logs{max-height:200px;overflow:auto;padding:.5rem;border:1px solid #ddd;border-radius:4px;background:#fff;}
    table.log-table{width:100%;border-collapse:collapse;margin-top:1rem;}
    table.log-table th, table.log-table td{padding:.5rem;border:1px solid #ddd;text-align:center;}
    table.log-table th{background:var(--accent);color:#fff;}
    .actions{display:flex;gap:1rem;}
    .actions button{flex:1;}
  </style>
</head>
<body>
  <div class="container">
    <h1>Registro de Ponto</h1>
    <select id="userName" name="colaborador_id">
      <option value="">— Selecione o Colaborador —</option>
      <?php foreach($colabs as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
      <?php endforeach; ?>
    </select>

    <input type="text" id="date" readonly/>
    <div id="clock"></div>

    <div class="actions">
      <button id="registerEntry" disabled>Registrar Entrada</button>
      <button id="registerExit"  disabled>Registrar Saída</button>
    </div>

    <div id="logs" class="logs">
      Selecione um colaborador para ver o ponto de hoje.
    </div>
  </div>

  <script>
  const selectUser = document.getElementById('userName'),
        logsDiv    = document.getElementById('logs'),
        btnEntry   = document.getElementById('registerEntry'),
        btnExit    = document.getElementById('registerExit'),
        clockDiv   = document.getElementById('clock'),
        dateInput  = document.getElementById('date');
  let selectedUserId = null;

  // atualiza data/hora
  function updateClock(){
    const now = new Date();
    dateInput.value = now.toLocaleDateString('pt-BR');
    clockDiv.textContent = now.toLocaleTimeString('pt-BR');
  }
  setInterval(updateClock, 1000);
  updateClock();

  // ao mudar colaborador, busca só o ponto de hoje (com cache-buster)
  selectUser.addEventListener('change', async () => {
    selectedUserId = parseInt(selectUser.value) || null;
    logsDiv.innerHTML = ''; 
    btnEntry.disabled = true; 
    btnExit.disabled  = true;

    if (!selectedUserId) {
      logsDiv.textContent = 'Selecione um colaborador para ver o ponto de hoje.';
      return;
    }

    try {
      logsDiv.textContent = 'Carregando ponto de hoje…';
      const res = await fetch(
        `registro-ponto.php?colaborador_id=${selectedUserId}&t=${Date.now()}`, 
        { cache: 'no-store' }
      );
      if (!res.ok) throw new Error(res.status);
      const data = await res.json();
      if (data.error) throw new Error(data.error);

      // monta tabela de hoje
      if (data.length === 0) {
        logsDiv.innerHTML = '<p>Nenhum registro hoje.</p>';
        btnEntry.disabled = false;
        btnExit.disabled  = true;
      } else {
        let html = `<table class="log-table">
          <thead><tr><th>Data</th><th>Entrada</th><th>Saída</th></tr></thead><tbody>`;
        for (let l of data) {
          html += `<tr>
            <td>${l.data_registro}</td>
            <td>${l.horario_entrada || '--'}</td>
            <td>${l.horario_saida   || '--'}</td>
          </tr>`;
        }
        html += `</tbody></table>`;
        logsDiv.innerHTML = html;

        // decide quais botões habilitar
        const last = data[0];
        if (last.horario_entrada && !last.horario_saida) {
          btnEntry.disabled = true;
          btnExit.disabled  = false;
        } else {
          btnEntry.disabled = true;
          btnExit.disabled  = true;
        }
      }
    } catch (e) {
      console.error(e);
      logsDiv.textContent = 'Falha ao buscar logs: ' + e.message;
    }
  });

  // registra entrada/saída
  async function registrar(tipo) {
    if (!selectedUserId) return;
    try {
      const fm = new FormData();
      fm.set('action', tipo);
      fm.set('colaborador_id', selectedUserId);
      const res = await fetch('registro-ponto.php', {
        method: 'POST',
        body: fm,
        cache: 'no-store'
      });
      if (!res.ok) throw new Error(res.status);
      const j = await res.json();
      if (j.error) throw new Error(j.error);
      alert(j.message);
      selectUser.dispatchEvent(new Event('change'));
    } catch (e) {
      alert('Erro: ' + e.message);
    }
  }

  btnEntry.addEventListener('click', () => registrar('entrada'));
  btnExit .addEventListener('click', () => registrar('saida'));
</script>

</body>
</html>
