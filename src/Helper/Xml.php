<?php

declare(strict_types=1);

namespace PHPCensor\Helper;

use DOMDocument;
use Exception;
use LibXMLError;
use SimpleXMLElement;

class Xml
{
    /**
     * @param $filePath
     *
     * @return null|SimpleXMLElement
     */
    public static function loadFromFile($filePath)
    {
        \stream_filter_register('xml_utf8_clean', 'PHPCensor\Helper\Xml\Utf8CleanFilter');

        try {
            $xml = \simplexml_load_file('php://filter/read=xml_utf8_clean/resource=' . $filePath);
        } catch (Exception $ex) {
            $xml = null;
        } catch (\Throwable $ex) { // since php7
            $xml = null;
        }

        if (!$xml) {
            // from https://stackoverflow.com/questions/7766455/how-to-handle-invalid-unicode-with-simplexml/8092672#8092672
            $oldUse = \libxml_use_internal_errors(true);

            \libxml_clear_errors();

            $dom = new DOMDocument("1.0", "UTF-8");

            $dom->strictErrorChecking = false;
            $dom->validateOnParse     = false;
            $dom->recover             = true;

            $dom->loadXML(\strtr(
                \file_get_contents($filePath),
                ['&quot;' => "'"] // &quot; in attribute names may mislead the parser
            ));

            /** @var LibXMLError $xmlError */
            $xmlError = \libxml_get_last_error();
            if ($xmlError) {
                $warning = \sprintf('L%s C%s: %s', $xmlError->line, $xmlError->column, $xmlError->message);
                print 'WARNING: ignored errors while reading phpunit result, '.$warning."\n";
            }

            if (!$dom->hasChildNodes()) {
                new SimpleXMLElement('<empty/>');
            }

            $xml = \simplexml_import_dom($dom);

            \libxml_clear_errors();
            \libxml_use_internal_errors($oldUse);
        }

        return $xml;
    }
}
