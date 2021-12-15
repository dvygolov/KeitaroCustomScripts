<?php
namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для реализации работы Эпсилон-жадного алгоритма многоруких бандитов.
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, выбираете метрику, по которой будет выбираться лучший лендинг:
lp_ctr, epc_confirmed, cr, crs, далее выбираете кол-во дней за которые будет производиться сравнение, 
где 1 - это сегодня, 2 - за вчера и сегодня и т.д. Последним выбираете процент рандома: т.е. какое кол-во трафа будет отправлено на рандомные проклы, а не на лучшие. На занижайте ниже 10! Больше можно, меньше 
не стоит. Далее делаете всё то же самое, но для офферов (только у них, ясное дело, нет lp_ctr).

©2021 by Yellow Web (https://yellowweb.top)
 */
class ywbegfullfilter extends AbstractFilter
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
        return 'Метрика для выявления лучшего лендинга: 
		<select class="form-control" ng-model="filter.payload.lmetric">
			<option value="lp_ctr">LP CTR</option>
			<option value="epc_confirmed">EPC</option>
			<option value="cr">CR</option>
			<option value="crs">CRs</option>
		</select>
		<br/>
        За сколько дней брать стату для подсчёта лучшей метрики: <input type="number" class="form-control" ng-model="filter.payload.ldays" placeholder="1"/>
		<br/>
        Процент рандома: <input type="number" class="form-control" ng-model="filter.payload.lpercent" placeholder="10"/>
        <br/>
        <hr/>
        Метрика для выявления лучшего оффера: 
		<select class="form-control" ng-model="filter.payload.ometric">
			<option value="epc_confirmed">EPC</option>
			<option value="cr">CR</option>
			<option value="crs">CRs</option>
		</select>
		<br/>
        За сколько дней брать стату для подсчёта лучшей метрики: <input type="number" class="form-control" ng-model="filter.payload.odays" placeholder="1"/>
		<br/>
        Процент рандома: <input type="number" class="form-control" ng-model="filter.payload.opercent" placeholder="10"/>';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
		$apiKey="<YOUR_API_KEY>";
		$apiAddress="http://<YOUR_TRACKER_DOMAIN>";
		$tz='Europe/Moscow'; //здесь меняем пояс, если ваш часовой пояс не Москва!!!
		
		//дальше ничего не трогаем, если не умеем!
		$lMetric='lp_ctr';
		$oMetric='cr';
		$lDays=1;
		$oDays=1;
		$lExplorationPercent=10; //сколько процентов трафа отправлять на рандомный ленд
		$oExplorationPercent=10; //сколько процентов трафа отправлять на рандомный оффер
		date_default_timezone_set($tz);

		//взяли настройки из настроек фильтра
		$settings= $filter->getPayload();
		if (isset($settings['lpercent']))
			$lExplorationPercent=$settings['lpercent'];
		if (isset($settings['lmetric']))
			$lMetric=$settings['lmetric'];
		if (isset($settings['ldays']))
			$lDays=$settings['ldays'];

		if (isset($settings['opercent']))
			$oExplorationPercent=$settings['opercent'];
		if (isset($settings['ometric']))
			$oMetric=$settings['ometric'];
		if (isset($settings['odays']))
			$oDays=$settings['odays'];
		//file_put_contents("/var/www/keitaro/application/filters/fulleg.txt",$explorationPercent.' '.$metric.' '.$days); //отладка

		$apiAddress=$apiAddress.'/admin_api/v1';
		$streamId=$filter->getStreamId();
		
		//запрашиваем все данные по потоку, чтобы вынуть из него идентификаторы лендингов
		$fullAddress=$apiAddress.'/streams/'.$streamId;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_URL, $fullAddress);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
		$res=curl_exec($ch);
		$streamParams=json_decode($res,true);
		curl_close($ch);

		//вынимаем идентификаторы лендов и офферов
		$landingIds=[];
		foreach($streamParams['landings'] as $landing)
		{
			array_push($landingIds,$landing['landing_id']);
		}
		$offerIds=[];
		foreach($streamParams['offers'] as $offer)
		{
			array_push($offerIds,$offer['offer_id']);
		}

		//определяем анонимные функции, поскольку Кейтаро не даёт создавать в классе фильтр
		//дополнительные методы у класса

	 $epsilon_greedy=function($itemIds,$explorationPercent,$days,$metric,$idName,$groupName,$tz,$apiKey,$apiAddress){
		$selectedItemId=-1;
		$random=rand(1,100);
		if ($random<=$explorationPercent){ //в $explorationPercent случаев выбираем рандомный вариант
			$random=rand(1,count($itemIds))-1;
			$selectedItemId=$itemIds[$random];
		}
		else{ //в остальных случаях выбираем лучшую по выбранному показателю (по умолчанию LP CTR)
			//запрашиваем отчёт по нашим проклам за нужное кол-во дней
			$days-=1;
			$from= date("Y-m-d", strtotime("-".$days." day"));
			$params = [
				'columns' => [],
				'metrics' => [$metric],
				'filters' => [
					['name' => $idName, 'operator' => 'IN_LIST', 'expression' => $itemIds]
				],
				'grouping' => [$groupName],
				'range' => [
					'timezone' => $tz,
					'from' => $from,
					'to' => date('Y-m-d')
				]
			];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_URL, $apiAddress.'/report/build');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
			$res=curl_exec($ch);
			$report=json_decode($res,true);
			curl_close($ch);
			//file_put_contents("/var/www/keitaro/application/filters/fulleg.txt",$res); //отладка
			
			//выбираем лучший вариант по показателям
			$bestMetric=0;
			$bestItemId=0;
			foreach($report['rows'] as $row)
			{
				if ($row[$metric]>$bestMetric)
				{
					$bestMetric=$row[$metric];
					$bestItemId=$row[$idName];
				}
			}
			if ($bestItemId===0) {
				//ситуация, когда у нас все показатели равны 0, берём рандомный вариант
				$random=rand(1,count($itemIds))-1;
				$bestItemId=$itemIds[$random];
			}
			$selectedItemId=$bestItemId;
		}
		return $selectedItemId;
	};

	//ставим в текущем потоке 100% трафа на выбранный вариант, и 0% для всех остальных
	$set_selected=function($itemIds,$selectedItemId,$idName,$groupName,$streamId,$apiKey,$apiAddress){
		$itemObjects=[];
		foreach($itemIds as $item)
		{
			$share=($item==$selectedItemId?100:0);
			
			$itemObj = (object) [
				$idName => $item,
				'share' => $share,
				'state'=> 'active'
			];
			array_push($itemObjects,$itemObj);
		}
		
		if (count($itemObjects)>0){
			$params = (object) [$groupName => $itemObjects];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_URL, $apiAddress.'/streams/'.$streamId);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
			$res=curl_exec($ch);
			$report=json_decode($res,true);
			curl_close($ch);
		}
	};


	if (count($landingIds)>0)
	{
		$selectedLandId=$epsilon_greedy($landingIds,$lExplorationPercent,$lDays,$lMetric,'landing_id','landing',$tz,$apiKey,$apiAddress);
		$set_selected($landingIds,$selectedLandId,'landing_id','landings',$streamId,$apiKey,$apiAddress);
	}

	if (count($offerIds)>0){
		$selectedOfferId=$epsilon_greedy($offerIds,$oExplorationPercent,$oDays,$oMetric,'offer_id','offer',$tz,$apiKey,$apiAddress);
		$set_selected($offerIds,$selectedOfferId,'offer_id','offers',$streamId,$apiKey,$apiAddress);
	}
	
		return ($filter->getMode() == StreamFilter::ACCEPT);
	}
}