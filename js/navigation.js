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
/**
 * @namespace OCA.Analytics.Navigation
 */
OCA.Analytics.Navigation = {
    quickstartValue: '',
    quickstartId: 0,
    handlers: {},

    registerHandler: function (context, handlerFunction) {
        OCA.Analytics.Navigation.handlers[context] = handlerFunction;
    },

    init: function (navigationItem) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        OCA.Analytics.Navigation.getNavigationContent(navigationItem);
        OCA.Analytics.Backend?.getDatasetDefinitions?.();
    },

    getNavigationContent: function (navigationId) {
        let requests = [];
        if (OCA.Analytics.isDataset) {
            requests.push(fetch(OC.generateUrl('apps/analytics/dataset'), {
                method: 'GET',
                headers: OCA.Analytics.headers()
            }));
        } else {
            requests.push(fetch(OC.generateUrl('apps/analytics/report'), {
                method: 'GET',
                headers: OCA.Analytics.headers()
            }));
            requests.push(fetch(OC.generateUrl('apps/analytics/panorama'), {
                method: 'GET',
                headers: OCA.Analytics.headers()
            }));
        }

        Promise.all(requests)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(responseData => {
                let data = [];
                if (OCA.Analytics.isDataset) {
                    data = responseData[0];
                } else {
                    const reports = responseData[0];
                    const panoramas = responseData[1];
                    if (Array.isArray(panoramas)) {
                        OCA.Analytics.Panorama.stories = panoramas;
                        data = panoramas.concat(reports);
                    } else {
                        data = reports;
                    }
                }

                if (!OCA.Analytics.isDataset && navigationId === undefined) {
                    OCA.Analytics.Dashboard.init();
                }
                OCA.Analytics.reports = data;
                OCA.Analytics.Navigation.buildNavigation(data);
                if (navigationId && data.indexOf(data.find(o => parseInt(o.id) === parseInt(navigationId))) !== -1) {
                    OCA.Analytics.Sidebar?.close?.();
                    let navigationItem = document.querySelector('#navigationDatasets [data-id="' + navigationId + '"]');
                    if (navigationItem.parentElement.parentElement.parentElement.classList.contains('collapsible')) {
                        navigationItem.parentElement.parentElement.parentElement.classList.add('open');
                    }
                    if (OCA.Analytics.isNewObject) {
                        OCA.Analytics.isNewObject = false;
                        document.querySelector('#navigationDatasets [data-id="' + navigationId + '"] #navigationMenuEdit').click();
                    } else {
                        navigationItem.click();
                    }
                }
            });
    },

    buildNavigation: function (data) {
        OCA.Analytics.Sidebar?.close?.();
        const nav = document.getElementById('navigationDatasets');
        nav.innerHTML = '';

        nav.appendChild(OCA.Analytics.Navigation.buildOverviewButton());
        if (data === undefined || data.length === 0) {
            nav.appendChild(OCA.Analytics.Navigation.buildIntroRow());
        } else if (!OCA.Analytics.isDataset) {
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Favorites'), 'section-favorites'));
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Panoramas'), 'section-panoramas'));
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Reports'), 'section-reports'));

            for (let navigation of data) {
                let rootId = navigation.item_type === 'panorama' ? 'section-panoramas' : 'section-reports';
                if (parseInt(navigation.favorite) === 1) {
                    rootId = 'section-favorites';
                }
                OCA.Analytics.Navigation.buildNavigationRow(navigation, rootId);
            }
        } else {
            for (let navigation of data) {
                OCA.Analytics.Navigation.buildNavigationRow(navigation);
            }
        }

        nav.appendChild(OCA.Analytics.Navigation.buildNewGroupPlaceholder());
        nav.appendChild(OCA.Analytics.Navigation.buildNewButton()); // first pinned
        if (!OCA.Analytics.isDataset && !OCA.Analytics.isPanorama) {
            nav.appendChild(OCA.Analytics.Navigation.buildDatasetMaintenanceButton()); // second pinned
        }
    },

    buildNewButton: function () {
        let li = document.createElement('li');
        li.classList.add('pinned', 'first-pinned');
        let navigationEntrydiv = document.createElement('div');
        navigationEntrydiv.classList.add('app-navigation-entry');
        let a = document.createElement('a');
        a.classList.add('icon-add', 'svg');
        a.id = 'newReportButton';
        a.addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        if (OCA.Analytics.isDataset) {
            a.innerText = t('analytics', 'New dataset');
        } else if (OCA.Analytics.isPanorama) {
            // TRANSLATORS "Panorama" will be a product name. Do not translate, just capitalize if required
            a.innerText = t('analytics', 'New panorama');
        } else {
            a.innerText = t('analytics', 'New report');
        }
        li.appendChild(navigationEntrydiv);
        navigationEntrydiv.appendChild(a);
        return li;
    },

    buildDatasetMaintenanceButton: function () {
        let li = document.createElement('li');
        li.classList.add('pinned', 'second-pinned');
        let navigationEntrydiv = document.createElement('div');
        navigationEntrydiv.classList.add('app-navigation-entry');
        let a = document.createElement('a');
        a.classList.add('icon-category-customization');
        a.innerText = t('analytics', 'Dataset maintenance');
        a.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
        li.appendChild(navigationEntrydiv);
        navigationEntrydiv.appendChild(a);
        return li;
    },

    buildNewGroupPlaceholder: function () {
        let li = document.createElement('li');
        li.style.visibility = 'hidden';
        li.id = 'NewGroupPlaceholder';
        let a = document.createElement('a');
        a.classList.add('icon-folder', 'svg');
        a.innerText = t('analytics', 'New report group');
        a.style.fontStyle = 'italic';
        a.addEventListener("drop", OCA.Analytics.Navigation.Drag.drop_newGroup_handler);
        a.addEventListener("dragover", OCA.Analytics.Navigation.Drag.dragover_handler);
        a.addEventListener("dragleave", OCA.Analytics.Navigation.Drag.dragleave_handler);
        li.appendChild(a);
        return li;
    },

    buildIntroRow: function () {
        let text;
        if (OCA.Analytics.isDataset) {
            text = t('analytics', 'No dataset yet');
        } else if (OCA.Analytics.isPanorama) {
            // TRANSLATORS "Panorama" will be a product name. Do not translate, just capitalize if required
            text = t('analytics', 'No panorama yet');
        } else {
            text = t('analytics', 'No report yet');
        }

        let li = document.createElement('li');
        li.innerHTML = '<div class="infoBox" style="margin-top: 50px;">' +
            '<img src="' + OC.imagePath('analytics', 'infoReport') + '" alt="info">\n' +
            '<div class="infoBoxHeader">' + text + '</div>\n';
        li.addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        return li;
    },

    buildOverviewButton: function () {
        const createNavEntry = (id, href, className, text, eventHandler) => {
            let div = document.createElement('div');
            div.classList.add('app-navigation-entry');
            div.style.width = "50%";

            let a = document.createElement('a');
            a.id = id;
            a.setAttribute('href', href);
            a.classList.add(className);
            a.innerText = text;
            if (eventHandler) a.addEventListener('click', eventHandler);

            div.appendChild(a);
            return div;
        };

        let li = document.createElement('li');
        li.style.marginBottom = "20px";
        const isReport = !OCA.Analytics.isDataset && !OCA.Analytics.isPanorama;

        const datatype = OCA.Analytics.isPanorama ? 'pa' : '';
        const hrefPrimary = OC.generateUrl('apps/analytics/' + datatype);
        const primaryClass = OCA.Analytics.isDataset ? 'icon-view-previous' : 'icon-analytics-overview';
        const primaryText = OCA.Analytics.isDataset ? t('analytics', 'Back to reports') : t('analytics', 'Overview');
        const primaryEvent = OCA.Analytics.isDataset ? OCA.Analytics.Navigation.handleBackToReportClicked : OCA.Analytics.Navigation.handleOverviewButton;

        li.appendChild(createNavEntry('overviewButton', hrefPrimary, primaryClass, primaryText, primaryEvent));

        const datatypeSecondary = OCA.Analytics.isDataset ? '' : OCA.Analytics.isPanorama ? '' : 'pa';
        const hrefSecondary = OC.generateUrl('apps/analytics/' + datatypeSecondary);

        // Determine the secondary button text and class based on conditions
        const secondaryText = OCA.Analytics.isPanorama
            ? t('analytics', 'Reports')
            : isReport
                // TRANSLATORS "Panorama" will be a product name. Do not translate, just capitalize if required
                ? t('analytics', 'Panoramas')
                : '';

        const secondaryClass = OCA.Analytics.isPanorama
            ? 'icon-analytics-report'
            : isReport
                ? 'icon-analytics-panorama'
                : '';

        if (secondaryText && secondaryClass) {
            li.appendChild(createNavEntry('overviewButton', hrefSecondary, secondaryClass, secondaryText, null));
        }

        return li;
    },

    buildSection: function (title, id) {
        let li = document.createElement('li');
        li.classList.add('collapsible', 'open');
        let div = document.createElement('div');
        div.classList.add('app-navigation-entry');
        let a = document.createElement('a');
        a.classList.add('icon-folder');
        a.innerText = title;
        a.setAttribute('href', '#');
        a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        div.appendChild(a);
        li.appendChild(div);
        let ul = document.createElement('ul');
        ul.id = id;
        li.appendChild(ul);
        return li;
    },

    buildNavigationRow: function (data, rootListId = 'navigationDatasets') {
        let li = document.createElement('li');
        let navigationEntryDiv = document.createElement('div');
        navigationEntryDiv.classList.add('app-navigation-entry');
        let typeIcon;
        let typeINT = parseInt(data['type']);

        let datatype;
        if (OCA.Analytics.isDataset) {
            datatype = 'd';
        } else if (data['item_type'] === 'panorama') {
            datatype = 'pa';
        } else {
            datatype = 'r';
        }

        let a = document.createElement('a');
        a.setAttribute('href', OC.generateUrl('apps/analytics/' + datatype + '/' + data['id']));
        a.style.position = 'relative';

        // make reports except folders drag-able
        a.draggable = false;
        if (typeINT !== OCA.Analytics.TYPE_GROUP) {
            a.draggable = true;
            a.addEventListener("dragstart", OCA.Analytics.Navigation.Drag.dragstart_handler);
            a.addEventListener("drop", OCA.Analytics.Navigation.Drag.drop_onReport_handler);
            a.addEventListener("dragover", OCA.Analytics.Navigation.Drag.dragover_report_handler);
            a.addEventListener("dragleave", OCA.Analytics.Navigation.Drag.dragleave_report_handler);
        }

        if (data['item_type'] === 'dataset') {
            typeIcon = 'icon-analytics-dataset';
        } else if (data['item_type'] === 'panorama') {
            typeIcon = 'icon-analytics-panorama';
        } else if (typeINT === OCA.Analytics.TYPE_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
            a.addEventListener("drop", OCA.Analytics.Navigation.Drag.drop_handler);
            a.addEventListener("dragover", OCA.Analytics.Navigation.Drag.dragover_handler);
            a.addEventListener("dragleave", OCA.Analytics.Navigation.Drag.dragleave_handler);
        } else {
            typeIcon = 'icon-analytics-report';
        }

        if (data['isShare'] === 1) {
            if (OCA.Analytics.isDataset) {
                // don´t show shared reports in advanced config mode at all as no config is possible
                return;
            }
            typeIcon = 'icon-shared';
            //data['type'] = OCA.Analytics.TYPE_SHARED;
        }

        a.classList.add(typeIcon);
        a.classList.add('svg');

        // also add items to the navigation menu
        a.innerText = data['name'];
        a.dataset.id = data['id'];
        a.dataset.type = data['type'];
        a.dataset.name = data['name'];
        a.dataset.parent = data['parent'];
        a.dataset.item_type = data['item_type'];
        if (data['permissions']) {
            a.dataset.permissons = data['permissions'];
        }
        li.appendChild(navigationEntryDiv);
        navigationEntryDiv.appendChild(a);

        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data['id'];

        if (parseInt(data['favorite']) === 1) {
            let divFav = OCA.Analytics.Navigation.buildFavoriteIcon(data['id'], data['name'])
            a.appendChild(divFav);
        }

        if (OCA.Analytics.isDataset) {
            let divUtils = OCA.Analytics.Navigation.buildNavigationUtilsDataset(data);
            navigationEntryDiv.appendChild(divUtils);
        } else {
            let divUtils = OCA.Analytics.Navigation.buildNavigationUtils(data);
            let divMenu = OCA.Analytics.Navigation.buildNavigationMenu(data);
            if (divMenu.firstElementChild.firstElementChild.childElementCount !== 0) {
                // do not add an empty menu. can occur for e.g. shared group folders
                navigationEntryDiv.appendChild(divUtils);
                navigationEntryDiv.appendChild(divMenu);
            }
        }

        if (typeINT === OCA.Analytics.TYPE_GROUP) {
            li.appendChild(ulSublist);
            a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Analytics.Navigation.handleNavigationClicked);
        }

        // add navigation row to navigation list or to an existing parent node
        let categoryList;
        if (parseInt(data['parent']) !== 0 && document.getElementById('dataset-' + data['parent'])) {
            categoryList = document.getElementById('dataset-' + data['parent']);
            categoryList.appendChild(li);
        } else {
            categoryList = document.getElementById(rootListId);
            if (parseInt(data['favorite']) === 1) {
                categoryList.prepend(li);
            } else {
                categoryList.appendChild(li);
            }
        }
    },

    buildFavoriteIcon: function (id, name) {
        let divFav = document.createElement('div');
        divFav.classList.add('favorite-mark');
        divFav.id = 'fav-' + id;
        let spanFav = document.createElement('span');
        spanFav.classList.add('icon', 'icon-starred', 'favorite-star');
        spanFav.dataset.testing = 'favI' + name;
        divFav.appendChild(spanFav)
        return divFav;
    },

    buildNavigationUtilsDataset: function (data) {
        let divUtils = document.createElement('div');
        divUtils.classList.add('app-navigation-entry-utils');
        let ulUtils = document.createElement('ul');

        // add indicators when a data load or schedule is existing
        if (data.schedules && parseInt(data.schedules) !== 0) {
            let liScheduleButton = document.createElement('li');
            liScheduleButton.classList.add('app-navigation-entry-utils-menu-button');
            let ScheduleButton = document.createElement('button');
            ScheduleButton.classList.add('icon-history', 'toolTip');
            ScheduleButton.setAttribute('title', t('analytics', 'Scheduled data load'));
            liScheduleButton.appendChild(ScheduleButton);
            ulUtils.appendChild(liScheduleButton);
        }
        if (data.dataloads && parseInt(data.dataloads) !== 0) {
            let liScheduleButton = document.createElement('li');
            liScheduleButton.classList.add('app-navigation-entry-utils-menu-button');
            let ScheduleButton = document.createElement('button');
            ScheduleButton.classList.add('icon-category-workflow', 'toolTip');
            ScheduleButton.setAttribute('title', t('analytics', 'Data load'));
            liScheduleButton.appendChild(ScheduleButton);
            ulUtils.appendChild(liScheduleButton);
        }
        divUtils.appendChild(ulUtils);

        return divUtils;
    },

    buildNavigationUtils: function (data) {
        let divUtils = document.createElement('div');
        divUtils.classList.add('app-navigation-entry-utils');
        let ulUtils = document.createElement('ul');

        let liMenuButton = document.createElement('li');
        liMenuButton.classList.add('app-navigation-entry-utils-menu-button');
        let button = document.createElement('button');
        button.addEventListener('click', OCA.Analytics.Navigation.handleOptionsClicked);
        button.dataset.id = data.id;
        button.dataset.name = data.name;
        button.dataset.type = data.type;
        button.ariaLabel = data.name;
        button.classList.add('menuButton');
        liMenuButton.appendChild(button);
        ulUtils.appendChild(liMenuButton);
        divUtils.appendChild(ulUtils);

        return divUtils;
    },

    buildNavigationMenu: function (data) {
        // clone the DOM template
        let navigationMenu = document.importNode(document.getElementById('templateNavigationMenu').content, true);

        let menu = navigationMenu.getElementById('navigationMenu');
        menu.dataset.id = data.id;
        menu.dataset.type = data.type;
        menu.dataset.name = data.name;
        menu.dataset.item_type = data.item_type;

        let edit = navigationMenu.getElementById('navigationMenuEdit');
        edit.addEventListener('click', OCA.Analytics.Navigation.handleBasicSettingsClicked);
        edit.dataset.testing = 'basic' + data.name;

        let newGroup = navigationMenu.getElementById('navigationMenuNewGroup');
        newGroup.addEventListener('click', OCA.Analytics.Navigation.handleNewGroupClicked);
        newGroup.dataset.testing = 'newGroup' + data.name;
        newGroup.dataset.id = data.id;

        let share = navigationMenu.getElementById('navigationMenuShare');
        share.addEventListener('click', OCA.Analytics.Navigation.buildShareModal);
        share.dataset.testing = 'share' + data.name;
        share.dataset.id = data.id;

        let dataset = navigationMenu.getElementById('navigationMenuAdvanced');
        dataset.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
        dataset.dataset.testing = 'advanced' + data.name;
        dataset.dataset.dataset = data.dataset;

        let favorite = navigationMenu.getElementById('navigationMenueFavorite');
        favorite.addEventListener('click', OCA.Analytics.Navigation.handleFavoriteClicked);
        favorite.dataset.testing = 'fav' + data.name;

        if (parseInt(data.favorite) === 1) {
            favorite.firstElementChild.classList.replace('icon-star', 'icon-starred');
            favorite.children[1].innerText = t('analytics', 'Remove from favorites');
        }

        let deleteReport = navigationMenu.getElementById('navigationMenuDelete');
        deleteReport.dataset.id = data.id;
        deleteReport.addEventListener('click', OCA.Analytics.Navigation.handleDeleteButton);

        let unshareReport = navigationMenu.getElementById('navigationMenuUnshare');
        unshareReport.dataset.shareId = data.shareId;
        unshareReport.addEventListener('click', OCA.Analytics.Navigation.handleUnshareButton);

        let separator = navigationMenu.getElementById('navigationMenueSeparator');

        if (data['isShare'] === undefined) {
            unshareReport.parentElement.remove();
        } else if (data['isShare'] !== undefined) {
            separator.remove();
            deleteReport.parentElement.remove();
            dataset.parentElement.remove();
            newGroup.parentElement.remove();
            share.parentElement.remove();
            if (parseInt(data['type']) === OCA.Analytics.TYPE_GROUP) {
                edit.parentElement.remove();
            }
            if (parseInt(data['isShare']) === OCA.Analytics.SHARE_TYPE_GROUP) {
                unshareReport.parentElement.remove();
            }
        }
        if (parseInt(data['type']) === OCA.Analytics.TYPE_GROUP) {
            favorite.parentElement.remove();
            newGroup.parentElement.remove();
            deleteReport.children[1].innerHTML = t('analytics', 'Delete folder');
        }
        if (parseInt(data['type']) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            dataset.parentElement.remove();
        }

        if (data['item_type'] === 'panorama') {
            edit.parentElement.remove();
            newGroup.parentElement.remove(); // re-add later
        } else {
            share.parentElement.remove();
        }

        return navigationMenu;
    },

    handleNewButton: function () {
        // ToDo: change app.js to register handler

        let handler = OCA.Analytics.Navigation.handlers['create'];
        if (handler) {
            handler();
        } else if (OCA.Analytics.isDataset) {
            OCA.Analytics.Wizard.sildeArray = [
                ['', ''],
                ['wizardDatasetGeneral', OCA.Analytics.Advanced.Dataset.wizard],
            ];
            OCA.Analytics.Wizard.show();
        } else {
            OCA.Analytics.Sidebar.close();
            OCA.Analytics.Wizard.sildeArray = [
                ['', ''],
                ['wizardNewGeneral', OCA.Analytics.Sidebar.Report.wizard],
                ['wizardNewType', ''],
                ['wizardNewVisual', '']
            ];
            OCA.Analytics.Wizard.show();
        }
    },

    handleOverviewButton: function (evt) {
        if (evt) {
            evt.preventDefault();
            history.pushState(null, '', evt.target.href);
        }

        OCA.Analytics.Sidebar?.close?.();
        if (document.querySelector('#navigationDatasets .active')) {
            document.querySelector('#navigationDatasets .active').classList.remove('active');
        }
        OCA.Analytics.Visualization.hideElement('analytics-content');
        OCA.Analytics.Visualization.showElement('analytics-intro');
        // ToDo: Do not reload DB all the time as it is already in the background
        document.getElementById('ulAnalytics').innerHTML = '';
        OCA.Analytics.Dashboard?.init?.();
        OCA.Analytics.Panorama?.Dashboard?.init?.();
    },

    handleNavigationClicked: function (evt) {
        // ToDo: change app.js to register handler
        evt.preventDefault();
        history.pushState(null, '', evt.target.href);

        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        let activeCategory = document.querySelector('#navigationDatasets .active');
        if (evt) {
            if (activeCategory) {
                activeCategory.classList.remove('active');
            }
            evt.target.parentElement.classList.add('active');
        }

        let handler = OCA.Analytics.Navigation.handlers['navigationClicked'];
        const itemType = evt.target.dataset.item_type;
        if (handler) {
            handler(evt);
        } else if (OCA.Analytics.isDataset) {
            OCA.Analytics.Advanced.showSidebar(evt);
            evt.stopPropagation();
        } else if (itemType === 'panorama') {
            OCA.Analytics.Panorama.handleNavigationClicked(evt);
        } else {
            document.getElementById('filterVisualisation').innerHTML = '';
            if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined') {
                // reset any user-filters and display the filters stored for the report
                delete OCA.Analytics.currentReportData.options;
            }
            OCA.Analytics.unsavedFilters = false;
            OCA.Analytics.Sidebar.close();
            OCA.Analytics.Backend.getData();
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Analytics.UI?.hideReportMenu?.();
        let openMenu;
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            openMenu = document.querySelector('.app-navigation-entry-menu.open').previousElementSibling.firstElementChild.firstElementChild.firstElementChild.dataset.id;
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        if (openMenu !== evt.target.dataset.id) {
            evt.target.parentElement.parentElement.parentElement.nextElementSibling.classList.add('open');
        }
    },

    handleBasicSettingsClicked: function (evt) {
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        evt.stopPropagation();
        OCA.Analytics.Sidebar?.showSidebar?.(evt);
    },

    handleNewGroupClicked: function (evt) {
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        evt.stopPropagation();
        OCA.Analytics.Sidebar.Report.createGroup(evt.target.parentElement.dataset.id);
    },


    handleAdvancedClicked: function (evt) {
        let datasetId = evt.target.parentNode.dataset.dataset;
        let datasetUrl = '';
        if (datasetId !== undefined) datasetUrl = '/' + datasetId
        window.location = OC.generateUrl('apps/analytics/d') + datasetUrl;
        evt.stopPropagation();
    },

    handleFavoriteClicked: function (evt) {
        let datasetId = evt.target.closest('div').dataset.id;
        let icon = evt.target.parentNode.firstElementChild;
        let isFavorite = 'false';

        if (icon.classList.contains('icon-star')) {
            icon.classList.replace('icon-star', 'icon-starred');
            evt.target.parentNode.children[1].innerHTML = t('analytics', 'Remove from favorites');
            isFavorite = 'true';

            let divFav = OCA.Analytics.Navigation.buildFavoriteIcon(datasetId, '');
            evt.target.closest('div').parentElement.firstElementChild.appendChild(divFav);

        } else {
            icon.classList.replace('icon-starred', 'icon-star');
            evt.target.parentNode.children[1].innerHTML = t('analytics', 'Add to favorites');
            document.getElementById('fav-' + datasetId).remove();
        }

        let handler = OCA.Analytics.Navigation.handlers['favoriteUpdate'];
        if (handler) {
            handler(datasetId, isFavorite);
        } else {
            OCA.Analytics.Navigation.favoriteUpdate(datasetId, isFavorite);
        }
        document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
    },

    handleBackToReportClicked: function (evt) {
        window.location = OC.generateUrl('apps/analytics/');
        evt.stopPropagation();
    },

    handleGroupClicked: function (evt) {
        if (evt.target.parentNode.parentNode.classList.contains('open')) {
            evt.target.parentNode.parentNode.classList.remove('open');
        } else {
            evt.target.parentNode.parentNode.classList.add('open');
        }
        evt.preventDefault();
        history.pushState(null, '', evt.target.href);
    },

    handleImportButton: function () {
        const fileInput = document.getElementById('importFile');
        fileInput.click();
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) {
                return;
            }
            const reader = new FileReader();
            reader.readAsText(file);
            reader.onload = async () => {
                OCA.Analytics.Sidebar.Report.import(null, reader.result);
            };
        })
    },

    handleSettingsButton: function () {
        document.getElementById('app-settings').classList.toggle('open');
    },

    handleDeleteButton: function (evt) {
        // ToDo: change app.js to register handler

        let handler = OCA.Analytics.Navigation.handlers['delete'];
        if (handler) {
            handler(evt);
        } else if (evt.target.parentNode.dataset.item_type === 'panorama') {
            OCA.Analytics.Panorama.handleDeletePanoramaButton(evt);
        } else {
            OCA.Analytics.Sidebar.Report.handleDeleteButton(evt);
        }
    },

    handleUnshareButton: function (evt) {
        let shareId = evt.target.parentNode.dataset.shareId;

        let xhr = new XMLHttpRequest();
        xhr.open('DELETE', OC.generateUrl('apps/analytics/share/' + shareId, true), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                OCA.Analytics.Navigation.init();
            }
        };

        xhr.send();
    },

    favoriteUpdate: function (datasetId, isFavorite) {
        let params = 'favorite=' + isFavorite;
        let xhr = new XMLHttpRequest();
        xhr.open('POST', OC.generateUrl('apps/analytics/favorite/' + datasetId, true), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(params);
    },

    buildShareModal: function (evt) {
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        let navigationItem = evt.target.closest('div');

        document.getElementById('app-sidebar').dataset.id = navigationItem.dataset.id;
        document.getElementById('app-sidebar').dataset.item_type = navigationItem.dataset.item_type;

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Share') + ' ' + navigationItem.dataset.name,
            OCA.Analytics.Notification.dialogClose
        );
        OCA.Analytics.Navigation.updateShareModal();
    },

    updateShareModal: function () {
        const dummy = document.createElement('div');
        dummy.id = 'tabHeaderShare';
        dummy.classList.add('tabHeaders', 'tabHeader', 'selected');
        dummy.addEventListener('click', OCA.Analytics.Navigation.updateShareModal);

        const container = document.createElement('div');
        container.id = 'tabContainerShare';

        const content = document.createDocumentFragment();
        content.appendChild(dummy);
        content.appendChild(container);

        OCA.Analytics.Notification.htmlDialogUpdate(
            content,
            t('analytics', 'Select the share receiver')
        );

        OCA.Analytics.Sidebar.Share.tabContainerShare();

    }

};
/**
 * @namespace OCA.Analytics.Navigation.Drag
 */
