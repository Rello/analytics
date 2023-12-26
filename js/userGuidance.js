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

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}
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
        let wizard = document.importNode(document.getElementById('wizardDialog').content, true);
        document.body.appendChild(wizard);
        document.getElementById('wizardNext').addEventListener('click', OCA.Analytics.Wizard.next);
        document.getElementById('wizardPrevious').addEventListener('click', OCA.Analytics.Wizard.previous);
        document.getElementById('wizardClose').addEventListener('click', OCA.Analytics.Wizard.cancel);

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
            if (i===1) dot.classList.add('active');
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
        document.getElementById('analyticsWizard').remove();
        if (!OCA.Analytics.isAdvanced) document.getElementById('overviewButton').click();
    },

    cancel: function () {
        document.getElementById('analyticsWizard').remove();
    },

    demo: function () {
        let thresholds = '{"report":{"name":"' + t('analytics', 'Demo: Thresholds') + '","type":2,"link":"{}","visualization":"table","chart":"","dimension1":"' + t('analytics', 'Type') + '","dimension2":"' + t('analytics', 'Thresholds') + '","dimension3":"","subheader":"' + t('analytics', 'Thresholds can be used to highlight data. Using the notification threshold, push messages are being sent.') + '","chartoptions":"","dataoptions":"","filteroptions":"{}","value":"' + t('analytics', 'Value') + '"},"dataload":[],"threshold":[{"id":94,"dimension1":"' + t('analytics', 'Threshold') + '","dimension2":null,"value":"1.00","option":"=","severity":4},{"id":95,"dimension1":"' + t('analytics', 'Threshold') + '","dimension2":null,"value":"2.00","option":"=","severity":3},{"id":97,"dimension1":"' + t('analytics', 'Threshold') + '","dimension2":null,"value":"4.00","option":"=","severity":1},{"id":98,"dimension1":"' + t('analytics', 'Threshold') + '","dimension2":null,"value":"3.00","option":">=","severity":2}],"favorite":"","data":[["' + t('analytics', 'Normal value') + '","' + t('analytics', 'none') + '","3.00"],["' + t('analytics', 'Threshold') + '","' + t('analytics', 'Green') + '","1.00"],["' + t('analytics', 'Threshold') + '","' + t('analytics', 'none') + '","0.00"],["' + t('analytics', 'Threshold') + '","' + t('analytics', 'Notification') + '","4.00"],["' + t('analytics', 'Threshold') + '","' + t('analytics', 'Red') + '","3.00"],["' + t('analytics', 'Threshold') + '","' + t('analytics', 'Yellow') + '","2.00"]],"favorite":"true"}';
        OCA.Analytics.Sidebar.Report.import(null, thresholds);

        let population = '{"report":{"name":"' + t('analytics', 'Demo: Population Data') + '","type":4,"link":"{\\"link\\":\\"https:\\/\\/raw.githubusercontent.com\\/Rello\\/analytics\\/master\\/sample_data\\/sample1.csv\\",\\"columns\\":\\"\\",\\"offset\\":\\"\\"}","visualization":"ct","chart":"line","dimension1":"","dimension2":"","dimension3":"","subheader":"' + t('analytics', 'Real-time CSV data from GitHub via the *External URL* data source.') + '","chartoptions":"","dataoptions":"","filteroptions":null,"value":""},"dataload":[],"threshold":[],"favorite":"true","data":[]}';
        OCA.Analytics.Sidebar.Report.import(null, population);

        let github = '{"report":{"name":"' + t('analytics', 'Demo: Analytics Downloads') + '","type":3,"link":"{\\"user\\":\\"rello\\",\\"repository\\":\\"analytics\\",\\"limit\\":\\"10\\",\\"timestamp\\":\\"false\\"}","visualization":"ct","chart":"column","dimension1":"' + t('analytics', 'Object') + '","dimension2":"' + t('analytics', 'Date') + '","dimension3":"","subheader":"' + t('analytics', 'Real-time download statistics from GitHub.') + '","chartoptions":"","dataoptions":"","filteroptions":null,"value":"' + t('analytics', 'Value') + '"},"dataload":[],"threshold":[{"id":33,"dimension1":"*","dimension2":null,"value":"5000.00","option":">","severity":4}],"favorite":"true","data":[]}';
        OCA.Analytics.Sidebar.Report.import(null, github);

        let finance = '{"report":{"name":"' + t('analytics', 'Demo: Finance') + '","type":2,"link":"{}","visualization":"ct","chart":"column","dimension1":"' + t('analytics', 'Segment') + '","dimension2":"' + t('analytics', 'Year') + '","dimension3":"","subheader":"' + t('analytics', 'Sales and revenue of the last years.') + '","chartoptions":"","dataoptions":"[{\\"yAxisID\\":\\"primary\\",\\"type\\":\\"bar\\"},{\\"yAxisID\\":\\"primary\\",\\"type\\":\\"bar\\"},{\\"yAxisID\\":\\"primary\\",\\"type\\":\\"bar\\"},{\\"yAxisID\\":\\"primary\\",\\"type\\":\\"line\\"}]","filteroptions":"{\\"filter\\":{\\"dimension2\\":{\\"option\\":\\"GT\\",\\"value\\":\\"2011\\"}}}","value":"\u20ac"},"dataload":[],"threshold":[],"favorite":"","data":[["Channel Partners","2011","30216.00"],["Channel Partners","2012","18540.00"],["Channel Partners","2013","30216.00"],["Channel Partners","2014","4404.00"],["Channel Partners","2015","10944.00"],["Channel Partners","2016","25932.00"],["Channel Partners","2017","18540.00"],["Channel Partners","2018","34056.00"],["Channel Partners","2019","23436.00"],["Channel Partners","2020","25692.00"],["Enterprise","2011","33318.00"],["Enterprise","2012","43125.00"],["Enterprise","2013","25500.00"],["Enterprise","2014","52625.00"],["Enterprise","2015","43125.00"],["Enterprise","2016","52743.00"],["Enterprise","2017","32370.00"],["Enterprise","2018","26420.00"],["Enterprise","2019","52950.00"],["Enterprise","2020","37980.00"],["Government","2011","15022.00"],["Government","2012","5840.00"],["Government","2013","35200.00"],["Government","2014","6181.00"],["Government","2015","8001.00"],["Government","2016","60350.00"],["Government","2017","36340.00"],["Government","2018","59550.00"],["Government","2019","10451.00"],["Government","2020","32100.00"],["Total Sales","2011","78556.00"],["Total Sales","2012","67505.00"],["Total Sales","2013","90916.00"],["Total Sales","2014","63210.00"],["Total Sales","2015","62070.00"],["Total Sales","2016","139025.00"],["Total Sales","2017","87250.00"],["Total Sales","2018","120026.00"],["Total Sales","2019","86837.00"],["Total Sales","2020","95772.00"]]}';
        OCA.Analytics.Sidebar.Report.import(null, finance);

        OCA.Analytics.Wizard.close();
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
    },

    wizardFinal: function () {
        document.getElementById('wizardEnd').addEventListener('click', OCA.Analytics.Wizard.close);
        document.getElementById('wizardDemo').addEventListener('click', OCA.Analytics.Wizard.demo);
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/wizard'),
        })
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
        $('.whatsNewPopover').remove();
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
            text.innerHTML = _.escape(whatsNewTextItem)
            menuItem.appendChild(text)

            item.appendChild(menuItem)
            list.appendChild(item)
        }

        // Changelog URL
        if (!_.isUndefined(data['changelogURL'])) {
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

    info: function (header, text, guidance) {
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" class="analyticsDialog">'
            + '<a class="analyticsDialogClose" id="analyticsDialogBtnClose"></a>'
            + '<h2 class="analyticsDialogHeader" id="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + header
            + '</h2>'
            + '<span id="analyticsDialogGuidance" class="userGuidance"></span><br><br>'
            + '<div id="analyticsDialogContent">'
            + '</div>'
            + '<br><div class="analyticsDialogButtonrow">'
            + '<a class="button primary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</a>'
            + '</div></div>'
        );
        document.getElementById('analyticsDialogGuidance').innerHTML = guidance;
        document.getElementById('analyticsDialogContent').innerHTML = text;
        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnGo").addEventListener("click", OCA.Analytics.Notification.dialogClose);
    },

    confirm: function (header, text, callback) {
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" class="analyticsDialog">'
            + '<a class="analyticsDialogClose" id="analyticsDialogBtnClose"></a>'
            + '<h2 class="analyticsDialogHeader" id="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + header
            + '</h2>'
            + '<div id="analyticsDialogContent">'
            + '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>'
            + '</div>'
            + '<br><div class="analyticsDialogButtonrow">'
            + '<a class="button primary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</a>'
            + '<a class="button" id="analyticsDialogBtnCancel">' + t('analytics', 'Cancel') + '</a>'
            + '</div></div>'
        );
        document.getElementById('analyticsDialogContent').innerHTML = text;
        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnCancel").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnGo").addEventListener("click", callback);
    },

    /**
     * Function to display notifications.
     * @param {('info'|'success'|'error')} type - The type of the notification.
     * @param {string} message - The notification message.
     */
    notification: function (type, message) {
        if (parseInt(OC.config.versionstring.substr(0, 2)) >= 17) {
            if (type === 'success') {
                OCP.Toast.success(message)
            } else if (type === 'error') {
                OCP.Toast.error(message)
            } else {
                OCP.Toast.info(message)
            }
        } else {
            OC.Notification.showTemporary(message);
        }
    },

    /**
     * @param {string} header Popup header as text
     * @param callback Callback function of the OK button
     */
    htmlDialogInitiate: function (header, callback) {
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analyticsDialogOverlay" class="analyticsDialogDim"></div>'
            + '<div id="analyticsDialogContainer" class="analyticsDialog">'
            + '<a class="analyticsDialogClose" id="analyticsDialogBtnClose"></a>'
            + '<h2 class="analyticsDialogHeader" id="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + header
            + '</h2>'
            + '<span id="analyticsDialogGuidance" class="userGuidance"></span><br><br>'
            + '<div id="analyticsDialogContent">'
            + '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>'
            + '</div>'
            + '<br><div class="analyticsDialogButtonrow">'
            + '<a class="button primary" id="analyticsDialogBtnGo">' + t('analytics', 'OK') + '</a>'
            + '<a class="button" id="analyticsDialogBtnCancel">' + t('analytics', 'Cancel') + '</a>'
            + '</div></div>'
        );

        document.getElementById("analyticsDialogBtnClose").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnCancel").addEventListener("click", OCA.Analytics.Notification.dialogClose);
        document.getElementById("analyticsDialogBtnGo").addEventListener("click", callback);
    },

    htmlDialogUpdate: function (content, guidance) {
        document.getElementById('analyticsDialogContent').innerHTML = '';
        document.getElementById('analyticsDialogContent').appendChild(content);
        document.getElementById('analyticsDialogGuidance').innerHTML = guidance;
    },

    htmlDialogUpdateAdd: function (guidance) {
        document.getElementById('analyticsDialogGuidance').innerHTML += '<br>' + guidance;
    },

    dialogClose: function () {
        document.getElementById('analyticsDialogContainer').remove();
        document.getElementById('analyticsDialogOverlay').remove();
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