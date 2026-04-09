<template>
    <Modal title="Confirm delete" ref="modal" :dialog-centered="true">
        <p>Do you really with to delete {{ deleteText }}?</p>

        <template #footer>
            <button class="btn btn-primary" @click="modal.hide()">Cancel</button>
            <MButton type="danger" :loading="loading" @click="onDelete">Delete</MButton>
        </template>
    </Modal>
</template>

<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import {showErrorNotification} from "../../utils/notifications";

const modal = ref(null);
const loading = ref(false);
const id = ref(null);
const deleteText = ref('');
const emit = defineEmits(['delete']);

function setData(_id, text) {
    id.value = _id;
    deleteText.value = text;
}

function onDelete() {
    loading.value = true;
    emit('delete', id.value, finish);
}

function finish(error) {
    if (error) {
        showErrorNotification('There was an error while trying to delete this resource', 'Check browser console for details.');
        console.error(error);
    } else {
        modal.value.hide();
        id.value = null;
        deleteText.value = '';
    }

    loading.value = false;
}

const show = () => modal.value.show();

defineExpose({ setData, show });
</script>
