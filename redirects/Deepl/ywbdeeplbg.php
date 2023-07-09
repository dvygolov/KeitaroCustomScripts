<?php

require_once(__DIR__.'/vendor/autoload.php');

class DeeplBackgroundTranslator
{
  protected $deeplApiKey  = "";  //ENTER YOUR DEEPL API KEY HERE
  protected $translator;

  function __construct(){
    echo "Deepl Background Translator v3.0 by Yellow Web\n";
    echo "Running...\n";
    ini_set('default_socket_Timeout', -1); // PHP configuration does not time out

    $logfile = '/home/keitaro/deepl/deepllib.log';
    $channel = 'events';
    $logger  = new SimpleLog\Logger($logfile, $channel);
    $logger->setOutput(true);
    $this->translator = new \DeepL\Translator($this->deeplApiKey, ["logger"=>$logger]);
    $redis = new Redis();
    $redis->connect("127.0.0.1", 6379);
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1); // redis mode does not time out. Recommended
    $redis->subscribe(['ywb-deepl-channel'], function ($r, $c, $m) {
      $this->callback($r, $c, $m);
    });
  }

  public function callback($instance, $channelName, $message)
  {
    $json = json_decode($message, true);
    echo $channelName, ":", json_encode($json,JSON_PRETTY_PRINT), PHP_EOL;
    $this->translate($json['inputPath'], $json['outputPath'], $json['lang']);
  }

  private function translate($inputPath, $outputPath, $lang)
  {
    if (file_exists($outputPath)) {
      echo "Translation ALREADY EXISTS! Exiting...", PHP_EOL;
      return;
    }
    $outputDir = dirname($outputPath);
    $hasLock = $this->checkLock($outputDir);
    if ($hasLock) {
      echo "Translation ALREADY in progress, wait!", PHP_EOL;
      return;
    }
    try {
        if (!is_file($inputPath)){
          echo "Input file $inputPath DOES NOT EXIST!!! EXITING!\n";
          return;
        }
        $this->createLock($outputDir);
        $this->printUsage();
        echo "Starting translation for ".$inputPath, PHP_EOL;
        $status = $this->translator->translateDocument($inputPath, $outputPath, null, $lang);
        echo "Translation status: ".$status->status." Errors: ".$status->errorMessage, PHP_EOL;
    }
    catch (\DeepL\DocumentTranslationException $e){
        echo "Error translating text: ".$e->getMessage(), PHP_EOL;
    }
    finally {
        $this->clearLock($outputDir);
    }
  }

  private function checkLock($outputDir){
    $lockFile = $outputDir."/translation.lock";

    if (file_exists($lockFile)) {
      $lastModifiedTime = filemtime($lockFile);
      // Get the current time
      $currentTime = time();
      // Calculate the difference in time (in seconds)
      $timeDifference = $currentTime - $lastModifiedTime;
      // Check if the difference is less than 5 minutes (300 seconds)
      if ($timeDifference <= 300) {
        echo "The file was modified less than 5 minutes ago", PHP_EOL;
        return true;
      } else {
        echo "The file was modified more than 5 minutes ago", PHP_EOL;
        return false;
      }
    } else {
      echo "The lock file does not exist", PHP_EOL;
      return false;
    }
  }

  private function createLock($outputDir){
    $this->clearLock($outputDir);
    if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);
    $lockFile = $outputDir . "/translation.lock";
    file_put_contents($lockFile, time());
    echo "Lock created in " . $outputDir, PHP_EOL;
  }

  private function clearLock($outputDir){
    $lockFile = $outputDir."/translation.lock";
    if (file_exists($lockFile)) unlink($lockFile);
  }

  private function printUsage(){
    $usage = $this->translator->getUsage();
    if ($usage->anyLimitReached()) {
        echo 'USAGE: Translation limit exceeded.\n';
    }
    if ($usage->character) {
        echo 'USAGE: Characters: ' . $usage->character->count . ' of ' . $usage->character->limit."\n";
    }
    if ($usage->document) {
        echo 'USAGE: Documents: ' . $usage->document->count . ' of ' . $usage->document->limit."\n";
    }
  }
}

$bgTranslator = new DeeplBackgroundTranslator();
