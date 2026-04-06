/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */

'use strict';

const getSafeReferenceUrl = function (value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    try {
        const url = new URL(value, window.location.origin);

        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return null;
        }

        return url.href;
    } catch (error) {
        return null;
    }
};

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
            const referenceUrl = getSafeReferenceUrl(richObject.url);
            const imageUrl = getSafeReferenceUrl(richObject.image);
            const widget = document.createElement(referenceUrl ? 'a' : 'div');
            widget.style.display = 'flex';

            if (referenceUrl) {
                widget.setAttribute('href', referenceUrl);
                widget.setAttribute('target', '_blank');
                widget.setAttribute('rel', 'noopener noreferrer');
            }

            const content = document.createElement('div');
            content.style.padding = '10px';
            content.style.width = imageUrl ? '75%' : '100%';

            const title = document.createElement('div');
            title.style.fontWeight = '600';
            title.textContent = richObject.name || '';

            const subheader = document.createElement('div');
            subheader.style.marginTop = '1em';
            subheader.textContent = richObject.subheader || '';

            content.appendChild(title);
            content.appendChild(subheader);

            if (imageUrl) {
                const image = document.createElement('img');
                image.setAttribute('src', imageUrl);
                image.setAttribute('alt', '');
                image.style.width = '20%';
                image.style.padding = '20px';
                image.style.opacity = '.5';
                widget.appendChild(image);
            }

            widget.appendChild(content);

            el.textContent = '';
            el.appendChild(widget);
        }, () => {}, { hasInteractiveView: false });
    },
}
