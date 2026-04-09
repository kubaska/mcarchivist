import {useNotificationStore} from "../stores/notifications";

let notificationStore = null;

const getStore = () => {
    if (! notificationStore)
        notificationStore = useNotificationStore();

    return notificationStore;
}

export const showNotification = (type, title, description = '', timeout = 5) => {
    getStore().add(title, description, type, timeout);
};

export const showSuccessNotification = (title, description = '', timeout = 5) => {
    showNotification('success', title, description, timeout);
};

export const showWarningNotification = (title, description = '', timeout = 5) => {
    showNotification('warning', title, description, timeout);
};

export const showErrorNotification = (title, description = '', timeout = 5) => {
    showNotification('danger', title, description, timeout);
};
