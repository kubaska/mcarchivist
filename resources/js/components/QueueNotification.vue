<template>
    <div class="queue-notification fs-7 border-bottom">
        <div class="queue-notification--badge">
            <div class="notification--badge h-100 d-flex align-items-center justify-content-center" :class="queueNotificationJobStyle[task.state]" :title="getJobState(task.state).name">
                <div class="spinner-border spinner-border-sm" v-if="task.state === JOB_STATE.RUNNING"></div>
                <fa-icon :icon="queueNotificationJobIcon[task.state]" :class="{ 'text-white': ! [JOB_STATE.QUEUED, JOB_STATE.RUNNING].includes(task.state) }" v-else />
            </div>
        </div>
        <div class="queue-notification--upper mx-1">
            <span class="queue-notification--name fw-semibold text-truncate">{{ task.name.split('\n', 1)[0] }}</span>
            <span class="queue-notification--time text-muted" :title="dateFormatter.format(new Date(task.updated_at))">
                {{ formatTimeAgoIntl(new Date(task.updated_at), queueNotificationTimeFormatterOptions) }}
            </span>
        </div>
        <div class="queue-notification--down mx-1">
            <div class="queue-notification--desc fs-8 text-truncate pb-1">{{ task.name.split('\n', 2)?.[1] ?? 'unknown' }}</div>
            <div class="queue-notification--controls d-flex">
                <button class="btn btn-icon btn-icon-sm align-self-center" title="Show details" @click="emit('details', task.id)"
                        v-if="!!task.details || !!task.exception">
                    <fa-icon icon="circle-info" />
                </button>
                <button class="btn btn-icon btn-icon-sm align-self-center" title="Retry" @click="retryJob(task.id)"
                        v-if="task.state === JOB_STATE.FAILED">
                    <fa-icon icon="arrow-rotate-right" />
                </button>
                <button class="btn btn-icon btn-icon-sm align-self-center" title="Cancel" @click="tryCancelJob(task.id)"
                        v-if="task.cancellable && (task.state === JOB_STATE.QUEUED || task.state === JOB_STATE.FAILED)">
                    <fa-icon icon="ban" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {formatTimeAgoIntl} from "@vueuse/core";
import {
    getJobState, JOB_STATE,
    queueNotificationJobIcon, queueNotificationJobStyle, queueNotificationTimeFormatterOptions
} from "../utils/utils";
import {useDateFormatter} from "../hooks/date";
import {showErrorNotificationFromAxiosError} from "../utils/notifications";
import {useQueueStore} from "../stores/queue";
import {ref} from "vue";

const props = defineProps({
    task: { type: Object, required: true }
});

const emit = defineEmits(['details']);
const dateFormatter = useDateFormatter();
const queueStore = useQueueStore();
const jobCancelling = ref(false);
const jobRetrying = ref(false);

function tryCancelJob(jobId) {
    if (jobCancelling.value) return;
    jobCancelling.value = true;

    queueStore.cancelJob(jobId)
        .catch(err => {
            showErrorNotificationFromAxiosError(err);
        })
        .finally(() => {
            jobCancelling.value = false;
        });
}
function retryJob(jobId) {
    if (jobRetrying.value) return;
    jobRetrying.value = true;

    queueStore.retryJob(jobId)
        .catch(err => {
            showErrorNotificationFromAxiosError(err);
        })
        .finally(() => {
            jobRetrying.value = false;
        });
}
</script>

<style lang="sass">
.queue-notification
    display: grid
    grid-template-columns: min-content auto
    grid-template-rows: auto auto
    grid-auto-flow: row
    grid-template-areas: "badge upper" "badge down"

    &--upper
        grid-area: upper
        display: grid
        grid-template-columns: 1fr auto
        grid-template-rows: auto
        grid-template-areas: "name time"
    &--down
        grid-area: down
        display: grid
        grid-template-columns: 1fr auto
        grid-template-rows: auto
        grid-template-areas: "desc controls"

    &--badge
        grid-area: badge
        width: 23px
        border-right: 1px solid #bdc3c7
    &--name
        grid-area: name
    &--time
        grid-area: time
    &--desc
        grid-area: desc
    &--controls
        grid-area: controls
</style>
