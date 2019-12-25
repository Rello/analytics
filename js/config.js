/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */
'use strict';

/**
 * @namespace OCA.Analytics.Config
 */
OCA.Analytics.Config = {

    tabContainerDataload: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;
        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderDataload').classList.add('selected');
        document.getElementById('tabContainerDataload').classList.remove('hidden');
        document.getElementById('tabContainerDataload').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('analytics', 'Reading data') + '</p></div>';
    }
};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.Sidebar.registerSidebarTab({
        id: 'tabHeaderDataload',
        class: 'tabContainerDataload',
        tabindex: '2',
        name: t('analytics', 'Dataload'),
        action: OCA.Analytics.Config.tabContainerDataload,
    });


});