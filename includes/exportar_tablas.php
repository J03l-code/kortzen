<?php
if (!function_exists('mb_str_split')) {
    function mb_str_split(string $string, int $length = 1, ?string $encoding = null): array
    {
        if ($length < 1) return [];
        if ($string === '') return [];
        preg_match_all('/.{1,' . $length . '}/us', $string, $matches);
        return !empty($matches[0]) ? $matches[0] : (str_split($string, $length) ?: []);
    }
}

/** XLSX real con celdas de texto explícitas: ningún nombre se interpreta como fórmula. */
function tablaXlsx(array $columnas, array $filas): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('Activa la extensión ZIP de PHP para descargar Excel.');
    $xml = fn ($s) => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><sheetData>';
    foreach (array_merge([$columnas], $filas) as $i => $fila) {
        $sheet .= '<row r="' . ($i + 1) . '">';
        foreach (array_values($fila) as $j => $valor) {
            $col = ''; $n = $j + 1;
            while ($n > 0) { $n--; $col = chr(65 + $n % 26) . $col; $n = intdiv($n, 26); }
            $ref = $col . ($i + 1);
            $sheet .= is_int($valor) || is_float($valor)
                ? '<c r="' . $ref . '"><v>' . $valor . '</v></c>'
                : '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($valor) . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData><autoFilter ref="A1:' . $col . (count($filas) + 1) . '"/></worksheet>';
    $ruta = tempnam(sys_get_temp_dir(), 'kortzen-xlsx-');
    if ($ruta === false) throw new RuntimeException('No se pudo preparar Excel.');
    try {
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear Excel.');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="KORTZEN" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        if (!$zip->close()) throw new RuntimeException('No se pudo finalizar Excel.');
        $bytes = file_get_contents($ruta);
        if ($bytes === false) throw new RuntimeException('No se pudo leer Excel.');
        return $bytes;
    } finally { if (is_file($ruta)) unlink($ruta); }
}

/** PDF A4 horizontal, con encabezados repetidos y filas que ajustan sus textos. */
function tablaPdf(string $titulo, array $columnas, array $filas): string
{
    $escapar = fn ($s) => str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $s));
    $texto = fn ($x, $y, $s, $tam = 8) => sprintf("BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $tam, $x, $y, $escapar($s));
    $anchos = count($columnas) === 7 ? [112, 160, 48, 85, 135, 120, 115] : array_fill(0, count($columnas), 775 / max(1, count($columnas)));
    $paginas = []; $contenido = ''; $y = 0;
    $nuevaPagina = function () use (&$paginas, &$contenido, &$y, $titulo, $columnas, $anchos, $texto) {
        if ($contenido !== '') $paginas[] = $contenido;
        $contenido = $texto(32, 561, 'KORTZEN · ' . $titulo, 12);
        $contenido .= "0.08 0.08 0.08 rg 32 520 775 24 re f\n1 1 1 rg\n";
        $x = 32;
        foreach ($columnas as $i => $columna) { $contenido .= $texto($x + 4, 529, $columna); $x += $anchos[$i]; }
        $contenido .= "0 0 0 rg\n";
        $y = 520;
    };
    $nuevaPagina();
    foreach ($filas as $fila) {
        $lineas = []; $alto = 22;
        foreach (array_values($fila) as $i => $valor) {
            $lineas[$i] = mb_str_split((string) $valor ?: ' ', max(8, (int) ($anchos[$i] / 4.4)), 'UTF-8');
            $alto = max($alto, 8 + count($lineas[$i]) * 11);
        }
        if ($y - $alto < 40) $nuevaPagina();
        $x = 32;
        foreach ($lineas as $i => $celda) {
            foreach ($celda as $j => $linea) $contenido .= $texto($x + 4, $y - 14 - $j * 11, $linea);
            $x += $anchos[$i];
        }
        $y -= $alto;
        $contenido .= sprintf("0.85 G 32 %.2f m 807 %.2f l S\n", $y, $y);
    }
    $paginas[] = $contenido;
    $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    $hijos = [];
    foreach ($paginas as $i => $pagina) {
        $num = 4 + $i * 2; $hijos[] = "$num 0 R";
        $pagina .= $texto(32, 22, 'Página ' . ($i + 1) . ' de ' . count($paginas));
        $objetos[$num] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . ($num + 1) . ' 0 R >>';
        $objetos[$num + 1] = '<< /Length ' . strlen($pagina) . ">>\nstream\n" . $pagina . "endstream";
    }
    $objetos[2] = '<< /Type /Pages /Count ' . count($paginas) . ' /Kids [' . implode(' ', $hijos) . '] >>';
    ksort($objetos); $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objetos as $num => $objeto) { $offsets[$num] = strlen($pdf); $pdf .= "$num 0 obj\n$objeto\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objetos); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    return $pdf . 'trailer << /Size ' . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
