/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.Reference.init();
})

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}
/**
 * @namespace OCA.Analytics.Reference
 */
OCA.Analytics.Reference = {
    init: function () {
        _registerWidget('analytics', async (el, {richObjectType, richObject, accessible}) => {
            const link = document.createElement('a');
            link.href = richObject.url || '';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.style.display = 'flex';

            const image = document.createElement('img');
            image.src = richObject.image || '';
            image.alt = '';
            image.style.width = '20%';
            image.style.padding = '20px';
            image.style.opacity = '.5';

            const content = document.createElement('div');
            content.style.width = '75%';
            content.style.padding = '10px';

            const title = document.createElement('div');
            title.style.fontWeight = '600';
            title.textContent = richObject.name || '';

            const subheader = document.createElement('div');
            subheader.style.marginTop = '1em';
            subheader.textContent = richObject.subheader || '';

            content.appendChild(title);
            content.appendChild(subheader);
            link.appendChild(image);
            link.appendChild(content);

            el.textContent = '';
            el.appendChild(link);
        }, () => {}, { hasInteractiveView: false });
    },
}
