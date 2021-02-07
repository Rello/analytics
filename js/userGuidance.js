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

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}
/**
 * @namespace OCA.Analytics.Wizzard
 */
OCA.Analytics.Wizzard = {
    sildeArray: [
        '',
        'wizzard-start',
        'wizzard-charts',
        'wizzard-filter',
        'wizzard-datasource',
        'wizzard-dataload',
        'wizzard-final'
    ],
    currentSlide: 0,

    show: function () {
        OCA.Analytics.Wizzard.currentSlide = 0;
        let wizzard = document.importNode(document.getElementById('wizzardDialog').content, true);
        document.getElementById('content').appendChild(wizzard);
        document.getElementById('wizzardNext').addEventListener('click', OCA.Analytics.Wizzard.next);
        document.getElementById('wizzardPrevious').addEventListener('click', OCA.Analytics.Wizzard.previous);
        OCA.Analytics.Wizzard.next();
    },

    next: function () {
        if (OCA.Analytics.Wizzard.currentSlide < OCA.Analytics.Wizzard.sildeArray.length - 1) {
            OCA.Analytics.Wizzard.currentSlide++;
            OCA.Analytics.Wizzard.showPage();
        }
    },

    previous: function () {
        if (OCA.Analytics.Wizzard.currentSlide !== 1) {
            OCA.Analytics.Wizzard.currentSlide--;
            OCA.Analytics.Wizzard.showPage();
        }
    },

    close: function () {
        OCA.Analytics.Wizzard.dismiss();
        document.getElementById('firstrunwizard').remove();
    },

    demo: function () {
        let notificationTest = '{"dataset":{"name":"Demo: Notification Test","type":2,"link":"{}","visualization":"table","chart":"","dimension1":"Object","dimension2":"Date","dimension3":"Value","subheader":"subhead","chartoptions":"","dataoptions":"","filteroptions":null,"value":"Value"},"dataload":[{"id":105,"user_id":"admin","dataset":103,"datasource":3,"name":"test github","option":"{\\"user\\":\\"a\\",\\"repository\\":\\"a\\",\\"limit\\":\\"1\\",\\"timestamp\\":\\"true\\",\\"delete\\":\\"false\\"}","schedule":""}],"threshold":[{"id":21,"dimension1":"a","dimension2":null,"value":"2.00","option":"=","severity":4},{"id":22,"dimension1":"a","dimension2":null,"value":"1.00","option":"!=","severity":1},{"id":32,"dimension1":"*","dimension2":null,"value":"6.00","option":"=","severity":2}],"data":[["a","a","3.00"],["a","b","2.00"],["a","c","2.00"],["b","b","6.00"],["red KPI","b","6.00"]]}';
        OCA.Analytics.Navigation.importDataset(null, notificationTest);

        let population = '{"dataset":{"name":"Demo: Population Data","type":4,"link":"{\\"link\\":\\"https:\\/\\/raw.githubusercontent.com\\/Rello\\/analytics\\/master\\/sample_data\\/sample1.csv\\",\\"columns\\":\\"\\",\\"offset\\":\\"\\"}","visualization":"ct","chart":"line","dimension1":"","dimension2":"","dimension3":null,"subheader":"Realtime CSV Data from GitHub via the \\"Extrernal URL\\" datasource","chartoptions":"","dataoptions":"","filteroptions":null,"value":""},"dataload":[],"threshold":[],"favorite":"true"}';
        OCA.Analytics.Navigation.importDataset(null, population);

        let github = '{"dataset":{"name":"Demo: Analytics Downloads","type":3,"link":"{\\"user\\":\\"rello\\",\\"repository\\":\\"analytics\\",\\"limit\\":\\"10\\",\\"timestamp\\":\\"false\\"}","visualization":"ct","chart":"column","dimension1":"Object","dimension2":"Date","dimension3":"Value","subheader":"Realtime download statistics form Github","chartoptions":"","dataoptions":"","filteroptions":null,"value":"Value"},"dataload":[{"id":59,"user_id":"admin","dataset":155,"datasource":6,"name":"New","option":"{}","schedule":null}],"threshold":[{"id":33,"dimension1":"*","dimension2":null,"value":"5000.00","option":">","severity":4}],"favorite":"true"}';
        OCA.Analytics.Navigation.importDataset(null, github);

    },

    showPage: function () {
        let nextSlide = OCA.Analytics.Wizzard.sildeArray[OCA.Analytics.Wizzard.currentSlide];
        let newpage = document.importNode(document.getElementById(nextSlide).content, true);
        let prev = document.getElementById('wizzardPrevious');
        let next = document.getElementById('wizzardNext');

        document.getElementById('pageBody').innerHTML = '';
        document.getElementById('pageBody').appendChild(newpage);

        if (OCA.Analytics.Wizzard.currentSlide === OCA.Analytics.Wizzard.sildeArray.length - 1) {
            document.getElementById('wizzardEnd').addEventListener('click', OCA.Analytics.Wizzard.close);
            document.getElementById('wizzardDemo').addEventListener('click', OCA.Analytics.Wizzard.demo);
        }

        OCA.Analytics.Wizzard.currentSlide === 1 ? prev.hidden = true : prev.hidden = false;
        OCA.Analytics.Wizzard.currentSlide === OCA.Analytics.Wizzard.sildeArray.length - 1 ? next.hidden = true : next.hidden = false;
    },

    dismiss: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/wizzard'),
        })
    },
}

/**
 * @namespace OCA.Analytics.Wizzard
 */
OCA.Analytics.WhatsNew = {

    whatsnew: function (options) {
        options = options || {}
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/whatsnew'),
            data: {'format': 'json'},
            success: options.success || function (data, statusText, xhr) {
                OCA.Analytics.WhatsNew.show(data, statusText, xhr);
            },
        });
    },

    dismiss: function (version) {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/whatsnew'),
            data: {version: encodeURIComponent(version)}
        })
        $('.whatsNewPopover').remove();
    },

    show: function (data, statusText, xhr) {
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
    }

}
