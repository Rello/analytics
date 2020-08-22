/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */
/** global: OCA */
/** global: OCP */
/** global: OC */
'use strict';
/**
 * @namespace OCA.Analytics.Navigation
 */
OCA.Analytics.Navigation = {

    init: function (datasetId) {
        document.getElementById('navigationDatasets').innerHTML = '';
        OCA.Analytics.Backend.getDatasets(datasetId);
    },

    handleNewDatasetButton: function () {
        OCA.Analytics.Backend.createDataset();
    },

    createDemoReport: function () {
        OCA.Analytics.Backend.createDataset('DEMO');
    },

    buildNavigation: function (data) {
        for (let navigation of data) {
            OCA.Analytics.Navigation.buildNavigationRow(navigation);
        }
    },

    buildNavigationRow: function (data) {
        let li = document.createElement('li');
        let typeIcon;

        let a = document.createElement('a');
        a.setAttribute('href', '#/r/' + data.id);
        let typeINT = parseInt(data.type);
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_GIT || typeINT === OCA.Analytics.TYPE_EXTERNAL_FILE) {
            typeIcon = 'icon-external';
        } else if (typeINT === OCA.Analytics.TYPE_SHARED) {
            if (document.getElementById('advanced').value === 'true') {
                // don´t show shared reports in advanced config mode as no config is possible
                return;
            }
            typeIcon = 'icon-shared';
        } else if (typeINT === OCA.Analytics.TYPE_EMPTY_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
        } else {
            typeIcon = '';
        }

        if (typeIcon) {
            a.classList.add(typeIcon);
        }
        a.innerText = data.name;
        a.dataset.id = data.id;
        a.dataset.type = data.type;
        a.dataset.name = data.name;


        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data.id;

        li.appendChild(a);
        if (typeINT !== OCA.Analytics.TYPE_SHARED) {
            let divUtils = OCA.Analytics.Navigation.buildNavigationUtils(data);
            let divMenu = OCA.Analytics.Navigation.buildNavigationMenu(data);
            li.appendChild(divUtils);
            li.appendChild(divMenu);
        }

        if (typeINT === OCA.Analytics.TYPE_EMPTY_GROUP) {
            li.appendChild(ulSublist);
            a.addEventListener('click', OCA.Analytics.Navigation.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Analytics.Navigation.handleNavigationClicked);
        }

        let categoryList;
        if (parseInt(data.parent) !== 0 && document.getElementById('dataset-' + data.parent)) {
            categoryList = document.getElementById('dataset-' + data.parent);
        } else {
            categoryList = document.getElementById('navigationDatasets');
        }
        categoryList.appendChild(li);
    },

    buildNavigationUtils: function (data) {
        let divUtils = document.createElement('div');
        divUtils.classList.add('app-navigation-entry-utils');
        let ulUtils = document.createElement('ul');

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

        navigationMenu.getElementById('navigationMenueFavorite').addEventListener('click', OCA.Analytics.Navigation.handleFavoriteClicked);

        if (document.getElementById('advanced').value === 'true') {
            advanced.addEventListener('click', OCA.Analytics.Navigation.handleReportClicked);
            advanced.children[0].classList.add('icon-category-monitoring');
            advanced.children[1].innerText = t('analytics', 'Back to report');
        } else {
            let advanced = navigationMenu.getElementById('navigationMenuAdvanced');
            advanced.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
            advanced.children[0].classList.add('icon-category-customization');
            advanced.children[1].innerText = t('analytics', 'Advanced');
        }
        return navigationMenu;
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
            evt.target.classList.add('active');
        }
        if (document.getElementById('advanced').value === 'true') {
            OCA.Analytics.Sidebar.showSidebar(evt);
            evt.stopPropagation();
        } else {
            document.getElementById('filterVisualisation').innerHTML = '';
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
            isFavorite = 'true';
        } else {
            icon.classList.replace('icon-starred', 'icon-star');
        }

        OCA.Analytics.Backend.favoriteUpdate(datasetId, isFavorite);
    },

    toggleFavorite: function (evt) {
        if (OCA.Audioplayer.Core.CategorySelectors[1][0] === 'S') {
            return;
        }
        var target = evt.target;
        var trackId = target.getAttribute('data-trackid');
        var isFavorite = OCA.Audioplayer.UI.toggleFavorite(target, trackId);
        OCA.Audioplayer.Backend.favoriteUpdate(trackId, isFavorite);
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
};