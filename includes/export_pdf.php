<?php

function exportResultToPdf(mysqli_result $result, string $filename, string $title): void
{
    $fields = $result->fetch_fields();
    $lines = [
        $title,
        'Generated: ' . date('Y-m-d H:i:s'),
        'Records: ' . $result->num_rows,
        str_repeat('-', 92),
    ];

    $sensitiveFields = ['password', 'pin_code', 'api_key', 'secret', 'token'];
    while ($row = $result->fetch_assoc()) {
        foreach ($row as $field => $value) {
            $label = (string)$field;
            $fieldKey = strtolower($label);
            foreach ($sensitiveFields as $sensitiveField) {
                if (str_contains($fieldKey, $sensitiveField)) {
                    $value = '[redacted]';
                    break;
                }
            }

            $text = $label . ': ' . ($value === null ? '' : (string)$value);
            $text = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $text);
            foreach (explode("\n", wordwrap($text, 92, "\n", true)) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = str_repeat('-', 92);
    }

    $pages = array_chunk($lines, 55);
    if (!$pages) {
        $pages = [[]];
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
    ];
    $pageReferences = [];

    foreach ($pages as $pageIndex => $pageLines) {
        $pageObject = 4 + ($pageIndex * 2);
        $contentObject = $pageObject + 1;
        $pageReferences[] = $pageObject . ' 0 R';

        $content = "BT\n/F1 8 Tf\n40 800 Td\n13 TL\n";
        foreach ($pageLines as $line) {
            $encodedLine = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string)$line);
            if ($encodedLine === false) {
                $encodedLine = preg_replace('/[^\x20-\x7E]/', '?', (string)$line);
            }
            $encodedLine = str_replace(
                ["\\", '(', ')'],
                ["\\\\", '\\(', '\\)'],
                $encodedLine
            );
            $content .= '(' . $encodedLine . ") Tj\nT*\n";
        }
        $content .= "ET";

        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n"
            . $content . "\nendstream";
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageReferences)
        . '] /Count ' . count($pageReferences) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= $objectNumber . " 0 obj\n" . $objectBody . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= count($objects); $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    if (file_put_contents($filename, $pdf) === false) {
        throw new RuntimeException('The PDF export file could not be written.');
    }
}
