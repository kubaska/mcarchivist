import axios from "axios";
import {useQueueStore} from "../stores/queue";

const request = axios.create({
    baseURL: '/api/'
});

const isTask = element => element.original_id && element.uuid !== undefined;

request.interceptors.response.use(response => {
    // Automatically add jobs to queue store
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

export default request;
