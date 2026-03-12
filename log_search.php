<?php
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $message = '') {
    echo json_encode([
        'ok' => $ok,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Metodo non consentito');
}

$ente = isset($_POST['ente']) ? trim((string)$_POST['ente']) : '';
$user = isset($_POST['user']) ? trim((string)$_POST['user']) : '';
$ricerca = isset($_POST['ricerca']) ? trim((string)$_POST['ricerca']) : '';

if ($ricerca === '') {
    http_response_code(400);
    respond(false, 'Ricerca mancante');
}

$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'ricerche_log.csv';
$delimiter = ';';

$isNewFile = !file_exists($logFile);

$fp = @fopen($logFile, 'a');
if (!$fp) {
    http_response_code(500);
    respond(false, 'Impossibile aprire ricerche_log.csv in scrittura');
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    respond(false, 'Impossibile bloccare il file di log');
}

try {
    if ($isNewFile || filesize($logFile) === 0) {
        fputcsv($fp, ['ente', 'user', 'ricerca', 'data'], $delimiter);
    }

    $data = date('Y-m-d H:i:s');

    fputcsv($fp, [
        $ente,
        $user,
        $ricerca,
        $data
    ], $delimiter);

    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    respond(true, 'Log salvato correttamente');
} catch (Throwable $e) {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(500);
    respond(false, 'Errore durante il salvataggio del log: ' . $e->getMessage());
}