<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\AppInfo;

use OCP\AppFramework\App;
use OCP\IContainer;

class Application extends App {

	public function __construct(array $urlParams = array()) {

		parent::__construct('data', $urlParams);
	}
}
