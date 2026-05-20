import { createApp } from 'vue';
import App from './App.vue';
import router from "./router";
import {createPinia} from "pinia";
import FloatingVue from 'floating-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { config, library } from '@fortawesome/fontawesome-svg-core';
import {
    faAngleDown, faArrowDownShortWide, faArrowDownWideShort, faArrowRightArrowLeft, faArrowRotateRight,
    faArrowUpRightFromSquare, faBoxArchive, faArrowsRotate, faCalendar, faClipboard, faCircleInfo, faCheck,
    faCircleCheck, faCode, faCog, faDisplay, faDownload, faDownLong, faEllipsisVertical, faFile, faFilter, faFolder,
    faFolderOpen, faList, faObjectGroup, faPencil, faServer, faStar, faTriangleExclamation, faUpLong, faXmark
} from '@fortawesome/free-solid-svg-icons';

config.styleDefault = 'fas';
library.add(
    faAngleDown, faArrowDownShortWide, faArrowDownWideShort, faArrowRightArrowLeft, faArrowRotateRight,
    faArrowUpRightFromSquare, faBoxArchive, faArrowsRotate, faCalendar, faClipboard, faCircleInfo, faCheck,
    faCircleCheck, faCode, faCog, faDisplay, faDownload, faDownLong, faEllipsisVertical, faFile, faFilter, faFolder,
    faFolderOpen, faList, faObjectGroup, faPencil, faServer, faStar, faTriangleExclamation, faUpLong, faXmark
);

import 'floating-vue/dist/style.css';

const pinia = createPinia();

const app = createApp(App)
    .use(router)
    .use(pinia)
    .use(FloatingVue)
    .component('fa-icon', FontAwesomeIcon)
    .mount('#app');
