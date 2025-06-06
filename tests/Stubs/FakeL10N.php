<?php
namespace OCA\Analytics\Tests\Stubs;

use OCP\IL10N;

class FakeL10N implements IL10N {
    public function t($text, array $parameters = [], $count = null) {
        if (!empty($parameters)) {
            return vsprintf($text, $parameters);
        }
        return $text;
    }
}
