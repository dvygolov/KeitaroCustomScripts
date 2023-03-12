<?php
namespace Filters;

use Redis;
use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для того, чтобы неуникальный пользователь попадал на разные проклы в рамках одного потока.
Фильтр запоминает на каких промо пользователь уже был, сравнивает с теми, что есть в потоке и выключает те, что уже были использованы.
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер.
Затем устанавливаете фильтр в потоке, в котором есть несколько прокл. Проклы будут показываться последовательно, в том порядке, в котором
они установлены в потоке. Если пользователь увидел ВСЕ проклы, то он останется на самой последней.
В качестве кеша для хранения данных используется Redis. 
Время хранения посещённых прокл для уникального пользователя = сутки.
Уникальность считается по IP и UserAgent пользователя.
©2023 by Yellow Web
*/
class ywbunique extends AbstractFilter
{
    private $apiKey = "API_KEY";
    private $apiAddress = "TRACKER_DOMAIN";
    private $logDir = "/var/www/keitaro/application/filters/ywbunique";
    private $isDebug = false;
    private $ch;
    private $redis;
    private $streamCacheTime = 300; //5 minutes
    private $visitedCacheTime = 60*60*24; //24 hours

    public function __construct(){
		$this->ch = curl_init();
		$this->apiAddress .= "/admin_api/v1";
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        parent::__construct();
    }

    public function getModes()
    {
        return [
            StreamFilter::ACCEPT => LocaleService::t('filters.binary_options.' . StreamFilter::ACCEPT),
            StreamFilter::REJECT => LocaleService::t('filters.binary_options.' . StreamFilter::REJECT),
        ];
    }

    public function getTemplate()
    {
        return 'What should I rotate: 
		<select class="form-control" ng-model="filter.payload.promo">
			<option value="landings">Landings</option>
			<option value="offers">Offers</option>
			<option value="both">Both</option>
		</select>
        <br/>
        <input name="debug" type="checkbox" ng-model="filter.payload.debug">
        <label for="debug">Debug Mode</label>';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
        $campaignId = $rawClick->getCampaignId();
        $ip = $rawClick->getIpString();
        $ua = $rawClick->getUserAgent();
        $hash = md5($ip.$ua);

		$settings = $filter->getPayload();
        $this->isDebug = isset($settings['debug'])?$settings['debug']:false;

		$streamId = $filter->getStreamId();
        $streamParams = $this->get_stream_params($streamId);
		
        $landingIds = array_column($streamParams['landings'], 'landing_id');
        $visitedIds = $this->get_visited_from_cache($hash);
        $notVisitedIds = array_values(array_diff($landingIds, $visitedIds));
        $this->log("$streamId-landings", $landingIds);
        $this->log("$streamId-visited", $visitedIds);
        $this->log("$streamId-notvisited", $notVisitedIds);
        if (count($notVisitedIds)===0) { //If we visited all we should make not-visited the last one visited
            $notVisitedIds[] = end($visitedIds);
        }
        else { //else we take first not visited
            $visitedIds[] = $notVisitedIds[0];
            $this->cache_visited($hash, $visitedIds);
        }
        $selectedLandId = $notVisitedIds[0];
        $this->log("$streamId-selected", $selectedLandId);

        $landObjects = array_map(function ($l) use ($selectedLandId) {
          return (object) [
            'landing_id' => $l,
            'share' => ($l === $selectedLandId ? 100 : 0),
            'state' => 'active'
          ];
        }, $landingIds);

        $this->log("$streamId-updateparams", json_encode($landObjects));

        $this->api_update_stream($streamId, $landObjects);
				
		return ($filter->getMode() == StreamFilter::ACCEPT);
    }

    private function log($filename, $data) {
        if (!$this->isDebug) return;
        file_put_contents($this->logDir."/$filename.txt", var_export($data,true));
    }

    private function api($addrPart){
        $fullAddress = $this->apiAddress.$addrPart;
        $opts = array(
            CURLOPT_URL => $fullAddress,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array("Api-Key: $this->apiKey")
        );
        curl_setopt_array($this->ch, $opts); 
        $res=curl_exec($this->ch);
        return $res;
    }

    private function api_update_stream($streamId, $landObjects){
		$params = (object) ['landings' => $landObjects];
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($this->ch, CURLOPT_URL, $this->apiAddress."/streams/$streamId");
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Api-Key: $this->apiKey"));
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($params));		
		$res=curl_exec($this->ch);
		$report=json_decode($res,true);
		curl_close($this->ch);
        $this->log("{$streamId}-update", $res);
    }

    private function get_stream_params($streamId){
        $fromRedis = true;
        $res = $this->get_stream_from_cache($streamId);
		if ($res===false) {
            $fromRedis = false;
            $res = $this->api("/streams/$streamId");
            $this->cache_stream($streamId, $res);
        }
        $streamParams=json_decode($res,true);

        $fromWhere = $fromRedis?"redis":"db";
        $this->log("{$streamId}-{$fromWhere}", $res);
        return $streamParams;
    }

    private function get_visited_from_cache($hash){
        $cachekey = 'ywbVisited-'.$hash;
        $res =  $this->redis->get($cachekey);
        if ($res === false) {
            $this->log("$hash-getvisited", "It is False!");
            return array();
        }
        $this->log("$hash-getvisited", $res);
        $array =  explode(',', $res);
        $array = array_map ('intval', $array);
        return $array;
    }

    private function cache_visited($hash, $visitedIds){
        $str = implode(',', $visitedIds);
        $cachekey = 'ywbVisited-'.$hash;
        $this->redis->set($cachekey, $str, ['ex'=>$this->visitedCacheTime]);
        $this->log("$hash-setvisited", $str);
    }

    private function get_stream_from_cache($streamId){
        $cachekey = 'ywbEgStream-'.$streamId;
        return $this->redis->get($cachekey);
    }

    private function cache_stream($streamId, $res){
        $cachekey = 'ywbEgStream-'.$streamId;
        $this->redis->set($cachekey, $res, ['nx', 'ex'=>$this->streamCacheTime]);
    }
}
