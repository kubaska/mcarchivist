<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">{{ title }}</p>
            <span class="fs-7 text-muted" v-if="description">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <select v-model="model" class="form-select">
                <option :value="option.id" v-for="option in options">{{ option.name }}</option>
            </select>
        </div>
    </div>
</template>

<script setup>
const model = defineModel();
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: false },
    options: { type: Array, required: true }
});
</script>
