<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">{{ title }}</p>
            <span class="fs-7 text-muted" v-if="description">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <input type="text" class="form-control" v-model="model">
        </div>
    </div>
</template>

<script setup>
const model = defineModel();
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: false },
});
</script>
