import './page/topdata-topseller-view';

Shopware.Module.register('topdata-topseller-view', {
    type: 'plugin',
    name: 'topdata-topseller-view.module.name',
    title: 'topdata-topseller-view.module.title',
    description: 'topdata-topseller-view.module.description',
    color: '#ff5722',
    icon: 'regular:star',

    routes: {
        list: {
            component: 'topdata-topseller-view',
            path: 'list',
        },
    },

    navigation: [{
        id: 'topdata-topseller-view',
        label: 'topdata-topseller-view.module.title',
        color: '#ff5722',
        icon: 'regular:star',
        path: 'topdata.topseller.view.list',
        parent: 'sw-order',
        position: 50,
    }],
});
