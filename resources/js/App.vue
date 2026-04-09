<template>
    <Nav />
    <div class="container" v-if="appReady">
        <div class="toast-container position-fixed mt-3 top-0 start-50 translate-middle-x">
            <Notification :notification="notification" v-for="notification in notificationStore.notifications" :key="notification.id"
                          @timeout="onNotificationTimeout"
            />
        </div>
        <router-view v-slot="{ Component, route }">
            <transition :name="route.meta.transition" mode="out-in" @before-leave="routerStore.transitionSetOut()" @enter="routerStore.transitionSetIn()">
                <KeepAlive :include="['ProjectsIndex']">
                    <NotFoundView v-if="routerStore.displayedError"></NotFoundView>
                    <component :is="Component" v-else />
                </KeepAlive>
            </transition>
        </router-view>
    </div>
    <div class="my-5" v-else>
        <LoadingSpinner text="Loading application..." />
    </div>
</template>

<script setup>
import Nav from "./components/Nav.vue";
import {useConfigStore} from "./stores/config";
import {useRouterStore} from "./stores/router";
import {ref} from "vue";
import {useMcaRoute} from "./hooks/route";
import {useQueueStore} from "./stores/queue";
import {useNotificationStore} from "./stores/notifications";
import Notification from "./components/base/Notification.vue";
import NotFoundView from "./views/NotFoundView.vue";
import LoadingSpinner from "./components/base/LoadingSpinner.vue";

const notificationStore = useNotificationStore();
const routerStore = useRouterStore();
const config = useConfigStore();
const queue = useQueueStore();
const route = useMcaRoute();
const appReady = ref(false);

function onNotificationTimeout(id) {
    notificationStore.remove(id);
}

Promise.allSettled([config.getConfig(), queue.getQueue(true)])
    .then(() => appReady.value = true);
</script>

<style>
/* Transitions */
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
    transition-duration: 0.1s;
    transition-property: height, opacity, transform;
    transition-timing-function: cubic-bezier(0.55, 0, 0.1, 1);
    overflow: hidden;
}

.slide-left-enter,
.slide-right-leave-active {
    opacity: 0;
    transform: translate(2em, 0);
}

.slide-left-leave-active,
.slide-right-enter {
    opacity: 0;
    transform: translate(-2em, 0);
}
</style>
