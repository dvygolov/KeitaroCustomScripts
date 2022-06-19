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
        $httpcode = 200;

        $ktRedis = \Traffic\Redis\Service\RedisStorageService::instance();
        $redis = $ktRedis->getOriginalClient();

        $cachekey = 'ywbDomain-'.$url;
        $res = $redis->get($cachekey);
        if ($res===false){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $url);
            $res = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 200 || $httpcode == 202){
                $redis->set($cachekey, $res, ['nx', 'ex' => $cachetime]);
            }
            curl_close($ch);
        }
    
        $this->setContentType('text/html');
        $this->setStatus($httpcode);            
        $this->setContent($res);         
    }
}
