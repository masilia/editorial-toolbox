import { setConfig } from './config';
import { mountIsland } from './mount';

import '../scss/_admin-toolbar.scss';
import '../scss/_toolbox-sidebar.scss';

const mountPoint = document.querySelector('.js-island-admin-toolbar');

if (mountPoint) {
    try {
        setConfig(JSON.parse(mountPoint.dataset.config || '{}'));
    } catch {
        setConfig({});
    }

    mountIsland('.js-island-admin-toolbar', () => import('./components/AdminToolbar.jsx'));
}
