<template>
    <div id="head-button" class="position-relative d-inline-block">
        <FailedTaskDetailsModal ref="failedTaskModal" />

        <button type="button" class="btn position-relative" @click="shown = !shown">
            <fa-icon icon="download" />
            <span class="queue-notifications--count position-absolute translate-middle badge rounded-pill bg-danger" v-if="queueStore.tasks.length">
                {{ queueStore.tasks.length > 9 ? '9+' : queueStore.tasks.length }}<span class="visually-hidden">active jobs</span>
            </span>
        </button>

        <div @click="shown = false" class="position-fixed inset-0 h-100 w-100 z-1" :class="{ 'd-none': !shown }"></div>
        <div class="position-absolute queue-notifications bg-body z-4" :class="{ 'queue-notifications--show': shown }">
            <p class="border-bottom text-center fs-5 m-0">Queue</p>
            <div class="queue-notifications--list">
                <div class="d-flex p-2 align-items-center align-items-center" v-if="queueStore.allTasks.length === 0 || !shown">
                    <p class="m-0">Queue is empty!</p>
                </div>
                <template v-else>
                    <QueueNotification v-for="task in queueStore.allTasks" :task="task" :key="task.id" @details="showDetails" />
                </template>
                <p class="my-1 text-center fs-7" :class="{ 'cursor-pointer': !queueStore.previousTasksExhausted }" @click="getPreviousTasks">
                    <span class="spinner-border spinner-border-sm" v-if="previousTasksLoading"></span>
                    <span v-else>{{ queueStore.previousTasksExhausted ? 'All tasks loaded' : 'Load more...' }}</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from "vue";
import {useQueueStore} from "../stores/queue";
import QueueNotification from "./QueueNotification.vue";
import FailedTaskDetailsModal from "./modals/FailedTaskDetailsModal.vue";

const queueStore = useQueueStore();
const previousTasksLoading = ref(false);
const shown = ref(false);
const failedTaskModal = ref();

function getPreviousTasks() {
    if (queueStore.previousTasksExhausted) return;

    previousTasksLoading.value = true;
    queueStore.getPreviousTasks()
        .finally(() => {
            previousTasksLoading.value = false;
        });
}
function showDetails(jobId) {
    const task = queueStore.getPreviousTask(jobId);
    if (! task) return;

    const details = (task.details ? (task.details + '\n') : '') + (task.exception ?? '');
    failedTaskModal.value.setDetails(details ?? 'No details available for this task');
    failedTaskModal.value.show();
}
</script>

<style lang="sass">
.queue-notifications
    min-width: 350px
    transition: 0.25s ease-out 0s opacity
    border: 1px solid #bdc3c7
    right: 0
    opacity: 0
    top: -999px

    &:after
        border: 10px solid transparent
        border-bottom-color: #bdc3c7
        content: ''
        display: block
        height: 0
        right: 10px
        position: absolute
        top: -20px
        width: 0

    @media (prefers-reduced-motion: reduce)
        transition: none

    &--list
        max-height: 350px
        overflow-y: auto

    &--show
        top: 60px
        opacity: 1

    &--count
        top: 10% !important
        left: 90% !important
</style>
