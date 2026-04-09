<template>
    <div id="head-button" class="position-relative d-inline-block">
        <FailedTaskDetailsModal ref="failedTaskModal" />

        <button type="button" class="btn position-relative" @click="shown = !shown">
            <fa-icon icon="download" />
            <span class="notifications--count position-absolute translate-middle badge rounded-pill bg-danger" v-if="queueStore.tasks.length">
                {{ queueStore.tasks.length > 9 ? '9+' : queueStore.tasks.length }}<span class="visually-hidden">active jobs</span>
            </span>
        </button>

        <div @click="shown = false" class="position-fixed inset-0 h-100 w-100 z-1" :class="{ 'd-none': !shown }"></div>
        <div class="position-absolute notifications bg-body z-4" :class="{ 'notifications--show': shown }">
            <p class="border-bottom text-center fs-5 m-0">Queue</p>
            <div class="d-flex p-2 align-items-center align-items-center" v-if="queueStore.allTasks.length === 0">
                <p class="m-0">Queue is empty!</p>
            </div>
            <div class="notifications-list" v-else>
                <div class="d-flex justify-content-between p-1 border-bottom fs-7 justify-content-center"
                     :class="jobStyle[task.state]" v-for="task in queueStore.allTasks" :key="task.id"
                >
                    <div class="d-flex gap-2 align-items-center">
                        <div class="spinner-border spinner-border-sm flex-shrink-0" role="status" v-if="task.state === 1"></div>
                        <fa-icon :icon="jobIcon[task.state]" v-if="jobIcon[task.state]" />
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-truncate" :title="task.name.split('\n', 1)[0]">{{ task.name.split('\n', 1)[0] }}</span>
                            <span class="fs-8">{{ task.name.split('\n', 2)?.[1] ?? 'unknown' }}</span>
                        </div>
                    </div>
                    <div class="d-flex">
                        <button class="btn btn-icon align-self-center" title="Show details" @click="showDetails(task.id)"
                                v-if="task.state === 3">
                            <fa-icon icon="circle-info" />
                        </button>
                        <button class="btn btn-icon align-self-center" title="Retry" @click="retryJob(task.id)"
                                v-if="task.state === 3">
                            <fa-icon icon="arrow-rotate-right" />
                        </button>
                        <button class="btn btn-icon align-self-center" title="Cancel" @click="tryCancelJob(task.id)"
                                v-if="task.cancellable && (task.state === 0 || task.state === 3)">
                            <fa-icon icon="xmark" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from "vue";
import FailedTaskDetailsModal from "./modals/FailedTaskDetailsModal.vue";
import {useQueueStore} from "../stores/queue";
import {showErrorNotification} from "../utils/notifications";

const queueStore = useQueueStore();
const shown = ref(false);
const failedTaskModal = ref();

const jobStyle = { 2: 'bg-success-subtle', 3: 'bg-danger-subtle' };
const jobIcon = { 2: 'check', 3: 'xmark' };

function showDetails(jobId) {
    const task = queueStore.getFailedTask(jobId);
    if (! task) return;

    const details = (task.details ? (task.details + '\n') : '') + (task.exception ?? '')
    failedTaskModal.value.setDetails(details ?? 'No details available for this task');
    failedTaskModal.value.show();
}
function tryCancelJob(jobId) {
    const result = queueStore.cancelJob(jobId);
    if (! result) {
        showErrorNotification('Unable to cancel task at this time');
    }
}
function retryJob(jobId) {
    queueStore.retryJob(jobId);
}
</script>

<style lang="sass">
.notifications
    min-width: 350px
    transition: 0.25s ease-out 0s opacity
    border: 1px solid #bdc3c7
    right: 0
    opacity: 0
    top: -999px

@media (prefers-reduced-motion: reduce)
    .notifications
        transition: none

.notifications:after
    border: 10px solid transparent
    border-bottom-color: #bdc3c7
    content: ''
    display: block
    height: 0
    right: 10px
    position: absolute
    top: -20px
    width: 0

.notifications-list
    max-height: 350px
    overflow-y: auto

    > *:last-child
        border: none !important

.notifications--show
    top: 60px
    opacity: 1

.notifications--count
    top: 10% !important
    left: 90% !important
</style>
