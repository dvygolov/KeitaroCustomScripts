<?php
require_once '/var/www/keitaro/application/redirects/mvtcore/html5dom.php';

class ABTest
{
    public array $_variations;
    public array $_subs;

    public function __construct()
    {
        $this->_subs = [];
        $this->_variations = [];
    }


    public function get_random_test_dom(string $html, array $settings) :string
    {
        $printDebug = filter_var($settings['debug'], FILTER_VALIDATE_BOOLEAN);

        $dom = new HTML5DOMDocument();
        $dom->loadHTML($html, 67108864);

        $tests = $settings['tests'];
        $testsCount = count($tests);
        for ($i = 0; $i < $testsCount; $i++) {
            $curTest = $tests[$i];
            $this->_subs[] = $curTest['sub'];

            $variable = $dom->querySelector($curTest['selector']);
            if (!isset($variable)) {
                $this->_variations[] = "Original";
                continue; //if there is no suitable element - continue
            }

            $variationsCount = count($curTest['variations']);
            $j = rand(0, $variationsCount);
            if ($j == $variationsCount) {
                $this->_variations[] = "Original";
                continue;//don't use variation
            }

            $curVariation = $curTest['variations'][$j];
            $this->_variations[] = $curVariation['name'];

            if ($curTest['type'] === 'text')
                $variable->textContent = $curVariation['value'];
            else if ($curTest['type'] === 'attribute')
                $variable->setAttribute($curTest['attribute'], $curVariation['value']);
        }

        if ($printDebug) {
            $body = $dom->querySelector('body');
            $testNameNode = new DOMText(implode('-',$this->_variations));
            $body->insertBefore($testNameNode, $body->firstChild);
        }
        $html = $dom->saveHTML();
        $html = str_replace("%7B","{", $html);
        $html = str_replace("%7D","}", $html);
        return $html;
    }
}

?>
