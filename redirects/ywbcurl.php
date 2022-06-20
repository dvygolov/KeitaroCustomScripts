<?php
namespace Redirects;

use Traffic\Actions\AbstractAction;
/*
Кастомный экшн для Кейтаро для подгрузки вайтов через CURL c кешированием результатов.
Скопировать файл экшна в папку application\redirects затем перелогиниться в трекер
Устанавливаете экшн в потоке, в поле пишите сайт, который будем подгружать.
В качестве механизма для кеширования используется Redis
©2022 by Yellow Web
 */
class ywbcurl extends AbstractAction
{
    protected $_name = 'ywbCurl';     // <-- Имя действия
    protected $_weight = 1;            // <-- Вес для сортировки в списке действий

    public function getType()
    {
        return self::TYPE_OTHER;              // <-- Указывает на тип
    }

    protected function _execute()  
    {
        $url = trim($this->getActionPayload());

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
