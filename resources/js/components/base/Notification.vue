<template>
    <div ref="toast" class="toast notification--progressbar bg-gradient border-0 fadeInDown"
         role="alert" aria-live="assertive" aria-atomic="true" v-on="eventMap"
         :style="[
             isInteracting ? '' : `--progressbar-duration: ${props.notification.timeout}s`,
             `--progressbar-background: var(--bs-${props.notification.type})`
         ]"
        >
        <div class="toast-header gap-1" :class="{ 'border-bottom-0': !notification.description }">
            <fa-icon :icon="icon" :class="'text-'+props.notification.type" />
            <strong class="me-auto">{{ notification.title }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" v-if="notification.description">
            <span>{{ notification.description }}</span>
        </div>
    </div>
</template>

<script setup>
import {computed, onMounted, ref} from "vue";
import {useBsToast} from "../../hooks/bootstrap";

const props = defineProps({
    notification: {
        type: Object,
        required: true,
        validator(value) {
            return ['primary', 'success', 'warning', 'danger'].includes(value.type)
        }
    }
});
const toast = ref(null);
const { bsComponent: bsToast } = useBsToast(toast, { delay: props.notification.timeout * 1000 });
const isInteracting = ref(false);
const emit = defineEmits(['timeout']);
const icon = computed(() => {
    switch (props.notification.type) {
        case 'primary': return 'circle-info';
        case 'success': return 'circle-check';
        case 'warning': return 'triangle-exclamation';
        case 'danger': return 'xmark';
    }
})

const hidden = () => emit('timeout', props.notification.id);

function pauseProgressbar() {
    isInteracting.value = true;
}
function unpauseProgressbar() {
    isInteracting.value = false;
}

const eventMap = {
    // 'hide.bs.toast': hide,
    'hidden.bs.toast': hidden,
    // 'show.bs.toast': show,
    // 'shown.bs.toast': shown,
    'mouseover': pauseProgressbar,
    'mouseout': unpauseProgressbar,
    'focusin': pauseProgressbar,
    'focusout': unpauseProgressbar
};

onMounted(() => {
    bsToast.value.show();
});
</script>

<style lang="sass">
.notification--progressbar
    position: relative
    overflow: hidden

    &:before
        content: ''
        height: 3px
        width: 100%
        background: var(--progressbar-background, #fff)
        position: absolute
        left: 0
        top: 0
        animation: progressbar var(--progressbar-duration) linear forwards

@keyframes progressbar
    100%
        width: 0
</style>
