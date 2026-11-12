/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
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
/** Shared keyboard behavior for the app's existing modal surfaces. */
OCA.Analytics.Dialog = {
    focusable: function (container) {
        return [...container.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]')]
            .filter(element => element.tabIndex >= 0 && !element.disabled
                && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
    },

    activate: function (container, close, initialFocus = container) {
        const opener = document.activeElement;
        const fallback = opener?.closest('#optionsMenu') ? document.getElementById('optionsMenuIcon')
            : document.getElementById('newReportButton');
        container.setAttribute('role', 'dialog');
        container.setAttribute('aria-modal', 'true');
        container.tabIndex = -1;
        const onKeydown = event => {
            if (event.defaultPrevented) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            } else if (event.key === 'Tab') {
                const controls = OCA.Analytics.Dialog.focusable(container);
                const index = controls.indexOf(document.activeElement);
                if (!controls.length || index < 0 || (!event.shiftKey && index === controls.length - 1)
                    || (event.shiftKey && index === 0)) {
                    event.preventDefault();
                    (event.shiftKey ? controls.at(-1) : controls[0])?.focus();
                }
            }
        };
        container.addEventListener('keydown', onKeydown);
        container._analyticsDialogDeactivate = () => {
            container.removeEventListener('keydown', onKeydown);
            const target = opener?.isConnected && opener.getClientRects().length ? opener : fallback;
            target?.focus({preventScroll: true});
        };
        initialFocus.focus({preventScroll: true});
    },
};

/**
 * @namespace OCA.Analytics.Wizard
 */
