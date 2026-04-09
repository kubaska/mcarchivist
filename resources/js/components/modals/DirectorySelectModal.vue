<template>
    <Modal ref="modal" title="Choose a directory" @hide="onHide" size-class="modal-lg" :dialog-scrollable="true">
        <div class="d-flex gap-2 align-items-center">
            <div class="spinner-border spinner-border-sm" role="status" v-if="isLoading">
                <span class="visually-hidden">Loading...</span>
            </div>
            <fa-icon icon="folder-open" v-else />
            <span>{{ currentDir }}</span>
        </div>
        <hr class="my-1" />
        <div class="d-flex flex-column">
            <div v-if="error">
                <div class="alert alert-danger">
                    <p class="m-0"><strong>{{ error?.error ?? 'There was an error' }}</strong></p>
                    <span>{{ error?.description ?? 'Something went wrong, sorry about that.' }}</span>
                </div>
                <button class="btn btn-secondary" @click="getDirectories()">Return to starting directory</button>
            </div>
            <LoadingSpinner v-else-if="dirStructure === null" />
            <template v-else>
                <div v-for="entry in dirStructure" class="d-flex align-items-center gap-2 user-select-none bg-light-hover">
                    <fa-icon :icon="entry.type === 'dir' ? 'folder' : 'file'" class="icon-16" />
                    <span @click="onTraverse(entry)" class="flex-grow-1 text-truncate"
                          :class="{ 'cursor-pointer': entry.type === 'dir', 'text-muted': entry.type !== 'dir' }"
                    >{{ entry.dir }}</span>
                    <span v-if="entry.modified_at" class="flex-shrink-0 text-muted" :key="entry.modified_at">
                        {{ dateFormatter.format(new Date(entry.modified_at * 1000)) }}
                    </span>
                </div>
                <p v-if="dirStructure.length === 1" class="text-center my-2 text-muted fst-italic">Directory is empty.</p>
            </template>
        </div>

        <template #footer>
            <button class="btn btn-secondary" @click="modal.hide">Cancel</button>
            <button class="btn btn-primary" @click="onDirectorySelect">Select</button>
        </template>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import api from "../../api/api";
import Modal from "../base/Modal.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";
import {useDateFormatter} from "../../hooks/date";

const modal = ref();
const currentDir = ref(null);
const dirStructure = ref(null);
const isLoading = ref(false);
const error = ref(null);
const promise = ref(null);
const dateFormatter = useDateFormatter();

function getDirectories(dir, traverse) {
    isLoading.value = true;
    return api.getDirectories({ dir, traverse })
        .then(res => {
            currentDir.value = res.data.dir;
            dirStructure.value = res.data.storage;
            error.value = null;
        })
        .catch(err => {
            console.log(err);
            error.value = err.response.data;
        })
        .finally(() => isLoading.value = false);
}

function onTraverse(entry) {
    if (entry.type !== 'dir') return;
    getDirectories(currentDir.value, entry.dir);
}

function onDirectorySelect() {
    if (promise.value) promise.value.resolve(currentDir.value);
    cleanup();
    modal.value.hide();
}

function onHide() {
    if (promise.value) promise.value.reject('Cancelled');
    cleanup();
}
function onShow() {
    getDirectories(currentDir.value)
    modal.value.show();
}
function awaitChoice() {
    return new Promise((resolve, reject) => {
        promise.value = { resolve, reject };
    });
}
function cleanup() {
    currentDir.value = null;
    dirStructure.value = null;
    error.value = null;
    promise.value = null;
}

defineExpose({ hide: onHide, show: onShow, setDirectory: (d) => currentDir.value = d, awaitChoice });
</script>

<style lang="sass">
.icon-16
    width: 16px
</style>
