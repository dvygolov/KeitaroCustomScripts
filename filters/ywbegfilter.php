<?php
namespace Filters;

use Redis;
use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для реализации работы Эпсилон-жадного алгоритма многоруких бандитов.
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер.
Устанавливаете фильтр в потоке, в поле пишите метрику, по которой будет выбираться лучшая прокла:
lp_ctr, epc_confirmed, cr, crs.
Также выбираете за сколько дней брать статистику, брать ли ей общую, только по вашей кампании или общую,
но с учётом крео (для работы настройки по крео имя креатива должно храниться в трекере в поле creative_id).
Последним выбираете процент рандома, для начала сойдёт 10%, далее можете экспериментировать.
В качестве кеша для хранения полученных из БД Кейтаро используется Redis. Время хранения записей кеша = 5 минут.
©2022 by Yellow Web
*/
class ywbegfilter extends AbstractFilter
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
        return 'Метрика для выявления лучшей проклы: 
		<select class="form-control" ng-model="filter.payload.metric">
			<option value="lp_ctr">LP CTR</option>
			<option value="epc_confirmed">EPC</option>
			<option value="cr">CR</option>
			<option value="crs">CRs</option>
		</select>
		<br/>
        За сколько дней брать стату для подсчёта лучшей метрики: <input type="number" class="form-control" ng-model="filter.payload.days" placeholder="Кол-во дней"/>
        <br/>
        Использовать стату: 
		<select class="form-control" ng-model="filter.payload.statistics">
			<option value="all">Общую</option>
			<option value="campaign">Этой кампании</option>
			<option value="creative">По крео</option>
        </select>
		<br/>
        Процент рандома: <input type="number" class="form-control" ng-model="filter.payload.percent" placeholder="Процент рандома"/>';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
		$apiKey="<YOUR_API_KEY>";
		$apiAddress="http://<YOUR_TRACKER_DOMAIN>";
		$tz='Europe/Moscow'; //здесь меняем пояс, если ваш часовой пояс не Москва!!!
		
		//дальше ничего не трогаем, если не умеем!
		$metric = 'lp_ctr';
		$days = 1;
		$explorationPercent = 10; 
        $statistics = 'all'; 
		date_default_timezone_set($tz);

        $logDir = '/var/www/keitaro/application/filters/ywbegfilter';
        $campaignId = $rawClick->getCampaignId();
        $adminCampaignId=-1; //Id of the campaign that we'll use to debug the filter

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $cachetime = 300; // 5 минут
		//взяли настройки из настроек фильтра
		$settings= $filter->getPayload();
		if (isset($settings['percent']))
			$explorationPercent=$settings['percent'];
		if (isset($settings['metric']))
			$metric=$settings['metric'];
		if (isset($settings['days']))
			$days = $settings['days'];
		if (isset($settings['statistics']))
			$statistics = $settings['statistics'];

		$ch = curl_init();
		$apiAddress = "$apiAddress/admin_api/v1";
		$streamId = $filter->getStreamId();
		
        $cachekey = 'ywbEgStream-'.$streamId;
        $res = $redis->get($cachekey);
        $fromRedis = true;
		if ($res===false) {
            $fromRedis = false;
            //запрашиваем все данные по потоку, чтобы вынуть из него идентификаторы лендингов
            $fullAddress = "$apiAddress/streams/$streamId";
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_URL, $fullAddress);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Api-Key: $apiKey"));
            $res=curl_exec($ch);
            $redis->set($cachekey, $res, ['nx', 'ex'=>$cachetime]);
        }
        $streamParams=json_decode($res,true);

        //logging
        $fromWhere = $fromRedis?"redis":"db";
        if ($campaignId === $adminCampaignId)
            file_put_contents("$logDir/$cachekey-$fromWhere.txt", json_encode($streamParams,JSON_PRETTY_PRINT));

		//вынимаем идентификаторы лендов
		$landingIds=[];
        try{
            foreach($streamParams['landings'] as $landing)
            {
                array_push($landingIds,$landing['landing_id']);
            }
        }
        catch(Exception $e){
            file_put_contents($logDir."/eg_errors.txt", 
                "Campaign Id:".$campaignId." Settings".$explorationPercent.' '.$metric.' '.$days , FILE_APPEND); //отладка
        }
        sort($landingIds);


		$selectedLandId=-1;
		$random=rand(1,100);
        $selectReason = "random";
		if ($random<=$explorationPercent){ //в $explorationPercent случаев выбираем рандомную проклу
			$selectedLandId=$landingIds[array_rand[$landingIds]];
		}
        else{ 
            $selectReason = "algorythm";
            $cachekey = 'ywbEgFilterlandings-'.$statistics.'-'.implode(",",$landingIds);
            $res = $redis->get($cachekey);
            $fromRedis = true;
            if ($res===false) {
                $fromRedis = false;
                //получаем страну, чтобы потом построить отчёт только по нужной стране
                $country=$rawClick->getCountry();
                //в остальных случаях выбираем лучшую по выбранному показателю
                //запрашиваем отчёт по нашим проклам за нужное кол-во дней
                $days-=1;
                $from= date("Y-m-d", strtotime("-".$days." day"));
                $params = [
                    'columns' => [],
                    'metrics' => [$metric],
                    'filters' => [
                        ['name' => 'landing_id', 'operator' => 'IN_LIST', 'expression' => $landingIds],
                    ],
                    'grouping' => ['landing_id'],
                    'range' => [
                        'timezone' => $tz,
                        'from' => $from,
                        'to' => date('Y-m-d')
                    ]
                ];

                if ($campaignId!==$adminCampaignId)
                    array_unshift($params['filters'], 
                        ['name' => 'country_code', 'operator'=> 'EQUALS', 'expression'=> $country]);

                if ($statistics==='campaign'){
                    array_unshift($params['filters'], 
                        ['name' => 'campaign_id', 'operator' => 'EQUALS', 'expression' => $campaignId]);
                }
                else if ($statistics==='creative'){
                    $creativeName = $rawClick->getCreativeId();
                    if (isset($creativeName) && $creativeName!=="")
                        array_unshift($params['filters'], 
                            ['name' => 'creative_id', 'operator' => 'EQUALS', 'expression' => $creativeName]);
                }

                if ($campaignId===$adminCampaignId)
                    file_put_contents("$logDir/$cachekey-query.txt", json_encode($params,JSON_PRETTY_PRINT)); //отладка
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                curl_setopt($ch, CURLOPT_URL, $apiAddress.'/report/build');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
                $res=curl_exec($ch);
                $redis->set($cachekey, $res, ['nx', 'ex'=>$cachetime]);
            }
            $report=json_decode($res,true);
            
            //logging
            $fromWhere = $fromRedis?"redis":"db";
            if ($campaignId===$adminCampaignId)
                file_put_contents("$logDir/$cachekey-$fromWhere.txt", json_encode($report,JSON_PRETTY_PRINT)); //отладка
			
			//выбираем лучшую проклу по показателям
			$bestMetric=0;
			$bestLandId=0;
			foreach($report['rows'] as $row)
			{
				if ($row[$metric]>$bestMetric)
				{
					$bestMetric=$row[$metric];
					$bestLandId=$row['landing_id'];
				}
			}

			if ($bestLandId===0) {
				//ситуация, когда у нас все показатели равны 0, берём рандомную
			    $bestLandId=$landingIds[array_rand[$landingIds]];
                $selectReason = "allequalrand";
			}
			$selectedLandId=$bestLandId;
		}
				
		//ставим в текущем потоке 100% трафа на выбранную проклу, и 0% для всех остальных
		$landObjects=[];
		foreach($landingIds as $l)
		{
			$share = $l==$selectedLandId?100:0;
			
			$landObj = (object) [
				'landing_id' => $l,
				'share' => $share,
				'state'=> 'active'
			];
			array_push($landObjects,$landObj);
		}
		
        //logging
        if ($campaignId===$adminCampaignId)
        {
            $landStr = implode(",",$landingIds);
            file_put_contents("$logDir/$landStr-$selectReason-$selectedLandId-result.txt", json_encode($landObjects,JSON_PRETTY_PRINT)); //отладка
        }

		if (count($landObjects)>0){
			$params = (object) ['landings' => $landObjects];
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_URL, "$apiAddress/streams/$streamId");
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Api-Key: $apiKey"));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
			$res=curl_exec($ch);
			$report=json_decode($res,true);
			curl_close($ch);
		}
				
		return ($filter->getMode() == StreamFilter::ACCEPT);
    }
}
