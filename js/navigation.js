/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
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
        let datatype;
        if (OCA.Analytics.isAdvanced) {
            datatype = 'dataset';
        } else if (OCA.Analytics.isPanorama) {
            datatype = 'panorama';
        } else {
            datatype = 'report';
        }

        let requestUrl = OC.generateUrl('apps/analytics/' + datatype);
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                if (OCA.Analytics.Panorama) {
                    OCA.Analytics.Panorama.stories = data;
                }
                OCA.Analytics.reports = data;
                OCA.Analytics.Navigation.buildNavigation(data);
                if (navigationId && data.indexOf(data.find(o => parseInt(o.id) === parseInt(navigationId))) !== -1) {
                    OCA.Analytics.Sidebar?.close?.();
                    let navigationItem = document.querySelector('#navigationDatasets [data-id="' + navigationId + '"]');
                    if (navigationItem.parentElement.parentElement.parentElement.classList.contains('collapsible')) {
                        navigationItem.parentElement.parentElement.parentElement.classList.add('open');
                    }
                    navigationItem.click();
                }
            });
    },

    buildNavigation: function (data) {
        OCA.Analytics.Sidebar?.close?.();
        document.getElementById('navigationDatasets').innerHTML = '';

        document.getElementById('navigationDatasets').appendChild(OCA.Analytics.Navigation.buildOverviewButton());
        if (data === undefined || data.length === 0) {
            document.getElementById('navigationDatasets').appendChild(OCA.Analytics.Navigation.buildIntroRow());
        } else {
            for (let navigation of data) {
                OCA.Analytics.Navigation.buildNavigationRow(navigation);
            }
        }

        document.getElementById('navigationDatasets').appendChild(OCA.Analytics.Navigation.buildNewGroupPlaceholder());
        document.getElementById('navigationDatasets').appendChild(OCA.Analytics.Navigation.buildNewButton()); // first pinned
        if (!OCA.Analytics.isAdvanced && !OCA.Analytics.isPanorama) {
            document.getElementById('navigationDatasets').appendChild(OCA.Analytics.Navigation.buildDatasetMaintenanceButton()); // second pinned
        }
    },

    buildNewButton: function () {
        let li = document.createElement('li');
        li.classList.add('pinned', 'first-pinned');
        let a = document.createElement('a');
        a.classList.add('icon-add', 'svg');
        a.id = 'newReportButton';
        a.addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        if (OCA.Analytics.isAdvanced) {
            a.innerText = t('analytics', 'New dataset');
        } else if (OCA.Analytics.isPanorama) {
            // TRANSLATORS "Panorama" will be a product name. Do not translate, just capitalize if required
            a.innerText = t('analytics', 'New panorama');
        } else {
            a.innerText = t('analytics', 'New report');
        }
        li.appendChild(a);
        return li;
    },

    buildDatasetMaintenanceButton: function () {
        let li = document.createElement('li');
        li.classList.add('pinned', 'second-pinned');
        let a = document.createElement('a');
        a.classList.add('icon-category-customization', 'svg');
        a.innerText = t('analytics', 'Dataset maintenance');
        a.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
        li.appendChild(a);
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
        if (OCA.Analytics.isAdvanced) {
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
        let li = document.createElement('li');
        let a = document.createElement('a');
        a.id = 'overviewButton'
        if (OCA.Analytics.isAdvanced) {
            a.classList.add('icon-view-previous', 'svg');
            a.innerText = t('analytics', 'Back to reports');
            a.addEventListener('click', OCA.Analytics.Navigation.handleBackToReportClicked);
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
        let typeINT = parseInt(data['type']);

        let a = document.createElement('a');
        a.setAttribute('href', '#/r/' + data['id']);
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

        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE || typeINT === OCA.Analytics.TYPE_EXCEL) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
            a.addEventListener("drop", OCA.Analytics.Navigation.Drag.drop_handler);
            a.addEventListener("dragover", OCA.Analytics.Navigation.Drag.dragover_handler);
            a.addEventListener("dragleave", OCA.Analytics.Navigation.Drag.dragleave_handler);
        } else {
            typeIcon = 'icon-external';
        }

        if (data['isShare'] === 1) {
            if (OCA.Analytics.isAdvanced) {
                // don´t show shared reports in advanced config mode at all as no config is possible
                return;
            }
            typeIcon = 'icon-shared';
            //data['type'] = OCA.Analytics.TYPE_SHARED;
        }

        a.classList.add(typeIcon);
        a.classList.add('svg');

        a.innerText = data['name'];
        a.dataset.id = data['id'];
        a.dataset.type = data['type'];
        a.dataset.name = data['name'];
        a.dataset.parent = data['parent'];
        li.appendChild(a);

        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data['id'];

        if (parseInt(data['favorite']) === 1) {
            let divFav = OCA.Analytics.Navigation.buildFavoriteIcon(data['id'], data['name'])
            a.appendChild(divFav);
        }

        if (OCA.Analytics.isAdvanced) {
            let divUtils = OCA.Analytics.Navigation.buildNavigationUtilsDataset(data);
            li.appendChild(divUtils);
        } else {
            let divUtils = OCA.Analytics.Navigation.buildNavigationUtils(data);
            let divMenu = OCA.Analytics.Navigation.buildNavigationMenu(data);
            if (divMenu.firstElementChild.firstElementChild.childElementCount !== 0) {
                // do not add an empty menu. can occur for e.g. shared group folders
                li.appendChild(divUtils);
                li.appendChild(divMenu);
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

        let edit = navigationMenu.getElementById('navigationMenuEdit');
        edit.addEventListener('click', OCA.Analytics.Navigation.handleBasicClicked);
        edit.children[1].innerText = t('analytics', 'Basic settings');
        edit.dataset.testing = 'basic' + data.name;

        let newGroup = navigationMenu.getElementById('navigationMenuNewGroup');
        newGroup.addEventListener('click', OCA.Analytics.Navigation.handleNewGroupClicked);
        newGroup.children[1].innerText = t('analytics', 'Add to new group');
        newGroup.dataset.testing = 'newGroup' + data.name;
        newGroup.dataset.id = data.id;

        let dataset = navigationMenu.getElementById('navigationMenuAdvanced');
        dataset.addEventListener('click', OCA.Analytics.Navigation.handleAdvancedClicked);
        dataset.children[1].innerText = t('analytics', 'Dataset maintenance');
        dataset.dataset.testing = 'advanced' + data.name;
        dataset.dataset.dataset = data.dataset;

        let favorite = navigationMenu.getElementById('navigationMenueFavorite');
        favorite.addEventListener('click', OCA.Analytics.Navigation.handleFavoriteClicked);
        favorite.dataset.testing = 'fav' + data.name;

        if (parseInt(data.favorite) === 1) {
            favorite.firstElementChild.classList.replace('icon-star', 'icon-starred');
            favorite.children[1].innerHTML = t('analytics', 'Remove from favorites');
        } else {
            favorite.children[1].innerHTML = t('analytics', 'Add to favorites');
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

        if (OCA.Analytics.isPanorama) {
            edit.parentElement.remove();
            newGroup.parentElement.remove(); // re-add later
        }

        return navigationMenu;
    },

    handleNewButton: function () {
        // ToDo: change app.js to register handler

        let handler = OCA.Analytics.Navigation.handlers['create'];
        if (handler) {
            handler();
        } else if (OCA.Analytics.isAdvanced) {
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

    handleOverviewButton: function () {
        OCA.Analytics.Sidebar?.close?.();
        if (document.querySelector('#navigationDatasets .active')) {
            document.querySelector('#navigationDatasets .active').classList.remove('active');
        }
        OCA.Analytics.Visualization.hideElement('analytics-content');
        OCA.Analytics.Visualization.showElement('analytics-intro');
        document.getElementById('ulAnalytics').innerHTML = '';
        window.location.href = '#';
        OCA.Analytics.Dashboard?.init?.();
        OCA.Analytics.Panorama?.Dashboard?.init?.();
    },

    handleNavigationClicked: function (evt) {
        // ToDo: change app.js to register handler

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
        if (handler) {
            handler(evt);
        } else if (OCA.Analytics.isAdvanced) {
            OCA.Analytics.Advanced.showSidebar(evt);
            evt.stopPropagation();
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

    handleBasicClicked: function (evt) {
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
        if (datasetId !== undefined) datasetUrl = '#/r/' + datasetId
        window.location = OC.generateUrl('apps/analytics/a/') + datasetUrl;
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
        let reportId = evt.target.closest('div').dataset.id;
        let reportUrl = '';
        if (reportId !== undefined) reportUrl = '#/r/' + reportId
        window.location = OC.generateUrl('apps/analytics/') + reportUrl;
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
    }
}

document.addEventListener('DOMContentLoaded', function () {
    if (!OCA.Analytics.isAdvanced && !OCA.Analytics.isPanorama) {
        OCA.Analytics.WhatsNew.whatsnew();
        if (OCA.Analytics.Core.getInitialState('wizard') !== '1') {
            OCA.Analytics.Wizard.showFirstStart();
        }
    }
    document.getElementById('wizardStart').addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
    document.getElementById('importDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleImportButton);
    document.getElementById('appSettingsButton').addEventListener('click', OCA.Analytics.Navigation.handleSettingsButton);
});