<?php
$trackerUrl="";
$trackerApiKey="";
$extraParams=[
  'scripts'=>'extra_param_10',
  'person'=>'extra_param_8',
  't'=>'extra_param_7',
  'gender'=>'extra_param_6',
  'vertical'=>'extra_param_5',
  'form'=>'extra_param_4',
  'product'=>'extra_param_3',
  'text'=>'extra_param_2',
  'lang'=>'extra_param_1'
];

echo "Crazy Profits Agency click's subs updater by Yellow Web\n";
echo "Running...\n";
ini_set('default_socket_Timeout', -1); // PHP configuration does not time out
$redis = new Redis();
$redis->connect("127.0.0.1",6379);
$redis->setOption(Redis::OPT_READ_TIMEOUT, -1); // redis mode does not time out. Recommended

$redis->subscribe(['ywb-subs-channel'],'callback'); // callback is the name of the callback function

//Callback function, write processing logic here
function callback($instance, $channelName, $message)
{
  echo $channelName, "==>", $message, PHP_EOL;
  $json = json_decode($message, true);
  $result = update_click_params($json['campaignId'], $json['subid'], $json);
  echo "Update click params for subid:".$json['subid']." - ".$result;
}

function update_click_params($campaignId,$subid,$gs)
{
  global $extraParams, $trackerUrl;
  $campaignAlias=get_campaign_alias_by_id($campaignId);
  $params=array();
  $params['_update_tokens']=1;
  $params['sub_id']=$subid;
  foreach ($gs as $name => $value) {
    if (array_key_exists($name,$extraParams))
      $params[$extraParams[$name]]=$value;
  }

  $qs=http_build_query($params);
  $url=$trackerUrl.$campaignAlias."?{$qs}";
	$ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_URL, $url);
  $res=curl_exec($ch);
  $result=json_decode($res,true);
  curl_close($ch);
  return $result;
}

function get_campaign_alias_by_id(string $campaignId):string
{
  $json=adminapi_request("campaigns/{$campaignId}");
  return $json['alias'];
}

function adminapi_request(string $address,array $params=null)
{
  global $trackerUrl,$trackerApiKey;
	$ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
  curl_setopt($ch, CURLOPT_URL, $trackerUrl."admin_api/v1/{$address}");
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Api-Key: '.$trackerApiKey));
  if (isset($params)){
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }
    $res=curl_exec($ch);
    $result=json_decode($res,true);
    curl_close($ch);
    return $result;
}
