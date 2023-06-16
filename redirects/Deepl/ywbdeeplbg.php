<?php

require_once('DeepLException.php');
require_once('DocumentTranslationException.php');
require_once('DocumentHandle.php');
require_once('DocumentStatus.php');
require_once('BackoffTimer.php');
require_once('HttpClient.php');
require_once('LanguageCode.php');
require_once('Translator.php');
require_once('TranslateDocumentOptions.php');
require_once('TranslatorOptions.php');
require_once('TranslateTextOptions.php');
require_once('TextResult.php');

use DeepL\Translator;

class DeeplBackgroundTranslator
{
  protected $deeplApiKey  = "";  //ENTER YOUR DEEPL API KEY HERE

  function __construct()
  {
    echo "Deepl Background Translator v2.0 by Yellow Web\n";
    echo "Running...\n";
    ini_set('default_socket_Timeout', -1); // PHP configuration does not time out
    $redis = new Redis();
    $redis->connect("127.0.0.1", 6379);
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1); // redis mode does not time out. Recommended
    $redis->subscribe(['ywb-deepl-channel'], function ($r, $c, $m) {
      $this->callback($r, $c, $m);
    });
  }

  public function callback($instance, $channelName, $message)
  {
    echo $channelName, "==>", $message, PHP_EOL;
    $json = json_decode($message, true);
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
      $this->createLock($outputDir);
      $translator = new Translator($this->deeplApiKey);
      echo "Starting translation for " . $inputPath, PHP_EOL;
      $status = $translator->translateDocument($inputPath, $outputPath, null, $lang);
      echo "Translation status: " . $status->status . " Errors: " . $status->errorMessage, PHP_EOL;
    } catch (\Exception $e) {
      echo "Error translating text: " . $e->getMessage(), PHP_EOL;
    } finally {
      $this->clearLock($outputDir);
    }
  }

  private function checkLock($outputDir)
  {
    $lockFile = $outputDir . "/translation.lock";

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

  private function createLock($outputDir)
  {
    $this->clearLock($outputDir);
    if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);
    $lockFile = $outputDir . "/translation.lock";
    file_put_contents($lockFile, time());
    echo "Lock created in " . $outputDir, PHP_EOL;
  }

  private function clearLock($outputDir)
  {
    $lockFile = $outputDir . "/translation.lock";
    if (file_exists($lockFile)) unlink($lockFile);
  }
}

$bgTranslator = new DeeplBackgroundTranslator();
