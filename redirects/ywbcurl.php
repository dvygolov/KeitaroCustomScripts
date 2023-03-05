<?php

namespace Redirects;

use Redis;
use Traffic\Actions\CurlService;
use Traffic\Actions\AbstractAction;
use Traffic\Request\ServerRequest;
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
        $tracking_scripts = array(
            'google_analytics' => 'https://www.google-analytics.com/analytics.js',
            'google_tag_manager' => 'https://www.googletagmanager.com/gtag/js',
            'facebook_pixel' => 'connect.facebook.net/en_US/fbevents.js',
            'twitter_conversion' => 'https://platform.twitter.com/oct.js',
            'linkedin_insight_tag' => 'https://snap.licdn.com/li.lms-analytics/insight.min.js',
            'pinterest_tag' => '//s.pinimg.com/ct/core.js',
            'adobe_dtm' => 'https://assets.adobedtm.com',
            'adobe_analytics' => '.sc.omtrdc.net/s/s_code.js',
            'hubspot_tracking_code' => '//js.hs-scripts.com/',
            'bing_ads' => '//bat.bing.com/bat.js',
            'crazy_egg' => '//script.crazyegg.com/pages/scripts/',
            'yandex_metrika' => 'https://mc.yandex.ru/metrika/tag.js',
            'hotjar' => 'static.hotjar.com/c/hotjar'
        );
        $url = trim($this->getActionPayload());

        $cachetime = 60 * 60 * 24; //кешируем на сутки
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redisReplace = false;
        if (array_key_exists('redisReplace', $_GET))
            $redisReplace = true;
        $cachekey = 'ywbCurl-' . $url;
        $content = $redis->get($cachekey);

        if ($content === false || $redisReplace) {
            $opts = [
                "localDomain" => $this->getServerRequest()->getHeaderLine('Host'),
                "url" => $url,
                "user_agent" => $this->getRawClick()->getUserAgent(),
                "referrer" => "https://google.com"
            ];
            $result = CurlService::instance()->request($opts);

            if (!empty($result["error"])) {
                $content = "Oops! Something went wrong on the requesting page:" . $result["error"];
            } else {
                if (!empty($result["body"])) {
                    $content = $result["body"];
                    foreach ($tracking_scripts as $key => $url) {
                        $pattern = '#<script[^>]*(src="[^"]*' . preg_quote($url) . '[^"]*")[^>]*>.*?</script>|<script[^>]*>[^<]*' . preg_quote($url) . '[^<]*</script>#is';
                        $content = preg_replace($pattern, '', $content);
                    }
                    $redis->set($cachekey, $content, ['nx', 'ex' => $cachetime]);
                    $this->addHeader("X-YWBCurl: from WWW " . $url);
                }
            }
        } else {
            $this->addHeader("X-YWBCurl: from Redis cache " . $url);
        }

        $content = $this->processMacros($content);
        $this->setContentType("text/html");
        $this->setContent($content);
    }
}
