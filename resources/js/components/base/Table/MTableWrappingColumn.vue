<template>
    <div class="d-flex flex-column justify-content-center" :class="`d-${breakpoint}-contents`">
        <slot></slot>
    </div>
</template>

<script setup>
const props = defineProps({
    breakpoint: { type: String, required: false, default: 'md' }
});
</script>
