<template>
    <Modal ref="modal" :title="title" @hide="onHide" :dialog-centered="dialogCentered">
        <div class="alert alert-danger" v-if="error">{{ error }}</div>

        <p v-if="text" class="m-0">{{ text }}</p>
        <slot v-else></slot>

        <template #footer>
            <button class="btn btn-secondary" @click="onCancel">Cancel</button>
            <MButton type="primary" :loading="loading" @click="onConfirm">Confirm</MButton>
        </template>
    </Modal>
</template>

<script setup>
import Modal from "../base/Modal.vue";
import {ref} from "vue";
import MButton from "../base/MButton.vue";

const modal = ref(null);
const props = defineProps({
    title: { type: String, required: true },
    dialogCentered: { type: Boolean, required: false, default: true }
});
const emit = defineEmits(['choice']);

const loading = ref(false);
const error = ref('');
const promise = ref(null);

const text = ref('');
const data = ref(null);

const hide = () => modal.value.hide();
const show = () => modal.value.show();

function cleanup() {
    error.value = '';
    promise.value = null;
    text.value = '';
    data.value = null;
}

function setData(_text, _data) {
    text.value = _text;
    data.value = _data;
}

function onCancel() {
    emit('choice', false);
    if (promise.value) promise.value.reject();
    hide();
    cleanup();
}
function onConfirm() {
    loading.value = true;
    emit('choice', true, finish);
    if (promise.value) promise.value.resolve({ finish });
}

function finish(_error) {
    if (_error) {
        if (_error?.response?.data?.error) {
            error.value = _error?.response?.data?.error + ': ' + _error?.response?.data?.description;
        } else {
            error.value = _error?.message ?? _error.toString();
        }
    } else {
        hide();
        text.value = '';
        cleanup();
    }

    loading.value = false;
}

async function awaitChoice() {
    return new Promise((resolve, reject) => {
        promise.value = { resolve, reject };
    });
}

function onHide() {
    if (promise.value) promise.value.reject('Cancelled');
    cleanup();
}

defineExpose({ awaitChoice, setData, hide, show });
</script>
