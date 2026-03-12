<?php
namespace OCP\EventDispatcher;

interface IEventDispatcher {
	public function dispatchTyped(object $event): object;
}
