<?php
namespace Filters;

use Core\Filter\AbstractFilter;
use Core\Locale\LocaleService;
use Traffic\Model\StreamFilter;
use Traffic\RawClick;

/*
Кастомный фильтр для Кейтаро для фильтрации по домену
Скопировать файл фильтра в папку application\filters затем перелогиниться в трекер
Устанавливаете фильтр в потоке, в поле пишите нужный вам домен без http/https.
©2021 by Yellow Web
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
        return '<input class="form-control" ng-model="filter.payload" />';
    }

	public function isPass(StreamFilter $filter, RawClick $rawClick)
    {
		$curdomain = $_SERVER['SERVER_NAME'];
		$filterdomains = $filter->getPayload();
		$domains = explode(',',$filterdomains);
		$match = in_array($curdomain,$domains);
			
		return ($filter->getMode() == StreamFilter::ACCEPT && $match)
			|| ($filter->getMode() == StreamFilter::REJECT && !$match);
    }
}