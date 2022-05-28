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
        $url = $this->getActionPayload();
        $cachetime = 60*60*24; //кешируем на сутки

        $urlPart = end(explode('/',rtrim($url,'/'))); //избавляемся от https://
        $cachekey = 'ywbDomain-'.$urlPart;

        $ktRedis = \Traffic\Redis\Service\RedisStorageService::instance();
        $redis = $ktRedis->getOriginalClient();

        $html='Curl Cached by Yellow Web';
        $res = $redis->get($cachekey);
        if ($res===false){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $url);
            $res = curl_exec($ch);
            $redis->set($cachekey, $res, ['nx', 'ex' => $cachetime]);
            curl_close($ch);
        }
    
        $this->setContentType('text/html');
        $this->setStatus(200);            
        $this->setContent($res);         
    }
}
