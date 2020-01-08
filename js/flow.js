(function () {

    var Component = {
        name: 'WorkflowScript',
        render: function (createElement) {
            var self = this;
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
                    }, [
                        createElement('option', {
                            domProps: {
                                value: '1',
                                innerText: 'test'
                            },

                        })
                    ]
                )
            ])
        },
        props: {
            value: ''
        },
        data: function () {
            return {
                description: t('workflow_script', 'Available placeholder variables are listed in the documentation') + '↗',
                link: 'https://github.com/nextcloud/workflow_script#placeholders'
            }
        }
    };

    window.OCA.WorkflowEngine.registerOperator({
        id: 'OCA\\Analytics\\Flow\\Operation',
        color: 'var(--color-success)',
        operation: '',
        options: Component,
    });
})();