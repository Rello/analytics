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
                        style: {
                            width: '100%'
                        },
                    }, [
                        createElement('option', {
                            domProps: {
                                value: '1',
                                innerText: 'Test Report'
                            },
                        })
                    ]
                ),
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