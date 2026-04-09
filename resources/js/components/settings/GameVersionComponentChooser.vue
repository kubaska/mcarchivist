<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <div class="d-flex justify-content-between">
                <p class="m-0 fw-semibold">{{ title }}</p>
                <button class="btn btn-icon btn-icon-sm d-flex align-items-center justify-content-center flex-shrink-0"
                        v-tooltip="'Change mode'" @click="basicMode = !basicMode"
                >
                    <fa-icon icon="pencil" size="sm" />
                </button>
            </div>
            <span class="fs-7 text-muted">{{ description }}</span>
        </div>
        <div class="col-12 col-md-8">
            <template v-if="basicMode">
                <div class="form-check" v-for="component in allComponents">
                    <label class="form-check-label">
                        <input class="form-check-input" type="checkbox"
                               :value="component.id" v-model="componentsAsArray"
                               :disabled="component.id !== '*' && wildcardSelected"
                        />{{ component.name }}
                    </label>
                    <template v-if="component.hint">
                        <br/><span class="text-secondary fs-7">{{ component.hint }}</span>
                    </template>
                </div>
            </template>
            <template v-else>
                <input type="text" v-model="componentsAsString" class="form-control my-1">
                <ul class="m-0">
                    <li>
                    <span class="text-muted fs-7">
                        <span>Allowed values: </span><span v-for="c in components">{{ c.id }}, </span><span>or "*" for all components.</span>
                    </span>
                    </li>
                    <li>
                        <span class="text-muted fs-7">Multiple values must be separated with a comma.</span>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>

<script setup>
import {computed, ref} from "vue";

const props = defineProps({
    components: { type: Array, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
});
const model = defineModel({
    set(v) {
        // Basic mode only, because this will override what user writes
        if (basicMode.value && v.includes('*')) {
            return ['*'];
        }
        else return v;
    }
});
const basicMode = ref(true);
const wildcardSelected = computed(() => model.value.includes('*'));
const allComponents = computed(() => [
    { id: '*', name: 'All', hint: 'Archive all components, including those that may be added in the future' },
    ...props.components
]);

const componentsAsArray = computed({
    get: () => {
        // All options should be checked if wildcard is selected.
        if (wildcardSelected.value) return allComponents.value.map(c => c.id);
        return model.value;
    },
    set: (v) => {
        if (v.includes('*')) model.value = ['*'];
        else model.value = v;
    }
});
const componentsAsString = computed({
    get: () => model.value.join(','),
    // split on empty string results in an array filled with an empty string. Yikes!
    set: (v) => model.value = (v === '' ? [] : v.split(','))
});
</script>
