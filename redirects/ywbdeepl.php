<?php
namespace Redirects;
use Traffic\Actions\AbstractAction;
use \Redis;

class ywbdeepl extends AbstractAction
{
    protected $_name = 'ywbDeepl';
    protected $_weight = 100;

    private $supportedLangs = [
        'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI', 'FR', 'HU', 'ID', 'IT', 'JA',
        'KO', 'LT', 'LV', 'NB', 'NL', 'PL', 'PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH'];


    public function getType()
    {
        return self::TYPE_OTHER;
    }

    protected function _execute()
    {
        $this->setContentType('text/html');
        $this->setStatus(200);

        $lang = $this->getTargetLang();
        $this->addHeader('YWB-TargetLang: '.$lang);
        $path = $this->getWebsitePath();
        $inputPath =$this->getInputFilePath();

        if ($inputPath === null) {
            $this->setContent("index.html or index.htm NOT FOUND!");
            return;
        }

        $outputPath = $this->getOutputFilePath($lang);

        $this->addHeader("YWB-Input: ".$inputPath);
        $this->addHeader("YWB-Output: ".$outputPath);

        if (!file_exists($outputPath)) {
            $this->startTranslation($lang, $inputPath, $outputPath);
            $this->setContent('
                <html>
                <head>
                <meta http-equiv="refresh" content="5">
                </head>
                LOADING, PLEASE WAIT!
                </html>
            ');
            return;
        }

        $content = file_get_contents($outputPath);
        $content = preg_replace("/<head>/",'<head><base href="'.$this->getWebsitePath(false).'/">',$content); //need to add base tag
        $content = $this->processMacros($content);
        $this->setContent($content);
    }

    private function getTargetLang(){
        $payload = $this->getActionPayload();
        parse_str(parse_url($payload, PHP_URL_QUERY), $queryParams);
        if (isset($queryParams['lang'])) {
            $lang = $queryParams['lang'];
        } else {
            $lang = $this->getLangFromHeader();
        }

        if ($lang==null || $lang=='en' || !in_array(strtoupper($lang), $this->supportedLangs)) {
            $lang = 'en-US';
        }

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

    private function getWebsitePath($getFullPath=true){
        $payload = $this->getActionPayload();
        $path = urldecode(parse_url($payload, PHP_URL_PATH));
        return $getFullPath?'/var/www/keitaro/'.$path:$path;
    }

    private function getInputFilePath(){
        $path = $this->getWebsitePath();
        $indexFiles = ['index.html', 'index.htm'];
        $filePath = null;
        foreach ($indexFiles as $indexFile) {
            $filePath = $path . '/' . $indexFile;
            if (file_exists($filePath)) break; 
        }
        return $filePath;
    }

    private function getOutputFilePath($lang){
        $path = $this->getWebsitePath();
        $langFolderPath = $path . '/languages/' . $lang;
        $langFilePath = $langFolderPath . '/index.html';
        return $langFilePath;
    }

    private function getFromCache($langFilePath){
        return file_get_contents($langFilePath);
    }

    private function startTranslation($lang, $inputFilePath, $outputFilePath){

        $params = [
            "inputPath"=>$inputFilePath,
            "outputPath"=>$outputFilePath,
            "lang"=>$lang
        ];

	    $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->publish('ywb-deepl-channel', json_encode($params));
    }
}
