import {useRouterStore} from "../stores/router";

export function displayNotFoundPage(text) {
    const routerStore = useRouterStore();
    routerStore.displayError(404, text);
}
