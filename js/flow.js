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
        data: function () {
            return {
                items: [],
                dataloads: []
            };
        },
        render: function (createElement) {
            let self = this;
            let items = [];
            let dataloadItems = [
                createElement('option', {
                    domProps: {
                        value: 0,
                        innerText: t('analytics', 'Import a csv directly; Do not use a data load.')
                    }
                })
            ];
            let operation = self.parseOperation(self.value);

            for (let navigation of self.items) {
                if (parseInt(navigation.type) === 2) {
                    items.push(createElement('option', {
                        domProps: {
                            value: navigation.id,
                            innerText: navigation.name
                        }
                    }))
                }
            }
            for (let dataload of self.dataloads) {
                dataloadItems.push(createElement('option', {
                    domProps: {
                        value: dataload.id,
                        innerText: dataload.name
                    }
                }))
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
                            value: operation.datasetId,
                            required: 'true'
                        },
                        style: {
                            width: '100%',
                            marginBottom: '6px'
                        },
                        on: {
                            input: function (event) {
                                let datasetId = parseInt(event.target.value);
                                self.fetchDataloads(datasetId);
                                self.emitOperation(datasetId, 0);
                            }
                        }
                    }, items
                ),
                createElement('select', {
                        attrs: {
                            id: 'dataload'
                        },
                        domProps: {
                            value: operation.dataloadId
                        },
                        style: {
                            width: '100%'
                        },
                        on: {
                            input: function (event) {
                                self.emitOperation(operation.datasetId, parseInt(event.target.value));
                            }
                        }
                    }, dataloadItems
                )
            ])
        },
        props: {
            value: ''
        },
        beforeMount() {
            let operation = this.parseOperation(this.value);
            this.fetchDatasets();
            if (operation.datasetId !== 0) {
                this.fetchDataloads(operation.datasetId);
            }
        },
        methods: {
            parseOperation(operation) {
                let parsedOperation = {
                    datasetId: 0,
                    dataloadId: 0
                };

                if (typeof operation === 'string' && operation.trim().charAt(0) === '{') {
                    try {
                        let jsonOperation = JSON.parse(operation);
                        parsedOperation.datasetId = parseInt(jsonOperation.datasetId) || 0;
                        parsedOperation.dataloadId = parseInt(jsonOperation.dataloadId) || 0;
                        return parsedOperation;
                    } catch (e) {
                    }
                }

                parsedOperation.datasetId = parseInt(operation) || 0;
                return parsedOperation;
            },
            emitOperation(datasetId, dataloadId) {
                this.$emit('input', JSON.stringify({
                    datasetId: parseInt(datasetId) || 0,
                    dataloadId: parseInt(dataloadId) || 0
                }));
            },
            fetchDatasets() {
                let requestUrl = OC.generateUrl('apps/analytics/dataset');
                let headers = new Headers();
                headers.append('requesttoken', OC.requestToken);
                headers.append('OCS-APIREQUEST', 'true');

                fetch(requestUrl, {
                    method: 'GET',
                    headers: headers
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Dataset request failed');
                        }
                        return response.json();
                    })
                    .then(data => {
                        this.items = Array.isArray(data) ? data : [];
                    })
                    .catch(() => {
                        this.items = [];
                    });
            },
            fetchDataloads(datasetId) {
                if (datasetId === 0) {
                    this.dataloads = [];
                    return;
                }

                let requestUrl = OC.generateUrl('apps/analytics/dataload') + '?' + new URLSearchParams({
                    datasetId: datasetId
                });
                let headers = new Headers();
                headers.append('requesttoken', OC.requestToken);
                headers.append('OCS-APIREQUEST', 'true');

                fetch(requestUrl, {
                    method: 'GET',
                    headers: headers
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Dataload request failed');
                        }
                        return response.json();
                    })
                    .then(data => {
                        this.dataloads = Array.isArray(data.dataloads) ? data.dataloads : [];
                    })
                    .catch(() => {
                        this.dataloads = [];
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
