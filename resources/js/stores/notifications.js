import {defineStore} from "pinia";

let id = 0;

export const useNotificationStore = defineStore('notifications', {
    state: () => ({
        notifications: []
    }),
    actions: {
        add(title, description, type = 'success', timeout = 5) {
            this.notifications.push({
                id: id++, title, description, type, timeout
            });
        },

        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }
});
