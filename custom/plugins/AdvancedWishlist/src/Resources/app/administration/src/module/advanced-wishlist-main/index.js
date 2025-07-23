import './page/wishlist-list';
import './page/wishlist-detail';
import './page/wishlist-create';
import './component/wishlist-card';
import './component/item-manager';
import './component/share-manager';

const { Module } = Shopware;

Module.register('advanced-wishlist-main', {
    type: 'plugin',
    name: 'advanced-wishlist-main',
    title: 'advanced-wishlist-main.general.mainMenuItemGeneral',
    description: 'advanced-wishlist-main.general.descriptionTextModule', 
    color: '#ff3e67',
    icon: 'default-shopping-heart',

    routes: {
        overview: {
            component: 'advanced-wishlist-list',
            path: 'overview',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'advanced_wishlist.viewer'
            }
        },
        detail: {
            component: 'advanced-wishlist-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'advanced.wishlist.main.overview',
                privilege: 'advanced_wishlist.viewer'
            }
        },
        create: {
            component: 'advanced-wishlist-create',
            path: 'create',
            meta: {
                parentPath: 'advanced.wishlist.main.overview',
                privilege: 'advanced_wishlist.creator'
            }
        }
    },

    navigation: [{
        id: 'advanced-wishlist-main',
        label: 'advanced-wishlist-main.general.mainMenuItemGeneral',
        color: '#ff3e67',
        path: 'advanced.wishlist.main.overview',
        icon: 'default-shopping-heart',
        position: 110,
        parent: 'sw-marketing',
        privilege: 'advanced_wishlist.viewer'
    }],

    settingsItem: {
        group: 'plugins',
        to: 'advanced.wishlist.main.overview',
        icon: 'default-shopping-heart',
        privilege: 'advanced_wishlist.viewer'
    }
});