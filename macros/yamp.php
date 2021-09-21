<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds Yandex Metrika code. 
  Use it in your landings. For example: {_yam:31113111,'WeightLossOffer'}
  First parameter is Metrika's Id
  Second parameter is a custom parameter that you want to add to each visit, for example, the offer's name
  Put this macro right after the <body> tag.
  Author: Yellow Web (http://yellowweb.top)
 */
class yamp extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $id, $param)
    {
		return "
<!-- Yandex.Metrika counter -->
<script type='text/javascript' >
   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
   m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
   (window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');

   ym({$id}, 'init', {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
   });
   ym({$id},'params',{offer:'{$param}'});
</script>
<noscript><div><img src='https://mc.yandex.ru/watch/{$id}' style='position:absolute; left:-9999px;' alt='' /></div></noscript>
<!-- /Yandex.Metrika counter -->";
    }
}