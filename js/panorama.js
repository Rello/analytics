/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */
/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.initialDocumentTitle = document.title;
    OCA.Analytics.Visualization.hideElement('analytics-warning');
    OCA.Analytics.Visualization.showElement('analytics-intro');

    // register handlers for the navigation bar
    OCA.Analytics.Navigation.registerHandler('create', function () {
        OCA.Analytics.Panorama.newPanorama();
    });

    OCA.Analytics.Navigation.registerHandler('navigationClicked', function (event) {
        OCA.Analytics.Panorama.handleNavigationClicked(event);
    });

    OCA.Analytics.Navigation.registerHandler('delete', function (event) {
        OCA.Analytics.Panorama.handleDeletePanoramaButton(event);
    });

    OCA.Analytics.Navigation.registerHandler('favoriteUpdate', function (id, isFavorite) {
        OCA.Analytics.Dashboard.favoriteUpdate(id, isFavorite);
    });

    const urlHash = decodeURI(location.hash);
    OCA.Analytics.Navigation.init(urlHash.length > 1 && urlHash[2] === 'r' ? parseInt(urlHash.substring(4)) : undefined);
    OCA.Analytics.Panorama.init();
})

OCA.Analytics = Object.assign({}, OCA.Analytics, {
    TYPE_GROUP: 0,
    TYPE_INTERNAL_FILE: 1,
    TYPE_INTERNAL_DB: 2,
    TYPE_GIT: 3,
    TYPE_EXTERNAL_FILE: 4,
    TYPE_EXTERNAL_REGEX: 5,
    TYPE_EXCEL: 7,
    TYPE_SHARED: 99,
    tableObject: [],
    isAdvanced: false,
    currentReportData: {},
    isPanorama: true,
    // flexible mapping depending on type required by the used chart library
    chartTypeMapping: {
        'datetime': 'line',
        'column': 'bar',
        'columnSt': 'bar', // map stacked type also to base type; needed in filter
        'columnSt100': 'bar', // map stacked type also to base type; needed in filter
        'area': 'line',
        'line': 'line',
        'doughnut': 'doughnut'
    },
    headers: function () {
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');
        return headers;
    },
    reports: [],
});

/**
 * @namespace OCA.Analytics.Panorama
 */
