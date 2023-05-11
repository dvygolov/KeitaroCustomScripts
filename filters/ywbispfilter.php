<?php

namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

require_once '/var/www/keitaro/application/filters/geoip2.phar';

use GeoIp2\Database\Reader;

/*
Кастомный фильтр для Кейтаро для фильтрации по ISP, используя бесплатную базу MaxMind ASN
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, в поле пишите (не)нужные вам ISP через запятую БЕЗ пробела, 
те IP, что не смогли определиться по базе будут от ISP с именем Unknown
Статья по работе с фильтром: https://vk.com/@yellowweb-dobavlyaem-v-keitaro-kastomnyi-filtr-dlya-filtracii-po-isp-p
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
        $sub_id_num = 7; //Поменяйте здесь номер суб-метки для записи в неё ISP
        $isp_str = $filter->getPayload(); //получаем все разрешённые/запрещённые ISP
        $isps = explode(',', $isp_str); //
        $reader = new Reader('/var/www/keitaro/application/filters/GeoLite2-ASN.mmdb');
        $ip = $rawClick->getIpString();

        try {
            $record = $reader->asn($ip);
            $cur_isp = $record->autonomousSystemOrganization;
        } catch (Exception $e) {
            $cur_isp = 'Unknown';
        }

        //file_put_contents("/var/www/keitaro/application/filters/isplog.txt",$ip." ".$cur_isp."\n",FILE_APPEND); //для отладки

        $hasMatches = false;
        foreach ($isps as $isp) {
            if (!empty(stristr($cur_isp, $isp))) {
                $hasMatches = true;
                break;
            }
        }

        //записываем у клика в выбранную суб-метку данные об ISP
        $subid = $rawClick->getSubId();
        //получаем текущий адрес
        $sreq = $this->getServerRequest();
        $uri = explode('?', urldecode($sreq->getUri()))[0];
        //file_put_contents("/var/www/keitaro/application/filters/isplog.txt",$uri."\n",FILE_APPEND); //для отладки
        $params = array();
        $params['_update_tokens'] = 1;
        $params['sub_id'] = $subid;
        $params['sub_id_' . $sub_id_num] = $cur_isp;
        $qs = http_build_query($params);
        $url = $uri . "?{$qs}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        $result = json_decode($res, true);
        curl_close($ch);

        return ($filter->getMode() == StreamFilter::ACCEPT && $hasMatches)
            || ($filter->getMode() == StreamFilter::REJECT && !$hasMatches);
    }
}
