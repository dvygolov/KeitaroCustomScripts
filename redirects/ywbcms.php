<?php
namespace Redirects;

use Traffic\Actions\AbstractAction;
/*
Кастомный экшн для Кейтаро для подгрузки сайта из внутренней CMS Crazy Profits Agency c кешированием результатов.
Скопировать файл экшна в папку application\redirects затем перелогиниться в трекер
Устанавливаете экшн в потоке, в поле пишите настройки в формате JSON, которые будут переданы в CMS.
В качестве механизма для кеширования используется Redis
©2022 by Yellow Web
 */
class ywbcms extends AbstractAction
{
    protected $_name = 'ywbCMS';     // <-- Имя действия
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
        $rawClick = $this->getRawClick();
        $payload = $this->getActionPayload();
        $json = json_decode($payload,true);
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                break;
            case JSON_ERROR_DEPTH:
                $this->setContent('JSON - Maximum stack depth exceeded');
                return;
            break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->setContent('JSON - Underflow or the modes mismatch');
                return;
            break;
            case JSON_ERROR_CTRL_CHAR:
                $this->setContent('JSON - Unexpected control character found');
                return;
            break;
            case JSON_ERROR_SYNTAX:
                $this->setContent('JSON - Syntax error, malformed JSON');
                return;
            break;
            case JSON_ERROR_UTF8:
                $this->setContent('JSON - Malformed UTF-8 characters, possibly incorrectly encoded');
                return;
            break;
            default:
                $this->setContent('JSON - Unknown error');
                return;
            break;
        }
        $redisReplace = false;
        if (array_key_exists('redisReplace',$json))
        {
            $redisReplace=true;
            unset($json['redisReplace']);
        }
        //include only important stuff into key
        $cachekey = 'ywbCMS-'.http_build_query($json);

        if ($redisReplace) $json['nocache']=true; //if we want to replace - replace the whole thing without any caches involved

        // $json['nocache']=1; //prevent internal caching, we'll use Redis
        $json['subid']=$rawClick->getSubId();
        $json['campaignId']=$rawClick->getCampaignId();
        $json['ip']=$rawClick->getIpString();
        $qs = http_build_query($json);
        $url = "/local/common4/index.php?{$qs}";
        $sreq=$this->getServerRequest();
        $uri = parse_url($sreq->getUri());
        $url=$uri["scheme"]."://".$uri["host"].$url;

        $cachetime = 60*60*24; //24 hours caching
        $ktRedis = \Traffic\Redis\Service\RedisStorageService::instance();
        $redis = $ktRedis->getOriginalClient();
        $content = $redis->get($cachekey);

        if ($content===false||$redisReplace){
            $opts = [
                "localDomain" => $sreq->getHeaderLine(\Traffic\Request\ServerRequest::HEADER_HOST), 
                "url" => $url, 
                "user_agent" => $rawClick->getUserAgent(), 
                "referrer" => $this->getPipelinePayload()->getActionOption("referrer")
            ];
            $result = \Traffic\Actions\CurlService::instance()->request($opts);

            if (!empty($result["error"])) {
                $content = "Oops! Something went wrong on the requesting page:".$result["error"];
            } else {
                if ($result["status"]==200 && !empty($result["body"])) {
                    $content = $result["body"];
                    if ($redisReplace) $redis->del($cachekey);
                    $redis->set($cachekey, $content, ['nx', 'ex' => $cachetime]);
                    $this->addHeader("X-YWB-CMS: from WWW " . $url);
                    $this->addHeader("X-YWB-RKey: set ".$cachekey);
                }
                else{
                    $content = "Oops! Something went wrong on the requesting page:".$result["body"]." Code:".$result["status"];
                    $this->setStatus($result["status"]);
                }
            }
        }
        else
        {
            $this->addHeader("X-YWB-CMS: from Redis " . $url);
            $this->addHeader("X-YWB-RKey: get ".$cachekey);
        }

        $content = $this->processMacros($content);
        $content = \Traffic\Tools\Tools::utf8ize($content);
        $this->setContentType("text/html");
        $this->setContent($content);
        //$redis->publish('ywb-subs-channel',json_encode($json));
    }
}
