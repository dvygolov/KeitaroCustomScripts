<?php

namespace Macros;

use Traffic\Model\Stream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro)

  This macro is for printing the group of the current campaign
  Usually campaign group = the mark of your mediabuyer, so this macro can be used
  to send the buyer's mark in the form with the lead data.

  Usage: Fill $apiKey with the Keitaro's api key.
  {campaign_group} will return the current campaign's group

  Author: Anonymous web from Yellow Web Chat(http://t.me/yellowwebchat)
 */

class campaign_group extends AbstractClickMacro
{
    private $apiKey = "";
    private $apiAddress = "http://127.0.0.1/admin_api/v1/";

    public function getCampaignGroup($campaignId)
    {
        $fullAddress = $this->apiAddress . 'campaigns/' . $campaignId;
        $campaignParams = $this->execCurl($fullAddress);
        return $campaignParams['group_id'];
    }

    public function getGroupName($groupId)
    {
        $fullAddress = $this->apiAddress . 'groups?type=campaigns';
        $groups = $this->execCurl($fullAddress);

        foreach ($groups as $group) {
            if ($group['id'] === $groupId) {
                return $group['name'];
            }
        }

        return "No Group Found!";
    }

    private function execCurl($fullAddress)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $fullAddress);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: ' . $this->apiKey));
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }

    public function process(?Stream $stream, RawClick $click)
    {
        $campaignId = $stream->getCampaignId();
        $groupId = $this->getCampaignGroup($campaignId);
        return $this->getGroupName($groupId);
    }
}
