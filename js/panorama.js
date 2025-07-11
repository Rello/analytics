/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // register handlers for the navigation bar
    OCA.Analytics.registerHandler('create', 'panorama', function () {
        OCA.Analytics.Panorama.newPanorama();
    });

    OCA.Analytics.registerHandler('navigationClicked', 'panorama', function (event) {
        OCA.Analytics.Panorama.handleNavigationClicked(event);
    });

    OCA.Analytics.registerHandler('delete', 'panorama', function (event) {
        OCA.Analytics.Panorama.handleDeletePanoramaButton(event);
    });

    OCA.Analytics.registerHandler('favoriteUpdate', 'panorama', function (id, isFavorite) {
        OCA.Analytics.Panorama.Dashboard.favoriteUpdate(id, isFavorite);
    });

    OCA.Analytics.registerHandler('saveIcon', 'panorama', function () {
        OCA.Analytics.Panorama.Backend.savePanorama();
    });
})

OCA = OCA || {};

OCA.Analytics.Panorama = OCA.Analytics.Panorama || {};
Object.assign(OCA.Analytics.Panorama = {

    stories: {},
    emptyPageTemplate: {page: 0, name: 'New', reports: [], layout: ''},
    layouts: [
        {
            id: 1, name: '2-1', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '</div>'
        },
        {
            id: 2, name: '1-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '<div class="flex-row" style="height: 50%;">' +
                '<div class="flex-item"></div><div class="flex-item"></div>' +
                '</div>' +
                '</div>'
        },
        {
            id: 3, name: '2-2', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
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
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
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
            id: 5, name: '1-1', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '<div class="flex-item" style="height: 50%;"></div>' +
                '</div>'
        },
        {
            id: 6, name: '1', layout: '<div class="flex-container">' +
                '<div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div>' +
                '<div class="flex-item"></div>' +
                '</div>'
        },
    ],

    /**
     * Attach click handlers for report menu elements
     */
    reportOptionsEventlisteners: function () {
        document.getElementById('optionsMenuPanoramaEdit').addEventListener('click', OCA.Analytics.Panorama.handleEditButton);
        document.getElementById('optionsMenuPanoramaLayout').addEventListener('click', OCA.Analytics.Panorama.buildLayoutSelector);
        document.getElementById('optionsMenuPanoramaDeletePage').addEventListener('click', OCA.Analytics.Panorama.handleDeletePageButton);
        document.getElementById('optionsMenuPanoramaPdf').addEventListener('click', OCA.Analytics.Panorama.handlePdfButton);

        document.getElementById('prevBtn').addEventListener('click', () => OCA.Analytics.Panorama.navigatePage('prev'));
        document.getElementById('nextBtn').addEventListener('click', () => OCA.Analytics.Panorama.navigatePage('next'));

        document.getElementById('layoutModalClose').addEventListener('click', function () {
            document.getElementById('layoutModal').style.display = 'none';
            document.getElementById('layoutModalGrid').innerHTML = '';
        });

    },

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
                'x': {
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

    handleNavigationClicked: function (evt) {
        OCA.Analytics.Sidebar.close();
        OCA.Analytics.Visualization.hideElement('addFilterIcon');
        OCA.Analytics.Visualization.hideElement('filterVisualisation');
        OCA.Analytics.Visualization.showContentByType('loading');

        const foundItem = OCA.Analytics.stories.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));
        OCA.Analytics.currentPanorama = JSON.parse(JSON.stringify(foundItem));
        //OCA.Analytics.currentPanorama = OCA.Analytics.stories.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));
        if (typeof OCA.Analytics.currentPanorama.pages === 'string') {
            OCA.Analytics.currentPanorama.pages = JSON.parse(OCA.Analytics.currentPanorama.pages);
        }
        OCA.Analytics.editMode = false;
        OCA.Analytics.Panorama.removeEditableTextBoxes();
        OCA.Analytics.Panorama.removeLayoutSelctor();
        OCA.Analytics.Panorama.hideOptionMenu();
        OCA.Analytics.Panorama.getPanorama();
    },

    newPanorama: function () {
        OCA.Analytics.Visualization.showContentByType('panorama');
        OCA.Analytics.currentPanorama = [];
        OCA.Analytics.Panorama.Backend.create();
    },

    newPage: function () {
        // store texts in case they were entered already
        OCA.Analytics.currentPanorama.name = document.getElementById('panoramaHeader').innerText;
        // get the subheaders and store them to the panorama
        document.querySelectorAll('.panoramaSubHeader').forEach((item) => {
            let page = item.id.split('-')[1];
            OCA.Analytics.currentPanorama.pages[page].name = item.innerText;
        });

        OCA.Analytics.currentPanorama.pages.push({page: 0, name: 'New', reports: [], layout: ''});
        OCA.Analytics.Panorama.getPanorama('next');
        OCA.Analytics.Panorama.updateNavButtons();
    },

    // get the panorama and loop all widgets
    getPanorama: function (targetPage) {
        // Reset existing pages
        document.getElementById('panoramaPages').innerHTML = '';

        OCA.Analytics.Panorama.updateOptionsMenuContent();

        // add an empty page if the panorama is empty/new
        if (OCA.Analytics.currentPanorama.pages.length === 0) {
            OCA.Analytics.currentPanorama.pages.push(OCA.Analytics.Panorama.emptyPageTemplate);
        }

        // Add the layout page by page
        OCA.Analytics.currentPanorama.pages.forEach((page, pageIndex) => {
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
            flexContainer.querySelectorAll('.panoramaSubHeader').forEach((item, idx) => {
                item.id = 'panoramaSubHeader-' + pageIndex;
                item.innerText = page.name;
            });

            document.getElementById('panoramaPages').appendChild(flexContainer);
        });

        // update the visibility of the next/prev buttons
        OCA.Analytics.Panorama.updateNavButtons();
        // updated the scrolling for multi pages
        OCA.Analytics.Panorama.updatePageWidth();

        // set the main header
        document.getElementById('panoramaHeader').innerText = OCA.Analytics.currentPanorama.name;

        //go to first page or to a dedicated one, if required
        if (targetPage) {
            OCA.Analytics.Panorama.navigatePage(targetPage);
        } else {
            OCA.Analytics.Panorama.navigatePage('start');
        }

        // fill all items with widgets
        let items = document.querySelectorAll('.flex-item');
        items.forEach((item) => {
            OCA.Analytics.Panorama.buildWidget(item.id);
        });

        // if still in edit mode, re-add the overlays over every item
        if (OCA.Analytics.editMode) OCA.Analytics.Panorama.addEditOverlays();

        OCA.Analytics.Visualization.showContentByType('panorama');
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
        //let reportId = OCA.Analytics.currentPanorama['reports'].split(',')[reportIndex];
        let page = OCA.Analytics.currentPanorama.pages[pageId];
        let itemContent = page.reports[itemIndex];

        if (itemContent !== null && itemContent !== undefined) {
            let contentValue = itemContent['value'];
            let contentType = parseInt(itemContent['type']);
            //let widget = document.querySelectorAll('div[data-chart]')[positionIndex];
            let widget = document.getElementById(itemId);
            widget.innerHTML = '';

            if (contentType === OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypeReport(parseInt(contentValue), itemId);
                widget.setAttribute('data-chart', contentValue);
                widget.insertAdjacentHTML('beforeend', itemWidget);
                OCA.Analytics.Panorama.Backend.getReportData(parseInt(contentValue), itemId);
            } else if (contentType === OCA.Analytics.PANORAMA_CONTENT_TYPE_TEXT) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypeText(contentValue);
                widget.appendChild(itemWidget);
            } else if (contentType === OCA.Analytics.PANORAMA_CONTENT_TYPE_PICTURE) {
                let itemWidget = OCA.Analytics.Panorama.buildWidgetTypePicture(contentValue);
                widget.appendChild(itemWidget);
            }
        }
    },

    buildWidgetTypeReport: function (reportId, itemId) {
        //let href = OC.generateUrl('apps/analytics/#/r/' + reportId);
        //return `<canvas id="myWidget${reportId}" class="chartContainer"></canvas>`;
        return `<div style="height: 30px;">
                    <div id="analyticsWidgetReport${itemId}" style="padding-left: 10px; font-weight: 500;"></div>
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

            // get display options for the item
            let chartOptions = OCA.Analytics.Panorama.getDefaultChartOptions();
            let pageId = itemId.split('-')[0];
            let itemIndex = itemId.split('-')[1];
            let itemContent = OCA.Analytics.currentPanorama.pages[pageId].reports[itemIndex];

            // legend = true
            if (itemContent?.options?.legend !== undefined) {
                chartOptions.plugins.legend.display = itemContent.options.legend;
            }

            OCA.Analytics.Visualization.buildChart(ctx, jsondata, chartOptions);
        } else {
            let canvasElement = document.getElementById(`myWidget${itemId}`);
            if (jsondata.data.length === 1) {
                // KPI view
                document.getElementById('analyticsWidgetReport' + itemId).innerText = '';
                let divElement = document.createElement('div');
                divElement.id = `myWidget${itemId}`;
                canvasElement.parentNode.replaceChild(divElement, canvasElement);
                OCA.Analytics.Visualization.buildKpiDisplay(document.getElementById('myWidget' + itemId), jsondata, false, itemId);
            } else {
                let divElement = document.createElement('table');
                divElement.id = `myWidget${itemId}`;
                canvasElement.parentNode.replaceChild(divElement, canvasElement);
                OCA.Analytics.Visualization.buildDataTable(document.getElementById('myWidget' + itemId), jsondata, false, itemId);
            }
        }
    },

    updateOptionsMenuContent: function () {
        if (OCA.Analytics.currentPanorama.permissions && parseInt(OCA.Analytics.currentPanorama.permissions) === OCA.Analytics.SHARE_PERMISSION_UPDATE) {
            document.getElementById('optionsMenuPanoramaEdit').disabled = false;
            document.getElementById('optionsMenuPanoramaLayout').disabled = false;
            document.getElementById('optionsMenuPanoramaDeletePage').disabled = false;
        } else {
            document.getElementById('optionsMenuPanoramaEdit').disabled = true;
            document.getElementById('optionsMenuPanoramaLayout').disabled = true;
            document.getElementById('optionsMenuPanoramaDeletePage').disabled = true;
        }
    },

    hideOptionMenu: function () {
        document.getElementById('optionsMenu').classList.remove('open');
    },

    handleEditButton: function () {
        if (OCA.Analytics.editMode) {
            // edit mode was active and is being cancelled
            // reload the panorama
            OCA.Analytics.editMode = false;
            OCA.Analytics.Panorama.removeEditableTextBoxes();
            OCA.Analytics.Panorama.removeLayoutSelctor();
            OCA.Analytics.Panorama.removeEditOverlays();
            OCA.Analytics.Panorama.hideOptionMenu();

        } else {
            OCA.Analytics.editMode = true;
            OCA.Analytics.Panorama.addEditOverlays();
            OCA.Analytics.Panorama.addEditableTextBoxes();
            OCA.Analytics.Panorama.updateNavButtons();
            OCA.Analytics.Panorama.hideOptionMenu();
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

        // add item specific extra options to overlay
        OCA.Analytics.Panorama.addDisplayOptionsToOverlay(overlay, flexItem);

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

    /**
     * Adds display options to the overlay depending on the item type.
     * e.g. choose legends for charts
     * Extend this function to support more types/options in the future.
     */
    addDisplayOptionsToOverlay: function (overlay, flexItem) {
        let itemId = flexItem.id;
        let pageId = itemId.split('-')[0];
        let itemIndex = itemId.split('-')[1];
        let page = OCA.Analytics.currentPanorama.pages[pageId];
        let itemContent = page.reports[itemIndex];

        // legend true/false for reports
        if (itemContent && parseInt(itemContent.type) === OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT) {
            let optionsContainer = document.createElement('div');
            optionsContainer.classList.add('overlayOptions');

            let legendCheckbox = document.createElement('input');
            legendCheckbox.type = 'checkbox';
            legendCheckbox.id = `legend-${itemId}`;
            legendCheckbox.checked = !(itemContent.options && itemContent.options.legend === false);
            legendCheckbox.addEventListener('change', function (e) {
                let showLegend = e.target.checked;
                page.reports[itemIndex].options ??= {};
                if (showLegend) {
                    delete page.reports[itemIndex].options.legend;
                } else {
                    page.reports[itemIndex].options.legend = false;
                }
                if (Object.keys(page.reports[itemIndex].options).length === 0) {
                    delete page.reports[itemIndex].options;
                }
                // rebuild widget to reflect legend change
                if (page.reports[itemIndex].type === OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT) {
                    OCA.Analytics.Panorama.Backend.getReportData(page.reports[itemIndex].value, itemId);
                }
            });

            let legendLabel = document.createElement('label');
            legendLabel.setAttribute('for', `legend-${itemId}`);
            legendLabel.innerText = t('analytics', 'Show legend');

            optionsContainer.appendChild(legendCheckbox);
            optionsContainer.appendChild(legendLabel);
            overlay.appendChild(optionsContainer);
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
        let editableElements = document.querySelectorAll('.editable');
        editableElements.forEach(editableElement => {
            if (!editableElement.hasAttribute('contenteditable')) {
                editableElement.setAttribute('contenteditable', 'true');
            }
            // add listener only once per element
            if (editableElement.getAttribute('listenerAdded') !== 'true') {
                editableElement.addEventListener('input', OCA.Analytics.Panorama.handleEditableChange);
                editableElement.setAttribute('listenerAdded', 'true');
            }
        });
    },

    removeEditableTextBoxes: function () {
        let editableElements = document.querySelectorAll('.editable');
        editableElements.forEach(editableElement => {
            if (editableElement.hasAttribute('contenteditable')) {
                editableElement.removeAttribute('contenteditable');
            }
            if (editableElement.getAttribute('listenerAdded') === 'true') {
                editableElement.removeEventListener('input', OCA.Analytics.Panorama.handleEditableChange);
                editableElement.removeAttribute('listenerAdded');
            }
        });
    },

    handleEditableChange: function () {
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Filter.toggleSaveButtonDisplay();
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
                            //let reportId = OCA.Analytics.currentPanorama['reports'].split(',')[reportIndex];
                            let page = OCA.Analytics.currentPanorama.pages[pageId];
                            let itemContent = page.reports[itemIndex];
                            let content = '';

                            if (itemContent && itemContent.type === OCA.Analytics.PANORAMA_CONTENT_TYPE_TEXT) {
                                content = itemContent.value;
                            }

                            window.OCA.Text.createEditor({
                                el: document.getElementById('textInput'),
                                content: content,
                                onUpdate: ({markdown}) => {
                                    document.getElementById('textInputContent').value = markdown
                                },
                            })
                        }
                        if (modalId === 'modalReport') {
                            OCA.Analytics.Panorama.buildWidgetContentReportSelector();
                            OCA.Analytics.Panorama.highlightSelectedReport(modal.dataset.itemId);
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
                let page = OCA.Analytics.currentPanorama.pages[pageId];
                //let reportsArr = page.reports.split(',');
                let reportsArr = page.reports;
                reportsArr[reportIndex] = {
                    'type': OCA.Analytics.PANORAMA_CONTENT_TYPE_TEXT,
                    'value': document.getElementById('textInputContent').value
                };
                delete reportsArr[reportIndex].options;
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
        reportSelectorContainer.innerText = '';
        let reportMap = new Map();
        let rootReports = [];

        // Separate reports into root-level and child reports
        OCA.Analytics.reports.forEach(report => {
            if (report.item_type === 'report') {
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
            }
        });

        // Sort root-level reports alphabetically
        rootReports.sort((a, b) => a.name.localeCompare(b.name));

        // Iterate and build the list
        rootReports.forEach(report => {
            let reportItem = OCA.Analytics.Panorama.buildWidgetContentReportSelectorItem(report, 5);
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

    highlightSelectedReport: function (itemId) {
        const items = document.querySelectorAll('#reportSelectorContainer .reportSelectorItem');
        items.forEach(i => i.classList.remove('selected'));

        if (!itemId) {
            return;
        }
        const pageId = itemId.split('-')[0];
        const reportIndex = itemId.split('-')[1];
        const report = OCA.Analytics.currentPanorama.pages[pageId]?.reports[reportIndex];
        if (!report || report.type !== OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT) {
            return;
        }
        const reportId = report.value;
        const selected = document.querySelector(`#reportSelectorContainer .reportSelectorItem[reportId='${reportId}']`);
        if (selected) {
            selected.classList.add('selected');
        }
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
                let page = OCA.Analytics.currentPanorama.pages[pageId];
                let reportsArr = page.reports;
                let existingOptions = reportsArr[reportIndex]?.options;
                reportsArr[reportIndex] = {'type': OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT, 'value': reportId};
                if (existingOptions && Object.keys(existingOptions).length > 0) {
                    reportsArr[reportIndex].options = existingOptions;
                }
                OCA.Analytics.Panorama.buildWidget(itemId);
                OCA.Analytics.Panorama.buildSingleOverlay(targetItem, true);

                // show the save icon
                OCA.Analytics.unsavedChanges = true;
                OCA.Analytics.Filter.toggleSaveButtonDisplay();

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
                    let page = OCA.Analytics.currentPanorama.pages[pageId];
                    //let reportsArr = page.reports.split(',');
                    let reportsArr = page.reports;
                    reportsArr[reportIndex] = {
                        'type': OCA.Analytics.PANORAMA_CONTENT_TYPE_PICTURE,
                        'value': fileInfo.id
                    };
                    delete reportsArr[reportIndex].options;
                    //page.reports = reportsArr.join(',');
                    OCA.Analytics.Panorama.buildWidget(itemId);
                    OCA.Analytics.Panorama.buildSingleOverlay(targetItem, true);

                    // show the save icon
                    OCA.Analytics.unsavedChanges = true;
                    OCA.Analytics.Filter.toggleSaveButtonDisplay();

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
                let currentPage = OCA.Analytics.currentPage;
                OCA.Analytics.currentPanorama.pages[currentPage].layout = selectedLayout.layout;
                OCA.Analytics.Panorama.getPanorama(currentPage);
                OCA.Analytics.editMode = false;

                // show the save icon
                OCA.Analytics.unsavedChanges = true;
                OCA.Analytics.Filter.toggleSaveButtonDisplay();
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
        OCA.Analytics.currentPanorama.pages.splice(OCA.Analytics.currentPage, 1);
        OCA.Analytics.Panorama.getPanorama();
        OCA.Analytics.Panorama.updateNavButtons();
        // show the save icon
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Filter.toggleSaveButtonDisplay();
    },

    handleDeletePanoramaButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = evt.target.dataset.id;

        OCA.Analytics.Notification.confirm(
            t('analytics', 'Delete'),
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data will be deleted!'),
            function () {
                OCA.Analytics.Panorama.Backend.delete(id);
                //OCA.Analytics.Report.resetContentArea();
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
    revertContentForPDF: function () {
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

        // const headerHeight = 35; // Fixed height that looks good
        const headerHeight = 0; // Fixed height that looks good
        const pages = document.querySelectorAll('.flex-container');
        const pdf = new jspdf.jsPDF({
            orientation: 'landscape',
            unit: 'pt',
        });

        OCA.Analytics.Notification.htmlDialogUpdate(
            document.createElement('div'),
            t('analytics', 'Starting PDF export')
        );

        const appContent = document.getElementById('app-content');
        const computedStyles = getComputedStyle(appContent);
        const backgroundColor = computedStyles.backgroundColor; // Get the resolved background color
        // Extract RGB values from "rgb(x, y, z)"
        const rgbMatch = backgroundColor.match(/\d+/g); // Matches ["240", "240", "240"]
        const [r, g, b] = rgbMatch.map(Number); // Convert to numbers


        // getting the header
        let headerElement = document.getElementById('panoramaHeaderRow');
        let headerCanvas = await html2canvas(headerElement, {scale: 2});
        let headerData = headerCanvas.toDataURL('image/png');
        let headerText = document.getElementById('panoramaHeader').textContent;

        // hide the subheaders. will only take the text later
        const elements = document.querySelectorAll('.panoramaSubHeaderRow');
        elements.forEach(element => {
            element.classList.add('analyticsFullscreen');
        });

        // getting "by analytics"
        let byAnalyticsImg = document.getElementById('byAnalyticsImg');
        let byAnalyticsElement = document.getElementById('byAnalytics');
        let byAnalyticsClass = byAnalyticsElement.classList.contains('analyticsFullscreen');

        if (!byAnalyticsClass) {
            byAnalyticsElement.classList.add('analyticsFullscreen');
        }

        // Temporarily reset styles
        byAnalyticsImg.style.width = byAnalyticsImg.naturalWidth + 'px';
        byAnalyticsImg.style.height = byAnalyticsImg.naturalHeight + 'px';
        byAnalyticsImg.style.transform = 'none';  // Remove any CSS transforms

        // set the grey background for export
        document.querySelectorAll('.flex-item').forEach(el => {
            el.style.backgroundColor = '#f5f5f5'; // Set background-color
        });

        let byAnalyticsCanvas = await html2canvas(byAnalyticsImg, {scale: 1});
        let byAnalyticsRawData = byAnalyticsCanvas.toDataURL('image/png');

        // restore the previous status
        byAnalyticsImg.style.width = '33px';
        byAnalyticsImg.style.height = '33px';
        if (!byAnalyticsClass) {
            byAnalyticsElement.classList.remove('analyticsFullscreen');
        }

        try {
            OCA.Analytics.Notification.htmlDialogUpdateAdd('header captured');

            // Set PDF metadata
            pdf.setProperties({
                title: headerText,
                subject: headerText,
                author: OC.getCurrentUser().displayName,
                keywords: headerText + ', PDF, Analytics, Panorama',
                creator: 'Analytics - Nextcloud'
            });

            for (const [index, page] of pages.entries()) {
                //page.style.backgroundColor = backgroundColor;
                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'capturing page {pageCount}', {pageCount: index}));
                const canvas = await html2canvas(page, {scale: 2});
                const imgData = canvas.toDataURL('image/png');

                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'captured page {pageCount}', {pageCount: index}));
                if (index > 0) {
                    pdf.addPage();
                }

                // Pull page background color
                // not used for now as it does not look good
                // pdf.setFillColor(r, g, b);
                // pdf.rect(0, 0, pdf.internal.pageSize.getWidth(), pdf.internal.pageSize.getHeight(), 'F');

                // Add graphical header
                // Determine the scale factor to fit the image within the PDF page size
                /*
                                let scaleX = pdf.internal.pageSize.getWidth() / headerCanvas.width;
                                let scaleY = (pdf.internal.pageSize.getHeight() - headerHeight) / headerCanvas.height;
                                let scaleFactor = Math.min(scaleX, scaleY);

                                // Calculate the scaled dimensions
                                let scaledWidth = canvas.width * scaleFactor;
                                let scaledHeight = canvas.height * scaleFactor;

                                // Calculate the center position
                                let xOffset = (pdf.internal.pageSize.getWidth() - scaledWidth) / 2;
                                let yOffset = (pdf.internal.pageSize.getHeight() - scaledHeight) / 2 + headerHeight;
                                pdf.addImage(headerData, 'PNG', xOffset+30, 20, 380, 25, 200, 'FAST');
                */

                // Add written header
                pdf.setFontSize(16); // Adjust as needed
                let textYOffset = 23; // Adjust based on your headerHeight and padding
                pdf.text(headerText, 40, textYOffset, {align: 'left'});

                // getting the subheader
                let subHeaderElement = document.getElementById('panoramaSubHeader-' + index);
                let subHeaderCanvas = await html2canvas(subHeaderElement, {scale: 2});
                let subHeaderData = subHeaderCanvas.toDataURL('image/png');
                // Add graphical header
                // pdf.addImage(subHeaderData, 'PNG', 28, 40, 380, 25, 200, 'FAST');

                // draw the subheader
                let subHeaderText = document.getElementById('panoramaSubHeader-' + index).textContent;
                pdf.setFontSize(12); // Adjust as needed
                pdf.text(subHeaderText, 40, 44, {align: 'left'});


                // Add the page content centered and scaled
                // Determine the scale factor to fit the image within the PDF page size
                let scaleX = pdf.internal.pageSize.getWidth() / canvas.width;
                let scaleY = (pdf.internal.pageSize.getHeight() - headerHeight) / canvas.height;
                let scaleFactor = Math.min(scaleX, scaleY);

                // Calculate the scaled dimensions
                let scaledWidth = canvas.width * scaleFactor;
                let scaledHeight = canvas.height * scaleFactor;

                // Calculate the center position
                let xOffset = (pdf.internal.pageSize.getWidth() - scaledWidth) / 2;
                let yOffset = (pdf.internal.pageSize.getHeight() - scaledHeight) / 2 + headerHeight;

                // Add the page content centered and scaled
                pdf.addImage(imgData, 'PNG', xOffset, yOffset, scaledWidth, scaledHeight, index, 'FAST');

                // Add the Analytics branding
                pdf.addImage(byAnalyticsRawData, 'PNG', pdf.internal.pageSize.getWidth() - 100, pdf.internal.pageSize.getHeight() - 35, 30, 30, 100, 'MEDIUM');
                pdf.setFontSize(8);
                pdf.text('Created with', pdf.internal.pageSize.getWidth() - 65, pdf.internal.pageSize.getHeight() - 22);
                pdf.text('Analytics', pdf.internal.pageSize.getWidth() - 65, pdf.internal.pageSize.getHeight() - 12);

                OCA.Analytics.Notification.htmlDialogUpdateAdd(t('analytics', 'page {pageCount} added to pdf', {pageCount: index}));
            }

            // adding the subheaders again
            const elements = document.querySelectorAll('.panoramaSubHeaderRow');
            elements.forEach(element => {
                element.classList.remove('analyticsFullscreen');
            });

            // reset the grey background for export
            document.querySelectorAll('.flex-item').forEach(el => {
                el.style.backgroundColor = '';
            });

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
                OCA.Analytics.Panorama.Backend.uploadPdf(path, pdfBlob);
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

        if (OCA.Analytics.currentPage === 0) {
            document.getElementById('prevBtn').classList.add('disabled');
        } else {
            document.getElementById('prevBtn').classList.remove('disabled');
        }

        if (OCA.Analytics.currentPage === pageCount - 1) {
            document.getElementById('nextBtn').classList.add('disabled');
        } else {
            document.getElementById('nextBtn').classList.remove('disabled');
        }

        if (OCA.Analytics.editMode && OCA.Analytics.currentPage === pageCount - 1) {
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
            if (OCA.Analytics.currentPage === pageCount - 1) {
                if (OCA.Analytics.editMode) {
                    // add a new page because we are in edit mode
                    OCA.Analytics.Panorama.newPage();
                }
                return; // No more pages to the right
            }
            OCA.Analytics.currentPage++;
        } else if (direction === 'prev') {
            if (OCA.Analytics.currentPage === 0) {
                return; // No more pages to the left
            }
            OCA.Analytics.currentPage--;
        } else if (direction === 'start') {
            OCA.Analytics.currentPage = 0;
        }

        const newMargin = OCA.Analytics.currentPage * -100;
        pagesContainer.style.marginLeft = `${newMargin}%`;
        OCA.Analytics.Panorama.updateNavButtons();
    },

});

