import template from './sw-order-list.html.twig';

const { Component } = Shopware;

Component.override('sw-order-list', {
    template,

    methods: {
        getOrderColumns() {
            const columns = this.$super('getOrderColumns');

            columns.push({
                property: 'primaryOrderTransaction.paymentMethod.name',
                dataIndex: 'primaryOrderTransaction.paymentMethod.name',
                label: 'topdata-topseller-export-sw6.list.columnPaymentMethod',
                allowResize: true,
            });

            return columns;
        },
    },
});
