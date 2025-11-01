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

    FILE_ACTION_ID: 'analytics',

    ALLOWED_MIMES: ['text/csv'],

    ICON_SVG: '<svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"><g transform="matrix(0.10000000149011612, 0, 0, -0.10000000149011612, 0, 300.00000000000006)" fill="#000000" stroke="none"><path d="M 2930.352 2749.78 C 2925.702 2747.773 2308.418 2109.704 2291.364 2095.665 C 2239.121 2095.485 2427.326 2027.76 2436.627 2019.739 C 2447.479 2011.716 1958.671 850.86 1951.383 865.013 C 1914.03 937.553 1370.025 2453.28 1364.874 2460.434 C 1173.207 2726.635 1120.792 2701.436 927.421 2457.64 C 924.671 2454.172 231.036 953.16 167.473 810.777 C -10.81 409.706 52.158 256.599 169.46 235.745 C 292.679 213.839 298.237 317.452 571.515 886.725 L 1307.383 889.675 C 1331.731 892.11 1284.555 1137.002 1155.706 1181.052 C 1064.171 1235.418 750.443 1210.622 706.359 1219.215 C 701.983 1220.068 1121.635 2146.56 1121.635 2146.56 L 1517.579 1053.974 C 1687.297 605.131 1787.366 321.612 1802.871 299.549 C 1874.182 197.277 2040.375 219.336 2083.122 313.908 C 2137.95 435.208 2742.923 1901.25 2753.31 1895.603 C 2770.487 1886.264 2915.546 1824.41 2915.546 1824.41 C 2915.546 1824.41 2935.003 2749.78 2930.352 2749.78 Z"/></g></svg>',

    registerFileActions: function () {
        OCA.Analytics.Viewer.registerNewFrontendAction();
        OCA.Analytics.Viewer.registerLegacyFileActions();
    },

    registerLegacyFileActions: function () {
        if (typeof OCA === 'undefined' || typeof OCA.Files === 'undefined' || typeof OCA.Files.fileActions === 'undefined' || typeof OCA.Files.fileActions.registerAction !== 'function') {
            return;
        }

        const iconUrl = OC.imagePath('analytics', 'app-dark');
        const displayName = t('analytics', 'Show in Analytics');

        for (let i = 0; i < OCA.Analytics.Viewer.ALLOWED_MIMES.length; i++) {
            const mime = OCA.Analytics.Viewer.ALLOWED_MIMES[i];
            OCA.Files.fileActions.registerAction({
                name: OCA.Analytics.Viewer.FILE_ACTION_ID,
                displayName: displayName,
                mime: mime,
                permissions: OC.PERMISSION_READ,
                icon: iconUrl,
                actionHandler: OCA.Analytics.Viewer.importFile
            });
        }
    },

    registerNewFrontendAction: function (retries) {
        if (typeof window === 'undefined') {
            return;
        }

        const viewer = OCA.Analytics.Viewer;
        const attemptsLeft = typeof retries === 'number' ? retries : 10;

        const register = function () {
            if (typeof window._nc_fileactions === 'undefined') {
                window._nc_fileactions = [];
            }

            if (!Array.isArray(window._nc_fileactions)) {
                return false;
            }

            if (window._nc_fileactions.some(function (action) {
                return action && action.id === viewer.FILE_ACTION_ID;
            })) {
                return true;
            }

            const analyticsAction = {
                id: viewer.FILE_ACTION_ID,
                displayName: function () {
                    return t('analytics', 'Show in Analytics');
                },
                iconSvgInline: function () {
                    return viewer.ICON_SVG;
                },
                enabled: function (nodes) {
                    if (!Array.isArray(nodes) || nodes.length !== 1) {
                        return false;
                    }

                    const node = nodes[0];
                    if (!node) {
                        return false;
                    }

                    if (typeof node.type === 'string' && node.type !== 'file') {
                        return false;
                    }

                    const mimeCandidates = [];
                    if (typeof node.mime === 'string') {
                        mimeCandidates.push(node.mime.toLowerCase());
                    }
                    if (typeof node.mimetype === 'string') {
                        mimeCandidates.push(node.mimetype.toLowerCase());
                    }
                    if (node.attributes && typeof node.attributes === 'object') {
                        const attributeMime = node.attributes.mimetype || node.attributes.mime;
                        if (typeof attributeMime === 'string') {
                            mimeCandidates.push(attributeMime.toLowerCase());
                        }
                    }

                    const isMimeAllowed = mimeCandidates.some(function (value) {
                        return viewer.ALLOWED_MIMES.indexOf(value) !== -1;
                    });

                    if (!isMimeAllowed) {
                        return false;
                    }

                    if (typeof node.permissions === 'number' && typeof OC !== 'undefined' && typeof OC.PERMISSION_READ === 'number') {
                        return (node.permissions & OC.PERMISSION_READ) !== 0;
                    }

                    return true;
                },
                exec: function (node, view, dir) {
                    viewer.openInAnalytics(dir, node);
                    return null;
                },
                order: 110
            };

            window._nc_fileactions.push(analyticsAction);
            return true;
        };

        const success = register();
        if (!success && attemptsLeft > 0) {
            window.setTimeout(function () {
                viewer.registerNewFrontendAction(attemptsLeft - 1);
            }, 500);
        }
    },

    openInAnalytics: function (dir, nodeOrFile) {
        let directory = '';
        if (typeof dir === 'string') {
            directory = dir;
        }

        if (directory && directory.charAt(0) === '/') {
            directory = directory.substr(1);
        }

        if (!directory && nodeOrFile && typeof nodeOrFile === 'object') {
            if (typeof nodeOrFile.dirname === 'string' && nodeOrFile.dirname !== '') {
                directory = nodeOrFile.dirname;
            } else if (typeof nodeOrFile.path === 'string') {
                let path = nodeOrFile.path;
                if (path.charAt(0) === '/') {
                    path = path.substr(1);
                }
                const lastSlash = path.lastIndexOf('/');
                if (lastSlash !== -1) {
                    directory = path.substring(0, lastSlash);
                }
            }

            if (directory && directory.charAt(0) === '/') {
                directory = directory.substr(1);
            }
        }

        if (directory && directory.slice(-1) !== '/') {
            directory = directory + '/';
        }

        let fileName = '';
        if (typeof nodeOrFile === 'string') {
            fileName = nodeOrFile;
        } else if (nodeOrFile && typeof nodeOrFile === 'object') {
            if (typeof nodeOrFile.basename === 'string') {
                fileName = nodeOrFile.basename;
            } else if (typeof nodeOrFile.displayname === 'string') {
                fileName = nodeOrFile.displayname;
            } else if (typeof nodeOrFile.name === 'string') {
                fileName = nodeOrFile.name;
            }
        }

        const encodedFile = encodeURIComponent(fileName);
        const targetUrl = OC.generateUrl('/apps/analytics/#/f/');
        window.location = targetUrl + directory + encodedFile;
    },

    importFile: function (file, data) {
        const dir = data && typeof data.dir === 'string' ? data.dir : '';
        OCA.Analytics.Viewer.openInAnalytics(dir, file);
    },
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof OCA !== 'undefined' && typeof OCA.Analytics !== 'undefined') {
        const header = document.getElementById('header');
        if (!(header && header.classList.contains('share-file'))) {
            OCA.Analytics.Viewer.registerFileActions();
        }
    }
    return true;
});