import {defineStore} from "pinia";

export const useRouterStore = defineStore('router', {
    state: () => ({
        transitionState: Promise.resolve(),
        transitionResolve: null,

        displayedError: null,
        errorText: null
    }),
    getters: {

    },
    actions: {
        transitionSetIn() {
            if (this.transitionResolve != null) {
                this.transitionResolve();
                this.transitionResolve = null
            }
        },
        transitionSetOut() {
            this.transitionState = new Promise(resolve => {
                this.transitionResolve = resolve
            });
        },

        displayError(error, text) {
            this.displayedError = error;
            this.errorText = text;
        },
        resetError() {
            this.displayedError = null;
            this.errorText = null;
        }
    }
});
