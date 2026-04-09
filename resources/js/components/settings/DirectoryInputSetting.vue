<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">{{ title }}</p>
            <span class="fs-7 text-muted" v-if="description">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <div class="input-group">
                <input type="text" class="form-control" v-model="model">
                <button class="btn btn-outline-dark" @click="emit('openDirectorySelector')">
                    <fa-icon icon="folder-open" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
const model = defineModel();
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: false },
});
const emit = defineEmits(['openDirectorySelector']);
</script>
