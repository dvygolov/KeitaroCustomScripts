<?php
namespace Redirects;

use Traffic\Actions\AbstractAction;
/*
Кастомный экшн для Кейтаро для подгрузки сайта из внутренней CMS Crazy Profits Agency c кешированием результатов.
ЕСЛИ ВЫ НЕ НОВЫЙ АДМИН НАШЕГО АГЕНТСТВА, ТО ЭТОТ ЭКШН ВАМ СОВЕРШЕННО НЕ НУЖЕН!
Скопировать файл экшна в папку application\redirects затем перелогиниться в трекер
Устанавливаете экшн в потоке, в поле пишите настройки в формате JSON, которые будут переданы в CMS.
В качестве механизма для кеширования используется Redis
©2022 by Yellow Web
 */
class ywbsystem extends AbstractAction
{
    protected $_name = 'ywbsystem';     // <-- Имя действия
    protected $_weight = 2;            // <-- Вес для сортировки в списке действий

    public function getType()
    {
        return self::TYPE_OTHER;              // <-- Указывает на тип
    }

    public function getField()
    {
        return self::TEXT;
    }

    protected function _execute()  
    {
        $json = json_decode($this->getActionPayload(),true);
        $json['nocache']=1;
        $qs = http_build_query($json);
        $url = "/local/common4/index.php?{$qs}";

        $sreq=$this->getServerRequest();
        $uri = parse_url($sreq->getUri());
        $url=$uri["scheme"]."://".$uri["host"].$url.'&'.$uri["query"];

        $cachetime = 60*60*24; //кешируем на сутки
        $ktRedis = \Traffic\Redis\Service\RedisStorageService::instance();
        $redis = $ktRedis->getOriginalClient();
        $cachekey = 'ywbCurl-'.$url;
        $content = $redis->get($cachekey);

        if ($content===false){
            $opts = [
                "localDomain" => $this->getServerRequest()->getHeaderLine(\Traffic\Request\ServerRequest::HEADER_HOST), 
                "url" => $url, 
                "user_agent" => $this->getRawClick()->getUserAgent(), 
                "referrer" => $this->getPipelinePayload()->getActionOption("referrer")
            ];
            $result = \Traffic\Actions\CurlService::instance()->request($opts);

            if (!empty($result["error"])) {
                $content = "Oops! Something went wrong on the requesting page:".$result["error"];
            } else {
                if (!empty($result["body"])) {
                    $content = $result["body"];
                    $redis->set($cachekey, $content, ['nx', 'ex' => $cachetime]);
                    $this->addHeader("X-YWBCurl: from WWW " . $url);
                }
            }
        }
        else
        {
            $this->addHeader("X-YWBCurl: from Redis cache " . $url);
        }

        $content = $this->processMacros($content);
        $content = \Traffic\Tools\Tools::utf8ize($content);
        $this->setContentType("text/html");
        $this->setContent($content);
    }
}
