<?php
namespace OCP\AppFramework;

use OCP\IRequest;

class Controller {
	/** @var string */
	protected $appName;

	/** @var IRequest */
	protected $request;

	public function __construct(string $appName, IRequest $request) {
		$this->appName = $appName;
		$this->request = $request;
	}
}
