<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">{{ title }}</p>
            <span class="fs-7 text-muted">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <div class="form-check" v-for="type in types">
                <label class="form-check-label">
                    <input class="form-check-input" type="checkbox" :value="type.name.toLowerCase()" v-model="model">
                    {{ type.name }}
                </label>
            </div>
        </div>
    </div>
</template>

<script setup>
const model = defineModel();
const props = defineProps({
    types: { type: Array, required: true },
    title: { type: String, required: true },
    description: { type: String, required: false },
});
</script>
