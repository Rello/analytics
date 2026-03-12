<?php
namespace OCP;

interface IAppConfig {
	public function getValueString(string $app, string $key, string $default = ''): string;
}
