/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
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

    init: function (datasetId) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" className="get-metadata icon-loading"></div>';
        OCA.Analytics.Navigation.getDatasets(datasetId);
    },

    buildNavigation: function (data) {
        document.getElementById('navigationDatasets').innerHTML = '';
        let li = document.createElement('li');
        let a = document.createElement('a');
        a.classList.add('icon-toggle-pictures');
        a.innerText = t('analytics', 'Overview');
        a.addEventListener('click', OCA.Analytics.Navigation.handleOverviewButton);
        li.appendChild(a);
        document.getElementById('navigationDatasets').appendChild(li);

        let li2 = document.createElement('li');
        let a2 = document.createElement('a');
        a2.classList.add('icon-add');
        a2.innerText = t('analytics', 'New report');

        a2.id = 'newDatasetButton';
        a2.addEventListener('click', OCA.Analytics.Navigation.handleNewDatasetButton);
        li2.appendChild(a2);
        document.getElementById('navigationDatasets').appendChild(li2);

        for (let navigation of data) {
            OCA.Analytics.Navigation.buildNavigationRow(navigation);
        }
    },

    buildNavigationRow: function (data) {
        let li = document.createElement('li');
        let typeIcon;

        let a = document.createElement('a');
        a.setAttribute('href', '#/r/' + data['id']);
        let typeINT = parseInt(data['type']);
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE || typeINT === OCA.Analytics.TYPE_EXCEL) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_SHARED) {
            if (document.getElementById('advanced').value === 'true') {
                // don´t show shared reports in advanced config mode at all as no config is possible
                return;
            }
            typeIcon = 'icon-shared';
        } else if (typeINT === OCA.Analytics.TYPE_EMPTY_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
        } else {
            typeIcon = 'icon-external';
        }
        a.classList.add(typeIcon);

        a.innerText = data['name'];
        a.dataset.id = data['id'];
        a.dataset.type = data['type'];
        a.dataset.name = data['name'];
        li.appendChild(a);

        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data['id'];

        if (parseInt(data['favorite']) === 1) {
            let spanFav = document.createElement('span');
            spanFav.id = 'fav-' + data['id'];
            spanFav.classList.add('icon', 'icon-starred');
            spanFav.style.opacity = '0.5';
            spanFav.dataset.testing = 'favI' + data['name'];
            li.appendChild(spanFav);
        }

        let divUtils = OCA.Analytics.Navigation.buildNavigationUtils(data);
        let divMenu = OCA.Analytics.Navigation.buildNavigationMenu(data);
        li.appendChild(divUtils);
        li.appendChild(divMenu);

        if (typeINT === OCA.Analytics.TYPE_EMPTY_GROUP) {
            li.appendChild(ulSublist);
            a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Analytics.Navigation.handleNavigationClicked);
        }

        // add navigation row to navigation list or to an existing parent node
        let categoryList;
        if (parseInt(data['parent']) !== 0 && document.getElementById('dataset-' + data['parent'])) {
            categoryList = document.getElementById('dataset-' + data['parent']);
        } else {
            categoryList = document.getElementById('navigationDatasets');
        }
        categoryList.appendChild(li);
    },

    buildNavigationUtils: function (data) {
        let divUtils = document.createElement('div');
        divUtils.classList.add('app-navigation-entry-utils');
        let ulUtils = document.createElement('ul');

        // add indicators when a dataload or schedule is existing
        if (document.getElementById('advanced').value === 'true') {
            if (data.schedules && parseInt(data.schedules) !== 0) {
                let liScheduleButton = document.createElement('li');
                liScheduleButton.classList.add('app-navigation-entry-utils-menu-button');
                let ScheduleButton = document.createElement('button');
                ScheduleButton.classList.add('icon-history', 'toolTip');
                ScheduleButton.setAttribute('title', t('analytics', 'scheduled dataload'));
                liScheduleButton.appendChild(ScheduleButton);
                ulUtils.appendChild(liScheduleButton);
            }
            if (data.dataloads && parseInt(data.dataloads) !== 0) {
                let liScheduleButton = document.createElement('li');
                liScheduleButton.classList.add('app-navigation-entry-utils-menu-button');
                let ScheduleButton = document.createElement('button');
                ScheduleButton.classList.add('icon-category-workflow', 'toolTip');
                ScheduleButton.setAttribute('title', t('analytics', 'Dataload'));
                liScheduleButton.appendChild(ScheduleButton);
                ulUtils.appendChild(liScheduleButton);
            }
        }

        let liMenuButton = document.createElement('li');
        liMenuButton.classList.add('app-navigation-entry-utils-menu-button');
        let button = document.createElement('button');
        button.addEventListener('click', OCA.Analytics.Navigation.handleOptionsClicked);
        button.dataset.id = data.id;
        button.dataset.name = data.name;
        button.dataset.type = data.type;
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

        let edit = navigationMenu.getElementById('navigationMenuEdit');
        edit.addEventListener('click', OCA.Analytics.Navigation.handleBasicClicked);
        edit.children[1].innerText = t('analytics', 'Basic settings');
        edit.dataset.testing = 'basic' + data.name;

        let favorite = navigationMenu.getElementById('navigationMenueFavorite');
        favorite.addEventListener('click', OCA.Analytics.Navigation.handleFavoriteClicked);
        favorite.dataset.testing = 'fav' + data.name;

        let advanced = navigationMenu.getElementById('navigationMenuAdvanced');
        if (document.getElementById('advanced').value === 'true') {
            edit.remove();
            advanced.addEventListener('click', OCA.Analytics.Navigation.handleReportClicked);
            advanced.children[0].classList.add('icon-category-monitoring');
            advanced.children[1].innerText = t('analytics', 'Back to report');
            advanced.dataset.testing = 'back' + data.name;
        } else {
            advanced.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
            advanced.children[0].classList.add('icon-category-customization');
            advanced.children[1].innerText = t('analytics', 'Advanced');
            advanced.dataset.testing = 'adv' + data.name;
        }

        if (parseInt(data.favorite) === 1) {
            favorite.firstElementChild.classList.replace('icon-star', 'icon-starred');
            favorite.children[1].innerHTML = t('analytics', 'Remove from favorites');
        } else {
            favorite.children[1].innerHTML = t('analytics', 'Add to favorites');
        }

        let deleteReport = navigationMenu.getElementById('navigationMenuDelete');
        deleteReport.dataset.id = data.id;
        deleteReport.addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleDeleteButton);

        if (parseInt(data['type']) === OCA.Analytics.TYPE_EMPTY_GROUP) {
            favorite.remove();
            deleteReport.children[1].innerHTML = t('analytics', 'Delete folder');
            advanced.remove();
        } else if (parseInt(data['type']) === OCA.Analytics.TYPE_SHARED) {
            advanced.remove();
            deleteReport.remove();
            edit.remove();
        }

        return navigationMenu;
    },

    handleNewDatasetButton: function () {
        OCA.Analytics.Navigation.createDataset();
    },

    handleOverviewButton: function () {
        if (document.querySelector('#navigationDatasets .active')) {
            document.querySelector('#navigationDatasets .active').classList.remove('active');
        }
        document.getElementById('analytics-content').hidden = true;
        document.getElementById('analytics-intro').removeAttribute('hidden');
        document.getElementById('ulAnalytics').innerHTML = '';
        window.location.href = '#'
        OCA.Analytics.Dashboard.init()
    },

    handleNavigationClicked: function (evt) {
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
        if (document.getElementById('advanced').value === 'true') {
            OCA.Analytics.Sidebar.showSidebar(evt);
            evt.stopPropagation();
        } else {
            document.getElementById('filterVisualisation').innerHTML = '';
            if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined') {
                // reset any user-filters and display the filters stored for the report
                delete OCA.Analytics.currentReportData.options.filteroptions;
            }
            OCA.Analytics.unsavedFilters = false;
            OCA.Analytics.Sidebar.hideSidebar();
            OCA.Analytics.Backend.getData();
        }
    },

    handleOptionsClicked: function (evt) {
        let openMenu;
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            openMenu = document.querySelector('.app-navigation-entry-menu.open').previousElementSibling.firstElementChild.firstElementChild.firstElementChild.dataset.id;
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        if (openMenu !== evt.target.dataset.id) {
            evt.target.parentElement.parentElement.parentElement.nextElementSibling.classList.add('open');
        }
    },

    handleBasicClicked: function (evt) {
        document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        evt.stopPropagation();
        OCA.Analytics.Sidebar.showSidebar(evt);
    },

    handleAdvancedClicked: function (evt) {
        document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        const datasetId = evt.target.closest('div').dataset.id;
        window.location = OC.generateUrl('apps/analytics/a/') + '#/r/' + datasetId;
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

            let spanFav = document.createElement('span');
            spanFav.id = 'fav-' + datasetId;
            spanFav.classList.add('icon', 'icon-starred');
            spanFav.style.opacity = '0.5';
            evt.target.closest('div').parentElement.firstElementChild.insertAdjacentElement("afterend", spanFav);

        } else {
            icon.classList.replace('icon-starred', 'icon-star');
            evt.target.parentNode.children[1].innerHTML = t('analytics', 'Add to favorites');
            document.getElementById('fav-' + datasetId).remove();
        }
        OCA.Analytics.Navigation.favoriteUpdate(datasetId, isFavorite);
        document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
    },

    handleReportClicked: function (evt) {
        const datasetId = evt.target.closest('div').dataset.id;
        window.location = OC.generateUrl('apps/analytics/') + '#/r/' + datasetId;
        evt.stopPropagation();
    },

    handleGroupClicked: function (evt) {
        if (evt.target.parentNode.classList.contains('open')) {
            evt.target.parentNode.classList.remove('open');
        } else {
            evt.target.parentNode.classList.add('open');
        }
        evt.stopPropagation();
    },

    handleImportButton: function () {
        const mimeparts = ['text/csv', 'text/plain'];
        OC.dialogs.filepicker(t('analytics', 'Select file'), OCA.Analytics.Navigation.importDataset.bind(this), false, mimeparts, true, 1);
    },

    importDataset: function (path, raw) {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset/import/'),
            data: {
                'path': path,
                'raw': raw
            },
            success: function () {
                OCA.Analytics.Navigation.init();
            }
        });

    },

    createDataset: function (file = '') {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset'),
            data: {
                'file': file,
            },
            success: function (data) {
                OCA.Analytics.Navigation.init(data);
            }
        });
    },

    getDatasets: function (datasetId) {
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset'),
            success: function (data) {
                OCA.Analytics.Navigation.buildNavigation(data);
                OCA.Analytics.datasets = data;
                if (datasetId) {
                    OCA.Analytics.Sidebar.hideSidebar();
                    let navigationItem = document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]');
                    if (navigationItem.parentElement.parentElement.parentElement.classList.contains('collapsible')) {
                        navigationItem.parentElement.parentElement.parentElement.classList.add('open');
                    }
                    navigationItem.click();
                    //todo open the exit dialog for a new data set
                    //document.querySelector('#navigationMenu[data-id="292"] #navigationMenuEdit').click()
                }
            }
        });
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
};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.WhatsNew.whatsnew();

    document.getElementById('importDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleImportButton);
    document.getElementById('wizzartStart').addEventListener('click', OCA.Analytics.Wizzard.show);
    if (parseInt(document.getElementById('analyticsWizzard').value) === 0) {
        OCA.Analytics.Wizzard.show();
    }
});