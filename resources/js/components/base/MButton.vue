<template>
    <button class="btn" :class="'btn-'+type" type="button" :disabled="disabled || loading">
        <span class="spinner-border spinner-border-sm me-1" aria-hidden="true" v-if="loading"></span>
        <span role="status"><slot></slot></span>
    </button>
</template>

<script setup>
const props = defineProps({
    type: { type: String, required: false, default: 'primary' },
    disabled: { type: Boolean, required: false, default: false },
    loading: { type: Boolean, required: false, default: false }
});
</script>