OCA.Analytics.Panorama = {
    TYPE_REPORT: 0,
    TYPE_TEXT: 1,
    TYPE_PICTURE: 2,
    stories: [],
    emptyPageTemplate: {page: 0, name: 'New', reports: [], layout: ''},
    currentPanorama: {},
    currentPage: 0,
    editMode: false,
    layouts: [
        {
            id: 1, name: '2-1', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '</div>'
        },
        {
            id: 2, name: '1-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
        {
            id: 3, name: '2-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
        {
            id: 4, name: '4-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
        {
            id: 3, name: '2-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
        {
            id: 4, name: '4-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
    ],

    init: function () {
        document.getElementById('prevBtn').addEventListener('click', () => OCA.Analytics.Panorama.navigatePage('prev'));
        document.getElementById('nextBtn').addEventListener('click', () => OCA.Analytics.Panorama.navigatePage('next'));
        document.getElementById('optionBtn').addEventListener('click', OCA.Analytics.Panorama.toggleOptionMenu);
        document.getElementById('optionMenuEdit').addEventListener('click', OCA.Analytics.Panorama.handleEditButton);
        document.getElementById('optionMenuLayout').addEventListener('click', OCA.Analytics.Panorama.buildLayoutSelector);
        document.getElementById('optionMenuDeletePage').addEventListener('click', OCA.Analytics.Panorama.handleDeletePageButton);
        document.getElementById('optionMenuPdf').addEventListener('click', OCA.Analytics.Panorama.handlePdfButton);

        document.getElementById("infoBoxReport").addEventListener('click', OCA.Analytics.Panorama.newPanorama);

        document.getElementById('layoutModalClose').addEventListener('click', function () {
            document.getElementById('layoutModal').style.display = 'none';
        });

        OCA.Analytics.Backend.getReports();
    },

    handleNavigationClicked: function (evt) {
        const foundItem = OCA.Analytics.Panorama.stories.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));
        OCA.Analytics.Panorama.currentPanorama = JSON.parse(JSON.stringify(foundItem));
        //OCA.Analytics.Panorama.currentPanorama = OCA.Analytics.Panorama.stories.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));
        if (typeof OCA.Analytics.Panorama.currentPanorama.pages === 'string') {
            OCA.Analytics.Panorama.currentPanorama.pages = JSON.parse(OCA.Analytics.Panorama.currentPanorama.pages);
        }
        OCA.Analytics.Panorama.editMode = false;
        OCA.Analytics.Panorama.removeEditableTextBoxes();
        OCA.Analytics.Panorama.removeLayoutSelctor();
        OCA.Analytics.Panorama.hideOptionMenu();
        OCA.Analytics.Panorama.getPanorama();
    },

    newPanorama: function () {
        OCA.Analytics.Visualization.hideElement('analytics-intro');
        OCA.Analytics.Visualization.showElement('analytics-content');
        OCA.Analytics.Panorama.currentPanorama = [];
        OCA.Analytics.Backend.create();
    },

    newPage: function () {
        // store texts in case they were entered already
        OCA.Analytics.Panorama.currentPanorama.name = document.getElementById('panoramaHeader').innerText;
        // get the subheaders and store them to the panorama
        document.querySelectorAll('.subHeader').forEach((item) => {
            let page = item.id.split('-')[1];
            OCA.Analytics.Panorama.currentPanorama.pages[page].name = item.innerText;
        });

        OCA.Analytics.Panorama.currentPanorama.pages.push({page: 0, name: 'New', reports: [], layout: ''});
        OCA.Analytics.Panorama.getPanorama('next');
        OCA.Analytics.Panorama.updateNavButtons();
    },
    
    // get the panorama and loop all widgets
    getPanorama: function (targetPage) {
        // Reset existing pages
        document.getElementById('panoramaPages').innerHTML = '';

        // add an empty page if the panorama is empty/new
        if (OCA.Analytics.Panorama.currentPanorama.pages.length === 0) {
            OCA.Analytics.Panorama.currentPanorama.pages.push(OCA.Analytics.Panorama.emptyPageTemplate);
        }

        // Add the layout page by page
        OCA.Analytics.Panorama.currentPanorama.pages.forEach((page, pageIndex) => {
            let flexContainer = null;
            if (page.layout !== '') {
                // Parse the string to DOM
                let parser = new DOMParser();
                let layout = parser.parseFromString(page.layout, 'text/html');
                flexContainer = layout.querySelector('div');
            } else {
                flexContainer = document.createElement('div');
                flexContainer.classList.add('flex-container');
                let hintBox = document.createElement('div');
                hintBox.classList.add('hintBox');
                hintBox.innerText = t('analytics', 'choose a layout');
                hintBox.addEventListener('click', OCA.Analytics.Panorama.buildLayoutSelector)
                flexContainer.appendChild(hintBox);
            }

            // assign the panorama id to the container
            flexContainer.id = pageIndex;

            // add the unique ids to all flex items
            flexContainer.querySelectorAll('.flex-item').forEach((item, idx) => {
                item.id = `${pageIndex}-${idx}`;
            });

            // add the unique ids to all subHeaders and set the text
            flexContainer.querySelectorAll('.subHeader').forEach((item, idx) => {
                item.id = 'subHeader-' + pageIndex;
                item.innerText = page.name;
            });

            document.getElementById('panoramaPages').appendChild(flexContainer);
        });


        OCA.Analytics.Visualization.hideElement('analytics-intro');
        OCA.Analytics.Visualization.showElement('analytics-content');

        // update the visibility of the next/prev buttons
        OCA.Analytics.Panorama.updateNavButtons();
        // updated the scrolling for multi pages
        OCA.Analytics.Panorama.updatePageWidth();

        // set the main header
        document.getElementById('panoramaHeader').innerText = OCA.Analytics.Panorama.currentPanorama.name;

        //go to first page or to a dedicated one, if required
        if (targetPage) {
            OCA.Analytics.Panorama.navigatePage(targetPage);
        } else {
            OCA.Analytics.Panorama.navigatePage('start');
        }

        // fill all items with widgets
        let items = document.getElementsByClassName('flex-item');
        items.forEach((item) => {
            OCA.Analytics.Panorama.buildWidget(item.id);
        });

        // if still in edit mode, re-add the overlays over every item
        if (OCA.Analytics.Panorama.editMode) OCA.Analytics.Panorama.addEditOverlays();
    },

    buildWidget: function (itemId) {
        // cleanup old charts
        let canvasElement = document.getElementById('myWidget' + itemId);
        // Destroy the chart instance associated with each canvas
        if (canvasElement && canvasElement.chart) {
            canvasElement.chart.destroy();
        }

        //let reportId = widget.dataset.chart;
        let pageId = itemId.split('-')[0];
        let itemIndex = itemId.split('-')[1];
        //let reportId = OCA.Analytics.Panorama.currentPanorama['reports'].split(',')[reportIndex];
        let page = OCA.Analytics.Panorama.currentPanorama.pages[pageId];
        let itemContent = page.reports[itemIndex];

        if (itemContent !== null && itemContent !== undefined) {
            let contentValue = itemContent['value'];
            let contentType = parseInt(itemContent['type']);
            //let widget = document.querySelectorAll('div[data-chart]')[positionIndex];
            let widget = document.getElementById(itemId);
            widget.innerHTML = '';

            if (contentType === OCA.Analytics.Panorama.TYPE_REPORT) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypeReport(parseInt(contentValue), itemId);
                widget.setAttribute('data-chart', contentValue);
                widget.insertAdjacentHTML('beforeend', itemWidget);
                OCA.Analytics.Backend.getReportData(parseInt(contentValue), itemId);
            } else if (contentType === OCA.Analytics.Panorama.TYPE_TEXT) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypeText(contentValue);
                widget.appendChild(itemWidget);
            } else if (contentType === OCA.Analytics.Panorama.TYPE_PICTURE) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypePicture(contentValue);
                widget.appendChild(itemWidget);
            }
        }
    },

    buildWidgetTypeReport: function (reportId, itemId) {
        //let href = OC.generateUrl('apps/analytics/#/r/' + reportId);
        //return `<canvas id="myWidget${reportId}" class="chartContainer"></canvas>`;
        return `<div style="height: 30px;">
                    <div id="analyticsWidgetReport${itemId}" style="padding-left: 10px;"></div>
                    <!--<div id="analyticsWidgetSmall${itemId}"></div>-->
                </div>
                <div style="position: relative; height: calc(100% - 60px);">
                        <div id="myWidget${itemId}"></div>
                </div>`;
    },

    buildWidgetTypeText: function (contentValue) {
        let textContainer = document.createElement('div');
        textContainer.classList.add('textContainer');
        window.OCA.Text.createEditor({
            el: textContainer,
            content: contentValue,
            readOnly: true,
        })
        return textContainer;
    },

    buildWidgetTypePicture: function (contentValue) {
        let textContainer = document.createElement('div');
        textContainer.classList.add('textContainer');
        let image = document.createElement('img');
        image.style.width = '80%';
        image.style.maxWidth = '80%';
        image.style.maxHeight = '80%';
        image.src = '/core/preview?fileId=' + contentValue + '&x=300&y=300&a=true';
        textContainer.appendChild(image);
        return textContainer;
    },
    
    setWidgetTypeReportContent: function (jsondata, itemId) {
        let report = jsondata['options']['name'];
        let type = jsondata['options']['visualization'];

        document.getElementById('analyticsWidgetReport' + itemId).innerText = report;

        if (type !== 'table') {
            let canvasElement = document.getElementById(`myWidget${itemId}`);
            let divElement = document.createElement('canvas');
            divElement.id = `myWidget${itemId}`;
            canvasElement.parentNode.replaceChild(divElement, canvasElement);
            let ctx = document.getElementById('myWidget' + itemId).getContext('2d');
            OCA.Analytics.Visualization.buildChart(ctx, jsondata, OCA.Analytics.UI.getDefaultChartOptions());
        } else {
            let canvasElement = document.getElementById(`myWidget${itemId}`);
            let divElement = document.createElement('table');
            divElement.id = `myWidget${itemId}`;
            canvasElement.parentNode.replaceChild(divElement, canvasElement);
            OCA.Analytics.Visualization.buildDataTable(document.getElementById('myWidget' + itemId), jsondata, false, itemId);
        }
    },
    
    toggleOptionMenu: function () {
        OCA.Analytics.Panorama.toggleEditSaveButtonDisplay();
        document.getElementById('optionMenu').classList.toggle('open');
    },

    hideOptionMenu: function () {
        document.getElementById('optionMenu').classList.remove('open');
    },

    handleEditButton: function () {
        if (OCA.Analytics.Panorama.editMode) {
            // edit mode was active and is being exited
            // store new values
            OCA.Analytics.Panorama.currentPanorama.name = document.getElementById('panoramaHeader').innerText;

            OCA.Analytics.Panorama.removeEditOverlays();
            OCA.Analytics.Panorama.removeEditableTextBoxes();
            OCA.Analytics.Panorama.removeLayoutSelctor();

            // get the subheaders and store them to the panorama
            document.querySelectorAll('.subHeader').forEach((item) => {
                let page = item.id.split('-')[1];
                OCA.Analytics.Panorama.currentPanorama.pages[page].name = item.innerText;
            });
            OCA.Analytics.Backend.update();
        } else {
            //OCA.Analytics.Panorama.buildLayoutSelector();
            OCA.Analytics.Panorama.addEditOverlays();
            OCA.Analytics.Panorama.addEditableTextBoxes();
        }

        OCA.Analytics.Panorama.editMode = !OCA.Analytics.Panorama.editMode;
        OCA.Analytics.Panorama.toggleEditSaveButtonDisplay();
        OCA.Analytics.Panorama.updateNavButtons();
        OCA.Analytics.Panorama.hideOptionMenu();
    },

    toggleEditSaveButtonDisplay: function () {
        let icon = document.getElementById('optionMenuEditIcon');
        let text = document.getElementById('optionMenuEditText');
        if (OCA.Analytics.Panorama.editMode) {
            icon.classList.remove('icon-rename');
            icon.classList.add('icon-analytics-save-warning');
            text.innerText = t('analytics', 'Save');
        } else {
            icon.classList.add('icon-rename');
            icon.classList.remove('icon-analytics-save-warning');
            text.innerText = t('analytics', 'Edit content');
        }
    },

    // create grey overlays to indicate the editable areas
    addEditOverlays: function () {
        const flexItems = document.querySelectorAll('.flex-item');
        flexItems.forEach(flexItem => {
            const existingOverlay = flexItem.querySelector('.overlay');
            if (!existingOverlay) {
                OCA.Analytics.Panorama.buildSingleOverlay(flexItem);
            }
        });
    },

    buildSingleOverlay: function (flexItem, isActive) {
        let overlay = document.createElement('div');
        overlay.classList.add('overlay');
        overlay.dataset.itemId = flexItem.id
        let overlayText = document.createElement('span');
        overlayText.classList.add('overlayText');
        overlayText.innerText = t('analytics', 'select to edit');
        overlayText.id = 'overlayText';
        overlay.appendChild(overlayText);
        flexItem.appendChild(overlay);

        overlay.addEventListener('click', function (evt) {
            // Remove the active state from any previous flex-item
            OCA.Analytics.Panorama.resetEditOverlays();

            // indicate now active overlay
            evt.currentTarget.classList.add('active');
            evt.currentTarget.firstChild.innerText = t('analytics', 'choose the content');

            OCA.Analytics.Panorama.showWidgetContentSelector(evt.currentTarget.dataset.itemId); // Position the menu items in a circle
        });

        if (isActive) {
            // when the item was drawn after a report change, the overlay needs to be added again
            //overlay.classList.add('active');
            //overlay.firstChild.innerText = '';
        }
    },

    resetEditOverlays: function () {
        let activeOverlays = document.querySelectorAll('.overlay.active');
        activeOverlays.forEach(function (activeOverlay) {
            activeOverlay.classList.remove('active');
            activeOverlay.firstChild.innerText = t('analytics', 'select to edit');
        });
    },

    // remove grey overlays from editable areas
    removeEditOverlays: function () {
        const flexItems = document.querySelectorAll('.flex-item');
        flexItems.forEach(flexItem => {
            const existingOverlay = flexItem.querySelector('.overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
        });
    },

    // make text boxes like headers editable
    addEditableTextBoxes: function () {
        let editableElements = document.getElementsByClassName('editable');
        editableElements.forEach(editableElement => {
            if (!editableElement.hasAttribute('contenteditable')) {
                editableElement.setAttribute('contenteditable', 'true');
            }
        });
    },

    removeEditableTextBoxes: function () {
        let editableElements = document.getElementsByClassName('editable');
        editableElements.forEach(editableElement => {
            if (editableElement.hasAttribute('contenteditable')) {
                editableElement.removeAttribute('contenteditable');
            }
        });
    },

    // show the flower style content selector menu when an overlay is clicked
    showWidgetContentSelector: function (itemId) {
        document.getElementById('editMenuContainer').style.display = 'block';
        const menu = document.getElementById('editMenu');
        //menu.style.display = 'block'; // Show the menu
        menu.dataset.itemId = itemId;
        // add the menu item event listeners in case they were not yet added
        // if not, add them to all menu related items one time
        if (menu.getAttribute('listenerAdded') !== 'true') {
            let menuItems = menu.querySelectorAll('.menu-item:not(.close-menu-item)');
            let numberOfItems = menuItems.length;
            let angleStep = 360 / numberOfItems;
            let menuRadius = menu.offsetWidth / 2; // Radius of the menu container

            menuItems.forEach(function (item, index) {
                // Calculate the angle for this item
                // first should start at the top so deducting 90°
                const angle = angleStep * index - 90;
                const angleRad = (angle * Math.PI) / 180;

                // Assuming menu items are circular, calculate the radius from the center of the menu
                const itemRadius = item.offsetWidth / 2; // Radius of a menu item

                // Position items inside the container, offset by the radius of a menu item
                const x = menuRadius + (menuRadius - itemRadius) * Math.cos(angleRad) - itemRadius;
                const y = menuRadius + (menuRadius - itemRadius) * Math.sin(angleRad) - itemRadius;

                // Apply the calculated positions
                item.style.left = `${x}px`;
                item.style.top = `${y}px`;
            });

            menu.addEventListener('click', function (e) {
                const action = e.target.getAttribute('data-modal');
                // Check if the clicked item is the close button
                if (action === 'close') {
                    document.getElementById('editMenuContainer').style.display = 'none';
                    // Remove the active state from any previous flex-item
                    OCA.Analytics.Panorama.resetEditOverlays();
                    event.stopPropagation()
                } else {
                    const modalId = e.target.dataset.modal;
                    const modal = document.getElementById(modalId);
                    // show the corresponding modal
                    if (modal) {
                        modal.style.display = 'block';
                        modal.dataset.itemId = e.target.parentElement.dataset.itemId;

                        // special handling for the text modal
                        if (modalId === 'modalText') {
                            let itemId = document.getElementById('modalText').dataset.itemId;
                            //let reportId = widget.dataset.chart;
                            let pageId = itemId.split('-')[0];
                            let itemIndex = itemId.split('-')[1];
                            //let reportId = OCA.Analytics.Panorama.currentPanorama['reports'].split(',')[reportIndex];
                            let page = OCA.Analytics.Panorama.currentPanorama.pages[pageId];
                            let itemContent = page.reports[itemIndex];

                            if (itemContent.type !== OCA.Analytics.Panorama.TYPE_TEXT) {
                                itemContent.value = '';
                            }

                            window.OCA.Text.createEditor({
                                el: document.getElementById('textInput'),
                                content: itemContent.value,
                                onUpdate: ({markdown}) => {
                                    document.getElementById('textInputContent').value = markdown
                                },
                            })
                        }
                    }
                }
            });
            // add an attribute to recognize if listener is already there
            menu.setAttribute('listenerAdded', 'true');

            // Close modals when clicking on the close button
            document.querySelectorAll('.modal .close').forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    const modal = this.closest('.modal');
                    modal.style.display = 'none';
                });
            });

            // Close the menu if the user clicks outside of it
            document.addEventListener('click', function (e) {
                if (!e.target.classList.contains('overlay')
                    && !e.target.classList.contains('menu-item')
                    && !e.target.classList.contains('close')
                    && !e.target.classList.contains('overlayText')
                ) {
                    document.getElementById('editMenuContainer').style.display = 'none';
                    // Remove the active state from any previous flex-item
                    OCA.Analytics.Panorama.resetEditOverlays();
                }
            });

            // event listener for the text input. to be moved out later
            document.getElementById('textInputButton').addEventListener('click', (e) => {
                let itemId = document.getElementById('modalText').dataset.itemId;
                let pageId = itemId.split('-')[0];
                let reportIndex = itemId.split('-')[1];
                let targetItem = document.getElementById(itemId);

                targetItem.setAttribute('data-chart', null);
                let page = OCA.Analytics.Panorama.currentPanorama.pages[pageId];
                //let reportsArr = page.reports.split(',');
                let reportsArr = page.reports;
                reportsArr[reportIndex] = {
                    'type': OCA.Analytics.Panorama.TYPE_TEXT,
                    'value': document.getElementById('textInputContent').value
                };
                //page.reports = reportsArr.join(',');
                OCA.Analytics.Panorama.buildWidget(itemId);
                OCA.Analytics.Panorama.buildSingleOverlay(targetItem, true);

                document.getElementById('modalText').style.display = 'none';
            });

            // Image Picker
            document.getElementById('pictureInputButton').addEventListener('click', (e) => {
                OCA.Analytics.Panorama.buildWidgetContentFileSelector();
            })
        }
    },
    
    buildWidgetContentReportSelector: function () {
        // Populate report selection menu with all available reports
        let reportSelectorContainer = document.getElementById('reportSelectorContainer');
        let reportMap = new Map();
        let rootReports = [];

        // Separate reports into root-level and child reports
        OCA.Analytics.reports.forEach(report => {
            if (report.parent === 0) {
                rootReports.push(report);
                if (report.type === 0) {
                    reportMap.set(report.id, []);
                }
            } else {
                if (reportMap.has(report.parent)) {
                    reportMap.get(report.parent).push(report);
                }
            }
        });

        // Sort root-level reports alphabetically
        rootReports.sort((a, b) => a.name.localeCompare(b.name));

        // Iterate and build the list
        rootReports.forEach(report => {
            let reportItem = OCA.Analytics.Panorama.buildWidgetContentReportSelectorItem(report, 0);
            reportSelectorContainer.appendChild(reportItem);

            // Add children for folders
            if (report.type === 0 && reportMap.has(report.id)) {
                reportMap.get(report.id).forEach(childReport => {
                    let childReportItem = OCA.Analytics.Panorama.buildWidgetContentReportSelectorItem(childReport, 20); // Indent child reports
                    reportSelectorContainer.appendChild(childReportItem);
                });
            }
        });
    },

    // Helper function to create report item element
    buildWidgetContentReportSelectorItem: function (report, indent) {
        const reportItem = document.createElement('div');
        reportItem.className = 'reportSelectorItem';
        reportItem.textContent = report.name;
        reportItem.setAttribute('reportId', report.id);
        reportItem.style.paddingLeft = indent + 'px';

        if (report.type !== 0) {
            reportItem.addEventListener('click', (e) => {
                let reportId = parseInt(e.target.getAttribute('reportId'));

                let itemId = document.getElementById('modalReport').dataset.itemId;
                let pageId = itemId.split('-')[0];
                let reportIndex = itemId.split('-')[1];
                let targetItem = document.getElementById(itemId);

                targetItem.setAttribute('data-chart', reportId);
                let page = OCA.Analytics.Panorama.currentPanorama.pages[pageId];
                let reportsArr = page.reports;
                reportsArr[reportIndex] = {'type': OCA.Analytics.Panorama.TYPE_REPORT, 'value': reportId};
                OCA.Analytics.Panorama.buildWidget(itemId);
                OCA.Analytics.Panorama.buildSingleOverlay(targetItem, true);

                document.getElementById('modalReport').style.display = 'none';
            });
        }
        return reportItem;
    },

    buildWidgetContentFileSelector: function () {
        let mime = ['image/png', 'image/x-png', 'image/jpeg'];
        //httpd/unix-directory

        OC.dialogs.filepicker(
            t('analytics', 'Select file'),
            function (path) {
                let itemId = document.getElementById('modalPicture').dataset.itemId;
                let pageId = itemId.split('-')[0];
                let reportIndex = itemId.split('-')[1];
                let targetItem = document.getElementById(itemId);

                let client = OC.Files.getClient()
                client.getFileInfo(path).then((status, fileInfo) => {
                    console.log(fileInfo.id)
                    targetItem.setAttribute('data-chart', null);
                    let page = OCA.Analytics.Panorama.currentPanorama.pages[pageId];
                    //let reportsArr = page.reports.split(',');
                    let reportsArr = page.reports;
                    reportsArr[reportIndex] = {'type': OCA.Analytics.Panorama.TYPE_PICTURE, 'value': fileInfo.id};
                    //page.reports = reportsArr.join(',');
                    OCA.Analytics.Panorama.buildWidget(itemId);
                    OCA.Analytics.Panorama.buildSingleOverlay(targetItem, true);
                    document.getElementById('modalPicture').style.display = 'none';
                })

            },
            false,
            mime,
            true,
            1);
    },

    buildLayoutSelector: function () {
        OCA.Analytics.Panorama.hideOptionMenu();
        document.getElementById('layoutModal').style.display = 'block';

        const layouts = OCA.Analytics.Panorama.layouts;
        const grid = document.getElementById('layoutModalGrid');
        grid.innerHTML = ''; // Clear existing content

        layouts.forEach(layout => {
            // Create a cell for each layout
            let cell = document.createElement('div');
            cell.className = 'layoutModalGridCell';
            cell.id = layout.id;
            cell.addEventListener('click', (e) => {
                grid.innerHTML = ''; // Clear existing content
                document.getElementById('layoutModal').style.display = 'none';
                let selectedLayout = OCA.Analytics.Panorama.layouts.find(x => parseInt(x.id) === parseInt(e.currentTarget.id));
                let currentPage = OCA.Analytics.Panorama.currentPage;
                OCA.Analytics.Panorama.currentPanorama.pages[currentPage].layout = selectedLayout.layout;
                OCA.Analytics.Panorama.getPanorama(currentPage);
                //OCA.Analytics.Panorama.addEditableTextBoxes();
                OCA.Analytics.Panorama.editMode = false;
                OCA.Analytics.Panorama.handleEditButton();
            });

            // Add the layout preview
            let preview = document.createElement('div');
            preview.className = 'layoutModalGridPreview';
            preview.innerHTML = layout.layout;
            cell.appendChild(preview);

            // Add the layout name below the preview
            let name = document.createElement('div');
            name.className = 'layoutModalName';
            name.textContent = layout.name;
            cell.appendChild(name);

            grid.appendChild(cell);
        });
    },

    removeLayoutSelctor: function () {
        //document.getElementById('layoutSelector').remove();
        document.getElementById('layoutModal').style.display = 'none';
    },

    handleDeletePageButton: function () {
        OCA.Analytics.Panorama.currentPanorama.pages.splice(OCA.Analytics.Panorama.currentPage, 1);
        OCA.Analytics.Panorama.getPanorama();
        OCA.Analytics.Panorama.updateNavButtons();
        OCA.Analytics.Panorama.toggleEditSaveButtonDisplay();
    },

    handleDeletePanoramaButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = evt.target.dataset.id;

        OCA.Analytics.Notification.confirm(
            t('analytics', 'Delete'),
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data will be deleted!'),
            function () {
                OCA.Analytics.Backend.delete(id);
                //OCA.Analytics.UI.resetContentArea();
                OCA.Analytics.Navigation.handleOverviewButton();
                OCA.Analytics.Notification.dialogClose();
            }
        );

    },

    // convert all canvas to images for better export quality
    // currently not used
    prepareContentForPDF: function () {
        // Find all charts within the page
        const charts = document.querySelectorAll('canvas');
        charts.forEach(chartCanvas => {
            const chart = Chart.getChart(chartCanvas); // Get the Chart.js instance from the canvas
            if (chart) {
                const imageData = chart.toBase64Image(); // Get a base64-encoded image of the chart

                // Create an image element
                const img = document.createElement('img');
                img.src = imageData;
                img.style = chartCanvas.style.cssText; // Copy the canvas styles to the image

                // Temporarily hide the canvas and place the image in the same location
                chartCanvas.style.display = 'none';
                chartCanvas.parentNode.insertBefore(img, chartCanvas);
            }
        });
    },

    // revers all images to canvas for further usage
    revertContentForPDF: function() {
        // Find all chart images and canvases to revert changes
        const charts = document.querySelectorAll('canvas');
        charts.forEach(chartCanvas => {
            const img = chartCanvas.previousSibling;
            if (img && img.tagName === 'IMG') {
                // Remove the image and show the canvas again
                img.parentNode.removeChild(img);
                chartCanvas.style.display = '';
            }
        });
    },

    // open the file picker which offers the selection for pdf save or download
    handlePdfButton: function () {
        let mime = ['httpd/unix-directory'];

        OC.dialogs.filepicker(
            t('analytics', 'Select the location for the PDF file'),
            function (path, button) {
                OCA.Analytics.Panorama.convertPDF(path, button === 'download');
            },
            false,  // multiselect
            mime,
            true,   // modal
            5,      // FILEPICKER_TYPE_CUSTOM
            null,   // path
            {
                buttons: [
                    {
                        text: t('analytics', 'Save to selected folder'),
                        type: 'save',
                        defaultButton: true,
                     },
                    {
                        text: t('analytics', 'Download'),
                        type: 'download',
                        defaultButton: false,
                    }
                ]
            }
        );
    },

    async convertPDF(path, download = false) {
        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Export as PDF'),
            OCA.Analytics.Notification.dialogClose
        );

        const headerHeight = 30; // Fixed height that looks good
        const pages = document.querySelectorAll('.flex-container');
        const pdf = new jspdf.jsPDF({
            orientation: 'landscape',
            unit: 'pt',
        });

        OCA.Analytics.Notification.htmlDialogUpdate(
            document.createElement('div'),
            t('analytics', 'Starting PDF export')
        );


        try {
            const headerText = document.querySelector('.panoramaHeader').textContent;
            OCA.Analytics.Notification.htmlDialogUpdateAdd('header captured');

            // Set PDF metadata
            pdf.setProperties({
                title:      headerText,
                subject:    headerText,
                author:     OC.getCurrentUser().displayName,
                keywords:   headerText + ', PDF, Analytics, Panorama',
                creator:    'Analytics - Nextcloud'
            });

            for (const [index, page] of pages.entries()) {
                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'capturing page {pageCount}', {pageCount: index}));
                const canvas = await html2canvas(page, {scale: 2});
                const imgData = canvas.toDataURL('image/png');

                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'captured page {pageCount}', {pageCount: index}));
                if (index > 0) {
                    pdf.addPage();
                }

                // Calculate the y position for the header
                pdf.setFontSize(12); // Adjust as needed
                const textYOffset = 20; // Adjust based on your headerHeight and padding

                // Add the header text centered at the top of each PDF page
                pdf.text(headerText, pdf.internal.pageSize.getWidth() / 2, textYOffset, { align: 'center' });

                // Determine the scale factor to fit the image within the PDF page size
                const scaleX = pdf.internal.pageSize.getWidth() / canvas.width;
                const scaleY = (pdf.internal.pageSize.getHeight() - headerHeight) / canvas.height;
                const scaleFactor = Math.min(scaleX, scaleY);

                // Calculate the scaled dimensions
                const scaledWidth = canvas.width * scaleFactor;
                const scaledHeight = canvas.height * scaleFactor;

                // Calculate the center position
                const xOffset = (pdf.internal.pageSize.getWidth() - scaledWidth) / 2;
                const yOffset = (pdf.internal.pageSize.getHeight() - scaledHeight) / 2 + headerHeight;

                // Add the page content centered and scaled
                pdf.addImage(imgData, 'PNG', xOffset, yOffset, scaledWidth, scaledHeight, index, 'FAST');
                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'page {pageCount} added to pdf', {pageCount: index}));
            }

            OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'creating pdf'));

            // Get the current date
            const currentDate = new Date();

            // Format the date as YYYY-MM-DD or any other format you prefer
            const formattedDate = currentDate.getFullYear() + "-"
                + String(currentDate.getMonth() + 1).padStart(2, '0') + "-"
                + String(currentDate.getDate()).padStart(2, '0');

            // Concatenate to get the new filename with the current date
            const fileName = headerText + "_" + formattedDate + '.pdf';

            if (download) {
                pdf.save(fileName);
            } else {
                let pdfBlob = pdf.output('blob');
                path = path + '/' + fileName;
                OCA.Analytics.Backend.uploadPdf(path, pdfBlob);
            }
            OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'created pdf'));
            OCA.Analytics.Notification.dialogClose();
        } catch (error) {
            OCA.Analytics.Notification.htmlDialogUpdateAdd("Error generating PDF: ", error);
        }
    },
    
    updateNavButtons: function () {
        let pagesContainer = document.getElementById('panoramaPages');
        let pageCount = pagesContainer.children.length;

        if (OCA.Analytics.Panorama.currentPage === 0) {
            document.getElementById('prevBtn').classList.add('disabled');
        } else {
            document.getElementById('prevBtn').classList.remove('disabled');
        }

        if (OCA.Analytics.Panorama.currentPage === pageCount - 1) {
            document.getElementById('nextBtn').classList.add('disabled');
        } else {
            document.getElementById('nextBtn').classList.remove('disabled');
        }

        if (OCA.Analytics.Panorama.editMode && OCA.Analytics.Panorama.currentPage === pageCount - 1) {
            // the next button will act as an add-page button
            document.getElementById('nextBtn').classList.remove('disabled');
            document.getElementById('nextBtn').classList.add('highlighted');
            document.getElementById('nextBtn').innerText = '+';
        } else {
            document.getElementById('nextBtn').classList.remove('highlighted');
            document.getElementById('nextBtn').innerText = '>';
        }
    },
    
    updatePageWidth: function () {
        let pagesContainer = document.getElementById('panoramaPages');
        let pageCount = pagesContainer.children.length;
        pagesContainer.style.width = `${pageCount * 100}%`;
    },

    navigatePage: function (direction) {
        let pagesContainer = document.getElementById('panoramaPages');
        let pageCount = pagesContainer.children.length;

        if (direction === 'next') {
            if (OCA.Analytics.Panorama.currentPage === pageCount - 1) {
                if (OCA.Analytics.Panorama.editMode) {
                    // add a new page because we are in edit mode
                    OCA.Analytics.Panorama.newPage();
                }
                return; // No more pages to the right
            }
            OCA.Analytics.Panorama.currentPage++;
        } else if (direction === 'prev') {
            if (OCA.Analytics.Panorama.currentPage === 0) {
                return; // No more pages to the left
            }
            OCA.Analytics.Panorama.currentPage--;
        } else if (direction === 'start') {
            OCA.Analytics.Panorama.currentPage = 0;
        }

        const newMargin = OCA.Analytics.Panorama.currentPage * -100;
        pagesContainer.style.marginLeft = `${newMargin}%`;
        OCA.Analytics.Panorama.updateNavButtons();
    },

}

