<?php

namespace Redirects;

use Traffic\Actions\AbstractAction;
use \Redis;

class ywbdeepl extends AbstractAction
{
    protected $_name = 'ywbDeepl';
    protected $_weight = 4;

    private $supportedLangs = [
        'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'EN-US', 'EN-GB', 'ES', 'ET', 'FI', 'FR', 'HU', 'ID', 'IT', 'JA',
        'KO', 'LT', 'LV', 'NB', 'NL', 'PL', 'PT', 'PT-PT', 'PT-BR', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH'
    ];


    public function getType()
    {
        return self::TYPE_OTHER;
    }

    protected function _execute()
    {
        $this->setContentType('text/html');
        $this->setStatus(200);

        $lang = $this->getTargetLang();
        $this->addHeader('YWB-TargetLang: ' . $lang);
        $path = $this->getWebsitePath();
        $inputPath = $this->getInputFilePath();

        if ($inputPath === null) {
            $this->setContent("index.html or index.htm NOT FOUND!");
            return;
        }

        $outputPath = $this->getOutputFilePath($lang);

        $this->addHeader("YWB-Input: " . $inputPath);
        $this->addHeader("YWB-Output: " . $outputPath);

        if (!file_exists($outputPath)) {
            $this->startTranslation($lang, $inputPath, $outputPath);
            $this->setContent(file_get_contents("/var/www/keitaro/application/redirects/loading.html"));
            return;
        }

        $content = file_get_contents($outputPath);
        $content = preg_replace("/<head>/", '<head><base href="' . $this->getWebsitePath(false) . '/">', $content); //need to add base tag
        $content = $this->processMacros($content);
        $content = $this->adaptAnchors($content);
        $this->setContent($content);
    }

    private function getTargetLang()
    {
        $payload = $this->getActionPayload();
        parse_str(parse_url($payload, PHP_URL_QUERY), $queryParams);
        if (isset($queryParams['lang'])) {
            $lang = $queryParams['lang'];
        } else {
            $lang = $this->getLangFromHeader();
        }

        if ($lang == null || $lang == 'en' || !in_array(strtoupper($lang), $this->supportedLangs)) {
            $lang = 'en-US';
        }

        if ($lang == 'pt') $lang = 'pt-PT';

        return $lang;
    }

    private function getLangFromHeader()
    {
        $langHeader = $this->getServerRequest()->getHeader('Accept-Language');
        if (is_array($langHeader) && !empty($langHeader)) {
            $langs = explode(',', $langHeader[0]);
            $lang = substr($langs[0], 0, 2);
            return $lang;
        }
        return null;
    }

    private function getWebsitePath($getFullPath = true)
    {
        $payload = $this->getActionPayload();
        $path = urldecode(parse_url($payload, PHP_URL_PATH));
        return $getFullPath ? '/var/www/keitaro/' . $path : $path;
    }

    private function getInputFilePath()
    {
        $path = $this->getWebsitePath();
        $indexFiles = ['index.html', 'index.htm'];
        foreach ($indexFiles as $indexFile) {
            $filePath = $path . '/' . $indexFile;
            if (file_exists($filePath)) return $filePath;
        }
        return null;
    }

    private function getOutputFilePath($lang)
    {
        $path = $this->getWebsitePath();
        $langFolderPath = $path . '/languages/' . $lang;
        $langFilePath = $langFolderPath . '/index.html';
        return $langFilePath;
    }

    private function getFromCache($langFilePath)
    {
        return file_get_contents($langFilePath);
    }

    private function startTranslation($lang, $inputFilePath, $outputFilePath)
    {

        $params = [
            "inputPath" => $inputFilePath,
            "outputPath" => $outputFilePath,
            "lang" => $lang
        ];

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->publish('ywb-deepl-channel', json_encode($params));
    }
    private function adaptAnchors($content)
    {
        $callback = function ($m) {
            if (strpos($m[1], "//") === 0 || strpos($m[1], "http://") === 0 || strpos($m[1], "https://") === 0) {
                return $m[0];
            }
            return " href=\"#" . $m[2] . "\" onclick=\"document.location.hash='" . $m[2] . "';return false;\"";
        };
        $content = preg_replace_callback("/\\shref\\s?=\\s?[\"']([^\"^']*?)#([^\"^']*?)[\"']/", $callback, $content);
        return $content;
    }
}
