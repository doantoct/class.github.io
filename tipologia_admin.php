<?php
function read_csv_assoc($filePath, $delimiter = ';') {
    if (!file_exists($filePath)) return [];

    $rows = [];
    $fp = fopen($filePath, 'r');
    if (!$fp) return [];

    $headers = fgetcsv($fp, 0, $delimiter);
    if (!$headers) {
        fclose($fp);
        return [];
    }

    while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = isset($data[$i]) ? $data[$i] : '';
        }
        $rows[] = $row;
    }

    fclose($fp);
    return $rows;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function norm($s) {
    return mb_strtolower(trim((string)$s), 'UTF-8');
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'tipologia.csv';
$rows = read_csv_assoc($filePath);

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$cls = isset($_GET['cls']) ? trim($_GET['cls']) : '';
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$err = isset($_GET['err']) ? trim($_GET['err']) : '';

$filtered = [];
foreach ($rows as $index => $row) {
    $joined = norm(
        ($row['CATEGORIA'] ?? '') . ' ' .
        ($row['CLASSE'] ?? '') . ' ' .
        ($row['TIPOLOGIA DOCUMENTARIA'] ?? '') . ' ' .
        ($row['CONSERVAZIONE'] ?? '')
    );

    if ($q !== '' && mb_strpos($joined, norm($q), 0, 'UTF-8') === false) continue;
    if ($cat !== '' && mb_strpos(norm($row['CATEGORIA'] ?? ''), norm($cat), 0, 'UTF-8') === false) continue;
    if ($cls !== '' && mb_strpos(norm($row['CLASSE'] ?? ''), norm($cls), 0, 'UTF-8') === false) continue;

    $filtered[] = ['index' => $index, 'row' => $row];
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['pagesize']) ? (int)$_GET['pagesize'] : 25;
if (!in_array($pageSize, [25, 50, 100, 200], true)) $pageSize = 25;

$totalRows = count($filtered);
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
if ($page > $totalPages) $page = $totalPages;

$start = ($page - 1) * $pageSize;
$visible = array_slice($filtered, $start, $pageSize);

function query_keep($extra = []) {
    $params = array_merge($_GET, $extra);
    return http_build_query($params);
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gestione Tipologia</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.8.0/dist/css/bootstrap-italia.min.css">
  <style>
    :root{
      --bg:#f3f5f8;
      --border:#e7ebf0;
      --shadow:0 10px 22px rgba(0,0,0,.08);
    }
    body{ background:var(--bg); }
    .wrap{ max-width:1450px; margin:24px auto; padding:0 12px; }
    .card-box{
      background:#fff; border:1px solid var(--border); border-radius:14px;
      box-shadow:var(--shadow); padding:18px; margin-bottom:16px;
    }
    .title{ font-size:28px; font-weight:900; color:#1f2a37; margin:0; }
    .toolbar{
      display:grid; grid-template-columns: 1.4fr 1fr 1fr 140px 140px;
      gap:10px; align-items:end;
    }
    .table-wrap{
      overflow:auto; border:1px solid #eef2f6; border-radius:12px; background:#fff;
    }
    table{ width:100%; border-collapse:collapse; min-width:1100px; }
    th, td{ border-bottom:1px solid #eef2f6; padding:8px; vertical-align:top; }
    th{
      position:sticky; top:0; background:#fafbfd; z-index:1;
      font-size:13px; font-weight:900; white-space:nowrap;
    }
    td input{ min-width:160px; }
    .top-actions{
      display:flex; gap:10px; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom:12px;
    }
    .left-actions, .right-actions{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    }
    .status-ok{ color:#166534; font-weight:800; }
    .status-err{ color:#b91c1c; font-weight:800; }
    .mini-btn{
      border-radius:999px; padding:5px 10px; font-size:12px; font-weight:800;
    }
    .row-form{ margin:0; }
    .counter{ font-size:14px; color:#374151; font-weight:800; }
    .pager{
      display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;
    }
    .new-row-grid{
      display:grid; grid-template-columns: 1fr 1fr 2fr 1.4fr 140px;
      gap:10px; align-items:end;
    }
    @media (max-width:1100px){
      .toolbar, .new-row-grid{ grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
  <div class="wrap">

    <div class="card-box">
      <div class="top-actions">
        <div class="left-actions">
          <h1 class="title">Gestione Tipologia</h1>
        </div>
        <div class="right-actions">
          <a href="index.php" class="btn btn-outline-primary">Torna alla ricerca</a>
          <a href="admin_db.php" class="btn btn-outline-primary">Gestione Fascicoli</a>
        </div>
      </div>

      <?php if ($msg !== ''): ?>
        <div class="status-ok mb-3"><?php echo h($msg); ?></div>
      <?php endif; ?>

      <?php if ($err !== ''): ?>
        <div class="status-err mb-3"><?php echo h($err); ?></div>
      <?php endif; ?>

      <form method="get" class="toolbar">
        <div>
          <label class="form-label fw-bold" for="q">Ricerca libera</label>
          <input id="q" name="q" type="text" class="form-control" value="<?php echo h($q); ?>" placeholder="Cerca in categoria, classe, tipologia, conservazione...">
        </div>

        <div>
          <label class="form-label fw-bold" for="cat">Filtro categoria</label>
          <input id="cat" name="cat" type="text" class="form-control" value="<?php echo h($cat); ?>" placeholder="Categoria">
        </div>

        <div>
          <label class="form-label fw-bold" for="cls">Filtro classe</label>
          <input id="cls" name="cls" type="text" class="form-control" value="<?php echo h($cls); ?>" placeholder="Classe">
        </div>

        <div>
          <label class="form-label fw-bold" for="pagesize">Righe</label>
          <select id="pagesize" name="pagesize" class="form-control">
            <option value="25" <?php echo $pageSize===25?'selected':''; ?>>25</option>
            <option value="50" <?php echo $pageSize===50?'selected':''; ?>>50</option>
            <option value="100" <?php echo $pageSize===100?'selected':''; ?>>100</option>
            <option value="200" <?php echo $pageSize===200?'selected':''; ?>>200</option>
          </select>
        </div>

        <div>
          <button class="btn btn-primary w-100" type="submit">Filtra</button>
          <a class="btn btn-outline-primary w-100 mt-2" href="tipologia_admin.php">Azzera</a>
        </div>
      </form>
    </div>

    <div class="card-box">
      <div class="top-actions">
        <div class="left-actions">
          <div class="counter">Risultati: <?php echo $totalRows; ?></div>
        </div>
      </div>

      <form method="post" action="save_tipologia.php" class="new-row-grid mb-4">
        <input type="hidden" name="action" value="insert">
        <input type="hidden" name="return_query" value="<?php echo h($_SERVER['QUERY_STRING'] ?? ''); ?>">

        <div>
          <label class="form-label fw-bold">CATEGORIA</label>
          <input name="CATEGORIA" class="form-control" required>
        </div>
        <div>
          <label class="form-label fw-bold">CLASSE</label>
          <input name="CLASSE" class="form-control" required>
        </div>
        <div>
          <label class="form-label fw-bold">TIPOLOGIA DOCUMENTARIA</label>
          <input name="TIPOLOGIA_DOCUMENTARIA" class="form-control" required>
        </div>
        <div>
          <label class="form-label fw-bold">CONSERVAZIONE</label>
          <input name="CONSERVAZIONE" class="form-control">
        </div>
        <div>
          <label class="form-label fw-bold">&nbsp;</label>
          <button class="btn btn-success w-100" type="submit">Inserisci</button>
        </div>
      </form>

      <div class="pager">
        <div class="counter">Pagina <?php echo $page; ?> / <?php echo $totalPages; ?></div>
        <div>
          <?php if ($page > 1): ?>
            <a class="btn btn-outline-primary" href="tipologia_admin.php?<?php echo h(query_keep(['page' => $page - 1])); ?>">Precedente</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="btn btn-outline-primary" href="tipologia_admin.php?<?php echo h(query_keep(['page' => $page + 1])); ?>">Successiva</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>CATEGORIA</th>
              <th>CLASSE</th>
              <th>TIPOLOGIA DOCUMENTARIA</th>
              <th>CONSERVAZIONE</th>
              <th>Salva</th>
              <th>Elimina</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$visible): ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Nessuna riga trovata</td>
              </tr>
            <?php endif; ?>

            <?php foreach ($visible as $i => $item): ?>
              <?php $row = $item['row']; $index = $item['index']; ?>
              <tr>
                <td><?php echo h($start + $i + 1); ?></td>

                <td>
                  <form method="post" action="save_tipologia.php" class="row-form">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="row_index" value="<?php echo h($index); ?>">
                    <input type="hidden" name="return_query" value="<?php echo h($_SERVER['QUERY_STRING'] ?? ''); ?>">
                    <input name="CATEGORIA" class="form-control" value="<?php echo h($row['CATEGORIA'] ?? ''); ?>">
                </td>
                <td><input name="CLASSE" class="form-control" value="<?php echo h($row['CLASSE'] ?? ''); ?>"></td>
                <td><input name="TIPOLOGIA_DOCUMENTARIA" class="form-control" value="<?php echo h($row['TIPOLOGIA DOCUMENTARIA'] ?? ''); ?>"></td>
                <td><input name="CONSERVAZIONE" class="form-control" value="<?php echo h($row['CONSERVAZIONE'] ?? ''); ?>"></td>
                <td>
                    <button class="btn btn-primary mini-btn" type="submit">Salva</button>
                  </form>
                </td>
                <td>
                  <form method="post" action="save_tipologia.php" class="row-form" onsubmit="return confirm('Eliminare questa riga?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="row_index" value="<?php echo h($index); ?>">
                    <input type="hidden" name="return_query" value="<?php echo h($_SERVER['QUERY_STRING'] ?? ''); ?>">
                    <button class="btn btn-outline-danger mini-btn" type="submit">Elimina</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>