import { createRouter, createWebHistory } from "vue-router";
import LoaderView from './views/LoaderView.vue';
import ProjectView from './views/ProjectView.vue';
import RulesetsView from './views/RulesetsView.vue';
import SettingsView from './views/SettingsView.vue';
import LoadersIndex from './views/LoadersIndex.vue';
import NotFoundView from './views/NotFoundView.vue';
import ProjectsIndex from './views/ProjectsIndex.vue';
import GameVersionsView from './views/GameVersionsView.vue';
import ProjectVersionsView from './views/Project/ProjectVersionsView.vue';
import ProjectDescriptionView from './views/Project/ProjectDescriptionView.vue';
import ProjectDependenciesView from './views/Project/ProjectDependenciesView.vue';
import {useRouterStore} from "./stores/router";

function makeRoutesForBase(base) {
    const paths = [
        // Archive base can browse projects without specifying source
        base === 'archive' ? `/${base}/projects/:id` : '',
        `/${base}/:source/projects/:id`
    ].filter(i => i);

    return [
        { path: `/${base}/:source?`, name: `${base}`, component: ProjectsIndex },
        ...paths.map(path => {
            return {
                path,
                component: ProjectView,
                children: [
                    { path: '', name: `${base}.project`, component: ProjectDescriptionView },
                    { path: 'versions', name: `${base}.project.versions`, component: ProjectVersionsView },
                    { path: 'dependencies', name: `${base}.project.dependencies`, component: ProjectDependenciesView, props: { mode: 'dependencies' } },
                    { path: 'dependants', name: `${base}.project.dependants`, component: ProjectDependenciesView, props: { mode: 'dependants' } },
                ]
            }
        })
    ];
}

const router = createRouter({
    linkActiveClass: 'active',
    history: createWebHistory('/'),
    routes: [
        {
            path: '/',
            name: 'home',
            redirect: { name: 'browse' }
        },

        {
            path: '/settings',
            name: 'settings',
            component: SettingsView
        },

        {
            path: '/loaders',
            name: 'loaders',
            component: LoadersIndex,
        },
        {
            path: '/loaders/:slug',
            name: 'loader',
            component: LoaderView,
        },

        {
            path: '/game-versions',
            name: 'game-versions',
            component: GameVersionsView,
        },

        {
            path: '/rulesets',
            name: 'rulesets',
            component: RulesetsView,
        },

        ...makeRoutesForBase('archive'),
        ...makeRoutesForBase('browse'),

        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: NotFoundView
        },
    ],
    async scrollBehavior(to, from, savedPosition) {
        const routerStore = useRouterStore();
        await routerStore.transitionState;

        if (savedPosition) {
            savedPosition.behavior = 'instant';
            return savedPosition;
        }
        else if (from.path === to.path) {
            // prevent scrolling when updating query params
            return {};
        }
        else return { top: 0, behavior: 'instant' };
    }
});

router.beforeEach(() => {
    const routerStore = useRouterStore();
    routerStore.resetError();
});

router.afterEach((to, from) => {
    const toDepth = to.path.split('/').length;
    const fromDepth = from.path.split('/').length;
    to.meta.transition = toDepth < fromDepth ? 'slide-right' : 'slide-left';
});

export default router;
