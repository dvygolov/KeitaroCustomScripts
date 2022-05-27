<?php
namespace Redirects;

use Traffic\Actions\AbstractAction;
/*
Кастомный экшн для Кейтаро для подгрузки вайтов через CURL c кешированием результатов.
Скопировать файл экшна в папку application\redirects затем перелогиниться в трекер
Устанавливаете экшн в потоке, в поле пишите сайт, который будем подгружать.
Для корректно работы кеша необходимо создать в папке application\redirects папку curlCache 
и дать туда права на запись для Кейтаро.
©2022 by Yellow Web
 */
class ywbcurl extends AbstractAction
{
    protected $_name = 'ywbCurl';     // <-- Имя действия
    protected $_weight = 100;            // <-- Вес для сортировки в списке действий

    public function getType()
    {
        return self::TYPE_OTHER;              // <-- Указывает на тип
    }

    protected function _execute()  
    {
        $url = $this->getActionPayload();
        $cacheDir="/var/www/keitaro/application/redirects/curlCache";
        $cachetime = 300; // 5 минут

        $urlPart=end(explode('/',rtrim($url,'/'))); //избавляемся от https://
        $cachefile = $cacheDir.'/'.$urlPart.'.html';
        $html='Curl Cached by Yellow Web';
		if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
            $html = file_get_contents($cachefile);
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $url);
            $html = curl_exec($ch);
            file_put_contents($cachefile, $html);
            curl_close($ch);
        }
    
        $this->setContentType('text/html');
        $this->setStatus(200);            
        $this->setContent($html);         
    }
}
