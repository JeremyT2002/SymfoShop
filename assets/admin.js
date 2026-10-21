/*
 * Admin entry point (rendered via {{ importmap('admin') }}).
 */
import './styles/app.css';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import { init as initColorScheme } from './js/components/color-scheme.js';

Alpine.plugin(focus);
Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

initColorScheme();

const pageModules = {
    dashboard: () => import('./js/admin/dashboard-charts.js'),
    'theme-editor': () => import('./js/admin/theme-editor.js'),
};

const page = document.body.dataset.page;
if (page && pageModules[page]) {
    pageModules[page]().then((module) => module.init?.());
}
