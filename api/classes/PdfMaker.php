<?php
// Minimal PDF generator (no external libs required)
class PdfMaker {
    private $pdf = '';
    private $page = 1;
    private $y = 30;
    private $title = '';

    public function SetTitle($t) { $this->title = $t; }
    public function AddPage() {
        $this->newPage();
    }
    public function SetFont($family, $style, $size) {
        // stored but minimal; uses built-in fonts via FPDF-like emulation
    }
    private function newPage() {
        $this->pdf .= sprintf("BT /F1 12 Tf 50 780 Td (%s) Tj ET\n", $this->escape($this->title));
        $this->y = 50;
        $this->page++;
    }
    public function Cell($w, $h, $text, $border = 0, $ln = 0, $align = 'L') {
        $x = 50;
        static $cx = 50;
        if ($align === 'R') $x = $cx + $w - 5 * strlen($text);
        if ($align === 'C') $x = $cx + ($w / 2) - (2.5 * strlen($text));
        $this->pdf .= sprintf("BT /F1 %d Tf %.0f %.0f Td (%s) Tj ET\n", $h * 2, $x, 840 - $this->y, $this->escape($text));
        $this->y += $h;
        if ($ln === 1) { $cx = 50; $this->y += 2; } else { $cx += $w; }
    }
    public function Ln($h = 5) { $this->y += $h; }
    public function Output($mode = 'I', $name = 'doc.pdf') {
        $this->finalize();
        if ($mode === 'D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            echo $this->pdf;
        } else {
            echo $this->pdf;
        }
        exit;
    }
    private function finalize() {
        $this->pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/MediaBox[0 0 595 842]>>endobj\n4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000066 00000 n\n0000000123 00000 n\n0000000210 00000 n\ntrailer<</Size 5/Root 1 0 R>>startxref\n" . strlen($this->pdf) . "\n%%EOF\n";
    }
    private function escape($s) {
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\)', '\\)'], $s);
        return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }
}
?>