OCA.Analytics.Navigation.Drag = {
    dragObject: null,

    dragstart_handler: function (ev) {
        ev.dataTransfer.setData("id", ev.target.dataset.id);
        ev.effectAllowed = "copyMove";
        OCA.Analytics.Navigation.Drag.dragObject = ev.target;
        document.getElementById('NewGroupPlaceholder').style.visibility = 'initial';
    },

    dragend_handler: function (ev) {
        ev.dataTransfer.clearData();
    },

    drop_handler: function (ev) {
        ev.preventDefault();
        OCA.Analytics.Navigation.Drag.addReportToGroup(this.dataset.id, ev.dataTransfer.getData("id"));
        ev.currentTarget.style.background = "";
    },

    drop_onReport_handler: function (ev) {
        ev.preventDefault();
        OCA.Analytics.Navigation.Drag.addReportToGroup(this.dataset.parent, ev.dataTransfer.getData("id"));
        ev.currentTarget.style.background = "";
    },

    drop_newGroup_handler: function (ev) {
        ev.preventDefault();
        OCA.Analytics.Sidebar.Report.createGroup(ev.dataTransfer.getData("id"));
        ev.currentTarget.style.background = "";
    },

    dragover_handler: function (ev) {
        ev.currentTarget.style.background = "#FCEFA1";
        ev.dataTransfer.dropEffect = "move";
        ev.preventDefault();
    },

    dragleave_handler: function (ev) {
        ev.currentTarget.style.background = "";
        ev.preventDefault();
    },

    dragover_report_handler: function (ev) {
        ev.dataTransfer.dropEffect = "move";
        ev.preventDefault();
    },

    dragleave_report_handler: function (ev) {
        ev.preventDefault();
    },

    addReportToGroup: function (groupId, reportId) {
        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId + '/group';
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                groupId: groupId,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init(groupId);
            });
    },
}

document.addEventListener('DOMContentLoaded', function () {
    if (!OCA.Analytics.isDataset && !OCA.Analytics.isPanorama) {
        OCA.Analytics.WhatsNew.whatsnew();
        if (OCA.Analytics.Core.getInitialState('wizard') !== '1') {
            OCA.Analytics.Wizard.showFirstStart();
        }
    }
    document.getElementById('wizardStart').addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
    document.getElementById('importDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleImportButton);
    document.getElementById('appSettingsButton').addEventListener('click', OCA.Analytics.Navigation.handleSettingsButton);
});