<?php
class FPDF {
    private $pages = [];
    private $page = 0;
    private $wPt = 595.28; // A4 width in points
    private $hPt = 841.89; // A4 height in points
    private $k = 72/25.4;
    private $x = 10;
    private $y = 10;
    private $fontSize = 12;

    function AddPage() {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->x = 10;
        $this->y = 10;
    }
    function SetFont($family, $style='', $size=12) {
        $this->fontSize = $size;
    }
    function Cell($w, $h, $txt, $border=0, $ln=0, $align='') {
        $safe = str_replace(['\\','(',')'],['\\\\','\(','\)'],$txt);
        $this->pages[$this->page] .= sprintf("BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n",
            $this->fontSize,
            $this->x * $this->k,
            $this->hPt - $this->y * $this->k,
            $safe);
        $this->x += $w;
        if($ln>0){
            $this->x = 10;
            $this->y += $h;
        }
    }
    function Ln($h=0){
        if($h==0) $h = $this->fontSize/2;
        $this->x = 10;
        $this->y += $h;
    }
    private function buildDoc(){
        $content = $this->pages[1] ?? '';
        $pdf = "%PDF-1.3\n";
        $offsets = [];
        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->wPt} {$this->hPt}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $offsets[4] = strlen($pdf);
        $len = strlen($content);
        $pdf .= "4 0 obj\n<< /Length $len >>\nstream\n$content\nendstream\nendobj\n";
        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for($i=1;$i<=5;$i++)
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
    function Output($dest='I', $name='doc.pdf') {
        $pdf = $this->buildDoc();
        if($dest=='I') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$name.'"');
            header('Content-Length: '.strlen($pdf));
            echo $pdf;
        } else {
            file_put_contents($name, $pdf);
        }
    }
}
?>
