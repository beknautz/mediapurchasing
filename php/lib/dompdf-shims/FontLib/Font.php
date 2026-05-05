<?php
/**
 * Stub shim for FontLib\Font.
 *
 * The real dompdf/php-font-lib is only needed when downloading and
 * caching remote/custom fonts. Since we use only the built-in fonts
 * that already have pre-generated .ufm files (DejaVu, Helvetica, etc.),
 * Font::load() is never called during a normal render and this stub
 * is sufficient to satisfy the `use FontLib\Font;` declaration.
 */

namespace FontLib;

class Font
{
    /** @return false Always returns false so dompdf skips remote font download gracefully */
    public static function load(string $file): false
    {
        return false;
    }

    public function parse(): void {}
    public function close(): void {}
    public function saveAdobeFontMetrics(string $file, array $options = []): void {}
    public function getFontType(): string { return 'TrueType'; }
}
