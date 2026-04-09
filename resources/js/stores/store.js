import {defineStore} from "pinia";

export const useStore = defineStore('store', {
    state: () => ({
        project: null,
    }),
    actions: {
        setProject(project) {
            this.project = project;
        },
        resetActiveProject() {
            this.project = null;
        },
    }
});