OCA.Analytics.Wizard = {
    sildeArray: [],
    currentSlide: 0,
    previousSlide: 0,

    showFirstStart: function () {
        OCA.Analytics.Wizard.sildeArray = [
            ['', ''],
            ['wizard-start', ''],
            ['wizard-charts', ''],
            ['wizard-filter', ''],
            ['wizard-datasource', ''],
            ['wizard-dataload', ''],
            ['wizardFinal', OCA.Analytics.Wizard.wizardFinal]
        ];
        OCA.Analytics.Wizard.show();
    },

    show: function () {
        OCA.Analytics.Wizard.currentSlide = 0;
        if (!document.getElementById('analyticsWizard')) {
            let wizard = document.importNode(document.getElementById('wizardDialog').content, true);
            document.body.appendChild(wizard);
        }
        document.getElementById('wizardNext').addEventListener('click', OCA.Analytics.Wizard.next);
        document.getElementById('wizardPrevious').addEventListener('click', OCA.Analytics.Wizard.previous);
        document.getElementById('wizardClose').addEventListener('click', OCA.Analytics.Wizard.cancel);
        OCA.Analytics.Dialog.activate(document.getElementById('analyticsWizard'), OCA.Analytics.Wizard.cancel);

        // load all pages in the background
        for (let i = 1; i < OCA.Analytics.Wizard.sildeArray.length; i++) {
            let newpage = document.importNode(document.getElementById(OCA.Analytics.Wizard.sildeArray[i][0]).content, true);
            newpage.firstElementChild.id = OCA.Analytics.Wizard.sildeArray[i][0] + 'Page';
            document.getElementById('pageBody').appendChild(newpage);

        }

        // create the dots
        for (let i = 1; i < OCA.Analytics.Wizard.sildeArray.length; i++) {
            let dot = document.createElement('div');
            dot.id = 'wizardDot' + i;
            dot.classList.add('dot');
            if (i === 1) dot.classList.add('active');
            document.getElementById('wizardFooter').appendChild(dot);
        }

        OCA.Analytics.Wizard.next();
    },

    next: function () {
        if (OCA.Analytics.Wizard.currentSlide < OCA.Analytics.Wizard.sildeArray.length - 1) {
            OCA.Analytics.Wizard.previousSlide = OCA.Analytics.Wizard.currentSlide;
            OCA.Analytics.Wizard.currentSlide++;
            OCA.Analytics.Wizard.showPage();
        }
    },

    previous: function () {
        if (OCA.Analytics.Wizard.currentSlide !== 1) {
            OCA.Analytics.Wizard.previousSlide = OCA.Analytics.Wizard.currentSlide;
            OCA.Analytics.Wizard.currentSlide--;
            OCA.Analytics.Wizard.showPage();
        }
    },

    close: function () {
        const wizard = document.getElementById('analyticsWizard');
        wizard?.remove();
        wizard?._analyticsDialogDeactivate?.();
    },

    cancel: function () {
        OCA.Analytics.Wizard.close();
    },

    dismissWizard: function () {
        let xhr = new XMLHttpRequest();
        xhr.open('POST', OC.generateUrl('apps/analytics/wizard'), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.send();
    },

    demo: async function () {
        if (OCA.Analytics.Wizard.demoInstalling) return;
        OCA.Analytics.Wizard.demoInstalling = true;
        try {
            const response = await fetch(OC.generateUrl('apps/analytics/demo'), {
                method: 'POST', headers: OCA.Analytics.headers(),
            });
            const result = await response.json();
            if (!response.ok || !(Number(result.panoramaId) > 0)) throw new Error('Demo import failed');
            OCA.Analytics.Wizard.dismissWizard();
            OCA.Analytics.Wizard.close();
            window.location.href = OC.generateUrl('apps/analytics/pa/' + result.panoramaId);
        } catch (error) {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Import failed'));
        } finally {
            OCA.Analytics.Wizard.demoInstalling = false;
        }
    },

    showPage: function () {
        let nextSlide = OCA.Analytics.Wizard.sildeArray[OCA.Analytics.Wizard.currentSlide][0];
        let prevSlide = OCA.Analytics.Wizard.sildeArray[OCA.Analytics.Wizard.previousSlide][0];
        let callback = OCA.Analytics.Wizard.sildeArray[OCA.Analytics.Wizard.currentSlide][1];
        let prev = document.getElementById('wizardPrevious');
        let next = document.getElementById('wizardNext');

        if (prevSlide !== '') document.getElementById(prevSlide + 'Page').style.display = 'none';
        document.getElementById(nextSlide + 'Page').style.display = '';

        document.querySelector('.dot.active').classList.remove('active');
        document.getElementById('wizardDot' + OCA.Analytics.Wizard.currentSlide).classList.add('active');

        if (typeof callback === 'function') {
            callback();
        }

        OCA.Analytics.Wizard.currentSlide === 1 ? prev.style.visibility = 'hidden' : prev.style.visibility = 'initial';
        OCA.Analytics.Wizard.currentSlide === OCA.Analytics.Wizard.sildeArray.length - 1 ? next.style.visibility = 'hidden' : next.style.visibility = 'initial';
        const page = document.getElementById(nextSlide + 'Page');
        const heading = page.querySelector('h2, h3');
        if (heading) {
            heading.id ||= nextSlide + 'Heading';
            heading.tabIndex = -1;
            document.getElementById('analyticsWizard').setAttribute('aria-labelledby', heading.id);
        }
        document.getElementById('pageBody').scrollTop = 0;
        (heading || OCA.Analytics.Dialog.focusable(page)[0] || next).focus({preventScroll: true});
    },

    wizardFinal: function () {
        document.getElementById('wizardEnd').addEventListener('click', OCA.Analytics.Wizard.close);
        document.getElementById('wizardDemo').addEventListener('click', OCA.Analytics.Wizard.demo);
        OCA.Analytics.Wizard.dismissWizard();
    }
}

/**
 * @namespace OCA.Analytics.WhatsNew
 */
OCA.Analytics.WhatsNew = {

    whatsnew: function (options) {
        options = options || {}

        let xhr = new XMLHttpRequest();
        xhr.open('GET', OC.generateUrl('apps/analytics/whatsnew?format=json'), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status !== 204) {
                let data = JSON.parse(xhr.response);
                OCA.Analytics.WhatsNew.show(data, xhr);
            }
        };
        xhr.send();
    },

    dismiss: function (version) {
        let params = 'version=' + encodeURIComponent(version);
        let xhr = new XMLHttpRequest();
        xhr.open('POST', OC.generateUrl('apps/analytics/whatsnew'), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(params);
        document.querySelectorAll('.whatsNewPopover').forEach((popover) => popover.remove());
    },

    show: function (data, xhr) {
        if (xhr.status !== 200) {
            return
        }

        let item, menuItem, text, icon

        const div = document.createElement('div')
        div.classList.add('popovermenu', 'open', 'whatsNewPopover', 'menu-left')

        const list = document.createElement('ul')

        // header
        item = document.createElement('li')
        menuItem = document.createElement('span')
        menuItem.className = 'menuitem'

        text = document.createElement('span')
        text.innerText = t('core', 'New in') + ' ' + data['product']
        text.className = 'caption'
        menuItem.appendChild(text)

        icon = document.createElement('span')
        icon.className = 'icon-close'
        icon.onclick = function () {
            OCA.Analytics.WhatsNew.dismiss(data['version'])
        }
        menuItem.appendChild(icon)

        item.appendChild(menuItem)
        list.appendChild(item)

        // Highlights
        for (const i in data['whatsNew']['regular']) {
            const whatsNewTextItem = data['whatsNew']['regular'][i]
            item = document.createElement('li')

            menuItem = document.createElement('span')
            menuItem.className = 'menuitem'

            icon = document.createElement('span')
            icon.className = 'icon-checkmark'
            menuItem.appendChild(icon)

            text = document.createElement('p')
            text.textContent = whatsNewTextItem
            menuItem.appendChild(text)

            item.appendChild(menuItem)
            list.appendChild(item)
        }

        // Changelog URL
        if (data['changelogURL'] !== undefined) {
            item = document.createElement('li')

            menuItem = document.createElement('a')
            menuItem.href = data['changelogURL']
            menuItem.rel = 'noreferrer noopener'
            menuItem.target = '_blank'

            icon = document.createElement('span')
            icon.className = 'icon-link'
            menuItem.appendChild(icon)

            text = document.createElement('span')
            text.innerText = t('core', 'View changelog')
            menuItem.appendChild(text)

            item.appendChild(menuItem)
            list.appendChild(item)
        }

        div.appendChild(list)
        document.body.appendChild(div)
    },
}
/**
 * @namespace OCA.Analytics.Notification
 */
OCA.Analytics.Notification = {
    draggedItem: null,
    DEFAULT_DIALOG_OPTIONS: {
        variant: 'simple',
        documentationUrl: null,
        leadingAction: null,
        showActions: true,
    },

    closeExistingDialog: function () {
        if (document.getElementById('analyticsDialogContainer') || document.getElementById('analyticsDialogOverlay')) {
            OCA.Analytics.Notification.dialogClose();
        }
    },

    info: function (header, text, guidance) {
        OCA.Analytics.Notification.closeExistingDialog();
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" aria-labelledby="analyticsDialogHeader" class="analyticsDialog">'
            + '<button type="button" class="analyticsDialogClose" id="analyticsDialogBtnClose" aria-label="' + t('analytics', 'Close') + '"></button>'
            + '<div class="analyticsDialogHeader"><span class="analyticsDialogHeaderIcon"></span><span id="analyticsDialogHeader" style="margin-left: 10px;"></span></div>'
            + '<span id="analyticsDialogGuidance" class="userGuidance"></span><br><br>'
            + '<div id="analyticsDialogContent">'
            + '</div>'
            + '<br><div class="analyticsDialogButtonrow">'
            + '<button type="button" class="button analyticsPrimary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</button>'
            + '</div></div>'
        );
        document.getElementById('analyticsDialogHeader').textContent = header;
        document.getElementById('analyticsDialogGuidance').innerHTML = guidance;
        document.getElementById('analyticsDialogContent').innerHTML = text;
        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnGo").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        OCA.Analytics.Dialog.activate(document.getElementById('analyticsDialogContainer'), OCA.Analytics.Notification.dialogClose);
    },

    confirm: function (header, text, callback) {
        OCA.Analytics.Notification.closeExistingDialog();
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" aria-labelledby="analyticsDialogHeader" class="analyticsDialog">'
            + '<button type="button" class="analyticsDialogClose" id="analyticsDialogBtnClose" aria-label="' + t('analytics', 'Close') + '"></button>'
            + '<div class="analyticsDialogHeader"><span class="analyticsDialogHeaderIcon"></span><span id="analyticsDialogHeader" style="margin-left: 10px;"></span></div>'
            + '<div id="analyticsDialogContent">'
            + '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>'
            + '</div>'
            + '<br><div class="analyticsDialogButtonrow">'
            + '<button type="button" class="button analyticsSecondary" id="analyticsDialogBtnCancel">' + t('analytics', 'Cancel') + '</button>'
            + '<button type="button" class="button analyticsPrimary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</button>'
            + '</div></div>'
        );
        document.getElementById('analyticsDialogHeader').textContent = header;
        document.getElementById('analyticsDialogContent').innerHTML = text;
        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnCancel").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnGo").addEventListener("click", callback);
        OCA.Analytics.Dialog.activate(document.getElementById('analyticsDialogContainer'), OCA.Analytics.Notification.dialogClose,
            document.getElementById('analyticsDialogBtnGo'));
    },

    /**
     * Function to display notifications.
     * @param {('info'|'success'|'error')} type - The type of the notification.
     * @param {string} message - The notification message.
     */
    notification: function (type, message) {
        if (type === 'success') {
            OCP.Toast.success(message)
        } else if (type === 'error') {
            OCP.Toast.error(message)
        } else {
            OCP.Toast.info(message)
        }
    },

    /**
     * @param {string} header Popup header as text
     * @param callback Callback function of the OK button
     */
    htmlDialogInitiate: function (header, callback, options = {}) {
        const dialogOptions = OCA.Analytics.Notification.normalizeDialogOptions(options);

        OCA.Analytics.Notification.closeExistingDialog();
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" aria-labelledby="analyticsDialogHeader" class="analyticsDialog'
            + (dialogOptions.variant === 'enhanced' ? ' analyticsDialog--enhanced' : '')
            + '">'
            + '<button type="button" class="analyticsDialogClose" id="analyticsDialogBtnClose" aria-label="' + t('analytics', 'Close') + '"></button>'
            + '<div class="analyticsDialogHeaderWrap">'
            + '<div class="analyticsDialogHeader"><span class="analyticsDialogHeaderIcon"></span><span id="analyticsDialogHeader" style="margin-left: 10px;"></span></div>'
            + '<span id="analyticsDialogGuidance" class="userGuidance analyticsDialogGuidance"></span>'
            + '</div>'
            + '<div id="analyticsDialogContent">'
            + '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>'
            + '</div>'
            + (
                dialogOptions.showActions
                    ? '<div class="analyticsDialogButtonrow">'
                    + (
                        dialogOptions.leadingAction
                            ? '<button type="button" class="button analyticsSecondary analyticsDialogButtonLeading'
                            + (dialogOptions.leadingAction.className ? ' ' + dialogOptions.leadingAction.className : '')
                            + '" id="analyticsDialogBtnLeading">'
                            + dialogOptions.leadingAction.label
                            + '</button>'
                            : ''
                    )
                    + '<button type="button" class="button analyticsSecondary" id="analyticsDialogBtnCancel">' + t('analytics', 'Cancel') + '</button>'
                    + '<button type="button" class="button analyticsPrimary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</button>'
                    + '</div>'
                    : ''
            )
            + '</div>'
        );

        const dialogContainer = document.getElementById('analyticsDialogContainer');
        dialogContainer._analyticsDialogOptions = dialogOptions;
        document.getElementById('analyticsDialogHeader').textContent = header;

        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        if (dialogOptions.showActions) {
            document.getElementById("analyticsDialogBtnCancel").addEventListener("click", OCA.Analytics.Notification.dialogClose);
            document.getElementById("analyticsDialogBtnGo").addEventListener("click", callback);
        }

        if (dialogOptions.showActions && dialogOptions.leadingAction) {
            document.getElementById('analyticsDialogBtnLeading').addEventListener('click', dialogOptions.leadingAction.onClick);
        }
        OCA.Analytics.Dialog.activate(dialogContainer, OCA.Analytics.Notification.dialogClose);
    },

    htmlDialogUpdate: function (content, guidance, options = null) {
        const dialogContainer = document.getElementById('analyticsDialogContainer');
        if (!dialogContainer) {
            return;
        }

        const dialogOptions = OCA.Analytics.Notification.normalizeDialogOptions({
            ...(dialogContainer._analyticsDialogOptions || {}),
            ...(options || {})
        });
        dialogContainer._analyticsDialogOptions = dialogOptions;
        dialogContainer.classList.toggle('analyticsDialog--enhanced', dialogOptions.variant === 'enhanced');

        const contentElement = document.getElementById('analyticsDialogContent');
        const guidanceElement = document.getElementById('analyticsDialogGuidance');

        if (typeof dialogContainer._analyticsDialogCleanup === 'function') {
            dialogContainer._analyticsDialogCleanup();
            dialogContainer._analyticsDialogCleanup = null;
        }

        contentElement.innerHTML = '';
		if (guidance instanceof Node) {
			guidanceElement.replaceChildren(guidance);
			guidanceElement.hidden = false;
		} else {
			guidanceElement.innerHTML = guidance;
			guidanceElement.hidden = guidance === '';
		}

        if (dialogOptions.variant === 'enhanced') {
            dialogContainer._analyticsDialogCleanup = OCA.Analytics.Notification.renderEnhancedDialogContent(
                contentElement,
                content,
                dialogOptions
            );
        } else {
            contentElement.appendChild(content);
        }
        if (!dialogContainer.contains(document.activeElement) || document.activeElement === dialogContainer) {
            (OCA.Analytics.Dialog.focusable(contentElement)[0] || dialogContainer).focus({preventScroll: true});
        }
    },

    htmlDialogUpdateAdd: function (guidance) {
        document.getElementById('analyticsDialogGuidance').innerHTML += '<br>' + guidance;
    },

    dialogClose: function () {
        const dialogContainer = document.getElementById('analyticsDialogContainer');
        if (dialogContainer && typeof dialogContainer._analyticsDialogCleanup === 'function') {
            dialogContainer._analyticsDialogCleanup();
        }
        dialogContainer?.remove();
        document.getElementById('analyticsDialogOverlay')?.remove();
        dialogContainer?._analyticsDialogDeactivate?.();
    },

    normalizeDialogOptions: function (options = {}) {
        const dialogOptions = {
            ...OCA.Analytics.Notification.DEFAULT_DIALOG_OPTIONS,
            ...(options || {}),
        };

        dialogOptions.variant = dialogOptions.variant === 'enhanced' ? 'enhanced' : 'simple';
        dialogOptions.documentationUrl = typeof dialogOptions.documentationUrl === 'string' && dialogOptions.documentationUrl !== ''
            ? dialogOptions.documentationUrl
            : null;
        dialogOptions.showActions = dialogOptions.showActions !== false;

        if (!dialogOptions.leadingAction || typeof dialogOptions.leadingAction.onClick !== 'function') {
            dialogOptions.leadingAction = null;
        }

        return dialogOptions;
    },

    renderEnhancedDialogContent: function (contentRoot, content, options) {
        const sectionScrollOffset = 24;
        const createSectionIcon = function (className, iconUrl) {
            if (!iconUrl) {
                return null;
            }

            const icon = document.createElement('span');
            icon.className = className;
            icon.setAttribute('aria-hidden', 'true');
            icon.style.setProperty('--analytics-dialog-section-icon', `url("${iconUrl}")`);
            return icon;
        };
        const layout = document.createElement('div');
        layout.className = 'analyticsEnhancedDialogLayout';

        const nav = document.createElement('div');
        nav.className = 'analyticsEnhancedDialogNav';

        const navList = document.createElement('div');
        navList.className = 'analyticsEnhancedDialogNavList';
        nav.appendChild(navList);

        const panel = document.createElement('div');
        panel.className = 'analyticsEnhancedDialogPanel';
        panel.appendChild(content);

        layout.appendChild(nav);
        layout.appendChild(panel);
        contentRoot.appendChild(layout);

        const sections = Array.from(panel.querySelectorAll('.analyticsDialogSection'))
            .filter(section => !section.hidden);
        if (sections.length === 0) {
            nav.remove();
            return null;
        }

        const navLinks = [];
        let navigationTarget = null;
        let navigationTimer = null;
        // offsetTop can be relative to the dialog rather than the scrolling panel.
        const sectionTop = section => section.getBoundingClientRect().top
            - panel.getBoundingClientRect().top + panel.scrollTop - panel.clientTop;
        const releaseNavigationTarget = function () {
            clearTimeout(navigationTimer);
            navigationTimer = setTimeout(() => { navigationTarget = null; }, 150);
        };
        sections.forEach((section, index) => {
            const heading = section.querySelector('h2');
            const label = heading ? heading.textContent.trim() : t('analytics', 'Chart options');
            const sectionId = section.id || 'analyticsDialogSection' + index;
            const sectionIconUrl = section.dataset.sectionIcon ? section.dataset.sectionIcon.trim() : '';
            section.id = sectionId;

            if (heading) {
                heading.classList.add('analyticsDialogSectionHeading');
                if (!heading.querySelector('.analyticsDialogSectionIcon')) {
                    const headingIcon = createSectionIcon('analyticsDialogSectionIcon', sectionIconUrl);
                    if (headingIcon) {
                        heading.prepend(headingIcon);
                    }
                }
            }

            const link = document.createElement('a');
            link.className = 'analyticsEnhancedDialogNavButton';
            link.href = '#' + sectionId;
            link.dataset.target = sectionId;

            const navIcon = createSectionIcon('analyticsEnhancedDialogNavIcon', sectionIconUrl);
            if (navIcon) {
                link.appendChild(navIcon);
            }

            const labelText = document.createElement('span');
            labelText.className = 'analyticsEnhancedDialogNavLabel';
            labelText.textContent = label;
            link.appendChild(labelText);

            link.addEventListener('click', function (event) {
                event.preventDefault();
                navigationTarget = sectionId;
                releaseNavigationTarget();
                const panelPaddingTop = parseInt(window.getComputedStyle(panel).paddingTop, 10) || 0;
                const targetTop = Math.max(sectionTop(section) - panelPaddingTop - sectionScrollOffset, 0);
                panel.scrollTo({top: targetTop, behavior: 'smooth'});
                OCA.Analytics.Notification.updateEnhancedDialogActiveSection(navLinks, sectionId);
            });

            navLinks.push(link);
            navList.appendChild(link);
        });

        if (options.documentationUrl) {
            const documentationLink = document.createElement('a');
            documentationLink.className = 'analyticsEnhancedDialogDocLink';
            documentationLink.href = options.documentationUrl;
            documentationLink.target = '_blank';
            documentationLink.rel = 'noreferrer noopener';
            documentationLink.textContent = t('analytics', 'Open documentation');
            nav.appendChild(documentationLink);
        }

        const syncActiveSection = function () {
            // Keep the clicked destination active while smooth scrolling settles,
            // including sections near the end that cannot reach the panel's top.
            if (navigationTarget) {
                OCA.Analytics.Notification.updateEnhancedDialogActiveSection(navLinks, navigationTarget);
                releaseNavigationTarget();
                return;
            }
            const panelPaddingTop = parseInt(window.getComputedStyle(panel).paddingTop, 10) || 0;
            const panelTop = panel.scrollTop + panelPaddingTop + sectionScrollOffset;
            let activeSectionId = sections[0].id;

            sections.forEach((section) => {
                if (sectionTop(section) <= panelTop + 1) {
                    activeSectionId = section.id;
                }
            });

            OCA.Analytics.Notification.updateEnhancedDialogActiveSection(navLinks, activeSectionId);
        };

        panel.addEventListener('scroll', syncActiveSection, {passive: true});
        syncActiveSection();

        return function () {
            clearTimeout(navigationTimer);
            panel.removeEventListener('scroll', syncActiveSection);
        };
    },

    updateEnhancedDialogActiveSection: function (navLinks, activeSectionId) {
        navLinks.forEach((link) => {
            link.classList.toggle('analyticsEnhancedDialogNavButton--active', link.dataset.target === activeSectionId);
        });
    },

    handleDragStart: function (e) {
        OCA.Analytics.Notification.draggedItem = this;
        e.dataTransfer.effectAllowed = "move";
    },

    handleDragOver: function (e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = "move";
        return false;
    },

    handleDrop: function (e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        if (OCA.Analytics.Notification.draggedItem !== this) {
            this.parentNode.insertBefore(OCA.Analytics.Notification.draggedItem, this);
        }
        return false;
    },
}
