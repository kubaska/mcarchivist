import {useRoute} from "vue-router";

export const useMcaRoute = () => {
    const route = useRoute();

    const isArchive = () => route.fullPath.startsWith('/archive');
    const isBrowse = () => route.fullPath.startsWith('/browse');
    const getBase = () => isArchive() ? 'archive' : 'browse';
    const getRouteForBase = (route) => `${getBase()}.${route}`;
    const isArchiveOrBrowse = () => isArchive() || isBrowse();

    route.isArchive = isArchive;
    route.isBrowse = isBrowse;
    route.getBase = getBase;
    route.getRouteForBase = getRouteForBase;
    route.isArchiveOrBrowse = isArchiveOrBrowse;

    return route;
}
