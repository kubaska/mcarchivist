import {useQueueStore} from "../stores/queue";
import {onMounted, onUnmounted, ref, toValue, watch} from "vue";

let queueStore = null;
const getQueueStore = () => {
    if (queueStore) return queueStore;
    queueStore = useQueueStore();
    return queueStore;
}

export const useTaskStateSpy = (frontendId, onTaskSuccess, onActivate = null) => {
    const queueTask = ref(null);
    const unsubscribe = ref(null);
    const findTask = (state) => state.tasks.find(task => task.frontend_id === toValue(frontendId));

    const detach = () => {
        if (unsubscribe.value) unsubscribe.value();
        unsubscribe.value = null;
        queueTask.value = null;
    }
    const attach = () => {
        if (unsubscribe.value) return;

        const task = findTask(getQueueStore());
        if (task) queueTask.value = task;

        unsubscribe.value = getQueueStore().$subscribe((mutation, state) => {
            const task = findTask(state);

            if (task) {
                queueTask.value = task;
            } else {
                detach();
                onTaskSuccess();
            }
        })
    };
    const attachIfTaskIsRunning = () => {
        const task = findTask(getQueueStore());
        if (task) {
            queueTask.value = task;
            if (onActivate) onActivate();
            attach();
        }
    }

    onMounted(() => attachIfTaskIsRunning());
    onUnmounted(() => detach());
    watch(() => frontendId, () => {
        detach();
        attachIfTaskIsRunning();
    });

    return { queueTask, attachTaskStateSpy: attach };
}
