<?php
require_once 'config.php';
require_once 'db.php';
require_once 'classes/Receipt.php';
require_once 'classes/Report.php';

$database = new Database();
$db = $database->connect();
$receipt = new Receipt($db);
$report = new Report($db, $receipt);

$clientId = (int)($_GET['client_id'] ?? 0);
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if ($_GET['format'] ?? '' === 'excel') {
    $report->exportCsv(0, $clientId ?: null, $start, $end);
} else {
    $report->exportPdf(0, $clientId ?: null, $start, $end);
}
?>