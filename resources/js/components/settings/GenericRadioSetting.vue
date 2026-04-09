<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">{{ title }}</p>
            <span class="fs-7 text-muted">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <div class="form-check" v-for="option in options">
                <label class="form-check-label">
                    <input class="form-check-input" :id="name" type="radio" :value="option.id" v-model="model">
                    {{ option.name }}
                </label>
            </div>
        </div>
    </div>
</template>

<script setup>
import {kebabCase} from "lodash-es";

const model = defineModel();
const props = defineProps({
    // HTML id
    name: { type: String, required: false, default(rawProps) { return 'input-'+kebabCase(rawProps.title); } },
    title: { type: String, required: true },
    description: { type: String, required: false },
    // Array of objects, each object must have id, name props.
    options: { type: Array, required: true },
});
</script>
