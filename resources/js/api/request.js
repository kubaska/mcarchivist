import axios from "axios";
import {useQueueStore} from "../stores/queue";

export const request = axios.create({
    baseURL: '/api/'
});

const isTask = element => element.original_id && element.uuid !== undefined;

// Automatically add jobs to queue store
request.interceptors.response.use(response => {
    if (! response.config.url.startsWith('/queue') && response.data?.data) {
        // Singular
        if (isTask(response.data.data)) {
            const queueStore = useQueueStore();
            queueStore.addJob(response.data.data);
        }

        // Collection
        if (response.data.data?.[0] && isTask(response.data.data[0])) {
            const queueStore = useQueueStore();
            response.data.data.forEach(el => {
                if (isTask(el)) queueStore.addJob(el);
            });
        }
    }

    return response;
});

export const abortableRequest = request.create();

const runningRequests = {};
abortableRequest.interceptors.request.use(config => {
    runningRequests[config.url]?.abort();
    runningRequests[config.url] = new AbortController();
    config.signal = runningRequests[config.url].signal;

    return config;
});
const abortableOnResponse = response => {
    if (axios.isCancel(response)) {
        return new Promise(() => {});
    }

    runningRequests[response.config.url] = null;

    return response;
};
abortableRequest.interceptors.response.use(abortableOnResponse, response => {
    return Promise.reject(abortableOnResponse(response));
});
