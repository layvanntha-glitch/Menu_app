<?php
/**
 * includes/pdf.php — a tiny, self-contained PDF generator.
 *
 * No Composer, no external library, no internet. It writes a valid PDF 1.4
 * document using the built-in Helvetica fonts — enough for a clean invoice
 * (text, lines, right-aligned amounts). Coordinates are in points from the
 * TOP-LEFT (y grows downward) for convenience; A4 page = 595.28 x 841.89 pt.
 */

class SimplePDF
{
    private float $w = 595.28;   // A4 width  (pt)
    private float $h = 841.89;   // A4 height (pt)
    private array $pages = [];
    private string $cur = '';

    public function addPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
    }

    /** Escape a string for a PDF text object and fold to WinAnsi-safe bytes. */
    private function esc(string $s): string
    {
        // Standard fonts use WinAnsi; drop anything outside it to avoid garbage.
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($s === false) {
            $s = '';
        }
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    /** Draw text at (x, y) from the top-left. */
    public function text(float $x, float $y, string $s, float $size = 11, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $ty = $this->h - $y;
        $this->cur .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $ty, $this->esc($s));
    }

    /** Right-align text so it ends at $xRight (approx width via 0.5*size per char). */
    public function textRight(float $xRight, float $y, string $s, float $size = 11, bool $bold = false): void
    {
        $width = strlen($s) * $size * 0.5;
        $this->text($xRight - $width, $y, $s, $size, $bold);
    }

    /** A horizontal (or any) line. */
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.6, float $gray = 0.0): void
    {
        $this->cur .= sprintf("%.3F G %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $gray, $width, $x1, $this->h - $y1, $x2, $this->h - $y2);
    }

    /** A filled rectangle (for the header band). */
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $rgb[0], $rgb[1], $rgb[2], $x, $this->h - $y - $h, $w, $h);
    }

    /** Coloured text (rgb 0..1). Resets to black afterwards. */
    public function textColor(float $x, float $y, string $s, array $rgb, float $size = 11, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $ty = $this->h - $y;
        $this->cur .= sprintf("%.3F %.3F %.3F rg BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET 0 0 0 rg\n",
            $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $ty, $this->esc($s));
    }

    public function width(): float  { return $this->w; }
    public function height(): float { return $this->h; }

    /** Assemble and return the raw PDF bytes. */
    public function output(): string
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
            $this->cur = '';
        }
        if (!$this->pages) {
            $this->pages[] = '';
        }

        $all = [];
        $all[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        // 2 = Pages (filled below)
        $all[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $all[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $num = 5;
        $kids = [];
        foreach ($this->pages as $content) {
            $pageNum    = $num++;
            $contentNum = $num++;
            $kids[] = "$pageNum 0 R";
            $all[$pageNum] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->w, $this->h, $contentNum
            );
            $all[$contentNum] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $all[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';

        ksort($all);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($all as $k => $v) {
            $offsets[$k] = strlen($pdf);
            $pdf .= "$k 0 obj\n$v\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($all) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($k = 1; $k < $count; $k++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$k]);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
