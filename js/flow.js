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
                $.ajax({
                    type: 'GET',
                    url: OC.generateUrl('apps/analytics/dataset'),
                    success: function (data) {
                        Component.items = data;
                    }
                });
            },
        },
    };

    window.OCA.WorkflowEngine.registerOperator({
        id: 'OCA\\Analytics\\Flow\\Operation',
        color: 'var(--color-primary-element-light)',
        operation: '',
        options: Component,
    });
})();