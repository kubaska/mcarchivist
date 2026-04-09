<template>
    <Modal ref="modal" :title="'Files for version ' + version?.name" :show-footer="false" size-class="modal-lg">
        <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-content-between" v-for="file in version?.files" :key="file.id">
                <div class="d-flex flex-column">
                    <p class="m-0 fw-semibold">{{ file.name }} <span class="text-muted fw-normal">({{ formatBytes(file.size) }})</span></p>
                    <span class="fs-7 text-muted">{{ basePath }}/{{ file.dir }}/{{ file.name }}</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-icon" @click="onDownloadFileBtnClick(file)">
                        <fa-icon icon="download" />
                    </button>
                    <button class="btn btn-icon" @click="onDeleteFileBtnClick(file)">
                        <fa-icon icon="xmark" />
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
<script setup>
import {computed, ref} from "vue";
import Modal from "../base/Modal.vue";
import {downloadFile, formatBytes} from "../../utils/utils";
import {useConfigStore} from "../../stores/config";

const modal = ref();
const version = ref(null);
const storageArea = ref(null);
const emit = defineEmits(['delete-file']);
const config = useConfigStore();
const basePath = computed(() => config.getSetting('general.storage.'+storageArea.value));

function onDownloadFileBtnClick(file) {
    downloadFile(file.url);
}

function onDeleteFileBtnClick(file) {
    emit('delete-file', version.value, file);
}

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (_version, area) => {
        version.value = _version;
        storageArea.value = area;
    }
});
</script>
