<?php
// Generador de PDF mínimo y correcto (sin librerías externas)
class PdfMaker {
    private $pages = [];
    private $ops = '';
    private $pageH = 842;
    private $pageW = 595;

    public function addPage() {
        if ($this->ops !== '') { $this->pages[] = $this->ops; $this->ops = ''; }
    }

    private function esc($s) {
        $s = (string)$s;
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
            if ($converted !== false) $s = $converted;
        }
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private function fmt($n) { return rtrim(rtrim(sprintf('%.2f', $n), '0'), '.'); }

    private function y($top) { return $this->pageH - $top; }

    public function text($x, $top, $size, $bold, $str, $align = 'L', $w = 0) {
        $font = $bold ? 'F2' : 'F1';
        $s = $this->esc($str);
        $tw = strlen($s) * $size * 0.5;
        if ($w > 0 && $align === 'R') $x = $x + $w - $tw - 4;
        if ($w > 0 && $align === 'C') $x = $x + ($w - $tw) / 2;
        $this->ops .= "BT /$font {$size} Tf 1 0 0 1 " . $this->fmt($x) . ' ' . $this->fmt($this->y($top)) . " Tm (" . $s . ") Tj ET\n";
    }

    public function rect($x, $top, $w, $h, $fill = false) {
        $y2 = $this->y($top + $h);
        if ($fill) {
            $this->ops .= "0.93 0.95 1 rg\n";
            $this->ops .= $this->fmt($x) . ' ' . $this->fmt($y2) . ' ' . $this->fmt($w) . ' ' . $this->fmt($h) . " re f\n";
            $this->ops .= "0 0 0 rg\n";
        } else {
            $this->ops .= '0.85 0.88 0.92 RG 0.7 w ' . $this->fmt($x) . ' ' . $this->fmt($y2) . ' ' . $this->fmt($w) . ' ' . $this->fmt($h) . " re S\n";
        }
    }

    public function line($x1, $top1, $x2, $top2) {
        $this->ops .= '0.85 0.88 0.92 RG 0.7 w ' . $this->fmt($x1) . ' ' . $this->fmt($this->y($top1)) . ' m ' . $this->fmt($x2) . ' ' . $this->fmt($this->y($top2)) . " l S\n";
    }

    public function output($mode = 'I', $name = 'documento.pdf') {
        if ($this->ops !== '') { $this->pages[] = $this->ops; $this->ops = ''; }
        if (!$this->pages) $this->pages[] = '';
        $pdf = $this->build();
        if ($mode === 'D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
        } else {
            header('Content-Type: application/pdf');
        }
        echo $pdf;
        exit;
    }

    private function build() {
        $objects = [];
        $n = count($this->pages);
        $pageIds = [];
        $contentsId = [];
        $id = 5;
        for ($i = 0; $i < $n; $i++) { $pageIds[$i] = $id++; $contentsId[$i] = $id++; }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = implode(' ', array_map(fn($p) => "$p 0 R", $pageIds));
        $objects[2] = "<< /Type /Pages /Kids [$kids] /Count $n >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($this->pages as $i => $content) {
            $objects[$pageIds[$i]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentsId[$i]} 0 R >>";
            $objects[$contentsId[$i]] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        ksort($objects);
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $objId => $body) {
            $offsets[$objId] = strlen($out);
            $out .= "$objId 0 obj\n$body\nendobj\n";
        }
        $maxId = max(array_keys($objects));
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $out;
    }
}