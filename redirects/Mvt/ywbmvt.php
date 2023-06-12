<?php

namespace Redirects;

include '/var/www/keitaro/application/redirects/mvtcore/abtest.php';

use Traffic\Actions\AbstractAction;

class ywbmvt extends AbstractAction
{
    protected $_name = 'ywbMVT';
    protected $_weight = 3;
    protected $_settings;


    public function getType()
    {
        return self::TYPE_OTHER;
    }

    public function getField()
    {
        return self::TEXT;
    }

    protected function _execute()
    {
        $this->setContentType('text/html');
        $this->setStatus(200);

        $this->_settings = json_decode($this->getActionPayload(), true);
        if ($this->jsonHasErrors()) return;
        $inputPath = $this->getInputFilePath();

        if ($inputPath == null) {
            $this->setContent("index.html or index.htm NOT FOUND!");
            return;
        }
        $content = file_get_contents($inputPath);

        $abtest = new \ABTest();
        $content = $abtest->get_random_test_dom($content, $this->_settings, true);
        $this->processSubs($abtest->_subs, $abtest->_variations);

        $content = $this->postProcess($content);
        $this->setContent($content);
    }

    private function getWebsitePath(bool $getFullPath = true): string
    {
        return $getFullPath ? '/var/www/keitaro/' . $this->_settings['inputDir'] : $this->_settings['inputDir'];
    }

    private function getInputFilePath(): string
    {
        $path = $this->getWebsitePath();
        $indexFiles = ['index.html', 'index.htm'];
        $filePath = null;
        foreach ($indexFiles as $indexFile) {
            $filePath = $path . '/' . $indexFile;
            if (file_exists($filePath)) break;
        }
        return $filePath;
    }

    private function processSubs(array $subs, array $variations)
    {
        $rawClick = $this->getRawClick();
        for ($i = 0; $i < count($subs); $i++) {
            $rawClick->setSubIdN($subs[$i], $variations[$i]);
        }
    }

    private function adaptAnchors($content)
    {
        $callback = function ($m) {
            if (
                strpos($m[1], "//") === 0 ||
                strpos($m[1], "http://") === 0 ||
                strpos($m[1], "https://") === 0
            ) {
                return $m[0];
            }
            return " href=\"#" . $m[2] . "\" onclick=\"document.location.hash='" . $m[2] . "';return false;\"";
        };
        $content = preg_replace_callback("/\\shref\\s?=\\s?[\"']([^\"^']*?)#([^\"^']*?)[\"']/", $callback, $content);
        return $content;
    }

    private function postProcess(string $content): string
    {
        $content = preg_replace("/<head>/", '<head><base href="' . $this->getWebsitePath(false) . '/">', $content); //need to add base tag
        $content = $this->processMacros($content);
        $content = $this->adaptAnchors($content);
        return $content;
    }
    private function jsonHasErrors()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return false;
                break;
            case JSON_ERROR_DEPTH:
                $this->setContent('JSON - Maximum stack depth exceeded');
                return true;
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->setContent('JSON - Underflow or the modes mismatch');
                return true;
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->setContent('JSON - Unexpected control character found');
                return true;
                break;
            case JSON_ERROR_SYNTAX:
                $this->setContent('JSON - Syntax error, malformed JSON');
                return true;
                break;
            case JSON_ERROR_UTF8:
                $this->setContent('JSON - Malformed UTF-8 characters, possibly incorrectly encoded');
                return true;
                break;
            default:
                $this->setContent('JSON - Unknown error');
                return true;
                break;
        }
        return false;
    }
}
