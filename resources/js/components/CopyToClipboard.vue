<template>
    <button class="btn btn-icon" :class="size ? 'btn-icon-'+size : ''" @click="copyToClipboard" v-tooltip="tooltip">
        <fa-icon icon="clipboard" />
    </button>
</template>

<script setup>
import {reactive} from "vue";

const props = defineProps({
    size: { type: String, required: false },
    data: { type: String, required: false }
});

let timeoutId = null;
const tooltip = reactive({
    content: 'Copy to clipboard',
    delay: { hide: 0 }
});

function copyToClipboard() {
    if (timeoutId) clearTimeout(timeoutId);
    tooltip.content = 'Copied!';
    tooltip.delay.hide = 1000;

    navigator.clipboard.writeText(props.data);

    timeoutId = setTimeout(() => {
        tooltip.content = 'Copy to clipboard';
        tooltip.delay.hide = 0;
    }, 1200);
}
</script>
