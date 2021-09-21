<?php
namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;
require_once 'geoip2.phar';
use GeoIp2\Database\Reader;

/*
Кастомный фильтр для Кейтаро для фильтрации по ISP, используя бесплатную базу MaxMind ASN
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, в поле пишите (не)нужные вам ISP через запятую БЕЗ пробела
©2021 by Yellow Web
 */
class ywbispfilter extends AbstractFilter
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
        return '<input class="form-control" ng-model="filter.payload" />';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
		$isp_str = $filter->getPayload(); //получаем все разрешённые/запрещённые ISP
		$isps = explode(',',$isp_str); //
		$reader = new Reader(__DIR__.'/GeoLite2-ASN.mmdb');
		$ip = $rawClick->getIpString();
		$record = $reader->asn($ip);
		$cur_isp=$record->autonomousSystemOrganization;
		//file_put_contents(__DIR__."/isplog.txt",$ip." ".$cur_isp."\n",FILE_APPEND);
		
		$hasMatches = false;
		foreach($isps as $isp){
			if(!empty(stristr($cur_isp,$isp))){
				$hasMatches=true;
				break;
			}
		}
		
		return ($filter->getMode() == StreamFilter::ACCEPT && $hasMatches)
            || ($filter->getMode() == StreamFilter::REJECT && !$hasMatches);
    }
}