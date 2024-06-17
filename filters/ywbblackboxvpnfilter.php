<?php

namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/* Custom filter for Keitaro tracker that filters traffic using blackbox.ipinfo.app service.
Copy ther filter's filt to /var/www/keitaro/application/filters and then relogin.
©2024 by Yellow Web https://yellowweb.top
 */

class ywbblackboxvpnfilter extends AbstractFilter
{
    public function getModes()
    {
        return [
            StreamFilter::ACCEPT => LocaleService::t('filters.binary_options.' . StreamFilter::ACCEPT),
            StreamFilter::REJECT => LocaleService::t('filters.binary_options.' . StreamFilter::REJECT),
        ];
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
        //Change the sub number here, so the filter will save if the click is Proxy/VPN to this sub
        $sub_id_num = 20;

        $ip = $rawClick->getIpString();
        $url = 'https://blackbox.ipinfo.app/lookup/';
        $res = $this->curlGet($url . $ip);

        $bbYes = false;
        if ($res['content'] === 'Y') {
            $bbYes = true;
        }

        //writing VPN check result to sub id 17
        $subid = $rawClick->getSubId();
        //getting current address
        $sreq = $this->getServerRequest();
        $uri = explode('?', urldecode($sreq->getUri()))[0];
        $params = array();
        $params['_update_tokens'] = 1;
        $params['sub_id'] = $subid;
        $params['sub_id_' . $sub_id_num] = ($bbYes ? 'Y' : 'N');
        $qs = http_build_query($params);
        $url = $uri . "?{$qs}";
        $this->curlGet($url);

        return ($filter->getMode() == StreamFilter::ACCEPT && $bbYes)
            || ($filter->getMode() == StreamFilter::REJECT && !$bbYes);
    }

    private function curlGet($url): array
    {
        $curl = curl_init();
        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_REFERER => $_SERVER['REQUEST_URI'],
            CURLOPT_USERAGENT => 'YWB BlackBox VPN Filter'
        );
        curl_setopt_array($curl, $optArray);
        $content = curl_exec($curl);
        $info = curl_getinfo($curl);
        $error = curl_error($curl);
        curl_close($curl);
        return ["content" => $content, "info" => $info, "error" => $error];
    }
}
