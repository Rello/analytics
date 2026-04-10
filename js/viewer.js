/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
        OCA.Files.fileActions.registerAction({
            name: 'analytics',
            displayName: t('analytics', 'Show in Analytics'),
            mime: 'text/csv',
            permissions: OC.PERMISSION_READ,
            icon: OC.imagePath('analytics', 'app-dark'),
            actionHandler: OCA.Analytics.Viewer.importFile
        });
    },

    importFile: function (file, data) {
        file = encodeURIComponent(file);
        const directory = typeof data?.dir === 'string' ? data.dir : '';
        let dirLoad = directory.startsWith('/') ? directory.slice(1) : directory;
        if (dirLoad !== '') {
            dirLoad = dirLoad + '/';
        }
        window.location = OC.generateUrl('/apps/analytics/#/f/') + dirLoad + file;
    },
};

document.addEventListener('DOMContentLoaded', function () {
    const header = document.getElementById('header');
    const isShareFileView = header ? header.classList.contains('share-file') : false;

    if (typeof OCA !== 'undefined' && typeof OCA.Files !== 'undefined' && typeof OCA.Files.fileActions !== 'undefined' && isShareFileView === false) {
        OCA.Analytics.Viewer.registerFileActions();
    }
    return true;
});
