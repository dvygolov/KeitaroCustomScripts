<?php
namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для работы с капами по проклам.
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, в поле пишите кап.
Фильтр проверяет общее кол-во лидов по ВСЕМ проклам в потоке
Если общее кол-во лидов за сегодня по этим проклам меньше заданного капа - пропускаем траф.
В качестве механизма для кеширования используется Redis
©2022 by Yellow Web
 */
class ywbcapfilter extends AbstractFilter
{
    public function getModes()
    {
        return [
            StreamFilter::ACCEPT => LocaleService::t('filters.binary_options.' . StreamFilter::ACCEPT),
            StreamFilter::REJECT => LocaleService::t('filters.binary_options.' . StreamFilter::REJECT),
        ];
    }

    public function getTemplate()
    {
        return 'Кол-во лидов: <input class="form-control" ng-model="filter.payload.cap" />';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
		$apiKey="<YOUR_APIKEY>";
		$apiAddress="http://<YOUR_TRACKER_ADDRESS>/admin_api/v1/";
		//здесь меняем пояс, если ваш часовой пояс не Москва!!!
		$tz='Europe/Moscow';
        //Дальше ничего не трогаем, если не умеем!!!
		date_default_timezone_set($tz);

        $cachetime = 300; // 5 минут
        
        $ktRedis = \Traffic\Redis\Service\RedisStorageService::instance();
        $redis = $ktRedis->getOriginalClient();

        $ch = curl_init();
        $streamId=$filter->getStreamId();
        $cachekey = 'ywbCapFilter-stream-'.$streamId;
        $res = $redis->get($cachekey);
        if ($res===false){
            //запрашиваем все данные по потоку, чтобы вынуть из него идентификаторы офферов
            $fullAddress=$apiAddress.'streams/'.$streamId;
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $fullAddress);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
            $res = curl_exec($ch);
            $redis->set($cachekey, $res, ['nx', 'ex'=>$cachetime]);
		    /* file_put_contents("/var/www/keitaro/application/filters/capFilterCache/stream_from_db.txt", $res); //отладка */
        }
        /* else{ */
		    /* file_put_contents("/var/www/keitaro/application/filters/capFilterCache/stream_from_redis.txt", $res); //отладка */
        /* } */
        $streamParams = json_decode($res,true);

		//вынимаем идентификаторы лендов
		$landingIds=[];
		foreach($streamParams['landings'] as $landing)
		{
			array_push($landingIds,$landing['landing_id']);
		}

        sort($landingIds);

        $cachekey = 'ywbCapFilter-landings-'.implode(",",$landingIds);
        $res = $redis->get($cachekey);
		if ($res===false) {
            //запрашиваем отчёт по кол-ву лидов у наших лендингов за сегодня
            $params = [
                'columns' => [],
                'metrics' => ['conversions'],
                'filters' => [
                    ['name' => 'landing_id', 'operator' => 'IN_LIST', 'expression' => $landingIds]
                ],
                'grouping' => ['landing'],
                'range' => [
                    'timezone' => $tz,
                    'from' => date('Y-m-d'),
                    'to' => date('Y-m-d')
                ]
            ];
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $apiAddress.'report/build');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
            $res=curl_exec($ch);
            $redis->set($cachekey, $res, ['nx', 'ex'=>$cachetime]);
		    /* file_put_contents("/var/www/keitaro/application/filters/capFilterCache/rep_from_db.txt", $res); //отладка */
        }
        /* else{ */
		    /* file_put_contents("/var/www/keitaro/application/filters/capFilterCache/rep_from_redis.txt", $res); //отладка */
        /* } */
        $report = json_decode($res, true);

		$totalLeads=0;
		foreach($report['rows'] as $row)
		{
			$totalLeads+= $row['conversions'];
		}
		
        //взяли кап из настроек фильтра
		$cap = $filter->getPayload()["cap"];
		
        return ($filter->getMode() == StreamFilter::ACCEPT && $totalLeads<$cap)
            || ($filter->getMode() == StreamFilter::REJECT && $totalLeads>=$cap);
    }
}
