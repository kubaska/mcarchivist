import axios from "axios";
import {useQueueStore} from "../stores/queue";

const request = axios.create({
    baseURL: '/api/'
});

request.interceptors.response.use(response => {
    // Automatically add jobs to queue store
    if (response.data?.data?.original_id && response.data?.data?.uuid !== undefined) {
        const queueStore = useQueueStore();
        queueStore.addJob(response.data.data);
    }
    return response;
});

export default request;
