<template>
    <Modal ref="modal" title="Confirmation" size-class="modal-lg" @hide="cleanup">
        <div class="alert alert-danger" v-if="error">{{ error }}</div>

        <p>Are you sure you want to remove <strong>{{ file?.name ?? version?.name }}</strong>?</p>
        <span>This operation is irreversible!</span>

        <template #footer>
            <button class="btn btn-secondary" @click="onCancel">Cancel</button>
            <MButton type="danger" :loading="loading" @click="onConfirm">Yes, delete it</MButton>
        </template>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";

const modal = ref();
const version = ref(null);
const file = ref(null);
const emit = defineEmits(['confirm']);
const loading = ref(false);
const error = ref('');

function finish(_error) {
    if (_error) {
        if (_error?.response?.data?.error) {
            error.value = _error?.response?.data?.error + ': ' + _error?.response?.data?.description;
        } else {
            error.value = _error?.message ?? _error.toString();
        }
    } else {
        cleanup();
        modal.value.hide();
    }
    loading.value = false;
}

function cleanup() {
    version.value = null;
    file.value = null;
    error.value = '';
}

function onConfirm() {
    loading.value = true;
    emit('confirm', version.value, file.value, finish);
}

function onCancel() {
    cleanup();
    modal.value.hide();
}

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (_version, _file = null) => {
        version.value = _version;
        file.value = _file;
    }
});
</script>
