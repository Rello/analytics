<?php
namespace OCP;

interface IL10N {
    public function t($text, array $parameters = [], $count = null);
}
