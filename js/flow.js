/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */
(function () {

    var Component = {
        name: 'WorkflowScript',
        items: [],
        render: function (createElement) {
            let self = this;
            let items = [];
            let selected;
            for (let navigation of Component.items) {
                if (parseInt(navigation.type) === 2) {
                    if (parseInt(self.value) === navigation.id) {
                        selected = 'selected';
                    } else {
                        selected = '';
                    }
                    items.push(createElement('option', {
                        domProps: {
                            value: navigation.id,
                            innerText: navigation.name,
                            selected
                        }
                    }))
                }
            }
            return createElement('div', {
                style: {
                    width: '100%'
                },
            }, [
                createElement('select', {
                        attrs: {
                            id: 'report'
                        },
                        domProps: {
                            value: self.value,
                            required: 'true'
                        },
                        style: {
                            width: '100%'
                        },
                        on: {
                            input: function (event) {
                                self.$emit('input', event.target.value)
                            }
                        }
                    }, items
                )
            ])
        },
        props: {
            value: ''
        },
        beforeMount() {
            this.fetchDatasets()
        },
        methods: {
            fetchDatasets() {
                let requestUrl = OC.generateUrl('apps/analytics/dataset');
                let headers = new Headers();
                headers.append('requesttoken', OC.requestToken);
                headers.append('OCS-APIREQUEST', 'true');

                fetch(requestUrl, {
                    method: 'GET',
                    headers: headers
                })
                    .then(response => response.json())
                    .then(data => {
                        Component.items = data;
                    });
            },
        },
    };

    window.OCA.WorkflowEngine.registerOperator({
        id: 'OCA\\Analytics\\Flow\\Operation',
        color: 'var(--color-primary-element)',
        operation: '',
        options: Component,
    });
})();