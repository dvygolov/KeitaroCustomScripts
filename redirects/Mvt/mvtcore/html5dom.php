<?php

class HTML5DOMDocument extends \DOMDocument
{

    use QuerySelectors;

    /**
     * An option passed to loadHTML() and loadHTMLFile() to disable duplicate element IDs exception.
     */
    const ALLOW_DUPLICATE_IDS = 67108864;

    /**
     * A modification (passed to modify()) that removes all but the last title elements.
     */
    const FIX_MULTIPLE_TITLES = 2;

    /**
     * A modification (passed to modify()) that removes all but the last metatags with matching name or property attributes.
     */
    const FIX_DUPLICATE_METATAGS = 4;

    /**
     * A modification (passed to modify()) that merges multiple head elements.
     */
    const FIX_MULTIPLE_HEADS = 8;

    /**
     * A modification (passed to modify()) that merges multiple body elements.
     */
    const FIX_MULTIPLE_BODIES = 16;

    /**
     * A modification (passed to modify()) that moves charset metatag and title elements first.
     */
    const OPTIMIZE_HEAD = 32;

    /**
     * A modification (passed to modify()) that removes all but first styles with duplicate content.
     */
    const FIX_DUPLICATE_STYLES = 64;

    /**
     *
     * @var array
     */
    static private $newObjectsCache = [];

    /**
     * Indicates whether an HTML code is loaded.
     *
     * @var boolean
     */
    private $loaded = false;

    /**
     * Creates a new HTML5DOMDocument object.
     *
     * @param string $version The version number of the document as part of the XML declaration.
     * @param string $encoding The encoding of the document as part of the XML declaration.
     */
    public function __construct(string $version = '1.0', string $encoding = '')
    {
        parent::__construct($version, $encoding);
        $this->registerNodeClass('DOMElement', '\HTML5DOMElement');
    }

    /**
     * Load HTML from a string.
     *
     * @param string $source The HTML code.
     * @param int $options Additional Libxml parameters.
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function loadHTML($source, $options = 0)
    {
        // Enables libxml errors handling
        $internalErrorsOptionValue = libxml_use_internal_errors();
        if ($internalErrorsOptionValue === false) {
            libxml_use_internal_errors(true);
        }

        $source = trim($source);

        // Add CDATA around script tags content
        $matches = null;
        preg_match_all('/<script(.*?)>/', $source, $matches);
        if (isset($matches[0])) {
            $matches[0] = array_unique($matches[0]);
            foreach ($matches[0] as $match) {
                if (substr($match, -2, 1) !== '/') { // check if ends with />
                    $source = str_replace($match, $match . '<![CDATA[-html5-dom-document-internal-cdata', $source); // Add CDATA after the open tag
                }
            }
        }
        $source = str_replace('</script>', '-html5-dom-document-internal-cdata]]></script>', $source); // Add CDATA before the end tag
        $source = str_replace('<![CDATA[-html5-dom-document-internal-cdata-html5-dom-document-internal-cdata]]>', '', $source); // Clean empty script tags
        $matches = null;
        preg_match_all('/\<!\[CDATA\[-html5-dom-document-internal-cdata.*?-html5-dom-document-internal-cdata\]\]>/s', $source, $matches);
        if (isset($matches[0])) {
            $matches[0] = array_unique($matches[0]);
            foreach ($matches[0] as $match) {
                if (strpos($match, '</') !== false) { // check if contains </
                    $source = str_replace($match, str_replace('</', '<-html5-dom-document-internal-cdata-endtagfix/', $match), $source);
                }
            }
        }

        $autoAddHtmlAndBodyTags = !defined('LIBXML_HTML_NOIMPLIED') || ($options & LIBXML_HTML_NOIMPLIED) === 0;
        $autoAddDoctype = !defined('LIBXML_HTML_NODEFDTD') || ($options & LIBXML_HTML_NODEFDTD) === 0;

        $allowDuplicateIDs = ($options & self::ALLOW_DUPLICATE_IDS) !== 0;

        // Add body tag if missing
        if ($autoAddHtmlAndBodyTags && $source !== '' && preg_match('/\<!DOCTYPE.*?\>/', $source) === 0 && preg_match('/\<html.*?\>/', $source) === 0 && preg_match('/\<body.*?\>/', $source) === 0 && preg_match('/\<head.*?\>/', $source) === 0) {
            $source = '<body>' . $source . '</body>';
        }

        // Add DOCTYPE if missing
        if ($autoAddDoctype && strtoupper(substr($source, 0, 9)) !== '<!DOCTYPE') {
            $source = "<!DOCTYPE html>\n" . $source;
        }

        // Adds temporary head tag
        $charsetTag = '<meta data-html5-dom-document-internal-attribute="charset-meta" http-equiv="content-type" content="text/html; charset=utf-8" />';
        $matches = [];
        preg_match('/\<head.*?\>/', $source, $matches);
        $removeHeadTag = false;
        $removeHtmlTag = false;
        if (isset($matches[0])) { // has head tag
            $insertPosition = strpos($source, $matches[0]) + strlen($matches[0]);
            $source = substr($source, 0, $insertPosition) . $charsetTag . substr($source, $insertPosition);
        } else {
            $matches = [];
            preg_match('/\<html.*?\>/', $source, $matches);
            if (isset($matches[0])) { // has html tag
                $source = str_replace($matches[0], $matches[0] . '<head>' . $charsetTag . '</head>', $source);
            } else {
                $source = '<head>' . $charsetTag . '</head>' . $source;
                $removeHtmlTag = true;
            }
            $removeHeadTag = true;
        }

        // Preserve html entities
        $source = preg_replace('/&([a-zA-Z]*);/', 'html5-dom-document-internal-entity1-$1-end', $source);
        $source = preg_replace('/&#([0-9]*);/', 'html5-dom-document-internal-entity2-$1-end', $source);

        $result = parent::loadHTML('<?xml encoding="utf-8" ?>' . $source, $options);
        if ($internalErrorsOptionValue === false) {
            libxml_use_internal_errors(false);
        }
        if ($result === false) {
            return false;
        }
        $this->encoding = 'utf-8';
        foreach ($this->childNodes as $item) {
            if ($item->nodeType === XML_PI_NODE) {
                $this->removeChild($item);
                break;
            }
        }
        /** @var HTML5DOMElement|null */
        $metaTagElement = $this->getElementsByTagName('meta')->item(0);
        if ($metaTagElement !== null) {
            if ($metaTagElement->getAttribute('data-html5-dom-document-internal-attribute') === 'charset-meta') {
                $headElement = $metaTagElement->parentNode;
                $htmlElement = $headElement->parentNode;
                $metaTagElement->parentNode->removeChild($metaTagElement);
                if ($removeHeadTag && $headElement !== null && $headElement->parentNode !== null && ($headElement->firstChild === null || ($headElement->childNodes->length === 1 && $headElement->firstChild instanceof \DOMText))) {
                    $headElement->parentNode->removeChild($headElement);
                }
                if ($removeHtmlTag && $htmlElement !== null && $htmlElement->parentNode !== null && $htmlElement->firstChild === null) {
                    $htmlElement->parentNode->removeChild($htmlElement);
                }
            }
        }

