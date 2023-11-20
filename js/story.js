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
    let prevBtn = document.getElementById('prevBtn');
    let nextBtn = document.getElementById('nextBtn');
    let editBtn = document.getElementById('editBtn');
    let plusBtn = document.getElementById('plusBtn');
    prevBtn.addEventListener('click', () => OCA.Analytics.Story.navigatePage('prev'));
    nextBtn.addEventListener('click', () => OCA.Analytics.Story.navigatePage('next'));
    editBtn.addEventListener('click', OCA.Analytics.Story.handleEditButton);
    plusBtn.addEventListener('click', OCA.Analytics.Story.addPage);

    OCA.Analytics.initialDocumentTitle = document.title;
    OCA.Analytics.UI.hideElement('analytics-warning');
    OCA.Analytics.UI.showElement('analytics-intro');
    OCA.Analytics.UI.hideElement('plusBtn');
    OCA.Analytics.Story.init();
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
 * @namespace OCA.Analytics.Story
 */
OCA.Analytics.Story = {
    TYPE_GROUP: 0,
    storiesTmp: {
        id: 1,
        name: 'Story 1',
        pages: [
            {page: 0, name: 'page 1', layout: ''},
        ]
    },
    stories: [],
    currentStory: {},
    currentPage: 0,
    editMode: false,
    layouts: [
        {
            id: 1, name: '2-1', layout: '<div class="flex-container">\n' +
                '<div class="storySubHeaderRow"><div class="subHeader editable"></div></div>' +
                '<div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '        <div class="flex-item" style="height: 50%;"></div>\n' +
                '    </div>'
        },
        {
            id: 2, name: '1-2', layout: '<div class="flex-container">\n' +
                '<div class="storySubHeaderRow"><div class="subHeader editable"></div></div>' +
                '        <div class="flex-item" style="height: 50%;"></div>\n' +
                '        <div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '    </div>'
        },
        {
            id: 3, name: '2-2', layout: '<div class="flex-container">\n' +
                '<div class="storySubHeaderRow"><div class="subHeader editable"></div></div>' +
                '        <div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '        <div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '    </div>'
        },
        {
            id: 4, name: '4-2', layout: '<div class="flex-container">\n' +
                '<div class="storySubHeaderRow"><div class="subHeader editable"></div></div>' +
                '        <div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '        <div class="flex-row" style="height: 50%;">\n' +
                '            <div class="flex-item"></div>\n' +
                '            <div class="flex-item"></div>\n' +
                '        </div>\n' +
                '    </div>'
        },
    ],

    init: function () {
        OCA.Analytics.Story.Backend.getReports();
        //OCA.Analytics.Story.getStory();
        // OCA.Analytics.Story.addEditLayer();
    },


    handleEditButton: function () {

        if (OCA.Analytics.Story.editMode) {
            // store edited values
            OCA.Analytics.Story.currentStory.name = document.getElementById('storyHeader').innerText;

            OCA.Analytics.Story.removeAllOverlays();
            OCA.Analytics.Story.removeEditableTextBoxes();

            // get the subheaders and store them to the story
            document.querySelectorAll('.subHeader').forEach((item) => {
                let page = item.id.split('-')[1];
                OCA.Analytics.Story.currentStory.pages[page].name = item.innerText;
            });

            // hide elements and disable edit mode
            OCA.Analytics.UI.hideElement('plusBtn');

            let editBoxes = document.getElementsByName('editBox');
            editBoxes.forEach((editBox) => {
                editBox.remove();
            });

        } else {
            OCA.Analytics.UI.showElement('plusBtn');
            OCA.Analytics.Story.buildLayoutEdit();
            OCA.Analytics.Story.addAllOverlays();
            OCA.Analytics.Story.addEditableTextBoxes();
        }

        OCA.Analytics.Story.editMode = !OCA.Analytics.Story.editMode;
    },

    addAllOverlays: function () {
        // create or remove grey overlays to indicate the editable areas
        const flexItems = document.querySelectorAll('.flex-item');
        flexItems.forEach(flexItem => {
            const existingOverlay = flexItem.querySelector('.overlay');
            if (!existingOverlay) {
                OCA.Analytics.Story.createOverlay(flexItem);
            }
        });
    },

    removeAllOverlays: function () {
        // create or remove grey overlays to indicate the editable areas
        const flexItems = document.querySelectorAll('.flex-item');
        flexItems.forEach(flexItem => {
            const existingOverlay = flexItem.querySelector('.overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
        });
    },

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

    // Function to create and show the overlay for all areas
    createOverlay: function (flexItem, isActive) {
        let overlay = document.createElement('div');
        overlay.classList.add('overlay');
        overlay.dataset.itemId = flexItem.id
        let overlayText = document.createElement('span');
        overlayText.classList.add('overlayText');
        overlayText.innerText = 'select to edit';
        overlayText.id = 'overlayText';
        overlay.appendChild(overlayText);
        flexItem.appendChild(overlay);

        overlay.addEventListener('click', function (evt) {
            // Remove the active state from any previous flex-item
            let activeOverlays = document.querySelectorAll('.overlay.active');
            activeOverlays.forEach(function (activeOverlay) {
                activeOverlay.classList.remove('active');
                activeOverlay.firstChild.innerText = 'select to edit';
            });

            // indicate now active overlay
            evt.target.classList.add('active');
            evt.target.firstChild.innerText = '';

            OCA.Analytics.Story.showMenu(evt.target.dataset.itemId); // Position the menu items in a circle
        });

        if (isActive) {
            // when the item was drawn after a report change, the overlay needs to be added again
            //overlay.classList.add('active');
            //overlay.firstChild.innerText = '';
        }
    },

    // Function to calculate the positions of the menu items
    showMenu: function (itemId) {
        const menu = document.querySelector('.menu');
        menu.style.display = 'block'; // Show the menu
        menu.dataset.itemId = itemId;
        let menuItems = menu.querySelectorAll('.menu-item:not(.close-menu-item)');
        let numberOfItems = menuItems.length;
        let angleStep = 360 / numberOfItems;
        let menuRadius = menu.offsetWidth / 2; // Radius of the menu container

        menuItems.forEach(function (item, index) {
            // Calculate the angle for this item
            const angle = angleStep * index;
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

        // add the menu item event listeners in case they were not yet added
        // if not, add them to all menu related items one time
        if (menu.getAttribute('listenerAdded') !== 'true') {
            menu.addEventListener('click', function (e) {
                const action = e.target.getAttribute('data-modal');
                // Check if the clicked item is the close button
                if (action === 'close') {
                    menu.style.display = 'none'; // Hide the menu
                    // remove all active states from overlays
                    let activeOverlays = document.querySelectorAll('.overlay.active');
                    activeOverlays.forEach(function (activeOverlay) {
                        activeOverlay.classList.remove('active');
                        activeOverlay.firstChild.innerText = 'select to edit';
                    });
                    event.stopPropagation()
                } else {
                    const modalId = e.target.dataset.modal;
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.style.display = 'block';
                        modal.dataset.itemId = e.target.parentElement.dataset.itemId;
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
                if (!e.target.classList.contains('overlay') && !e.target.classList.contains('menu-item') && !e.target.classList.contains('close')) {
                    menu.style.display = 'none';
                    let overlays = document.querySelectorAll('.overlay.active');
                    overlays.forEach(function (item) {
                        item.classList.remove('active');
                    });
                }
            });
        }
    },

    buildWidgetEdit: function () {
        let flexItems = document.getElementsByClassName('flex-item');
        Array.from(flexItems).forEach((item, positionIndex) => {
            const hoverBox = document.createElement('div');
            const dropdown = document.createElement('select');
            let pageId = item.id.split('-')[0];
            let reportIndex = item.id.split('-')[1];

            // Populate dropdown with given numbers
            OCA.Analytics.reports.forEach((report) => {
                const option = document.createElement('option');
                option.value = report.id;
                option.text = report.name;
                dropdown.appendChild(option);
            });

            dropdown.value = item.getAttribute('data-chart');

            dropdown.id = `dropdown-${item.id}`;
            dropdown.addEventListener('change', (e) => {
                item.setAttribute('data-chart', parseInt(e.target.value));
                let page = OCA.Analytics.Story.currentStory.pages[pageId];
                let reportsArr = page.reports.split(',');
                reportsArr[reportIndex] = parseInt(e.target.value);
                page.reports = reportsArr.join(',');
                OCA.Analytics.Story.buildWidget(item.id);
                // Re-attach hoverBox to DOM
                dropdown.value = e.target.value;
                item.appendChild(hoverBox);
            });

            hoverBox.appendChild(dropdown);
            hoverBox.style.position = "absolute";
            hoverBox.style.top = "5px";
            hoverBox.style.right = "0";
            hoverBox.style.zIndex = "1";
            hoverBox.style.background = "white";
            hoverBox.setAttribute('name', 'editBox');

            item.style.position = "relative";
            item.appendChild(hoverBox);
        });

        let editableElements = document.getElementsByClassName('editable');
        for (let i = 0; i < editableElements.length; i++) {
            if (editableElements[i].hasAttribute('contenteditable')) {
            } else {
                // Add the attribute if it doesn't exist
                editableElements[i].setAttribute('contenteditable', 'true');
            }
        }
    },

    buildLayoutEdit: function () {
        let storyHeader = document.getElementById('app-content');
        let hoverBox = document.createElement('div');
        let dropdown = document.createElement('select');

        let option = document.createElement('option');
        option.value = 0;
        option.text = 'Layout';
        option.selected = true;
        dropdown.appendChild(option);

        // Populate dropdown with given numbers
        OCA.Analytics.Story.layouts.forEach((layout) => {
            const option = document.createElement('option');
            option.value = layout.id;
            option.text = layout.name;
            dropdown.appendChild(option);
        });

        dropdown.addEventListener('change', (e) => {
            let layout = OCA.Analytics.Story.layouts.find(x => parseInt(x.id) === parseInt(e.target.value));
            let page = OCA.Analytics.Story.currentPage;
            let story = OCA.Analytics.Story.currentStory.pages[page];
            story.layout = layout.layout;
            OCA.Analytics.Story.getStory(page);
            OCA.Analytics.Story.addAllOverlays();
            OCA.Analytics.Story.addEditableTextBoxes();
        });

        hoverBox.appendChild(dropdown);
        hoverBox.style.position = "absolute";
        hoverBox.style.top = "20px";
        hoverBox.style.right = "50px";
        hoverBox.style.zIndex = "1";
        hoverBox.style.background = "white";
        hoverBox.setAttribute('name', 'editBox');

        storyHeader.style.position = "relative";
        storyHeader.appendChild(hoverBox);
    },

    newStory: function () {
        OCA.Analytics.UI.hideElement('analytics-intro');
        OCA.Analytics.UI.showElement('analytics-content');
        OCA.Analytics.Story.currentStory = [];
        OCA.Analytics.Story.addPage();
    },

    getStory: function (targetPage) {
        //go to first page
        if (targetPage) {
            OCA.Analytics.Story.navigatePage(targetPage);
        } else {
            OCA.Analytics.Story.navigatePage('start');
        }

        // add the layout structure for all pages
        document.getElementById('storyPages').innerHTML = '';

        OCA.Analytics.Story.currentStory.pages.forEach((page, pageIndex) => {
            let flexContainer = null;
            if (page.layout !== '') {
                // Parse the string to DOM
                let parser = new DOMParser();
                let layout = parser.parseFromString(page.layout, 'text/html');
                flexContainer = layout.querySelector('div');
            } else {
                flexContainer = document.createElement('div');
                flexContainer.classList.add('flex-container');
            }

            // assign the story id to the container
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

            // set the main header
            document.getElementById('storyHeader').innerText = OCA.Analytics.Story.currentStory.name;

            document.getElementById('storyPages').appendChild(flexContainer);
        });

        OCA.Analytics.Story.updateNavButtons();
        OCA.Analytics.Story.updatePageWidth();

        let items = document.getElementsByClassName('flex-item');
        items.forEach((item) => {
            OCA.Analytics.Story.buildWidget(item.id);
        });
        if (OCA.Analytics.Story.editMode) OCA.Analytics.Story.addAllOverlays();
        ;
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
        let reportIndex = itemId.split('-')[1];
        //let reportId = OCA.Analytics.Story.currentStory['reports'].split(',')[reportIndex];
        let page = OCA.Analytics.Story.currentStory.pages[pageId];
        let reportId = page.reports[reportIndex];

        if (reportId !== undefined && reportId !== '') {
            reportId = parseInt(reportId['value']);
            //let widget = document.querySelectorAll('div[data-chart]')[positionIndex];
            let widget = document.getElementById(itemId);
            widget.innerHTML = '';
            widget.setAttribute('data-chart', reportId);

            let chartWidget = OCA.Analytics.Story.buildChartWidget(reportId, itemId);
            widget.insertAdjacentHTML('beforeend', chartWidget);
            OCA.Analytics.Story.getData(reportId, itemId);
        }
    },

    getData: function (datasetId, itemId) {
        const url = OC.generateUrl('apps/analytics/data/' + datasetId, true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                let jsondata = JSON.parse(xhr.response);
                if (jsondata['status'] !== 'nodata' && jsondata['data'].length > 20) {
                    jsondata['data'].slice(jsondata['data'].length - 20); //just the last 20 records for the micro charts
                }
                OCA.Analytics.Story.setWidgetContent(jsondata, itemId);
            }
        };
        xhr.send();
    },

    setWidgetContent: function (jsondata, itemId) {
        let report = jsondata['options']['name'];
        let subheader = jsondata['options']['subheader'];
        let reportId = jsondata['options']['id'];
        let type = jsondata['options']['visualization'];

        //let widgetRow = OCA.Analytics.Story.buildWidgetRow(report, reportId, subheader, value, jsondata.thresholds);
        document.getElementById('analyticsWidgetReport' + itemId).innerText = report;
        //document.getElementById('analyticsWidgetSmall' + itemId).innerText = subheader;

        if (type !== 'table') {
            let canvasElement = document.getElementById(`myWidget${itemId}`);
            let divElement = document.createElement('canvas');
            divElement.id = `myWidget${itemId}`;
            canvasElement.parentNode.replaceChild(divElement, canvasElement);
            OCA.Analytics.Story.buildChart(jsondata, itemId);
        } else {
            let canvasElement = document.getElementById(`myWidget${itemId}`);
            let divElement = document.createElement('table');
            divElement.id = `myWidget${itemId}`;
            canvasElement.parentNode.replaceChild(divElement, canvasElement);
            OCA.Analytics.Story.buildDataTable(jsondata, itemId);
        }
    },

    buildChartWidget: function (reportId, itemId) {
        let href = OC.generateUrl('apps/analytics/#/r/' + reportId);

        //return `<canvas id="myWidget${reportId}" class="chartContainer"></canvas>`;

        return `<div style="height: 30px;">
                    <div id="analyticsWidgetReport${itemId}"></div>
                    <!--<div id="analyticsWidgetSmall${itemId}"></div>-->
                </div>
                <div style="position: relative; height: calc(100% - 60px);">
                        <div id="myWidget${itemId}"></div>
                </div>`;
    },

    navigatePage: function (direction) {
        let pagesContainer = document.getElementById('storyPages');
        let pageCount = pagesContainer.children.length;

        if (direction === 'next') {
            if (OCA.Analytics.Story.currentPage === pageCount - 1) {
                return; // No more pages to the right
            }
            OCA.Analytics.Story.currentPage++;
        } else if (direction === 'prev') {
            if (OCA.Analytics.Story.currentPage === 0) {
                return; // No more pages to the left
            }
            OCA.Analytics.Story.currentPage--;
        } else if (direction === 'start') {
            OCA.Analytics.Story.currentPage = 0;
        }

        const newMargin = OCA.Analytics.Story.currentPage * -100;
        pagesContainer.style.marginLeft = `${newMargin}%`;
        OCA.Analytics.Story.updateNavButtons();
    },

    updateNavButtons: function () {
        let pagesContainer = document.getElementById('storyPages');
        let pageCount = pagesContainer.children.length;

        if (OCA.Analytics.Story.currentPage === 0) {
            document.getElementById('prevBtn').classList.add('disabled');
        } else {
            document.getElementById('prevBtn').classList.remove('disabled');
        }

        if (OCA.Analytics.Story.currentPage === pageCount - 1) {
            document.getElementById('nextBtn').classList.add('disabled');
        } else {
            document.getElementById('nextBtn').classList.remove('disabled');
        }
    },

    addPage: function () {
        OCA.Analytics.Story.currentStory.pages.push({page: 0, name: 'New', reports: '', layout: ''});
        OCA.Analytics.Story.getStory('next');
        OCA.Analytics.Story.updateNavButtons();
    },

    updatePageWidth: function () {
        let pagesContainer = document.getElementById('storyPages');
        let pageCount = pagesContainer.children.length;
        pagesContainer.style.width = `${pageCount * 100}%`;
    },

    nFormatter: function (num) {
        if (num >= 1000000000) {
            return (num / 1000000000).toFixed(1).replace(/\.0$/, '') + 'G';
        }
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return num;
    },

    validateThreshold: function (kpi, value, thresholds) {
        const operators = {
            '=': function (a, b) {
                return a === b
            },
            '<': function (a, b) {
                return a < b
            },
            '>': function (a, b) {
                return a > b
            },
            '<=': function (a, b) {
                return a <= b
            },
            '>=': function (a, b) {
                return a >= b
            },
            '!=': function (a, b) {
                return a !== b
            },
        };
        let thresholdColor;

        thresholds = thresholds.filter(p => p.dimension1 === kpi || p.dimension1 === '*');

        for (let threshold of thresholds) {
            const comparison = operators[threshold['option']](parseFloat(value), parseFloat(threshold['value']));
            threshold['severity'] = parseInt(threshold['severity']);
            if (comparison === true) {
                if (threshold['severity'] === 2) {
                    thresholdColor = 'style="color: red;"';
                } else if (threshold['severity'] === 3) {
                    thresholdColor = 'style="color: orange;"';
                } else if (threshold['severity'] === 4) {
                    thresholdColor = 'style="color: green;"';
                }
            }
        }

        return thresholdColor;
    },

    buildChart: function (jsondata, positionIndex) {

        let ctx = document.getElementById('myWidget' + positionIndex).getContext('2d');

        // store the full chart type for deriving the stacked attribute later
        // the general chart type is used for the chart from here on
        let chartTypeFull;
        jsondata.options.chart === '' ? chartTypeFull = 'column' : chartTypeFull = jsondata.options.chart;
        let chartType = chartTypeFull.replace(/St100$/, '').replace(/St$/, '');

        // get the default settings for a chart
        let chartOptions = OCA.Analytics.Story.getDefaultChartOptions();
        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.line.tension = 0.1;
        Chart.defaults.elements.point.radius = 0;
        Chart.defaults.plugins.tooltip.enabled = true;
        Chart.defaults.plugins.legend.display = false;

        // convert the data array
        let [xAxisCategories, datasets] = OCA.Analytics.Story.convertDataToChartJsFormat(jsondata.data, chartType);

        // do the color magic
        // a predefined color array is used
        let colors = ["#aec7e8", "#ffbb78", "#98df8a", "#ff9896", "#c5b0d5", "#c49c94", "#f7b6d2", "#c7c7c7", "#dbdb8d", "#9edae5"];
        for (let i = 0; i < datasets.length; ++i) {
            let j = i - (Math.floor(i / colors.length) * colors.length)

            // in only one dataset is being shown, create a fancy gradient fill
            if (datasets.length === 1 && chartType !== 'column' && chartType !== 'doughnut') {
                const hexToRgb = colors[j].replace(/^#?([a-f\d])([a-f\d])([a-f\d])$/i
                    , (m, r, g, b) => '#' + r + r + g + g + b + b)
                    .substring(1).match(/.{2}/g)
                    .map(x => parseInt(x, 16));

                datasets[0].backgroundColor = function (context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    let gradient = ctx.createLinearGradient(0, 0, 0, chart.height);
                    gradient.addColorStop(0, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ')');
                    gradient.addColorStop(1, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ',0)');
                    return gradient;
                }
                datasets[i].borderColor = colors[j];
                Chart.defaults.elements.line.fill = true;
            } else if (chartType === 'doughnut') {
                // special array handling for doughnuts
                if (jsondata.options.dataoptions !== null) {
                    const arr = JSON.parse(jsondata.options.dataoptions);
                    let index = 0;
                    for (const obj of arr) {
                        if (obj.backgroundColor) {
                            colors[index] = obj.backgroundColor;
                        }
                        index++;
                    }
                }
                datasets[i].backgroundColor = datasets[i].borderColor = colors;
                Chart.defaults.elements.line.fill = false;
            } else {
                datasets[i].backgroundColor = colors[j];
                Chart.defaults.elements.line.fill = false;
                datasets[i].borderColor = colors[j];
            }
        }

        // derive the stacked or the stacked-100 option and adjust the data and options
        let stacked = chartTypeFull.endsWith('St') || chartTypeFull.endsWith('St100');
        let stacked100 = chartTypeFull.endsWith('St100');
        if (stacked === true) {
            chartOptions.scales['primary'].stacked = chartOptions.scales['xAxes'].stacked = true;
            chartOptions.scales['primary'].max = 100;
        }
        if (stacked100 === true) {
            datasets = OCA.Analytics.UI.calculateStacked100(datasets);
        }

        // overwrite some default chart options depending on the chart type
        if (chartType === 'datetime') {
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
            chartOptions.scales['primary'].stacked = true;
            chartOptions.scales['xAxes'].stacked = false; // area does not work otherwise
            Chart.defaults.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales['xAxes'].display = false;
            chartOptions.scales['primary'].display = chartOptions.scales['primary'].grid.display = false;
            chartOptions.scales['secondary'].display = chartOptions.scales['secondary'].grid.display = false;
            chartOptions.circumference = 180;
            chartOptions.rotation = -90;
            datasets[0]['borderWidth'] = 0;
        }

        // the user can add/overwrite chart options
        // the user can put the options in array-format into the report definition
        // these are merged with the standard report settings
        // e.g. the display unit for the x-axis can be overwritten '{"scales": {"xAxes": [{"time": {"unit" : "month"}}]}}'
        // e.g. add a secondary y-axis '{"scales": {"yAxes": [{},{"id":"B","position":"right"}]}}'
        let userChartOptions = jsondata.options.chartoptions;
        if (userChartOptions !== '' && userChartOptions !== null) {
            chartOptions = cloner.deep.merge(chartOptions, JSON.parse(userChartOptions));
        }

        // never show any axis in the dashboard
        chartOptions.scales['secondary'].display = false;
        chartOptions.scales['primary'].display = true;

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        //let userDatasetOptions = document.getElementById('userDatasetOptions').value;
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null && chartType !== 'doughnut') {
            let numberOfDatasets = datasets.length;
            let userDatasetOptionsCleaned = JSON.parse(userDatasetOptions);
            userDatasetOptionsCleaned.length = numberOfDatasets; // cut saved definitions if report now has less data sets
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, userDatasetOptionsCleaned);
            datasets = Object.values(datasets);
        }

        let myChart = new Chart(ctx, {
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });
    },

    buildDataTable: function (jsondata, positionIndex) {
        //OCA.Analytics.UI.showElement('tableContainer');
        //OCA.Analytics.UI.showElement('tableMenuBar');

        let columns = [];
        let data, unit = '';

        let header = jsondata.header;
        let allDimensions = jsondata.dimensions;
        (jsondata.dimensions) ? allDimensions = jsondata.dimensions : allDimensions = jsondata.header;
        let headerKeys = Object.keys(header);
        for (let i = 0; i < headerKeys.length; i++) {
            columns[i] = {'title': (header[headerKeys[i]] !== null) ? header[headerKeys[i]] : ""};
            let columnType = Object.keys(allDimensions).find(key => allDimensions[key] === header[headerKeys[i]]);

            if (i === headerKeys.length - 1) {
                // this is the last column

                // prepare for later unit cloumn
                //columns[i]['render'] = function(data, type, row, meta) {
                //    return data + ' ' + row[row.length-2];
                //};
                if (header[headerKeys[i]] !== null && header[headerKeys[i]].length === 1) {
                    unit = header[headerKeys[i]] + ' ';
                }
                //columns[i]['render'] = DataTable.render.number(null, null, 2, unit + ' ');
                columns[i]['render'] = function (data, type, row, meta) {
                    // If display or filter data is requested, format the number
                    if (type === 'display' || type === 'filter') {
                        return unit + parseFloat(data).toLocaleString();
                    }
                    // Otherwise the data type requested (`type`) is type detection or
                    // sorting data, for which we want to use the integer, so just return
                    // that, unaltered
                    return data;
                }
                columns[i]['className'] = 'dt-right';
            } else if (columnType === 'timestamp') {
                columns[i]['render'] = function (data, type) {
                    // If display or filter data is requested, format the date
                    if (type === 'display' || type === 'filter') {
                        return new Date(data * 1000).toLocaleString();
                    }
                    // Otherwise the data type requested (`type`) is type detection or
                    // sorting data, for which we want to use the integer, so just return
                    // that, unaltered
                    return data;
                }
            } else if (columnType === 'unit') {
                columns[i]['visible'] = false;
                columns[i]['searchable'] = false;
            }
        }
        data = jsondata.data;

        const language = {
            // TRANSLATORS Noun
            search: t('analytics', 'Search'),
            lengthMenu: t('analytics', 'Show _MENU_ entries'),
            info: t('analytics', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            infoEmpty: t('analytics', 'Showing 0 to 0 of 0 entries'),
            paginate: {
                // TRANSLATORS pagination description non-capital
                first: t('analytics', 'first'),
                // TRANSLATORS pagination description non-capital
                previous: t('analytics', 'previous'),
                // TRANSLATORS pagination description non-capital
                next: t('analytics', 'next'),
                // TRANSLATORS pagination description non-capital
                last: t('analytics', 'last')
            },
        };

        // restore saved table state
        let tableOptions = JSON.parse(jsondata.options.tableoptions)
            ? JSON.parse(jsondata.options.tableoptions)
            : {};
        let defaultOrder = [];
        let defaultLength = 10;

        OCA.Analytics.tableObject = new DataTable(document.getElementById('myWidget' + positionIndex), {
            //dom: 'tipl',
            order: tableOptions.order || defaultOrder,
            pageLength: tableOptions.length || defaultLength,
            scrollX: true,
            autoWidth: false,
            fixedColumns: true,
            data: data,
            columns: columns,
            language: language,
            rowCallback: function (row, data, index) {
                //OCA.Analytics.UI.dataTableRowCallback(row, data, index, jsondata.thresholds)
            },
            initComplete: function () {
                let info = this.closest('.dataTables_wrapper').find('.dataTables_info');
                info.toggle(this.api().page.info().pages > 1);
                let length = this.closest('.dataTables_wrapper').find('.dataTables_length');
                length.toggle(this.api().page.info().pages > 1);
                let filter = this.closest('.dataTables_wrapper').find('.dataTables_filter');
                filter.toggle(this.api().page.info().pages > 1);
            },
            drawCallback: function () {
                let pagination = this.closest('.dataTables_wrapper').find('.dataTables_paginate');
                pagination.toggle(this.api().page.info().pages > 1);

            }
        });

        // Listener for when the pagination length is changed
        OCA.Analytics.tableObject.on('length.dt', function () {
            OCA.Analytics.unsavedFilters === true;
            document.getElementById('saveIcon').style.removeProperty('display');
        });

        // Listener for when the table is ordered
        OCA.Analytics.tableObject.on('order.dt', function () {
            OCA.Analytics.unsavedFilters === true;
            document.getElementById('saveIcon').style.removeProperty('display');
        });
    },

    getDefaultChartOptions: function () {
        return {
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
            legend: {
                display: false,
            },
            animation: {
                duration: 0 // general animation time
            },
            plugins: {
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

    convertDataToChartJsFormat: function (data, chartType) {
        const labelMap = new Map();
        let datasetCounter = 0;
        let datasets = [], xAxisCategories = [];
        data.forEach((row) => {
            // default expected columns
            let dataSeriesColumn, characteristicColumn, value;

            // when only 2 columns are provided, no label will be set
            if (row.length >= 3) {
                [dataSeriesColumn, characteristicColumn, value] = row.slice(-3);
            } else if (row.length === 2) {
                [characteristicColumn, value] = row;
                dataSeriesColumn = '';
            }

            // Add category labels only once and not for every data series
            if (!xAxisCategories.includes(characteristicColumn)) {
                xAxisCategories.push(characteristicColumn);
            }

            // create the data series
            if (!labelMap.has(dataSeriesColumn)) {
                labelMap.set(dataSeriesColumn, {
                    ...(chartType !== 'doughnut' && {label: dataSeriesColumn || undefined}), // no label for doughnut charts
                    data: [],
                    hidden: datasetCounter >= 4 // default hide > 4th series for better visibility
                });
                datasetCounter++;
            }

            const dataset = labelMap.get(dataSeriesColumn);
            if (chartType === 'doughnut') {
                dataset.data.push(parseFloat(value));
            } else {
                dataset.data.push({x: characteristicColumn, y: parseFloat(value)});
            }
        });

        if (chartType === 'doughnut') {
            datasets = [{data: Array.from(labelMap.values()).flatMap(d => d.data)}];
        } else {
            datasets = Array.from(labelMap.values());
        }
        return [xAxisCategories, datasets];
    },

    handleNavigationClicked: function (evt) {
        let reportId = evt.target.closest('a').parentElement.id.replace('analyticsWidgetItem', '');
        if (document.querySelector('#navigationDatasets [data-id="' + reportId + '"]') !== null) {
            document.querySelector('#navigationDatasets [data-id="' + reportId + '"]').click();
        }
    },
}

/**
 * @namespace OCA.Analytics.Story.Backend
 */
OCA.Analytics.Story.Backend = {
    getReports: function () {
        let requestUrl = OC.generateUrl('apps/analytics/report');
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.reports = data;

                // Populate report selection menu with given numbers
                let reportSelectorContainer = document.getElementById('reportSelectorContainer');
                OCA.Analytics.reports.forEach((report) => {
                    const reportItem = document.createElement('div');
                    reportItem.className = 'report-item'; // You can add CSS classes for styling here.
                    reportItem.textContent = report.name;
                    reportItem.setAttribute('reportId', report.id);

                    reportItem.addEventListener('click', (e) => {
                        let reportId = parseInt(e.target.getAttribute('reportId'));

                        let itemId = document.getElementById('modal1').dataset.itemId;
                        let pageId = itemId.split('-')[0];
                        let reportIndex = itemId.split('-')[1];
                        let targetItem = document.getElementById(itemId);

                        targetItem.setAttribute('data-chart', reportId);
                        let page = OCA.Analytics.Story.currentStory.pages[pageId];
                        //let reportsArr = page.reports.split(',');
                        let reportsArr = page.reports;
                        reportsArr[reportIndex] = {'type':'r', 'value': reportId};
                        //page.reports = reportsArr.join(',');
                        OCA.Analytics.Story.buildWidget(itemId);
                        OCA.Analytics.Story.createOverlay(targetItem, true);

                        document.getElementById('modal1').style.display = 'none';
                    });

                    reportSelectorContainer.appendChild(reportItem);
                });
            });
    },

    create: function (page, parent, layout) {
        let requestUrl = OC.generateUrl('apps/analytics/story');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: 'New story header',
                type: 0,
                parent: parent,
                pages: '',
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Story.currentStory.push({
                    id: storyId,
                    name: 'New story header',
                    reports: '',
                    parent: 1,
                    page: page,
                    layout: '<div class="flex-container"></div>'
                });
                OCA.Analytics.Wizard.close();
                OCA.Analytics.Navigation.init();
            });
    },

    update: function () {

        let requestUrl = OC.generateUrl('apps/analytics/story/') + OCA.Analytics.Story.currentStory.id;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify(OCA.Analytics.Story.currentStory)
        })
            .then(response => response.json())
            .then(data => {
                //
            });
    }
}

OCA.Analytics.UI = {
    hideElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = true;
            //document.getElementById(element).style.display = 'none';
        }
    },

    showElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = false;
            //document.getElementById(element).style.removeProperty('display');
        }
    },
}