/**
 * @namespace OCA.Analytics.Backend
 */
OCA.Analytics.Backend = {
    getReports: function () {
        let requestUrl = OC.generateUrl('apps/analytics/report');
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.reports = data;
                OCA.Analytics.Panorama.buildWidgetContentReportSelector();
            });
    },

    getReportData: function (datasetId, itemId) {
        const url = OC.generateUrl('apps/analytics/data/' + datasetId, true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                let jsondata = JSON.parse(xhr.response);
                // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
                jsondata = OCA.Analytics.Visualization.sortDates(jsondata);
                jsondata.data = OCA.Analytics.Visualization.formatDates(jsondata.data);
                OCA.Analytics.Panorama.setWidgetTypeReportContent(jsondata, itemId);
            }
        };
        xhr.send();
    },
    
    create: function () {
        let requestUrl = OC.generateUrl('apps/analytics/panorama');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                type: OCA.Analytics.TYPE_INTERNAL_FILE,
                parent: 0,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init(data);
            });
    },

    update: function () {
        let requestUrl = OC.generateUrl('apps/analytics/panorama/') + OCA.Analytics.Panorama.currentPanorama.id;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify(OCA.Analytics.Panorama.currentPanorama)
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init();
            });
    },

    delete: function (id) {
        let requestUrl = OC.generateUrl('apps/analytics/panorama/') + id;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init();
            })
            .catch(error => {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Request could not be processed'))
            });
    },

    uploadPdf: function (path, pdfBlob) {
        let username = OC.currentUser;
        let requestUrl = OC.generateUrl('remote.php/dav/files/') + username + path;

        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: pdfBlob,
        })
            .then(response => {
                if (!response.ok) {
                    OCA.Analytics.Notification.notification('error', t('analytics', 'Request could not be processed'))
                }
                OCA.Analytics.Notification.notification('success', 'Document was saved');
            })
        },
}

