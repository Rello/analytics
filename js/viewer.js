/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */
/** global: OCA */
/** global: OCP */
/** global: OC */
'use strict';

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}
/**
 * @namespace OCA.Analytics.Viewer
 */
OCA.Analytics.Viewer = {

    registerFileActions: function () {
        let mime_array = ['text/csv'];
        let icon_url = OC.imagePath('analytics', 'app-dark');

        for (let i = 0; i < mime_array.length; i++) {
            let mime = mime_array[i];
            OCA.Files.fileActions.registerAction({
                name: 'analytics',
                displayName: t('analytics', 'Show in Analytics'),
                mime: mime,
                permissions: OC.PERMISSION_READ,
                icon: icon_url,
                actionHandler: OCA.Analytics.Viewer.importFile
            });
        }
    },

    importFile: function (file, data) {
        file = encodeURIComponent(file);
        let dirLoad = data.dir.substr(1);
        if (dirLoad !== '') {
            dirLoad = dirLoad + '/';
        }
        window.location = OC.generateUrl('/apps/analytics/#/f/') + dirLoad + file;
    },
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof OCA !== 'undefined' && typeof OCA.Files !== 'undefined' && typeof OCA.Files.fileActions !== 'undefined' && $('#header').hasClass('share-file') === false) {
        OCA.Analytics.Viewer.registerFileActions();
    }
    return true;
});