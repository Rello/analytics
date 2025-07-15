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

OCA.Analytics.Panorama = {
    stories: {},
}
/**
 * @namespace OCA.Analytics.Navigation
 */
OCA.Analytics.Navigation = {
    quickstartValue: '',
    quickstartId: 0,

    init: function (navigationItem) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        OCA.Analytics.Navigation.getNavigationContent(navigationItem);
        OCA.Analytics.Report?.Backend?.getDatasetDefinitions?.();
    },

    getNavigationContent: function (navigationItem) {
        let requests = [];
        // always fetch datasets so they can be listed together with reports
        requests.push(fetch(OC.generateUrl('apps/analytics/dataset'), {
            method: 'GET',
            headers: OCA.Analytics.headers()
        }));

        requests.push(fetch(OC.generateUrl('apps/analytics/report'), {
            method: 'GET',
            headers: OCA.Analytics.headers()

        }));
        requests.push(fetch(OC.generateUrl('apps/analytics/panorama'), {
            method: 'GET',
            headers: OCA.Analytics.headers()
        }));

        Promise.all(requests)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(responseData => {
                const datasets = responseData[0];
                const reports = responseData[1];
                const panoramas = responseData[2];

                OCA.Analytics.datasets = datasets;
                let data;
                if (Array.isArray(panoramas)) {
                    OCA.Analytics.stories = panoramas;
                    OCA.Analytics.reports = panoramas.concat(reports);
                    data = panoramas.concat(reports, datasets);
                } else {
                    OCA.Analytics.reports = reports;
                    data = reports.concat(datasets);
                }

                OCA.Analytics.Navigation.buildNavigation(data);

                if (navigationItem === undefined) {
                    OCA.Analytics.Dashboard.init();
                } else {
                    let navigationType = navigationItem[1]; // r, pa, d
                    let navigationId = navigationItem[2];
                    // Map short type to real item_type
                    const typeMap = {r: 'report', pa: 'panorama', d: 'dataset'};
                    const mappedType = typeMap[navigationType] || navigationType;

                    // Find the item with both id and item_type
                    const foundItem = data.find(
                        o => parseInt(o.id) === parseInt(navigationId) && o.item_type === mappedType
                    );

                    if (navigationId && foundItem) {
                        OCA.Analytics.Sidebar?.close?.();
                        // QuerySelector for both id and item_type
                        let navigationItemElem = document.querySelector(
                            '#navigationDatasets [data-id="' + navigationId + '"][data-item_type="' + mappedType + '"]'
                        );
                        if (
                            navigationItemElem &&
                            navigationItemElem.parentElement.parentElement.parentElement.classList.contains('collapsible')
                        ) {
                            navigationItemElem.parentElement.parentElement.parentElement.classList.add('open');
                        }
                        if (OCA.Analytics.isNewObject) {
                            OCA.Analytics.isNewObject = false;
                            document.querySelector(
                                '#navigationDatasets [data-id="' + navigationId + '"][data-item_type="' + mappedType + '"] #navigationMenuEdit'
                            ).click();
                        } else if (navigationItemElem) {
                            navigationItemElem.click();
                        }
                        OCA.Analytics.Navigation.saveOpenState();
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
        } else {
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Favorites'), 'section-favorites', 'icon-analytics-star', true, true));
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Panoramas'), 'section-panoramas', 'icon-analytics-panorama'));
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Reports'), 'section-reports', 'icon-analytics-report'));
            nav.appendChild(OCA.Analytics.Navigation.buildSection(t('analytics', 'Datasets'), 'section-datasets', 'icon-analytics-dataset'));

            for (let navigation of data) {
                let rootId = 'section-reports';
                if (navigation.item_type === 'panorama') {
                    rootId = 'section-panoramas';
                } else if (navigation.item_type === 'dataset') {
                    rootId = 'section-datasets';
                }

                // always render the item in its original section
                OCA.Analytics.Navigation.buildNavigationRow(navigation, rootId);

                // additionally show favorites in the favorites section
                if (parseInt(navigation.favorite) === 1 &&
                    parseInt(navigation.type) !== OCA.Analytics.TYPE_GROUP) {
                    OCA.Analytics.Navigation.buildNavigationRow(navigation, 'section-favorites');
                }
            }
        }

        nav.appendChild(OCA.Analytics.Navigation.buildNewGroupPlaceholder());
        nav.appendChild(OCA.Analytics.Navigation.buildNewButton()); // first pinned
        // no secondary pinned buttons
        OCA.Analytics.Navigation.restoreOpenState();
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
        a.innerText = t('analytics', 'New');

        li.appendChild(navigationEntrydiv);
        navigationEntrydiv.appendChild(a);
        navigationEntrydiv.appendChild(OCA.Analytics.Navigation.buildNewMenu());
        return li;
    },

    buildNewMenu: function () {
        let menu = document.importNode(document.getElementById('templateNewMenu').content, true);
        menu = menu.firstElementChild;

        menu.querySelector('#newMenuReport').addEventListener('click', function (evt) {
            evt.stopPropagation();
            OCA.Analytics.Navigation.handleNewMenu('report');
        });
        menu.querySelector('#newMenuPanorama').addEventListener('click', function (evt) {
            evt.stopPropagation();
            OCA.Analytics.Navigation.handleNewMenu('panorama');
        });
        menu.querySelector('#newMenuDataset').addEventListener('click', function (evt) {
            evt.stopPropagation();
            OCA.Analytics.Navigation.handleNewMenu('dataset');
        });

        return menu;
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
        if (OCA.Analytics.isPanorama) {
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

    // Overview remains a standalone entry. Using buildSection would add a collapsible
    // wrapper which does not fit this single-link element.
    buildOverviewButton: function () {
        const createNavEntry = (id, href, className, text, eventHandler) => {
            let div = document.createElement('div');
            div.classList.add('app-navigation-entry');

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

        const datatype = OCA.Analytics.isPanorama ? 'pa' : '';
        const hrefPrimary = OC.generateUrl('apps/analytics/' + datatype);
        const primaryClass = 'icon-analytics-overview';
        const primaryText = t('analytics', 'Overview');

        li.appendChild(createNavEntry('overviewButton', hrefPrimary, primaryClass, primaryText, OCA.Analytics.Navigation.handleOverviewButton));

        return li;
    },

    buildSection: function (title, id, icon = 'icon-folder', open = false, closable = true) {
        let li = document.createElement('li');
        li.classList.add('collapsible');
        if (open) {
            li.classList.add('open');
        }
        li.dataset.sectionId = id;
        let div = document.createElement('div');
        div.classList.add('app-navigation-entry');
        let a = document.createElement('a');
        a.classList.add(icon, 'svg');
        a.innerText = title;
        a.setAttribute('href', '#');
        if (closable) {
            a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        }
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
        if (data['item_type'] === 'dataset') {
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
            typeIcon = 'icon-shared';
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

        if (data['item_type'] === 'dataset') {
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
            li.dataset.id = data['id'];
            a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Analytics.Navigation.handleNavigationClicked);
        }

        // add navigation row to navigation list or to an existing parent node
        let categoryList;
        if (rootListId !== 'section-favorites' &&
            parseInt(data['parent']) !== 0 &&
            document.getElementById('dataset-' + data['parent'])) {
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

    updateFavoriteUI: function (datasetId, itemType, isFavorite) {
        const anchors = document.querySelectorAll(
            '#navigationDatasets a[data-id="' + datasetId + '"][data-item_type="' + itemType + '"]'
        );
        anchors.forEach(anchor => {
            const favMark = anchor.querySelector('#fav-' + datasetId);
            if (isFavorite === 'true') {
                if (!favMark) {
                    anchor.appendChild(OCA.Analytics.Navigation.buildFavoriteIcon(datasetId, ''));
                }
            } else if (favMark) {
                favMark.remove();
            }
        });

        const menus = document.querySelectorAll(
            '.app-navigation-entry-menu[data-id="' + datasetId + '"][data-item_type="' + itemType + '"] #navigationMenueFavorite'
        );
        menus.forEach(menuItem => {
            const icon = menuItem.firstElementChild;
            if (isFavorite === 'true') {
                icon.classList.replace('icon-star', 'icon-starred');
                menuItem.children[1].innerHTML = t('analytics', 'Remove from favorites');
            } else {
                icon.classList.replace('icon-starred', 'icon-star');
                menuItem.children[1].innerHTML = t('analytics', 'Add to favorites');
            }
        });
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
        share.addEventListener('click', OCA.Analytics.Share.buildShareModal);
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
        }

        return navigationMenu;
    },

    handleNewButton: function (evt) {
        evt?.preventDefault();
        const openMenu = document.querySelector('.app-navigation-entry-menu.open');
        if (openMenu && openMenu.id !== 'newMenu') {
            openMenu.classList.remove('open');
        }

        const menu = document.getElementById('newMenu');
        menu.classList.toggle('open');
    },

    handleNewMenu: function (type) {
        const menu = document.getElementById('newMenu');
        menu.classList.remove('open');

        const handler = OCA.Analytics.handlers['create']?.[type];
        if (handler) {
            handler();
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
        // ToDo: Do not reload DB all the time as it is already in the background
        document.getElementById('ulAnalytics').innerHTML = '';
        OCA.Analytics.Dashboard.init();
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

        let type = evt.target.dataset.item_type;
        let handler = OCA.Analytics.handlers['navigationClicked']?.[type];
        if (handler) {
            handler(evt);
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Analytics.Report?.hideReportMenu?.();
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

        const menu = evt.target.closest('#navigationMenu');
        const id = menu.dataset.id;
        const type = menu.dataset.item_type;
        const anchor = document.querySelector(
            '#navigationDatasets a[data-id="' + id + '"][data-item_type="' + type + '"]'
        );
        if (anchor.parentElement.classList.contains('active') === false) {
            anchor?.click();
        }

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
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        evt.stopPropagation();

        const datasetId = evt.target.closest('a').dataset.dataset;
        const anchor = document.querySelector(
            '#navigationDatasets a[data-id="' + datasetId + '"][data-item_type="dataset"]'
        );

        if (anchor) {
            let parent = anchor.parentElement;
            while (parent && parent.id !== 'navigationDatasets') {
                if (parent.classList && parent.classList.contains('collapsible')) {
                    parent.classList.add('open');
                }
                parent = parent.parentElement;
            }
            anchor.click();
            OCA.Analytics.Navigation.saveOpenState();
        } else if (datasetId !== undefined) {
            const datasetUrl = '/' + datasetId;
            window.location = OC.generateUrl('apps/analytics/d') + datasetUrl;
        }
    },

    handleFavoriteClicked: function (evt) {
        const menu = evt.target.closest('div');
        const datasetId = menu.dataset.id;
        const itemType = menu.dataset.item_type;
        const isAdding = evt.target.parentNode.firstElementChild.classList.contains('icon-star');

        let isFavorite = 'false';

        if (isAdding) {
            isFavorite = 'true';

            // add item to favorites section if not present
            const existing = document.querySelector('#section-favorites [data-id="' + datasetId + '"][data-item_type="' + itemType + '"]');
            if (!existing) {
                const entry = OCA.Analytics.reports.find(x =>
                    parseInt(x.id) === parseInt(datasetId) && x.item_type === itemType
                );
                if (entry) {
                    OCA.Analytics.Navigation.buildNavigationRow(entry, 'section-favorites');
                }
            }

        } else {

            // remove item from favorites section
            const favItem = document.querySelector('#section-favorites [data-id="' + datasetId + '"][data-item_type="' + itemType + '"]');
            if (favItem) favItem.parentElement.remove();
        }

        OCA.Analytics.Navigation.updateFavoriteUI(datasetId, itemType, isAdding ? 'true' : 'false');

        const handler = OCA.Analytics.handlers['favoriteUpdate']?.[itemType];
        if (handler) {
            handler(datasetId, isFavorite);
        }
        document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
    },


    handleGroupClicked: function (evt) {
        const li = evt.target.parentNode.parentNode;
        /*if (li.dataset.sectionId === 'section-favorites') {
            // favorites are always open and not collapsible
            evt.preventDefault();
            return;
        }*/

        if (li.classList.contains('open')) {
            li.classList.remove('open');
        } else {
            // Only close other root sections when toggling a root section
            if (li.dataset.sectionId) {
                document.querySelectorAll('#navigationDatasets > li.collapsible[data-section-id]')
                    .forEach(node => {
                        if (node !== li) {
                            node.classList.remove('open');
                        }
                    });
            }
            li.classList.add('open');
        }
        OCA.Analytics.Navigation.saveOpenState();
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

    addNavigationItem: function (item) {
        if (!item.item_type) item.item_type = 'report';

        if (item.item_type === 'dataset') {
            OCA.Analytics.datasets.push(item);
        } else if (item.item_type === 'panorama') {
            OCA.Analytics.stories.push(item);
            OCA.Analytics.reports.push(item);
        } else {
            OCA.Analytics.reports.push(item);
        }

        let section = 'section-reports';
        if (item.item_type === 'panorama') {
            section = 'section-panoramas';
        } else if (item.item_type === 'dataset') {
            section = 'section-datasets';
        }
        OCA.Analytics.Navigation.buildNavigationRow(item, section);

        const rootLi = document.querySelector('#navigationDatasets li.collapsible[data-section-id="' + section + '"]');
        if (rootLi) {
            document.querySelectorAll('#navigationDatasets > li.collapsible[data-section-id]')
                .forEach(node => {
                    if (node !== rootLi && node.dataset.sectionId !== 'section-favorites') {
                        node.classList.remove('open');
                    }
                });
            rootLi.classList.add('open');
        }

        let parent = item.parent;
        while (parent && parseInt(parent) !== 0) {
            const groupLi = document.querySelector('#navigationDatasets li.collapsible[data-id="' + parent + '"]');
            if (groupLi) {
                groupLi.classList.add('open');
                const anchor = groupLi.querySelector('a');
                parent = anchor ? anchor.dataset.parent : 0;
            } else {
                break;
            }
        }

        OCA.Analytics.Navigation.saveOpenState();
    },

    removeNavigationItem: function (id, itemType) {
        document.querySelectorAll('#navigationDatasets a[data-id="' + id + '"][data-item_type="' + itemType + '"]').forEach(anchor => {
            const li = anchor.closest('li');
            if (li) li.remove();
        });

        if (itemType === 'dataset') {
            OCA.Analytics.datasets = OCA.Analytics.datasets.filter(d => parseInt(d.id) !== parseInt(id));
        } else if (itemType === 'panorama') {
            OCA.Analytics.stories = OCA.Analytics.stories.filter(p => parseInt(p.id) !== parseInt(id));
            OCA.Analytics.reports = OCA.Analytics.reports.filter(r => !(parseInt(r.id) === parseInt(id) && r.item_type === 'panorama'));
        } else {
            OCA.Analytics.reports = OCA.Analytics.reports.filter(r => !(parseInt(r.id) === parseInt(id) && r.item_type !== 'panorama'));
        }
    },

    handleDeleteButton: function (evt) {
        const menu = evt.target.closest('#navigationMenu');
        const type = menu?.dataset.item_type;
        const handler = OCA.Analytics.handlers['delete']?.[type];
        if (handler) {
            handler(evt);
        }
    },

    handleUnshareButton: function (evt) {
        let shareId = evt.target.parentNode.dataset.shareId;

        OCA.Analytics.Navigation.saveOpenState();

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

    saveOpenState: function () {
        const openNodes = [];
        document.querySelectorAll('#navigationDatasets li.collapsible.open').forEach(li => {
            if (li.dataset.id) {
                openNodes.push('g-' + li.dataset.id);
            } else if (li.dataset.sectionId) {
                openNodes.push('s-' + li.dataset.sectionId);
            }
        });
        localStorage.setItem('analyticsNavState', JSON.stringify(openNodes));
    },

    restoreOpenState: function () {
        let saved;
        try {
            saved = JSON.parse(localStorage.getItem('analyticsNavState')) || [];
        } catch (e) {
            saved = [];
        }
        saved.forEach(id => {
            if (id.startsWith('g-')) {
                const gid = id.slice(2);
                const li = document.querySelector('#navigationDatasets li.collapsible[data-id="' + gid + '"]');
                if (li) li.classList.add('open');
            } else if (id.startsWith('s-')) {
                const sid = id.slice(2);
                const li = document.querySelector('#navigationDatasets li.collapsible[data-section-id="' + sid + '"]');
                if (li) li.classList.add('open');
            }
        });

        const fav = document.querySelector('#navigationDatasets li.collapsible[data-section-id="section-favorites"]');
        if (fav) fav.classList.add('open');

        const openSections = Array.from(document.querySelectorAll('#navigationDatasets > li.collapsible[data-section-id]:not([data-section-id="section-favorites"]).open'));
        if (openSections.length > 1) {
            for (let i = 1; i < openSections.length; i++) {
                openSections[i].classList.remove('open');
            }
        }
    },

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
        OCA.Analytics.Navigation.saveOpenState();
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
    OCA.Analytics.WhatsNew.whatsnew();
    if (OCA.Analytics.Core.getInitialState('wizard') !== '1') {
        OCA.Analytics.Wizard.showFirstStart();
    }

    document.getElementById('wizardStart').addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
    document.getElementById('importDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleImportButton);
    document.getElementById('appSettingsButton').addEventListener('click', OCA.Analytics.Navigation.handleSettingsButton);
});