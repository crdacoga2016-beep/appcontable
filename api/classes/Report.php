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

    public function getData($userId, $clientId, $start = null, $end = null) {
        return $this->receipt->listReceipts($userId, $clientId, $start, $end);
    }

    public function exportCsv($userId, $clientId, $start = null, $end = null) {
        $rows = $this->receipt->listReceipts($userId, $clientId, $start, $end);
        $total = $this->receipt->totalByClient($userId, $clientId, $start, $end);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_recibos.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
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
        $clientName = null;
        if ($rows) $clientName = $rows[0]['client_name'] ?? 'Sin cliente';

        $pdf = new PdfMaker();
        $pdf->SetTitle('Reporte de Recibos - ' . ($clientName ?? 'General'));
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Reporte de Comprobantes', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, 'Cliente: ' . htmlspecialchars($clientName ?? 'General'), 0, 1);
        $pdf->Cell(0, 8, 'Periodo: ' . ($start ?: 'inicio') . ' al ' . ($end ?: 'fin'), 0, 1);
        $pdf->Cell(0, 8, 'Generado: ' . date('d/m/Y H:i'), 0, 1);
        $pdf->Ln(5);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 8, 'Fecha', 1, 0, 'C');
        $pdf->Cell(60, 8, 'Cliente', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Método', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Monto (COP)', 1, 0, 'C');
        $pdf->Cell(45, 8, 'Nota', 1, 1, 'C');

        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $r) {
            $pdf->Cell(30, 8, htmlspecialchars($r['receipt_date']), 1, 0);
            $pdf->Cell(60, 8, htmlspecialchars($r['client_name'] ?? '-'), 1, 0);
            $pdf->Cell(35, 8, htmlspecialchars($r['method']), 1, 0);
            $pdf->Cell(30, 8, number_format($r['amount'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(45, 8, htmlspecialchars($r['note'] ?? ''), 1, 1);
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(125, 8, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(30, 8, number_format($total['total'], 0, ',', '.'), 1, 1, 'R');

        $pdf->Output('D', 'reporte_recibos_' . ($clientId ?: 'general') . '.pdf');
    }
}
?>