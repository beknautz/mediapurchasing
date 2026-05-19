<?php
/**
 * src/XlsxWriter.php
 * Minimal XLSX file writer using only PHP's built-in ZipArchive extension.
 * No external library required.
 *
 * Usage:
 *   $w = new XlsxWriter();
 *   $w->addSheet('Sheet1', $rows, $colWidths);
 *   $path = $w->save('/tmp/output.xlsx');
 *
 * Cell formats:
 *   Plain value  → string or number, default style
 *   Styled cell  → ['v' => value, 's' => styleIndex]   (0=normal, 1=bold)
 *   s=0  Normal
 *   s=1  Bold
 */
class XlsxWriter
{
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $colWidths = []): void
    {
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'cols' => $colWidths];
    }

    /** Write to $path (must end in .xlsx) and return $path on success. */
    public function save(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('XlsxWriter: cannot create ' . $path);
        }

        $sheetCount = count($this->sheets);

        $zip->addFromString('[Content_Types].xml',          $this->contentTypes($sheetCount));
        $zip->addFromString('_rels/.rels',                  $this->rootRels());
        $zip->addFromString('xl/workbook.xml',              $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels',   $this->workbookRels($sheetCount));
        $zip->addFromString('xl/styles.xml',                $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($i + 1) . '.xml',
                $this->worksheet($sheet['rows'], $sheet['cols'])
            );
        }

        $zip->close();
        return $path;
    }

    // ── XML parts ─────────────────────────────────────────────────────────────

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
        }
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  {$overrides}
</Types>
XML;
    }

    private function rootRels(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $sheets .= '<sheet name="' . $this->xe($sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>' . "\n";
        }
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    {$sheets}
  </sheets>
</workbook>
XML;
    }

    private function workbookRels(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>' . "\n";
        }
        $stylesId = $sheetCount + 1;
        $rels    .= '<Relationship Id="rId' . $stylesId . '"'
                 . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
                 . ' Target="styles.xml"/>';
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  {$rels}
</Relationships>
XML;
    }

    private function styles(): string
    {
        // Index 0 = normal, index 1 = bold
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private function worksheet(array $rows, array $colWidths): string
    {
        // Column width definitions
        $colDefs = '';
        if (!empty($colWidths)) {
            $colDefs = '<cols>';
            foreach ($colWidths as $idx => $w) {
                $col = $idx + 1;
                $colDefs .= '<col min="' . $col . '" max="' . $col . '" width="' . $w . '" customWidth="1"/>';
            }
            $colDefs .= '</cols>';
        }

        $sheetData = '<sheetData>';
        foreach ($rows as $ri => $row) {
            $rowNum  = $ri + 1;
            $cellXml = '';
            foreach ($row as $ci => $cell) {
                $colLetter = $this->colName($ci);
                $ref       = $colLetter . $rowNum;
                $style     = 0;
                $value     = $cell;

                if (is_array($cell)) {
                    $value = $cell['v'] ?? '';
                    $style = (int)($cell['s'] ?? 0);
                }

                $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';

                if (is_numeric($value) && $value !== '') {
                    $cellXml .= '<c r="' . $ref . '"' . $styleAttr . '><v>' . $value . '</v></c>';
                } elseif ((string)$value !== '') {
                    $escaped  = $this->xe((string)$value);
                    $cellXml .= '<c r="' . $ref . '" t="inlineStr"' . $styleAttr . '><is><t>' . $escaped . '</t></is></c>';
                }
                // empty cells — omit entirely
            }
            if ($cellXml !== '') {
                $sheetData .= '<row r="' . $rowNum . '">' . $cellXml . '</row>';
            }
        }
        $sheetData .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colDefs
            . $sheetData
            . '</worksheet>';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function colName(int $col): string
    {
        $name = '';
        do {
            $name = chr($col % 26 + 65) . $name;
            $col  = intdiv($col, 26) - 1;
        } while ($col >= 0);
        return $name;
    }

    /** XML-escape a string value */
    private function xe(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