        if (!$allowDuplicateIDs) {
            $matches = [];
            preg_match_all('/\sid[\s]*=[\s]*(["\'])(.*?)\1/', $source, $matches);
            if (!empty($matches[2]) && max(array_count_values($matches[2])) > 1) {
                $elementIDs = [];
                $walkChildren = function ($element) use (&$walkChildren, &$elementIDs) {
                    foreach ($element->childNodes as $child) {
                        if ($child instanceof \DOMElement) {
                            if ($child->attributes->length > 0) { // Performance optimization
                                $id = $child->getAttribute('id');
                                if ($id !== '') {
                                    if (isset($elementIDs[$id])) {
                                        throw new \Exception('A DOM node with an ID value "' . $id . '" already exists! Pass the HTML5DOMDocument::ALLOW_DUPLICATE_IDS option to disable this check.');
                                    } else {
                                        $elementIDs[$id] = true;
                                    }
                                }
                            }
                            $walkChildren($child);
                        }
                    }
                };
                $walkChildren($this);
            }
        }

        $this->loaded = true;
        return true;
    }

    /**
     * Load HTML from a file.
     *
     * @param string $filename The path to the HTML file.
     * @param int $options Additional Libxml parameters.
     */
    public function loadHTMLFile($filename, $options = 0)
    {
        return $this->loadHTML(file_get_contents($filename), $options);
    }

    /**
     * Adds the HTML tag to the document if missing.
     *
     * @return boolean TRUE on success, FALSE otherwise.
     */
    private function addHtmlElementIfMissing(): bool
    {
        if ($this->getElementsByTagName('html')->length === 0) {
            if (!isset(self::$newObjectsCache['htmlelement'])) {
                self::$newObjectsCache['htmlelement'] = new \DOMElement('html');
            }
            $this->appendChild(clone (self::$newObjectsCache['htmlelement']));
            return true;
        }
        return false;
    }

    /**
     * Adds the HEAD tag to the document if missing.
     *
     * @return boolean TRUE on success, FALSE otherwise.
     */
    private function addHeadElementIfMissing(): bool
    {
        if ($this->getElementsByTagName('head')->length === 0) {
            $htmlElement = $this->getElementsByTagName('html')->item(0);
            if (!isset(self::$newObjectsCache['headelement'])) {
                self::$newObjectsCache['headelement'] = new \DOMElement('head');
            }
            $headElement = clone (self::$newObjectsCache['headelement']);
            if ($htmlElement->firstChild === null) {
                $htmlElement->appendChild($headElement);
            } else {
                $htmlElement->insertBefore($headElement, $htmlElement->firstChild);
            }
            return true;
        }
        return false;
    }

    /**
     * Adds the BODY tag to the document if missing.
     *
     * @return boolean TRUE on success, FALSE otherwise.
     */
    private function addBodyElementIfMissing(): bool
    {
        if ($this->getElementsByTagName('body')->length === 0) {
            if (!isset(self::$newObjectsCache['bodyelement'])) {
                self::$newObjectsCache['bodyelement'] = new \DOMElement('body');
            }
            $this->getElementsByTagName('html')->item(0)->appendChild(clone (self::$newObjectsCache['bodyelement']));
            return true;
        }
        return false;
    }

    /**
     * Dumps the internal document into a string using HTML formatting.
     *
     * @param \DOMNode $node Optional parameter to output a subset of the document.
     * @return string The document (or node) HTML code as string.
     */
    public function saveHTML(\DOMNode $node = null): string
    {
        $nodeMode = $node !== null;
        if ($nodeMode && $node instanceof \DOMDocument) {
            $nodeMode = false;
        }

        if ($nodeMode) {
            if (!isset(self::$newObjectsCache['html5domdocument'])) {
                self::$newObjectsCache['html5domdocument'] = new HTML5DOMDocument();
            }
            $tempDomDocument = clone (self::$newObjectsCache['html5domdocument']);
            if ($node->nodeName === 'html') {
                $tempDomDocument->loadHTML('<!DOCTYPE html>');
                $tempDomDocument->appendChild($tempDomDocument->importNode(clone ($node), true));
                $html = $tempDomDocument->saveHTML();
                $html = substr($html, 16); // remove the DOCTYPE + the new line after
            } elseif ($node->nodeName === 'head' || $node->nodeName === 'body') {
                $tempDomDocument->loadHTML("<!DOCTYPE html>\n<html></html>");
                $tempDomDocument->childNodes[1]->appendChild($tempDomDocument->importNode(clone ($node), true));
                $html = $tempDomDocument->saveHTML();
                $html = substr($html, 22, -7); // remove the DOCTYPE + the new line after + html tag
            } else {
                $isInHead = false;
                $parentNode = $node;
                for ($i = 0; $i < 1000; $i++) {
                    $parentNode = $parentNode->parentNode;
                    if ($parentNode === null) {
                        break;
                    }
                    if ($parentNode->nodeName === 'body') {
                        break;
                    } elseif ($parentNode->nodeName === 'head') {
                        $isInHead = true;
                        break;
                    }
                }
                $tempDomDocument->loadHTML("<!DOCTYPE html>\n<html>" . ($isInHead ? '<head></head>' : '<body></body>') . '</html>');
                $tempDomDocument->childNodes[1]->childNodes[0]->appendChild($tempDomDocument->importNode(clone ($node), true));
                $html = $tempDomDocument->saveHTML();
                $html = substr($html, 28, -14); // remove the DOCTYPE + the new line + html + body or head tags
            }
            $html = trim($html);
        } else {
            $removeHtmlElement = false;
            $removeHeadElement = false;
            $headElement = $this->getElementsByTagName('head')->item(0);
            if ($headElement === null) {
                if ($this->addHtmlElementIfMissing()) {
                    $removeHtmlElement = true;
                }
                if ($this->addHeadElementIfMissing()) {
                    $removeHeadElement = true;
                }
                $headElement = $this->getElementsByTagName('head')->item(0);
            }
            $meta = $this->createElement('meta');
            $meta->setAttribute('data-html5-dom-document-internal-attribute', 'charset-meta');
            $meta->setAttribute('http-equiv', 'content-type');
            $meta->setAttribute('content', 'text/html; charset=utf-8');
            if ($headElement->firstChild !== null) {
                $headElement->insertBefore($meta, $headElement->firstChild);
            } else {
                $headElement->appendChild($meta);
            }
            $html = parent::saveHTML();
            $html = rtrim($html, "\n");

            if ($removeHeadElement) {
                $headElement->parentNode->removeChild($headElement);
            } else {
                $meta->parentNode->removeChild($meta);
            }

            if (strpos($html, 'html5-dom-document-internal-entity') !== false) {
                $html = preg_replace('/html5-dom-document-internal-entity1-(.*?)-end/', '&$1;', $html);
                $html = preg_replace('/html5-dom-document-internal-entity2-(.*?)-end/', '&#$1;', $html);
            }

            $codeToRemove = [
                'html5-dom-document-internal-content',
                '<meta data-html5-dom-document-internal-attribute="charset-meta" http-equiv="content-type" content="text/html; charset=utf-8">',
                '</area>', '</base>', '</br>', '</col>', '</command>', '</embed>', '</hr>', '</img>', '</input>', '</keygen>', '</link>', '</meta>', '</param>', '</source>', '</track>', '</wbr>',
                '<![CDATA[-html5-dom-document-internal-cdata', '-html5-dom-document-internal-cdata]]>', '-html5-dom-document-internal-cdata-endtagfix'
            ];
            if ($removeHeadElement) {
                $codeToRemove[] = '<head></head>';
            }
            if ($removeHtmlElement) {
                $codeToRemove[] = '<html></html>';
            }

            $html = str_replace($codeToRemove, '', $html);
        }
        return $html;
    }

    /**
     * Dumps the internal document into a file using HTML formatting.
     * 
     * @param string $filename The path to the saved HTML document.
     * @return int|false the number of bytes written or FALSE if an error occurred.
     */
    #[\ReturnTypeWillChange] // Return type "int|false" is invalid in older supported versions.
    public function saveHTMLFile($filename)
    {
        if (!is_writable($filename)) {
            return false;
        }
        $result = $this->saveHTML();
        file_put_contents($filename, $result);
        $bytesWritten = filesize($filename);
        if ($bytesWritten === strlen($result)) {
            return $bytesWritten;
        }
        return false;
    }

    /**
     * Returns the first document element matching the selector.
     *
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname.classname.classname2, .classname.classname2, tagname[attribute-selector], [attribute-selector], "div, p", div p, div > p, div + p and p ~ ul.
     * @return HTML5DOMElement|null The result DOMElement or null if not found.
     * @throws \InvalidArgumentException
     */
    public function querySelector(string $selector)
    {
        return $this->internalQuerySelector($selector);
    }

    /**
     * Returns a list of document elements matching the selector.
     *
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname.classname.classname2, .classname.classname2, tagname[attribute-selector], [attribute-selector], "div, p", div p, div > p, div + p and p ~ ul.
     * @return HTML5DOMNodeList Returns a list of DOMElements matching the criteria.
     * @throws \InvalidArgumentException
     */
    public function querySelectorAll(string $selector)
    {
        return $this->internalQuerySelectorAll($selector);
    }

    /**
     * Creates an element that will be replaced by the new body in insertHTML.
     *
     * @param string $name The name of the insert target.
     * @return HTML5DOMElement A new DOMElement that must be set in the place where the new body will be inserted.
     */
    public function createInsertTarget(string $name)
    {
        if (!$this->loaded) {
            $this->loadHTML('');
        }
        $element = $this->createElement('html5-dom-document-insert-target');
        $element->setAttribute('name', $name);
        return $element;
    }

    /**
     * Inserts a HTML document into the current document. The elements from the head and the body will be moved to their proper locations.
     *
     * @param string $source The HTML code to be inserted.
     * @param string $target Body target position. Available values: afterBodyBegin, beforeBodyEnd or insertTarget name.
     */
    public function insertHTML(string $source, string $target = 'beforeBodyEnd')
    {
        $this->insertHTMLMulti([['source' => $source, 'target' => $target]]);
    }

    /**
     * Inserts multiple HTML documents into the current document. The elements from the head and the body will be moved to their proper locations.
     *
     * @param array $sources An array containing the source of the document to be inserted in the following format: [ ['source'=>'', 'target'=>''], ['source'=>'', 'target'=>''], ... ]
     * @throws \Exception
     */
    public function insertHTMLMulti(array $sources)
    {
        if (!$this->loaded) {
            $this->loadHTML('');
        }

        if (!isset(self::$newObjectsCache['html5domdocument'])) {
            self::$newObjectsCache['html5domdocument'] = new HTML5DOMDocument();
        }

        $currentDomDocument = &$this;

        $copyAttributes = function ($sourceNode, $targetNode) {
            foreach ($sourceNode->attributes as $attributeName => $attribute) {
                $targetNode->setAttribute($attributeName, $attribute->value);
            }
        };

        $currentDomHTMLElement = null;
        $currentDomHeadElement = null;
        $currentDomBodyElement = null;

        $insertTargetsList = null;
        $prepareInsertTargetsList = function () use (&$insertTargetsList) {
            if ($insertTargetsList === null) {
                $insertTargetsList = [];
                $targetElements = $this->getElementsByTagName('html5-dom-document-insert-target');
                foreach ($targetElements as $targetElement) {
                    $insertTargetsList[$targetElement->getAttribute('name')] = $targetElement;
                }
            }
        };

        foreach ($sources as $sourceData) {
            if (!isset($sourceData['source'])) {
                throw new \Exception('Missing source key');
            }
            $source = $sourceData['source'];
            $target = isset($sourceData['target']) ? $sourceData['target'] : 'beforeBodyEnd';

            $domDocument = clone (self::$newObjectsCache['html5domdocument']);
            $domDocument->loadHTML($source, self::ALLOW_DUPLICATE_IDS);

            $htmlElement = $domDocument->getElementsByTagName('html')->item(0);
            if ($htmlElement !== null) {
                if ($htmlElement->attributes->length > 0) {
                    if ($currentDomHTMLElement === null) {
                        $currentDomHTMLElement = $this->getElementsByTagName('html')->item(0);
                        if ($currentDomHTMLElement === null) {
                            $this->addHtmlElementIfMissing();
                            $currentDomHTMLElement = $this->getElementsByTagName('html')->item(0);
                        }
                    }
                    $copyAttributes($htmlElement, $currentDomHTMLElement);
                }
            }

            $headElement = $domDocument->getElementsByTagName('head')->item(0);
            if ($headElement !== null) {
                if ($currentDomHeadElement === null) {
                    $currentDomHeadElement = $this->getElementsByTagName('head')->item(0);
                    if ($currentDomHeadElement === null) {
                        $this->addHtmlElementIfMissing();
                        $this->addHeadElementIfMissing();
                        $currentDomHeadElement = $this->getElementsByTagName('head')->item(0);
                    }
                }
                foreach ($headElement->childNodes as $headElementChild) {
                    $newNode = $currentDomDocument->importNode($headElementChild, true);
                    if ($newNode !== null) {
                        $currentDomHeadElement->appendChild($newNode);
                    }
                }
                if ($headElement->attributes->length > 0) {
                    $copyAttributes($headElement, $currentDomHeadElement);
                }
            }

            $bodyElement = $domDocument->getElementsByTagName('body')->item(0);
            if ($bodyElement !== null) {
                if ($currentDomBodyElement === null) {
                    $currentDomBodyElement = $this->getElementsByTagName('body')->item(0);
                    if ($currentDomBodyElement === null) {
                        $this->addHtmlElementIfMissing();
                        $this->addBodyElementIfMissing();
                        $currentDomBodyElement = $this->getElementsByTagName('body')->item(0);
                    }
                }
                $bodyElementChildren = $bodyElement->childNodes;
                if ($target === 'afterBodyBegin') {
                    $bodyElementChildrenCount = $bodyElementChildren->length;
                    for ($i = $bodyElementChildrenCount - 1; $i >= 0; $i--) {
                        $newNode = $currentDomDocument->importNode($bodyElementChildren->item($i), true);
                        if ($newNode !== null) {
                            if ($currentDomBodyElement->firstChild === null) {
                                $currentDomBodyElement->appendChild($newNode);
                            } else {
                                $currentDomBodyElement->insertBefore($newNode, $currentDomBodyElement->firstChild);
                            }
                        }
                    }
                } elseif ($target === 'beforeBodyEnd') {
                    foreach ($bodyElementChildren as $bodyElementChild) {
                        $newNode = $currentDomDocument->importNode($bodyElementChild, true);
                        if ($newNode !== null) {
                            $currentDomBodyElement->appendChild($newNode);
                        }
                    }
                } else {
                    $prepareInsertTargetsList();
                    if (isset($insertTargetsList[$target])) {
                        $targetElement = $insertTargetsList[$target];
                        $targetElementParent = $targetElement->parentNode;
                        foreach ($bodyElementChildren as $bodyElementChild) {
                            $newNode = $currentDomDocument->importNode($bodyElementChild, true);
                            if ($newNode !== null) {
                                $targetElementParent->insertBefore($newNode, $targetElement);
                            }
                        }
                        $targetElementParent->removeChild($targetElement);
                    }
                }
                if ($bodyElement->attributes->length > 0) {
                    $copyAttributes($bodyElement, $currentDomBodyElement);
                }
            } else { // clear the insert target when there is no body element
                $prepareInsertTargetsList();
                if (isset($insertTargetsList[$target])) {
                    $targetElement = $insertTargetsList[$target];
                    $targetElement->parentNode->removeChild($targetElement);
                }
            }
        }
    }

    /**
     * Applies the modifications specified to the DOM document.
     * 
     * @param int $modifications The modifications to apply. Available values:
     *  - HTML5DOMDocument::FIX_MULTIPLE_TITLES - removes all but the last title elements.
     *  - HTML5DOMDocument::FIX_DUPLICATE_METATAGS - removes all but the last metatags with matching name or property attributes.
     *  - HTML5DOMDocument::FIX_MULTIPLE_HEADS - merges multiple head elements.
     *  - HTML5DOMDocument::FIX_MULTIPLE_BODIES - merges multiple body elements.
     *  - HTML5DOMDocument::OPTIMIZE_HEAD - moves charset metatag and title elements first.
     *  - HTML5DOMDocument::FIX_DUPLICATE_STYLES - removes all but first styles with duplicate content.
     */
    public function modify($modifications = 0)
    {

        $fixMultipleTitles = ($modifications & self::FIX_MULTIPLE_TITLES) !== 0;
        $fixDuplicateMetatags = ($modifications & self::FIX_DUPLICATE_METATAGS) !== 0;
        $fixMultipleHeads = ($modifications & self::FIX_MULTIPLE_HEADS) !== 0;
        $fixMultipleBodies = ($modifications & self::FIX_MULTIPLE_BODIES) !== 0;
        $optimizeHead = ($modifications & self::OPTIMIZE_HEAD) !== 0;
        $fixDuplicateStyles = ($modifications & self::FIX_DUPLICATE_STYLES) !== 0;

        /** @var \DOMNodeList<HTML5DOMElement> */
        $headElements = $this->getElementsByTagName('head');

        if ($fixMultipleHeads) { // Merges multiple head elements.
            if ($headElements->length > 1) {
                $firstHeadElement = $headElements->item(0);
                while ($headElements->length > 1) {
                    $nextHeadElement = $headElements->item(1);
                    $nextHeadElementChildren = $nextHeadElement->childNodes;
                    $nextHeadElementChildrenCount = $nextHeadElementChildren->length;
                    for ($i = 0; $i < $nextHeadElementChildrenCount; $i++) {
                        $firstHeadElement->appendChild($nextHeadElementChildren->item(0));
                    }
                    $nextHeadElement->parentNode->removeChild($nextHeadElement);
                }
                $headElements = [$firstHeadElement];
            }
        }

        foreach ($headElements as $headElement) {

            if ($fixMultipleTitles) { // Remove all title elements except the last one.
                $titleTags = $headElement->getElementsByTagName('title');
                $titleTagsCount = $titleTags->length;
                for ($i = 0; $i < $titleTagsCount - 1; $i++) {
                    $node = $titleTags->item($i);
                    $node->parentNode->removeChild($node);
                }
            }

            if ($fixDuplicateMetatags) { // Remove all meta tags that has matching name or property attributes.
                $metaTags = $headElement->getElementsByTagName('meta');
                if ($metaTags->length > 0) {
                    $list = [];
                    $idsList = [];
                    foreach ($metaTags as $metaTag) {
                        $id = $metaTag->getAttribute('name');
                        if ($id !== '') {
                            $id = 'name:' . $id;
                        } else {
                            $id = $metaTag->getAttribute('property');
                            if ($id !== '') {
                                $id = 'property:' . $id;
                            } else {
                                $id = $metaTag->getAttribute('charset');
                                if ($id !== '') {
                                    $id = 'charset';
                                }
                            }
                        }
                        if (!isset($idsList[$id])) {
                            $idsList[$id] = 0;
                        }
                        $idsList[$id]++;
                        $list[] = [$metaTag, $id];
                    }
                    foreach ($idsList as $id => $count) {
                        if ($count > 1 && $id !== '') {
                            foreach ($list as $i => $item) {
                                if ($item[1] === $id) {
                                    $node = $item[0];
                                    $node->parentNode->removeChild($node);
                                    unset($list[$i]);
                                    $count--;
                                }
                                if ($count === 1) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if ($fixDuplicateStyles) {
                $styles = $headElement->getElementsByTagName('style');
                if ($styles->length > 0) {
                    $stylesToRemove = [];
                    $list = [];
                    foreach ($styles as $style) {
                        $innerHTML = trim($style->innerHTML);
                        if (array_search($innerHTML, $list) === false) {
                            $list[] = $innerHTML;
                        } else {
                            $stylesToRemove[] = $style;
                        }
                    }
                    foreach ($stylesToRemove as $styleToRemove) {
                        $styleToRemove->parentNode->removeChild($styleToRemove);
                    }
                    unset($list);
                }
                unset($styles);
            }

            if ($optimizeHead) { // Moves charset metatag and title elements first.
                $titleElement = $headElement->getElementsByTagName('title')->item(0);
                $hasTitleElement = false;
                if ($titleElement !== null && $titleElement->previousSibling !== null) {
                    $headElement->insertBefore($titleElement, $headElement->firstChild);
                    $hasTitleElement = true;
                }
                $metaTags = $headElement->getElementsByTagName('meta');
                $metaTagsLength = $metaTags->length;
                if ($metaTagsLength > 0) {
                    $charsetMetaTag = null;
                    $nodesToMove = [];
                    for ($i = $metaTagsLength - 1; $i >= 0; $i--) {
                        $nodesToMove[$i] = $metaTags->item($i);
                    }
                    for ($i = $metaTagsLength - 1; $i >= 0; $i--) {
                        $nodeToMove = $nodesToMove[$i];
                        if ($charsetMetaTag === null && $nodeToMove->getAttribute('charset') !== '') {
                            $charsetMetaTag = $nodeToMove;
                        }
                        $referenceNode = $headElement->childNodes->item($hasTitleElement ? 1 : 0);
                        if ($nodeToMove !== $referenceNode) {
                            $headElement->insertBefore($nodeToMove, $referenceNode);
                        }
                    }
                    if ($charsetMetaTag !== null && $charsetMetaTag->previousSibling !== null) {
                        $headElement->insertBefore($charsetMetaTag, $headElement->firstChild);
                    }
                }
            }
        }

        if ($fixMultipleBodies) { // Merges multiple body elements.
            $bodyElements = $this->getElementsByTagName('body');
            if ($bodyElements->length > 1) {
                $firstBodyElement = $bodyElements->item(0);
                while ($bodyElements->length > 1) {
                    $nextBodyElement = $bodyElements->item(1);
                    $nextBodyElementChildren = $nextBodyElement->childNodes;
                    $nextBodyElementChildrenCount = $nextBodyElementChildren->length;
                    for ($i = 0; $i < $nextBodyElementChildrenCount; $i++) {
                        $firstBodyElement->appendChild($nextBodyElementChildren->item(0));
                    }
                    $nextBodyElement->parentNode->removeChild($nextBodyElement);
                }
            }
        }
    }
}

class HTML5DOMElement extends \DOMElement
{

    use QuerySelectors;

    /**
     *
     * @var array
     */
    static private $foundEntitiesCache = [[], []];

    /**
     *
     * @var array
     */
    static private $newObjectsCache = [];

    /*
     * 
     * @var HTML5DOMTokenList
     */
    private $classList = null;

    /**
     * Returns the value for the property specified.
     *
     * @param string $name
     * @return string
     * @throws \Exception
     */
    public function __get(string $name)
    {
        if ($name === 'innerHTML') {
            if ($this->firstChild === null) {
                return '';
            }
            $html = $this->ownerDocument->saveHTML($this);
            $nodeName = $this->nodeName;
            return preg_replace('@^<' . $nodeName . '[^>]*>|</' . $nodeName . '>$@', '', $html);
        } elseif ($name === 'outerHTML') {
            if ($this->firstChild === null) {
                $nodeName = $this->nodeName;
                $attributes = $this->getAttributes();
                $result = '<' . $nodeName . '';
                foreach ($attributes as $name => $value) {
                    $result .= ' ' . $name . '="' . htmlentities($value) . '"';
                }
                if (array_search($nodeName, ['area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr']) === false) {
                    $result .= '></' . $nodeName . '>';
                } else {
                    $result .= '/>';
                }
                return $result;
            }
            return $this->ownerDocument->saveHTML($this);
        } elseif ($name === 'classList') {
            if ($this->classList === null) {
                $this->classList = new HTML5DOMTokenList($this, 'class');
            }
            return $this->classList;
        }
        throw new \Exception('Undefined property: HTML5DOMElement::$' . $name);
    }

    /**
     * Sets the value for the property specified.
     *
     * @param string $name
     * @param string $value
     * @throws \Exception
     */
    public function __set(string $name, $value)
    {
        if ($name === 'innerHTML') {
            while ($this->hasChildNodes()) {
                $this->removeChild($this->firstChild);
            }
            if (!isset(self::$newObjectsCache['html5domdocument'])) {
                self::$newObjectsCache['html5domdocument'] = new \HTML5DOMDocument();
            }
            $tmpDoc = clone (self::$newObjectsCache['html5domdocument']);
            $tmpDoc->loadHTML('<body>' . $value . '</body>', HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
            foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
                $node = $this->ownerDocument->importNode($node, true);
                $this->appendChild($node);
            }
            return;
        } elseif ($name === 'outerHTML') {
            if (!isset(self::$newObjectsCache['html5domdocument'])) {
                self::$newObjectsCache['html5domdocument'] = new \HTML5DOMDocument();
            }
            $tmpDoc = clone (self::$newObjectsCache['html5domdocument']);
            $tmpDoc->loadHTML('<body>' . $value . '</body>', HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
            foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
                $node = $this->ownerDocument->importNode($node, true);
                $this->parentNode->insertBefore($node, $this);
            }
            $this->parentNode->removeChild($this);
            return;
        } elseif ($name === 'classList') {
            $this->setAttribute('class', $value);
            return;
        }
        throw new \Exception('Undefined property: HTML5DOMElement::$' . $name);
    }

    /**
     * Updates the result value before returning it.
     *
     * @param string $value
     * @return string The updated value
     */
    private function updateResult(string $value): string
    {
        $value = str_replace(self::$foundEntitiesCache[0], self::$foundEntitiesCache[1], $value);
        if (strstr($value, 'html5-dom-document-internal-entity') !== false) {
            $search = [];
            $replace = [];
            $matches = [];
            preg_match_all('/html5-dom-document-internal-entity([12])-(.*?)-end/', $value, $matches);
            $matches[0] = array_unique($matches[0]);
            foreach ($matches[0] as $i => $match) {
                $search[] = $match;
                $replace[] = html_entity_decode(($matches[1][$i] === '1' ? '&' : '&#') . $matches[2][$i] . ';');
            }
            $value = str_replace($search, $replace, $value);
            self::$foundEntitiesCache[0] = array_merge(self::$foundEntitiesCache[0], $search);
            self::$foundEntitiesCache[1] = array_merge(self::$foundEntitiesCache[1], $replace);
            unset($search);
            unset($replace);
            unset($matches);
        }
        return $value;
    }

    /**
     * Returns the updated nodeValue Property
     * 
     * @return string The updated $nodeValue
     */
    public function getNodeValue(): string
    {
        return $this->updateResult($this->nodeValue);
    }

    /**
     * Returns the updated $textContent Property
     * 
     * @return string The updated $textContent
     */
    public function getTextContent(): string
    {
        return $this->updateResult($this->textContent);
    }

    /**
     * Returns the value for the attribute name specified.
     *
     * @param string $name The attribute name.
     * @return string The attribute value.
     * @throws \InvalidArgumentException
     */
    public function getAttribute($name): string
    {
        if ($this->attributes->length === 0) { // Performance optimization
            return '';
        }
        $value = parent::getAttribute($name);
        return $value !== '' ? (strstr($value, 'html5-dom-document-internal-entity') !== false ? $this->updateResult($value) : $value) : '';
    }

    /**
     * Returns an array containing all attributes.
     *
     * @return array An associative array containing all attributes.
     */
    public function getAttributes(): array
    {
        $attributes = [];
        foreach ($this->attributes as $attributeName => $attribute) {
            $value = $attribute->value;
            $attributes[$attributeName] = $value !== '' ? (strstr($value, 'html5-dom-document-internal-entity') !== false ? $this->updateResult($value) : $value) : '';
        }
        return $attributes;
    }

    /**
     * Returns the element outerHTML.
     *
     * @return string The element outerHTML.
     */
    public function __toString(): string
    {
        return $this->outerHTML;
    }

    /**
     * Returns the first child element matching the selector.
     *
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname.classname.classname2, .classname.classname2, tagname[attribute-selector], [attribute-selector], "div, p", div p, div > p, div + p and p ~ ul.
     * @return HTML5DOMElement|null The result DOMElement or null if not found.
     * @throws \InvalidArgumentException
     */
    public function querySelector(string $selector)
    {
        return $this->internalQuerySelector($selector);
    }

    /**
     * Returns a list of children elements matching the selector.
     *
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname.classname.classname2, .classname.classname2, tagname[attribute-selector], [attribute-selector], "div, p", div p, div > p, div + p and p ~ ul.
     * @return HTML5DOMNodeList Returns a list of DOMElements matching the criteria.
     * @throws \InvalidArgumentException
     */
    public function querySelectorAll(string $selector)
    {
        return $this->internalQuerySelectorAll($selector);
    }
}
/**
 * Represents a list of DOM nodes.
 * 
 * @property-read int $length The list items count
 */
class HTML5DOMNodeList extends \ArrayObject
{

    public function item(int $index)
    {
        return $this->offsetExists($index) ? $this->offsetGet($index) : null;
    }

    /**
     * Returns the value for the property specified.
     * 
     * @param string $name The name of the property.
     * @return mixed
     * @throws \Exception
     */
    public function __get(string $name)
    {
        if ($name === 'length') {
            return sizeof($this);
        }
        throw new \Exception('Undefined property: \HTML5DOMNodeList::$' . $name);
    }
}

use ArrayIterator;
use DOMElement;

/**
 * Represents a set of space-separated tokens of an element attribute.
 * 
 * @property-read int $length The number of tokens.
 * @property-read string $value A space-separated list of the tokens.
 */
class HTML5DOMTokenList
{

    /**
     * @var string
     */
    private $attributeName;

    /**
     * @var DOMElement
     */
    private $element;

    /**
     * @var string[]
     */
    private $tokens;

    /**
     * @var string
     */
    private $previousValue;

    /**
     * Creates a list of space-separated tokens based on the attribute value of an element.
     * 
     * @param DOMElement $element The DOM element.
     * @param string $attributeName The name of the attribute.
     */
    public function __construct(DOMElement $element, string $attributeName)
    {
        $this->element = $element;
        $this->attributeName = $attributeName;
        $this->previousValue = null;
        $this->tokenize();
    }

    /**
     * Adds the given tokens to the list.
     * 
     * @param string[] $tokens The tokens you want to add to the list.
     * @return void
     */
    public function add(string ...$tokens)
    {
        if (count($tokens) === 0) {
            return;
        }
        foreach ($tokens as $t) {
            if (in_array($t, $this->tokens)) {
                continue;
            }
            $this->tokens[] = $t;
        }
        $this->setAttributeValue();
    }

    /**
     * Removes the specified tokens from the list. If the string does not exist in the list, no error is thrown.
     * 
     * @param string[] $tokens The token you want to remove from the list.
     * @return void
     */
    public function remove(string ...$tokens)
    {
        if (count($tokens) === 0) {
            return;
        }
        if (count($this->tokens) === 0) {
            return;
        }
        foreach ($tokens as $t) {
            $i = array_search($t, $this->tokens);
            if ($i === false) {
                continue;
            }
            array_splice($this->tokens, $i, 1);
        }
        $this->setAttributeValue();
    }

    /**
     * Returns an item in the list by its index (returns null if the number is greater than or equal to the length of the list).
     * 
     * @param int $index The zero-based index of the item you want to return.
     * @return null|string
     */
    public function item(int $index)
    {
        $this->tokenize();
        if ($index >= count($this->tokens)) {
            return null;
        }
        return $this->tokens[$index];
    }

    /**
     * Removes a given token from the list and returns false. If token doesn't exist it's added and the function returns true.
     * 
     * @param string $token The token you want to toggle.
     * @param bool $force A Boolean that, if included, turns the toggle into a one way-only operation. If set to false, the token will only be removed but not added again. If set to true, the token will only be added but not removed again.
     * @return bool false if the token is not in the list after the call, or true if the token is in the list after the call.
     */
    public function toggle(string $token, bool $force = null): bool
    {
        $this->tokenize();
        $isThereAfter = false;
        $i = array_search($token, $this->tokens);
        if (is_null($force)) {
            if ($i === false) {
                $this->tokens[] = $token;
                $isThereAfter = true;
            } else {
                array_splice($this->tokens, $i, 1);
            }
        } else {
            if ($force) {
                if ($i === false) {
                    $this->tokens[] = $token;
                }
                $isThereAfter = true;
            } else {
                if ($i !== false) {
                    array_splice($this->tokens, $i, 1);
                }
            }
        }
        $this->setAttributeValue();
        return $isThereAfter;
    }

    /**
     * Returns true if the list contains the given token, otherwise false.
     * 
     * @param string $token The token you want to check for the existence of in the list.
     * @return bool true if the list contains the given token, otherwise false.
     */
    public function contains(string $token): bool
    {
        $this->tokenize();
        return in_array($token, $this->tokens);
    }

    /**
     * Replaces an existing token with a new token.
     * 
     * @param string $old The token you want to replace.
     * @param string $new The token you want to replace $old with.
     * @return void
     */
    public function replace(string $old, string $new)
    {
        if ($old === $new) {
            return;
        }
        $this->tokenize();
        $i = array_search($old, $this->tokens);
        if ($i !== false) {
            $j = array_search($new, $this->tokens);
            if ($j === false) {
                $this->tokens[$i] = $new;
            } else {
                array_splice($this->tokens, $i, 1);
            }
            $this->setAttributeValue();
        }
    }

    /**
     * 
     * @return string
     */
    public function __toString(): string
    {
        $this->tokenize();
        return implode(' ', $this->tokens);
    }

    /**
     * Returns an iterator allowing you to go through all tokens contained in the list.
     * 
     * @return ArrayIterator
     */
    public function entries(): ArrayIterator
    {
        $this->tokenize();
        return new ArrayIterator($this->tokens);
    }

    /**
     * Returns the value for the property specified
     *
     * @param string $name The name of the property
     * @return string The value of the property specified
     * @throws \Exception
     */
    public function __get(string $name)
    {
        if ($name === 'length') {
            $this->tokenize();
            return count($this->tokens);
        } elseif ($name === 'value') {
            return $this->__toString();
        }
        throw new \Exception('Undefined property: HTML5DOMTokenList::$' . $name);
    }

    /**
     * 
     * @return void
     */
    private function tokenize()
    {
        $current = $this->element->getAttribute($this->attributeName);
        if ($this->previousValue === $current) {
            return;
        }
        $this->previousValue = $current;
        $tokens = explode(' ', $current);
        $finals = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (in_array($token, $finals)) {
                continue;
            }
            $finals[] = $token;
        }
        $this->tokens = $finals;
    }

    /**
     * 
     * @return void
     */
    private function setAttributeValue()
    {
        $value = implode(' ', $this->tokens);
        if ($this->previousValue === $value) {
            return;
        }
        $this->previousValue = $value;
        $this->element->setAttribute($this->attributeName, $value);
    }
}

trait QuerySelectors
{

    /**
     * Returns the first element matching the selector.
     * 
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname[attribute-selector] and [attribute-selector].
     * @return HTML5DOMElement|null The result DOMElement or null if not found
     */
    private function internalQuerySelector(string $selector)
    {
        $result = $this->internalQuerySelectorAll($selector, 1);
        return $result->item(0);
    }

    /**
     * Returns a list of document elements matching the selector.
     * 
     * @param string $selector A CSS query selector. Available values: *, tagname, tagname#id, #id, tagname.classname, .classname, tagname[attribute-selector] and [attribute-selector].
     * @param int|null $preferredLimit Preferred maximum number of elements to return.
     * @return DOMNodeList Returns a list of DOMElements matching the criteria.
     * @throws \InvalidArgumentException
     */
    private function internalQuerySelectorAll(string $selector, $preferredLimit = null)
    {
        $selector = trim($selector);

        $cache = [];
        $walkChildren = function (\DOMNode $context, $tagNames, callable $callback) use (&$cache) {
            if (!empty($tagNames)) {
                $children = [];
                foreach ($tagNames as $tagName) {
                    $elements = $context->getElementsByTagName($tagName);
                    foreach ($elements as $element) {
                        $children[] = $element;
                    }
                }
            } else {
                $getChildren = function () use ($context) {
                    $result = [];
                    $process = function (\DOMNode $node) use (&$process, &$result) {
                        foreach ($node->childNodes as $child) {
                            if ($child instanceof \DOMElement) {
                                $result[] = $child;
                                $process($child);
                            }
                        }
                    };
                    $process($context);
                    return $result;
                };
                if ($this === $context) {
                    $cacheKey = 'walk_children';
                    if (!isset($cache[$cacheKey])) {
                        $cache[$cacheKey] = $getChildren();
                    }
                    $children = $cache[$cacheKey];
                } else {
                    $children = $getChildren();
                }
            }
            foreach ($children as $child) {
                if ($callback($child) === true) {
                    return true;
                }
            }
        };

        $getElementById = function (\DOMNode $context, $id, $tagName) use (&$walkChildren) {
            if ($context instanceof \DOMDocument) {
                $element = $context->getElementById($id);
                if ($element && ($tagName === null || $element->tagName === $tagName)) {
                    return $element;
                }
            } else {
                $foundElement = null;
                $walkChildren($context, $tagName !== null ? [$tagName] : null, function ($element) use ($id, &$foundElement) {
                    if ($element->attributes->length > 0 && $element->getAttribute('id') === $id) {
                        $foundElement = $element;
                        return true;
                    }
                });
                return $foundElement;
            }
            return null;
        };

        $simpleSelectors = [];

        // all
        $simpleSelectors['\*'] = function (string $mode, array $matches, \DOMNode $context, callable $add = null) use ($walkChildren) {
            if ($mode === 'validate') {
                return true;
            } else {
                $walkChildren($context, [], function ($element) use ($add) {
                    if ($add($element)) {
                        return true;
                    }
                });
            }
        };

        // tagname
        $simpleSelectors['[a-zA-Z0-9\-]+'] = function (string $mode, array $matches, \DOMNode $context, callable $add = null) use ($walkChildren) {
            $tagNames = [];
            foreach ($matches as $match) {
                $tagNames[] = strtolower($match[0]);
            }
            if ($mode === 'validate') {
                return array_search($context->tagName, $tagNames) !== false;
            }
            $walkChildren($context, $tagNames, function ($element) use ($add) {
                if ($add($element)) {
                    return true;
                }
            });
        };

        // tagname[target] or [target] // Available values for targets: attr, attr="value", attr~="value", attr|="value", attr^="value", attr$="value", attr*="value"
        $simpleSelectors['(?:[a-zA-Z0-9\-]*)(?:\[.+?\])'] = function (string $mode, array $matches, \DOMNode $context, callable $add = null) use ($walkChildren) {
            $run = function ($match) use ($mode, $context, $add, $walkChildren) {
                $attributeSelectors = explode('][', substr($match[2], 1, -1));
                foreach ($attributeSelectors as $i => $attributeSelector) {
                    $attributeSelectorMatches = null;
                    if (preg_match('/^(.+?)(=|~=|\|=|\^=|\$=|\*=)\"(.+?)\"$/', $attributeSelector, $attributeSelectorMatches) === 1) {
                        $attributeSelectors[$i] = [
                            'name' => strtolower($attributeSelectorMatches[1]),
                            'value' => $attributeSelectorMatches[3],
                            'operator' => $attributeSelectorMatches[2]
                        ];
                    } else {
                        $attributeSelectors[$i] = [
                            'name' => $attributeSelector
                        ];
                    }
                }
                $tagName = strlen($match[1]) > 0 ? strtolower($match[1]) : null;
                $check = function ($element) use ($attributeSelectors) {
                    if ($element->attributes->length > 0) {
                        foreach ($attributeSelectors as $attributeSelector) {
                            $isMatch = false;
                            $attributeValue = $element->getAttribute($attributeSelector['name']);
                            if (isset($attributeSelector['value'])) {
                                $valueToMatch = $attributeSelector['value'];
                                switch ($attributeSelector['operator']) {
                                    case '=':
                                        if ($attributeValue === $valueToMatch) {
                                            $isMatch = true;
                                        }
                                        break;
                                    case '~=':
                                        $words = preg_split("/[\s]+/", $attributeValue);
                                        if (array_search($valueToMatch, $words) !== false) {
                                            $isMatch = true;
                                        }
                                        break;

                                    case '|=':
                                        if ($attributeValue === $valueToMatch || strpos($attributeValue, $valueToMatch . '-') === 0) {
                                            $isMatch = true;
                                        }
                                        break;

                                    case '^=':
                                        if (strpos($attributeValue, $valueToMatch) === 0) {
                                            $isMatch = true;
                                        }
                                        break;

                                    case '$=':
                                        if (substr($attributeValue, -strlen($valueToMatch)) === $valueToMatch) {
                                            $isMatch = true;
                                        }
                                        break;

                                    case '*=':
                                        if (strpos($attributeValue, $valueToMatch) !== false) {
                                            $isMatch = true;
                                        }
                                        break;
                                }
                            } else {
                                if ($attributeValue !== '') {
                                    $isMatch = true;
                                }
                            }
                            if (!$isMatch) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false;
                };
                if ($mode === 'validate') {
                    return ($tagName === null ? true : $context->tagName === $tagName) && $check($context);
                } else {
                    $walkChildren($context, $tagName !== null ? [$tagName] : null, function ($element) use ($check, $add) {
                        if ($check($element)) {
                            if ($add($element)) {
                                return true;
                            }
                        }
                    });
                }
            };
            // todo optimize
            foreach ($matches as $match) {
                if ($mode === 'validate') {
                    if ($run($match)) {
                        return true;
                    }
                } else {
                    $run($match);
                }
            }
            if ($mode === 'validate') {
                return false;
            }
        };

        // tagname#id or #id
        $simpleSelectors['(?:[a-zA-Z0-9\-]*)#(?:[a-zA-Z0-9\-\_]+?)'] = function (string $mode, array $matches, \DOMNode $context, callable $add = null) use ($getElementById) {
            $run = function ($match) use ($mode, $context, $add, $getElementById) {
                $tagName = strlen($match[1]) > 0 ? strtolower($match[1]) : null;
                $id = $match[2];
                if ($mode === 'validate') {
                    return ($tagName === null ? true : $context->tagName === $tagName) && $context->getAttribute('id') === $id;
                } else {
                    $element = $getElementById($context, $id, $tagName);
                    if ($element) {
                        $add($element);
                    }
                }
            };
            // todo optimize
            foreach ($matches as $match) {
                if ($mode === 'validate') {
                    if ($run($match)) {
                        return true;
                    }
                } else {
                    $run($match);
                }
            }
            if ($mode === 'validate') {
                return false;
            }
        };

        // tagname.classname, .classname, tagname.classname.classname2, .classname.classname2
        $simpleSelectors['(?:[a-zA-Z0-9\-]*)\.(?:[a-zA-Z0-9\-\_\.]+?)'] = function (string $mode, array $matches, \DOMNode $context, callable $add = null) use ($walkChildren) {
            $rawData = []; // Array containing [tag, classnames]
            $tagNames = [];
            foreach ($matches as $match) {
                $tagName = strlen($match[1]) > 0 ? $match[1] : null;
                $classes = explode('.', $match[2]);
                if (empty($classes)) {
                    continue;
                }
                $rawData[] = [$tagName, $classes];
                if ($tagName !== null) {
                    $tagNames[] = $tagName;
                }
            }
            $check = function ($element) use ($rawData) {
                if ($element->attributes->length > 0) {
                    $classAttribute = ' ' . $element->getAttribute('class') . ' ';
                    $tagName = $element->tagName;
                    foreach ($rawData as $rawMatch) {
                        if ($rawMatch[0] !== null && $tagName !== $rawMatch[0]) {
                            continue;
                        }
                        $allClassesFound = true;
                        foreach ($rawMatch[1] as $class) {
                            if (strpos($classAttribute, ' ' . $class . ' ') === false) {
                                $allClassesFound = false;
                                break;
                            }
                        }
                        if ($allClassesFound) {
                            return true;
                        }
                    }
                }
                return false;
            };
            if ($mode === 'validate') {
                return $check($context);
            }
            $walkChildren($context, $tagNames, function ($element) use ($check, $add) {
                if ($check($element)) {
                    if ($add($element)) {
                        return true;
                    }
                }
            });
        };

        $isMatchingElement = function (\DOMNode $context, string $selector) use ($simpleSelectors) {
            foreach ($simpleSelectors as $simpleSelector => $callback) {
                $match = null;
                if (preg_match('/^' . (str_replace('?:', '', $simpleSelector)) . '$/', $selector, $match) === 1) {
                    return call_user_func($callback, 'validate', [$match], $context);
                }
            }
        };

        $complexSelectors = [];

        $getMatchingElements = function (\DOMNode $context, string $selector, $preferredLimit = null) use (&$simpleSelectors, &$complexSelectors) {

            $processSelector = function (string $mode, string $selector, $operator = null) use (&$processSelector, $simpleSelectors, $complexSelectors, $context, $preferredLimit) {
                $supportedSimpleSelectors = array_keys($simpleSelectors);
                $supportedSimpleSelectorsExpression = '(?:(?:' . implode(')|(?:', $supportedSimpleSelectors) . '))';
                $supportedSelectors = $supportedSimpleSelectors;
                $supportedComplexOperators = array_keys($complexSelectors);
                if ($operator === null) {
                    $operator = ',';
                    foreach ($supportedComplexOperators as $complexOperator) {
                        array_unshift($supportedSelectors, '(?:(?:(?:' . $supportedSimpleSelectorsExpression . '\s*\\' . $complexOperator . '\s*))+' . $supportedSimpleSelectorsExpression . ')');
                    }
                }
                $supportedSelectorsExpression = '(?:(?:' . implode(')|(?:', $supportedSelectors) . '))';

                $vallidationExpression = '/^(?:(?:' . $supportedSelectorsExpression . '\s*\\' . $operator . '\s*))*' . $supportedSelectorsExpression . '$/';
                if (preg_match($vallidationExpression, $selector) !== 1) {
                    return false;
                }
                $selector .= $operator; // append the seprator at the back for easier matching below

                $result = [];
                if ($mode === 'execute') {
                    $add = function ($element) use ($preferredLimit, &$result) {
                        $found = false;
                        foreach ($result as $addedElement) {
                            if ($addedElement === $element) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $result[] = $element;
                            if ($preferredLimit !== null && sizeof($result) >= $preferredLimit) {
                                return true;
                            }
                        }
                        return false;
                    };
                }

                $selectorsToCall = [];
                $addSelectorToCall = function ($type, $selector, $argument) use (&$selectorsToCall) {
                    $previousIndex = sizeof($selectorsToCall) - 1;
                    // todo optimize complex too
                    if ($type === 1 && isset($selectorsToCall[$previousIndex]) && $selectorsToCall[$previousIndex][0] === $type && $selectorsToCall[$previousIndex][1] === $selector) {
                        $selectorsToCall[$previousIndex][2][] = $argument;
                    } else {
                        $selectorsToCall[] = [$type, $selector, [$argument]];
                    }
                };
                for ($i = 0; $i < 100000; $i++) {
                    $matches = null;
                    preg_match('/^(?<subselector>' . $supportedSelectorsExpression . ')\s*\\' . $operator . '\s*/', $selector, $matches); // getting the next subselector
                    if (isset($matches['subselector'])) {
                        $subSelector = $matches['subselector'];
                        $selectorFound = false;
                        foreach ($simpleSelectors as $simpleSelector => $callback) {
                            $match = null;
                            if (preg_match('/^' . (str_replace('?:', '', $simpleSelector)) . '$/', $subSelector, $match) === 1) { // if simple selector
                                if ($mode === 'parse') {
                                    $result[] = $match[0];
                                } else {
                                    $addSelectorToCall(1, $simpleSelector, $match);
                                    //call_user_func($callback, 'execute', $match, $context, $add);
                                }
                                $selectorFound = true;
                                break;
                            }
                        }
                        if (!$selectorFound) {
                            foreach ($complexSelectors as $complexOperator => $callback) {
                                $subSelectorParts = $processSelector('parse', $subSelector, $complexOperator);
                                if ($subSelectorParts !== false) {
                                    $addSelectorToCall(2, $complexOperator, $subSelectorParts);
                                    //call_user_func($callback, $subSelectorParts, $context, $add);
                                    $selectorFound = true;
                                    break;
                                }
                            }
                        }
                        if (!$selectorFound) {
                            throw new \Exception('Internal error for selector "' . $selector . '"!');
                        }
                        $selector = substr($selector, strlen($matches[0])); // remove the matched subselector and continue parsing
                        if (strlen($selector) === 0) {
                            break;
                        }
                    }
                }
                foreach ($selectorsToCall as $selectorToCall) {
                    if ($selectorToCall[0] === 1) { // is simple selector
                        call_user_func($simpleSelectors[$selectorToCall[1]], 'execute', $selectorToCall[2], $context, $add);
                    } else { // is complex selector
                        call_user_func($complexSelectors[$selectorToCall[1]], $selectorToCall[2][0], $context, $add); // todo optimize and send all arguments
                    }
                }
                return $result;
            };

            return $processSelector('execute', $selector);
        };

        // div p (space between) - all <p> elements inside <div> elements
        $complexSelectors[' '] = function (array $parts, \DOMNode $context, callable $add = null) use (&$getMatchingElements) {
            $elements = null;
            foreach ($parts as $part) {
                if ($elements === null) {
                    $elements = $getMatchingElements($context, $part);
                } else {
                    $temp = [];
                    foreach ($elements as $element) {
                        $temp = array_merge($temp, $getMatchingElements($element, $part));
                    }
                    $elements = $temp;
                }
            }
            foreach ($elements as $element) {
                $add($element);
            }
        };

        // div > p - all <p> elements where the parent is a <div> element
        $complexSelectors['>'] = function (array $parts, \DOMNode $context, callable $add = null) use (&$getMatchingElements, &$isMatchingElement) {
            $elements = null;
            foreach ($parts as $part) {
                if ($elements === null) {
                    $elements = $getMatchingElements($context, $part);
                } else {
                    $temp = [];
                    foreach ($elements as $element) {
                        foreach ($element->childNodes as $child) {
                            if ($child instanceof \DOMElement && $isMatchingElement($child, $part)) {
                                $temp[] = $child;
                            }
                        }
                    }
                    $elements = $temp;
                }
            }
            foreach ($elements as $element) {
                $add($element);
            }
        };

        // div + p - all <p> elements that are placed immediately after <div> elements
        $complexSelectors['+'] = function (array $parts, \DOMNode $context, callable $add = null) use (&$getMatchingElements, &$isMatchingElement) {
            $elements = null;
            foreach ($parts as $part) {
                if ($elements === null) {
                    $elements = $getMatchingElements($context, $part);
                } else {
                    $temp = [];
                    foreach ($elements as $element) {
                        if ($element->nextSibling !== null && $isMatchingElement($element->nextSibling, $part)) {
                            $temp[] = $element->nextSibling;
                        }
                    }
                    $elements = $temp;
                }
            }
            foreach ($elements as $element) {
                $add($element);
            }
        };

        // p ~ ul -	all <ul> elements that are preceded by a <p> element
        $complexSelectors['~'] = function (array $parts, \DOMNode $context, callable $add = null) use (&$getMatchingElements, &$isMatchingElement) {
            $elements = null;
            foreach ($parts as $part) {
                if ($elements === null) {
                    $elements = $getMatchingElements($context, $part);
                } else {
                    $temp = [];
                    foreach ($elements as $element) {
                        $nextSibling = $element->nextSibling;
                        while ($nextSibling !== null) {
                            if ($isMatchingElement($nextSibling, $part)) {
                                $temp[] = $nextSibling;
                            }
                            $nextSibling = $nextSibling->nextSibling;
                        }
                    }
                    $elements = $temp;
                }
            }
            foreach ($elements as $element) {
                $add($element);
            }
        };

        $result = $getMatchingElements($this, $selector, $preferredLimit);
        if ($result === false) {
            throw new \InvalidArgumentException('Unsupported selector (' . $selector . ')');
        }
        return new \HTML5DOMNodeList($result);
    }
}
