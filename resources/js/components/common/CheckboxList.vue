<template>
    <ul class="list-style-reset m-0">
        <template v-for="option in _sortBy(options, sortBy)" :key="option[trackBy]">
            <li class="d-flex justify-content-between">
                <label>
                    <input type="checkbox" :value="modelBy ? option[modelBy] : option" v-model="model" :disabled="!isSelected(option) && maxNoOfCategoriesSelected">
                    <span class="ps-2" :class="{ 'fw-semibold': isSelected(option) }">{{ option[displayBy] }}</span>
                </label>
                <span v-if="displayChildren && option.children.length" @click="toggleExpandCategory(option[trackBy])">
                    <fa-icon icon="angle-down" :class="{ 'fa-flip-vertical': expandedCategories.indexOf(option[trackBy]) > -1 }" />
                </span>
            </li>
            <CheckboxList v-if="displayChildren && option.children?.length && expandedCategories.indexOf(option[trackBy]) > -1" class="ps-4"
                          v-model="model" :options="_sortBy(option.children, sortBy)"
                          :track-by="trackBy" :display-by="displayBy" :model-by="modelBy"
                          :display-children="false" :max="max"
            />
        </template>
    </ul>
</template>

<script setup>
import {computed, ref} from "vue";
import {sortBy as _sortBy} from "lodash-es";

const props = defineProps({
    options: { type: Array, required: true },
    trackBy: { type: String, required: false, default: 'id' },
    displayBy: { type: String, required: false, default: 'name' },
    modelBy: { type: String, required: false },
    sortBy: { type: [String, Function], required: false },
    displayChildren: { type: Boolean, required: false, default: false },
    max: { type: Number, required: false }
});

const model = defineModel();
const expandedCategories = ref([]);
const maxNoOfCategoriesSelected = computed(() => props.max ? model.value.length >= props.max : false);
const selectedValues = computed(() => model.value.map(v => v[props.trackBy]));

function isSelected(option) {
    if (props.modelBy) {
        return model.value.indexOf(option[props.modelBy]) > -1;
    } else {
        return selectedValues.value.indexOf(option[props.trackBy]) > -1;
    }
}

function toggleExpandCategory(id) {
    const index = expandedCategories.value.indexOf(id);
    if (index > -1) expandedCategories.value.splice(index, 1);
    else expandedCategories.value.push(id);
}
</script>
