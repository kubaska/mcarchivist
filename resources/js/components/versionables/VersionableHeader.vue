<template>
    <div class="d-flex flex-column flex-md-row gap-md-3 justify-content-between my-2">
        <h3>{{ name }}</h3>
        <div class="d-flex gap-2 align-items-center">
            <div :class="{ 'btn-group': canRevalidate }" v-if="canUpdateIndex">
                <MButton @click="onUpdateIndex(false)" :loading="isUpdatingIndex">
                    <fa-icon icon="arrows-rotate" :class="{ 'd-none': isUpdatingIndex }" /> Update Index
                </MButton>
                <button type="button" v-show="canRevalidate" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                        :disabled="isUpdatingIndex" data-bs-toggle="dropdown" aria-expanded="false" ref="updateIndexDropdownElem"
                >
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" @click.prevent="onUpdateIndex(true)">Full Revalidate</a></li>
                </ul>
            </div>

            <button class="btn btn-secondary" v-if="canUseSettings" @click="emit('open-settings')"><fa-icon icon="cog" /> Settings</button>
        </div>
    </div>
    <hr class="mt-0" />
</template>

<script setup>
import {ref} from "vue";
import MButton from "../base/MButton.vue";
import {useTaskStateSpy} from "../../hooks/queue";
import {useBsDropdown} from "../../hooks/bootstrap";

const props = defineProps({
    name: { type: String, required: true },
    canUpdateIndex: { type: Boolean, required: true },
    canRevalidate: { type: Boolean, required: false, default: true },
    canUseSettings: { type: Boolean, required: true },
    updateIndexTaskId: { type: String, required: false },
    updateIndexFn: { type: Function, required: false }
});
const emit = defineEmits(['refresh-data', 'open-settings']);
const isUpdatingIndex = ref(false);
const updateIndexDropdownElem = ref(null);
const { bsComponent: updateIndexDropdown } = useBsDropdown(updateIndexDropdownElem);

const { attachTaskStateSpy } = useTaskStateSpy(props.updateIndexTaskId, () => {
    emit('refresh-data');
    isUpdatingIndex.value = false;
}, () => isUpdatingIndex.value = true);

function onUpdateIndex(revalidate = false) {
    isUpdatingIndex.value = true;
    updateIndexDropdown.value.hide();
    props.updateIndexFn({ revalidate })
        .then(() => {
            attachTaskStateSpy();
        });
}
</script>
