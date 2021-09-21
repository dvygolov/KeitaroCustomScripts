<?php
namespace Redirects;

use Traffic\Actions\AbstractAction;

class epsilongreedy extends AbstractAction
{
    protected $_name = 'EpsilonGreedy';
    protected $_weight = 100;
	
	public function getType()
    {
        return self::TYPE_REDIRECT;
    }
	
    protected function _execute()
    {
		$apiKey='<YOURAPIKEY>';
		$apiAddress='<YOURTRACKERDOMAIN>';
    	$tz='Europe/Samara'; //здесь меняем пояс, если ваш часовой пояс не Москва!!!
		$explorationPercent=10; //процент случаев, когда будет выбираться не лучшая прокла, а рандомная. Можно увеличить/уменьшить по вкусу.
    	
		//дальше ничего не трогаем
		$egparam='eg';
    	$apiAddress=$apiAddress.'/admin_api/v1';
		$defaultMetric='lp_ctr';
		$metric=$this->getActionPayload();
		$allowedMetrics=array('lp_ctr','epc_confirmed','cr','crs');
		if (!in_array($metric,$allowedMetrics))
			$metric=$defaultMetric;
		$rawClick = $this->getRawClick();
		$cid=$rawClick->getCampaignId(); //идентификатор текущей кампании
		$sid=$rawClick->getStreamId(); //идентификатор текущего потока
		//$this->setContent('Campaign ID:'.$cid); //логирование для отладки
		
		//запрашиваем все данные по потокам кампании, чтобы вынуть из потоков идентификаторы прокл
		$fullAddress=$apiAddress.'/campaigns/'.$cid.'/streams';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_URL, $fullAddress);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
		$res=curl_exec($ch);
		$campStreams=json_decode($res,true);
		//$this->setContent('Campaign Streams:'.$res); //логирование для отладки

		//вынимаем идентификаторы всех прокл из тех потоков, у которых стоит параметр eg типа: eg=1234 
		$landingIds=[];
		foreach($campStreams as $stream){
			if ($stream['id']===$sid) continue;
			foreach($stream['filters'] as $filter){
				if ($filter['name']==='parameter'&&
					$filter['payload']['name']===$egparam&&
					$filter['mode']==='accept')
				{
					$landingId=$stream['landings'][0]['landing_id'];
					//$landingId=$filter['payload']['value'][0];
					array_push($landingIds,$landingId);
					break;
				}
			}
		}
		//$this->setContent('Landing IDs:'.implode(',',$landingIds).$log); //логирование для отладки		

		//Получаем текущий адрес
		$sreq=$this->getServerRequest();
		$uri=urldecode($sreq->getUri());
		$delimiter='?';
		if (strpos($uri,'&')!==false) $delimiter='&';
		//$this->setContent('Current URL:'.$uri); //логирование для отладки

		$random=rand(1,100);
		if ($random<=$explorationPercent){ //в $explorationPercent случаев выбираем рандомную проклу
			$random=rand(1,count($landingIds))-1;
			$randomLandId=$landingIds[$random];
			$this->redirect($uri.$delimiter.'eg='.$randomLandId);
		}
		else{ //в остальных случаях выбираем лучшую по выбранному показателю (по умолчанию LP CTR)
			//запрашиваем отчёт по нашим проклам за сегодня
			$ch = curl_init();
			date_default_timezone_set($tz);
			$params = [
				'columns' => [],
				'metrics' => [$metric],
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
			curl_setopt($ch, CURLOPT_URL, $apiAddress.'/report/build');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$apiKey));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));		
			$res=curl_exec($ch);
			$report=json_decode($res,true);
			//$this->setContent(var_export($params,true).'LPCTR Report:'.$res); //логирование для отладки
			
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
				$random=rand(1,count($landingIds))-1;
				$bestLandId=$landingIds[$random];
				//$this->setContent('Metrics ARE EQUAL to 0, got random landing:'.$bestLandId); //логирование для отладки
			}
			//$this->setContent('Best Landing ID:'.$bestLandId.' Metric value:'.$bestMetric); //логирование для отладки
			$this->redirect($uri.$delimiter.'eg='.$bestLandId);
		}
	}
}
