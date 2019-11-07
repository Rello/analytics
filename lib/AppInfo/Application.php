<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\AppInfo;

use OCP\AppFramework\App;

class Application extends App {

	public function __construct(array $urlParams = array()) {

        parent::__construct('analytics', $urlParams);
	}
}
