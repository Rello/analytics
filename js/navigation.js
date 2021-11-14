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
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        OCA.Analytics.Navigation.getDatasets(datasetId);
        OCA.Analytics.Backend.getDatasetDefinitions();
    },

    buildNavigation: function (data) {
        OCA.Analytics.Sidebar.hideSidebar();
        document.getElementById('navigationDatasets').innerHTML = '';

        let li = OCA.Analytics.Navigation.buildOverviewButton();
        document.getElementById('navigationDatasets').appendChild(li);

        let li2 = document.createElement('li');
        li2.classList.add('pinned', 'first-pinned');
        let a2 = document.createElement('a');
        a2.classList.add('icon-add', 'svg');
        a2.id = 'newDatasetButton';
        a2.addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        if (OCA.Analytics.isAdvanced) {
            a2.innerText = t('analytics', 'New dataset');
        } else {
            a2.innerText = t('analytics', 'New report');
        }
        li2.appendChild(a2);
        document.getElementById('navigationDatasets').appendChild(li2);

        if (!OCA.Analytics.isAdvanced) {
            let li3 = document.createElement('li');
            li3.classList.add('pinned', 'second-pinned');
            let a3 = document.createElement('a');
            a3.classList.add('icon-category-customization', 'svg');
            a3.innerText = t('analytics', 'Dataset maintenance');
            a3.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
            li3.appendChild(a3);
            document.getElementById('navigationDatasets').appendChild(li3);
        }

        for (let navigation of data) {
            OCA.Analytics.Navigation.buildNavigationRow(navigation);
        }
    },

    buildOverviewButton: function () {
        let li = document.createElement('li');
        let a = document.createElement('a');
        a.id = 'overviewButton'
        if (OCA.Analytics.isAdvanced) {
            a.classList.add('icon-view-previous', 'svg');
            a.innerText = t('analytics', 'Back to reports');
            a.addEventListener('click', OCA.Analytics.Navigation.handleReportClicked);
        } else {
            a.classList.add('icon-toggle-pictures', 'svg');
            a.innerText = t('analytics', 'Overview');
            a.addEventListener('click', OCA.Analytics.Navigation.handleOverviewButton);
        }
        li.appendChild(a);
        return li;
    },

    buildNavigationRow: function (data) {
        let li = document.createElement('li');
        let typeIcon;

        let a = document.createElement('a');
        a.setAttribute('href', '#/r/' + data['id']);
        a.style.position = 'relative';
        let typeINT = parseInt(data['type']);
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE || typeINT === OCA.Analytics.TYPE_EXCEL) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_SHARED) {
            if (OCA.Analytics.isAdvanced) {
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
        a.classList.add('svg');

        a.innerText = data['name'];
        a.dataset.id = data['id'];
        a.dataset.type = data['type'];
        a.dataset.name = data['name'];
        li.appendChild(a);

        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data['id'];

        if (parseInt(data['favorite']) === 1) {
            let divFav = OCA.Analytics.Navigation.buildFavoriteIcon(data['id'], data['name'])
            a.appendChild(divFav);
        }

        if (!OCA.Analytics.isAdvanced) {
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

        // add navigation row to navigation list or to an existing parent node
        let categoryList;
        if (parseInt(data['parent']) !== 0 && document.getElementById('dataset-' + data['parent'])) {
            categoryList = document.getElementById('dataset-' + data['parent']);
            categoryList.appendChild(li);
        } else {
            categoryList = document.getElementById('navigationDatasets');
            if (parseInt(data['favorite']) === 1) {
                categoryList.insertBefore(li, categoryList.children[2]);
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

    buildNavigationUtils: function (data) {
        let divUtils = document.createElement('div');
        divUtils.classList.add('app-navigation-entry-utils');
        let ulUtils = document.createElement('ul');

        // add indicators when a dataload or schedule is existing
        if (OCA.Analytics.isAdvanced) {
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

/*
        let advanced = navigationMenu.getElementById('navigationMenuAdvanced');
        if (OCA.Analytics.isAdvanced) {
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
*/

        if (parseInt(data.favorite) === 1) {
            favorite.firstElementChild.classList.replace('icon-star', 'icon-starred');
            favorite.children[1].innerHTML = t('analytics', 'Remove from favorites');
        } else {
            favorite.children[1].innerHTML = t('analytics', 'Add to favorites');
        }

        let deleteReport = navigationMenu.getElementById('navigationMenuDelete');
        deleteReport.dataset.id = data.id;
        deleteReport.addEventListener('click', OCA.Analytics.Sidebar.Report.handleDeleteButton);

        let unshareReport = navigationMenu.getElementById('navigationMenuUnshare');

        if (parseInt(data['type']) === OCA.Analytics.TYPE_EMPTY_GROUP) {
            unshareReport.remove();
            favorite.remove();
            deleteReport.children[1].innerHTML = t('analytics', 'Delete folder');
            //advanced.remove();
        } else if (parseInt(data['type']) === OCA.Analytics.TYPE_SHARED) {
            advanced.remove();
            deleteReport.remove();
            edit.remove();
            unshareReport.dataset.shareId = data.shareId;
            unshareReport.addEventListener('click', OCA.Analytics.Navigation.handleUnshareButton);
        } else {
            unshareReport.remove();
        }

        return navigationMenu;
    },

    handleNewButton: function () {
        if (OCA.Analytics.isAdvanced) {
            OCA.Analytics.Wizard.sildeArray = [
                ['',''],
                ['wizardDatasetGeneral', OCA.Analytics.Advanced.Dataset.wizard],
            ];
            OCA.Analytics.Wizard.show();
        } else {
            OCA.Analytics.Wizard.sildeArray = [
                ['',''],
                ['wizardNewGeneral', OCA.Analytics.Sidebar.Report.wizard],
                ['wizardNewType',''],
                ['wizardNewVisual','']
            ];
            OCA.Analytics.Wizard.show();
        }
    },

    handleOverviewButton: function () {
        OCA.Analytics.Sidebar.hideSidebar();
        if (document.querySelector('#navigationDatasets .active')) {
            document.querySelector('#navigationDatasets .active').classList.remove('active');
        }
        OCA.Analytics.UI.hideElement('analytics-content');
        OCA.Analytics.UI.showElement('analytics-intro');
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
        if (OCA.Analytics.isAdvanced) {
            OCA.Analytics.Advanced.showSidebar(evt);
            evt.stopPropagation();
        } else {
            document.getElementById('filterVisualisation').innerHTML = '';
            if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined') {
                // reset any user-filters and display the filters stored for the report
                delete OCA.Analytics.currentReportData.options;
            }
            OCA.Analytics.unsavedFilters = false;
            OCA.Analytics.Sidebar.hideSidebar();
            OCA.Analytics.Backend.getData();
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Analytics.UI.hideReportMenu();
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
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }
        evt.stopPropagation();
        OCA.Analytics.Sidebar.showSidebar(evt);
    },

    handleAdvancedClicked: function (evt) {
        window.location = OC.generateUrl('apps/analytics/a/#');
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
        const mimeparts = ['text/plain'];
        OC.dialogs.filepicker(t('analytics', 'Select file'), OCA.Analytics.Sidebar.Report.import.bind(this), false, mimeparts, true, 1);
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

    getDatasets: function (datasetId) {
        let datatype;
        if (OCA.Analytics.isAdvanced) {
            datatype = 'dataset';
        } else {
            datatype = 'report';
        }
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/' + datatype),
            success: function (data) {
                OCA.Analytics.Navigation.buildNavigation(data);
                OCA.Analytics.reports = data;
                if (datasetId && data.indexOf(data.find(o => o.id === datasetId)) !== -1) {
                    OCA.Analytics.Sidebar.hideSidebar();
                    let navigationItem = document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]');
                    if (navigationItem.parentElement.parentElement.parentElement.classList.contains('collapsible')) {
                        navigationItem.parentElement.parentElement.parentElement.classList.add('open');
                    }
                    navigationItem.click();
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
    if (!OCA.Analytics.isAdvanced) {
        OCA.Analytics.WhatsNew.whatsnew();
        document.getElementById('wizardStart').addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
        if (OCA.Analytics.Core.getInitialState('wizard') !== '1') {
            OCA.Analytics.Wizard.showFirstStart();
        }
    }
    document.getElementById('importDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleImportButton);
});