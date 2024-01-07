<?php
namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для фильтрации по домену (или поддоменам)
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, в поле пишите нужный вам домен без http/https,
либо несколько доменов подряд через запятую без пробелов. Если хотите, чтобы
фильтр срабатывала на ВСЕ поддомены домена, то пишите: *.вашдомен.ком
©2021-2024 by Yellow Web
 */
class ywbdomainfilter extends AbstractFilter
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
        return 'Домен(ы) (без http и www): <input class="form-control" ng-model="filter.payload.domain" />';
    }

    public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
        $curdomain = $_SERVER['SERVER_NAME'];
        $filterdomains = $filter->getPayload();
        $domains = explode(',', $filterdomains["domain"]);
        $match = false;

        foreach ($domains as $domain) {
            if (strpos($domain, '*.') === 0) {
                // Remove wildcard and dot to get the main domain
                $mainDomain = substr($domain, 2);

                // Check if current domain is a subdomain of the main domain
                if (strpos($curdomain, $mainDomain) !== false && 
                    $curdomain !== $mainDomain) {
                    $match = true;
                    break;
                }
            } else {
                // Direct match
                if ($curdomain === $domain) {
                    $match = true;
                    break;
                }
            }
        }

        return ($filter->getMode() == StreamFilter::ACCEPT && $match)
            || ($filter->getMode() == StreamFilter::REJECT && !$match);
    }
}
