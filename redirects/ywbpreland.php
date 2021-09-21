<?php
//Скрипт кастомного Action для трекера Кейтаро by Yellow Web ©2020
//Экшн позволяет использовать любую проклу из интернета, как если бы она была у вас локально загружена в трекер
//Использование:
//Создать Лендинг, указать в Схеме Действие - Украсть проклу, указать адрес по которому висит прокла
//Создать кампанию в трекере с этим Лендингом, добавить Оффер, лить в плюс!
namespace Redirects;

use Traffic\Actions\AbstractAction;

class ywbPreland extends AbstractAction
{
    protected $_name = 'Украсть проклу';
    protected $_weight = 100;

	public function getType()
    {
        return self::TYPE_OTHER; 
    }    
	
	protected function _execute()
    {

		$url = $this->getActionPayload();
		$curl = curl_init();
		$optArray = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_FOLLOWLOCATION => true);
		curl_setopt_array($curl, $optArray);
		$html = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);
		$html=preg_replace('/(<a[^>]+href=\")([^"]+)/i','$1{offer}',$html);
		$html=$this->processMacros($html);
		$this->setContent($html);
    }
}