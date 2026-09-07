<template>
    <Modal ref="modal" :title="type+' List'" :show-footer="false" @hide="data = null" size-class="modal-lg" body-class="pt-0">
        <LoadingSpinner v-if="data === null" />
        <div class="my-2 text-center" v-else-if="data.length === 0">
            <p class="m-0">This version does not list any {{ type.toLowerCase() }}.</p>
        </div>
        <div class="d-flex flex-column gap-2" v-else>
            <MTable :columns="[['Project', 'Versions'], ['Project', 'Versions'], ['Project', 'Versions']]" :sizing="['min-content', 'auto']">
                <MTableRow v-for="versionable in data">
                    <MTableColumn>{{ versionable.name }}</MTableColumn>
                    <MTableColumn>
                        <div class="d-flex gap-2">
                            <span v-for="version in versionable.versions">{{ version.version }}</span>
                        </div>
                    </MTableColumn>
                </MTableRow>
            </MTable>
        </div>
    </Modal>
</template>

<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import MTable from "../base/Table/MTable.vue";
import MTableRow from "../base/Table/MTableRow.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";
import MTableColumn from "../base/Table/MTableColumn.vue";

const modal = ref(null);
const type = ref('Dependency');
const data = ref(null);

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (_type, _data) => {
        type.value = _type;
        data.value = _data;
    }
});
</script>