OCA.Analytics.Panorama.Backend = OCA.Analytics.Panorama.Backend || {};
Object.assign(OCA.Analytics.Panorama.Backend = {
    /*    getReports: function () {
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
        },*/

    getReportData: function (datasetId, itemId) {
        const url = OC.generateUrl('apps/analytics/data/pa/' + datasetId, true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 404) {
                    document.getElementById('analyticsWidgetReport' + itemId).innerText = '';
                    document.getElementById('myWidget' + itemId).innerText = t('analytics', 'The report is not available anymore');
                    return;
                }
                let jsondata = JSON.parse(xhr.response);
                // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
                if (parseInt(jsondata.error) !== 0) {
                    OCA.Analytics.Notification.notification('error', jsondata.error);
                    return;
                }

                jsondata.data = OCA.Analytics.Visualization.formatDates(jsondata.data);

                try {
                    // Chart.js v4.4.3 changed from xAxes to x. In case the user has old chart options, they need to be corrected
                    let parsedChartOptions = JSON.parse(jsondata.options.chartoptions.replace(/xAxes/g, 'x'));
                    jsondata.options.chartoptions = (parsedChartOptions !== null && typeof parsedChartOptions === 'object') ? parsedChartOptions : {};
                } catch (e) {
                    jsondata.options.chartoptions = {};
                }

                try {
                    let parsedDataOptions = JSON.parse(jsondata.options.dataoptions);
                    jsondata.options.dataoptions = (parsedDataOptions !== null && typeof parsedDataOptions === 'object') ? parsedDataOptions : {};
                } catch (e) {
                    jsondata.options.dataoptions = {};
                }

                try {
                    let parsedFilterOptions = JSON.parse(jsondata.options.filteroptions);
                    jsondata.options.filteroptions = (parsedFilterOptions !== null && typeof parsedFilterOptions === 'object') ? parsedFilterOptions : {};
                } catch (e) {
                    jsondata.options.filteroptions = {};
                }

                try {
                    let parsedTableOptions = JSON.parse(jsondata.options.tableoptions);
                    jsondata.options.tableoptions = (parsedTableOptions !== null && typeof parsedTableOptions === 'object') ? parsedTableOptions : {};
                } catch (e) {
                    jsondata.options.tableoptions = {};
                }

                jsondata = OCA.Analytics.Visualization.sortDates(jsondata);
                jsondata = OCA.Analytics.Visualization.applyTimeAggregation(jsondata);
                jsondata = OCA.Analytics.Visualization.applyTopN(jsondata);

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
            .then(id => {
                return fetch(OC.generateUrl('apps/analytics/panorama/') + id, {
                    method: 'GET',
                    headers: OCA.Analytics.headers(),
                });
            })
            .then(response => response.json())
            .then(data => {
                data.item_type = 'panorama';
                OCA.Analytics.Navigation.addNavigationItem(data);
                const anchor = document.querySelector('#navigationDatasets a[data-id="' + data.id + '"][data-item_type="panorama"]');
                anchor?.click();
            });
    },

    update: function () {
        // clean up possible html data within the headers
        let requestUrl = OC.generateUrl('apps/analytics/panorama/') + OCA.Analytics.currentPanorama.id;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify(OCA.Analytics.currentPanorama)
        })
            .then(response => response.json())
            .then(data => {
                const id = OCA.Analytics.currentPanorama.id;
                const name = OCA.Analytics.currentPanorama.name;

                const anchor = document.querySelector('#navigationDatasets a[data-id="' + id + '"][data-item_type="panorama"]');
                if (anchor) {
                    anchor.dataset.name = name;
                    if (anchor.firstChild) {
                        anchor.firstChild.textContent = name;
                    } else {
                        anchor.textContent = name;
                    }
                }

                let story = OCA.Analytics.stories.find(p => parseInt(p.id) === parseInt(id));
                if (story) {
                    story.name = name;
                    story.pages = OCA.Analytics.currentPanorama.pages;
                }

                anchor?.click();
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
                OCA.Analytics.Navigation.removeNavigationItem(id, 'panorama');
                OCA.Analytics.Navigation.handleOverviewButton();
            })
            .catch(error => {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Request could not be processed'))
            });
    },

    uploadPdf: function (path, pdfBlob) {
        let username = OC.currentUser;
        let requestUrl = OC.linkToRemote('dav/files/') + username + path;

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

    savePanorama: function () {
        OCA.Analytics.unsavedChanges = false;
        OCA.Analytics.currentPanorama.name = document.getElementById('panoramaHeader').innerText;

        OCA.Analytics.Panorama.removeEditOverlays();
        OCA.Analytics.Panorama.removeEditableTextBoxes();
        OCA.Analytics.Panorama.removeLayoutSelctor();

        // get the subheaders and store them to the panorama
        document.querySelectorAll('.panoramaSubHeader').forEach((item) => {
            let page = item.id.split('-')[1];
            item.innerText = item.textContent;
            OCA.Analytics.currentPanorama.pages[page].name = item.textContent;
        });
        OCA.Analytics.Panorama.Backend.update();

        OCA.Analytics.Filter.toggleSaveButtonDisplay();
        OCA.Analytics.Panorama.updateNavButtons();
        OCA.Analytics.Panorama.hideOptionMenu();
    },

});

OCA.Analytics.Panorama.Dashboard = OCA.Analytics.Panorama.Dashboard || {};
Object.assign(OCA.Analytics.Panorama.Dashboard = {
    init: function () {
        OCA.Analytics.Panorama.Dashboard.getFavorites();
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
                        let story = OCA.Analytics.stories.find(x => parseInt(x.id) === parseInt(panorama));

                        let li = '<li id="analyticsWidgetItem-panorama-' + panorama + '" class="analyticsWidgetItem"></li>';
                        document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);
                        let widgetRow = OCA.Analytics.Dashboard.buildPanoramaRow(story.name, panorama);
                        document.getElementById('analyticsWidgetItem-panorama-' + panorama).insertAdjacentHTML('beforeend', widgetRow);
                        document.getElementById('analyticsWidgetItem-panorama-' + panorama).addEventListener('click', OCA.Analytics.Dashboard.handleNavigationClicked);

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
});
