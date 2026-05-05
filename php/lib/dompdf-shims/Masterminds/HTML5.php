<?php
/**
 * Stub shim for Masterminds\HTML5.
 *
 * Dompdf calls this unconditionally in loadHtml() regardless of the
 * isHtml5ParserEnabled option.  This stub wraps PHP's built-in DOMDocument
 * so Dompdf gets a working DOM without requiring the real masterminds/html5-php
 * Composer package.
 *
 * The real package's API used by Dompdf (src/Dompdf.php ~line 517):
 *   $html5 = new HTML5(["encoding" => "UTF-8", "disable_html_ns" => true]);
 *   $dom   = $html5->loadHTML($str);          // → DOMDocument
 *   $doc->loadHTML($html5->saveHTML($dom), …); // → string
 */

namespace Masterminds;

class HTML5
{
    public function __construct(array $options = [])
    {
        // options intentionally ignored — DOMDocument handles encoding fine
    }

    /**
     * Parse an HTML string and return a DOMDocument.
     * Mirrors Masterminds\HTML5::loadHTML().
     */
    public function loadHTML(string $html, array $options = []): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;

        $prev = libxml_use_internal_errors(true);
        // The xml encoding declaration avoids charset issues in older PHP
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $doc;
    }

    /**
     * Serialize a DOMDocument/DOMNode back to an HTML string.
     * Mirrors Masterminds\HTML5::saveHTML().
     */
    public function saveHTML(\DOMNode $node = null): string
    {
        if ($node instanceof \DOMDocument) {
            return (string)$node->saveHTML();
        }
        if ($node !== null && $node->ownerDocument instanceof \DOMDocument) {
            return (string)$node->ownerDocument->saveHTML($node);
        }
        return '';
    }
}
