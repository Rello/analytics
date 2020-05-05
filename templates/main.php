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
Util::addStyle('analytics', 'sharetabview');
Util::addStyle('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'sidebar');
Util::addScript('analytics', 'navigation');
Util::addScript('analytics', 'filter');
Util::addScript('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', '3rdParty/Chart.bundle.min');
Util::addScript('analytics', '3rdParty/chartjs-plugin-colorschemes.min');
Util::addScript('analytics', '3rdParty/cloner');
?>

    <div id="app-navigation">
        <?php print_unescaped($this->inc('part.navigation')); ?>
        <?php print_unescaped($this->inc('part.settings')); ?>
    </div>

    <div id="app-content">
        <div id="loading">
            <i class="ioc-spinner ioc-spin"></i>
        </div>
        <?php print_unescaped($this->inc('part.content')); ?>
    </div>
<?php print_unescaped($this->inc('part.sidebar')); ?>
<?php print_unescaped($this->inc('part.templates')); ?>