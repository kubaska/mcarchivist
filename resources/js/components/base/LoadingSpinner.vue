<template>
    <div class="d-flex flex-column m-2 p-2 align-items-center gap-2">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading</span>
        </div>
        <span>{{ text }}</span>
    </div>
</template>

<script setup>
const props = defineProps({
    text: { type: String, required: false, default: 'Loading' }
});
</script>
