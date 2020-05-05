<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

use OCP\Util;

Util::addStyle('analytics', 'style');
Util::addStyle('analytics', 'advanced');
Util::addStyle('analytics', 'sharetabview');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'sidebar');
Util::addScript('analytics', 'navigation');
Util::addScript('analytics', 'advanced');
?>

    <div id="app-navigation">
        <?php print_unescaped($this->inc('part.navigation')); ?>
    </div>

    <div id="app-content">
        <div id="loading">
            <i class="ioc-spinner ioc-spin"></i>
        </div>
        <?php print_unescaped($this->inc('part.content_advanced')); ?>
    </div>
<?php print_unescaped($this->inc('part.templates')); ?>