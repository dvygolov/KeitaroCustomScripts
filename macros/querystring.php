<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/**
 * This macro get's the request's querystring and outputs it
 */
class querystring extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click)
    {
    	$arr=explode('?',$_SERVER['REQUEST_URI']);
        if (count($arr)!==2) return '';
    	return $arr[1];
    }
}