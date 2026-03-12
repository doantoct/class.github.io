<?php
function redirect_back($ok, $message, $returnQuery = '') {
    $params = [];
    parse_str((string)$returnQuery, $params);

    if ($ok) {
        $params['msg'] = $message;
        unset($params['err']);
    } else {
        $params['err'] = $message;
        unset($params['msg']);
    }

    $url = 'tipologia_admin.php';
    $qs = http_build_query($params);
    if ($qs !== '') $url .= '?' . $qs;

    header('Location: ' . $url);
    exit;
}

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
    return [$headers, $rows];
}

function write_csv_assoc($filePath, $headers, $rows, $delimiter = ';') {
    $backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backup_tipologia';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }

    if (file_exists($filePath)) {
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'tipologia_' . date('Ymd_His') . '.csv';
        @copy($filePath, $backupPath);
    }

    $tmpPath = __DIR__ . DIRECTORY_SEPARATOR . 'tipologia_tmp_' . uniqid('', true) . '.csv';

    $fp = fopen($tmpPath, 'w');
    if (!$fp) return false;

    fputcsv($fp, $headers, $delimiter);

    foreach ($rows as $row) {
      $line = [];
      foreach ($headers as $h) {
          $line[] = isset($row[$h]) ? $row[$h] : '';
      }
      fputcsv($fp, $line, $delimiter);
    }

    fclose($fp);

    if (!@rename($tmpPath, $filePath)) {
        @unlink($tmpPath);
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back(false, 'Metodo non consentito');
}

$returnQuery = isset($_POST['return_query']) ? (string)$_POST['return_query'] : '';
$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'tipologia.csv';

$result = read_csv_assoc($filePath, ';');
if (!$result) {
    redirect_back(false, 'Impossibile leggere tipologia.csv', $returnQuery);
}

[$headers, $rows] = $result;

$requiredHeaders = ['CATEGORIA','CLASSE','TIPOLOGIA DOCUMENTARIA','CONSERVAZIONE'];
if ($headers !== $requiredHeaders) {
    $headers = $requiredHeaders;
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

$newRow = [
    'CATEGORIA' => trim((string)($_POST['CATEGORIA'] ?? '')),
    'CLASSE' => trim((string)($_POST['CLASSE'] ?? '')),
    'TIPOLOGIA DOCUMENTARIA' => trim((string)($_POST['TIPOLOGIA_DOCUMENTARIA'] ?? '')),
    'CONSERVAZIONE' => trim((string)($_POST['CONSERVAZIONE'] ?? ''))
];

if ($action === 'insert') {
    if ($newRow['CATEGORIA'] === '' || $newRow['CLASSE'] === '' || $newRow['TIPOLOGIA DOCUMENTARIA'] === '') {
        redirect_back(false, 'Categoria, Classe e Tipologia documentaria sono obbligatorie', $returnQuery);
    }

    array_unshift($rows, $newRow);

    if (!write_csv_assoc($filePath, $headers, $rows, ';')) {
        redirect_back(false, 'Impossibile salvare tipologia.csv', $returnQuery);
    }

    redirect_back(true, 'Riga inserita correttamente', $returnQuery);
}

if ($action === 'update') {
    $rowIndex = isset($_POST['row_index']) ? (int)$_POST['row_index'] : -1;

    if (!isset($rows[$rowIndex])) {
        redirect_back(false, 'Riga non trovata', $returnQuery);
    }

    if ($newRow['CATEGORIA'] === '' || $newRow['CLASSE'] === '' || $newRow['TIPOLOGIA DOCUMENTARIA'] === '') {
        redirect_back(false, 'Categoria, Classe e Tipologia documentaria sono obbligatorie', $returnQuery);
    }

    $rows[$rowIndex] = $newRow;

    if (!write_csv_assoc($filePath, $headers, $rows, ';')) {
        redirect_back(false, 'Impossibile salvare tipologia.csv', $returnQuery);
    }

    redirect_back(true, 'Riga aggiornata correttamente', $returnQuery);
}

if ($action === 'delete') {
    $rowIndex = isset($_POST['row_index']) ? (int)$_POST['row_index'] : -1;

    if (!isset($rows[$rowIndex])) {
        redirect_back(false, 'Riga non trovata', $returnQuery);
    }

    array_splice($rows, $rowIndex, 1);

    if (!write_csv_assoc($filePath, $headers, $rows, ';')) {
        redirect_back(false, 'Impossibile salvare tipologia.csv', $returnQuery);
    }

    redirect_back(true, 'Riga eliminata correttamente', $returnQuery);
}

redirect_back(false, 'Azione non valida', $returnQuery);