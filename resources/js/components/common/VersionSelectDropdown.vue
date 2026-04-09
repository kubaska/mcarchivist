<template>
    <SelectableDropdown v-model="model" :options="gameVersions" track-by="id">
        <template #beforeMenu>
            <div class="p-2 border-bottom">
                <input type="text" class="form-control form-select-sm" placeholder="Search..." v-model="searchQuery" />
                <div class="form-check-inline" v-if="!hasOnlyReleaseVersions">
                    <label class="form-label m-0 text-nowrap">
                        <input type="checkbox" class="form-check-input" v-model="displayAllVersions" />
                        Display all versions
                    </label>
                </div>
            </div>
        </template>
        <slot></slot>
    </SelectableDropdown>
</template>
<script setup>
import SelectableDropdown from "./SelectableDropdown.vue";
import {computed, ref} from "vue";

const props = defineProps({
    versions: { type: Array, required: true }
});

const model = defineModel();
const searchQuery = ref('');
const displayAllVersions = ref(false);
const hasOnlyReleaseVersions = computed(() => props.versions.every(v => v.type === 0));
const gameVersions = computed(() => {
    // Display all versions regardless, if there's only "release" type versions
    let gv = hasOnlyReleaseVersions.value || displayAllVersions.value
        ? props.versions
        : props.versions.filter(v => v.type === 0);

    return searchQuery.value ? gv.filter(v => v.name.toLowerCase().includes(searchQuery.value.toLowerCase())) : gv;
});
</script>
