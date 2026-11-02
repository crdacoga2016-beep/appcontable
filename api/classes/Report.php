<?php
require_once 'config.php';
require_once 'classes/PdfMaker.php';

class Report {
    private $db;
    private $receipt;

    public function __construct($db, $receipt) {
        $this->db = $db;
        $this->receipt = $receipt;
    }

    public function exportCsv($userId, $clientId, $start = null, $end = null) {
        $rows = $this->receipt->listReceipts($userId, $clientId, $start, $end);
        $total = $this->receipt->totalByClient($userId, $clientId, $start, $end);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_recibos.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Fecha', 'Cliente', 'Método', 'Monto (COP)', 'Nota', 'Archivo'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['receipt_date'],
                $r['client_name'] ?? 'Sin cliente',
                $r['method'],
                $r['amount'],
                $r['note'] ?? '',
                basename($r['file_path'] ?? '')
            ], ';');
        }
        fputcsv($out, ['', '', 'TOTAL', $total['total'], '', ''], ';');
        fclose($out);
        exit;
    }

    public function exportPdf($userId, $clientId, $start = null, $end = null) {
        $rows = $this->receipt->listReceipts($userId, $clientId, $start, $end);
        $total = $this->receipt->totalByClient($userId, $clientId, $start, $end);
        $clientName = $rows ? ($rows[0]['client_name'] ?? 'Sin cliente') : 'Sin cliente';
        if ($clientId && !$rows) {
            $stmt = $this->db->prepare("SELECT name FROM clients WHERE id = ? AND user_id = ?");
            $stmt->execute([$clientId, $userId]);
            $clientName = $stmt->fetchColumn() ?: 'Sin cliente';
        }

        $fmt = fn($v) => number_format((float)$v, 0, ',', '.');

        $pdf = new PdfMaker();
        $pdf->addPage();

        $pdf->text(40, 55, 18, true, 'Reporte de Comprobantes', 'C', 515);
        $pdf->text(40, 82, 10, false, 'Cliente: ' . $clientName, 'L', 515);
        $pdf->text(40, 98, 10, false, 'Periodo: ' . ($start ?: 'inicio') . '  al  ' . ($end ?: 'fin'), 'L', 515);
        $pdf->text(40, 114, 9, false, 'Generado: ' . date('d/m/Y H:i'), 'L', 515);

        $cols = [
            ['Fecha', 40, 70, 'L'],
            ['Cliente', 110, 150, 'L'],
            ['Método', 260, 95, 'L'],
            ['Monto (COP)', 355, 80, 'R'],
            ['Nota', 435, 120, 'L'],
        ];

        $y = 140;
        $rowH = 18;
        $header = function () use ($pdf, $cols, &$y, $rowH) {
            $pdf->rect(40, $y, 515, $rowH, true);
            foreach ($cols as $c) { $pdf->text($c[1] + 4, $y + 12, 9, true, $c[0], $c[3], $c[2]); }
            $y += $rowH;
        };
        $header();

        foreach ($rows as $r) {
            if ($y > 770) { $pdf->addPage(); $y = 55; $header(); }
            $pdf->rect(40, $y, 515, $rowH);
            $pdf->text($cols[0][1] + 4, $y + 12, 9, false, $r['receipt_date'], 'L', $cols[0][2]);
            $pdf->text($cols[1][1] + 4, $y + 12, 9, false, substr($r['client_name'] ?? '-', 0, 30), 'L', $cols[1][2]);
            $pdf->text($cols[2][1] + 4, $y + 12, 9, false, substr($r['method'], 0, 22), 'L', $cols[2][2]);
            $pdf->text($cols[3][1] + 4, $y + 12, 9, false, $fmt($r['amount']), 'R', $cols[3][2]);
            $pdf->text($cols[4][1] + 4, $y + 12, 9, false, substr($r['note'] ?? '', 0, 24), 'L', $cols[4][2]);
            $y += $rowH;
        }

        $y += 6;
        $pdf->text(300, $y + 12, 11, true, 'TOTAL:', 'R', 150);
        $pdf->text(455, $y + 12, 12, true, '$ ' . $fmt($total['total']), 'R', 100);

        $pdf->text(40, 810, 8, false, 'AppContable - ' . count($rows) . ' comprobantes', 'L', 515);

        $pdf->output('D', 'reporte_' . ($clientId ?: 'general') . '.pdf');
    }
}