/**
 * @namespace OCA.Analytics.Dashboard
 */
OCA.Analytics.Dashboard = {
    init: function () {
        if (decodeURI(location.hash).length === 0) {
            OCA.Analytics.Dashboard.getFavorites();
        }
    },

    getFavorites: function () {
        const url = OC.generateUrl('apps/analytics/panoramaFavorites', true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.response !== '[]') {
                    for (let panorama of JSON.parse(xhr.response)) {
                        let story = OCA.Analytics.Panorama.stories.find(x => parseInt(x.id) === parseInt(panorama));

                        let li = '<li id="analyticsWidgetItem' + panorama + '" class="analyticsWidgetItem">' + story.name + '</li>';
                        document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);

                    }
                } else {
                    document.getElementById('ulAnalytics').innerHTML = '<div>'
                        + t('analytics', 'Add a report to the favorites to be shown here.')
                        + '</div>';
                }
            }
        };
        xhr.send();
    },

    favoriteUpdate: function (panoramaId, isFavorite) {
        let params = 'favorite=' + isFavorite;
        let xhr = new XMLHttpRequest();
        xhr.open('POST', OC.generateUrl('apps/analytics/panoramaFavorite/' + panoramaId, true), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(params);
    },
}

OCA.Analytics.UI = {
    getDefaultChartOptions: function () {
        return {
            devicePixelRatio: 2,
            bezierCurve: false, //remove curves from your plot
            scaleShowLabels: false, //remove labels
            tooltipEvents: [], //remove trigger from tooltips so they will not be show
            pointDot: false, //remove the points markers
            scaleShowGridLines: false, //set to false to remove the grids background
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                'primary': {
                    stacked: false,
                    position: 'left',
                    display: true,
                    grid: {
                        display: false,
                    },
                },
                'secondary': {
                    stacked: false,
                    position: 'right',
                    display: false,
                    grid: {
                        display: false,
                    },
                },
                'xAxes': {
                    type: 'category',
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: true,
                },
            },
            animation: {
                duration: 0 // general animation time
            },
            plugins: {
                legend: {
                    display: true,
                },
                datalabels: {
                    display: false,
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        value = (value * 100 / sum).toFixed(0);
                        if (value > 5) {
                            return value + "%";
                        } else {
                            return '';
                        }
                    },
                }
            },
        };
    },
}