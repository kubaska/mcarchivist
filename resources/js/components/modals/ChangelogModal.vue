<template>
    <Modal ref="modal" title="Changelog" :show-footer="false">
        <div v-html="changelog"></div>
    </Modal>
</template>
<script setup>
import Modal from "../base/Modal.vue";
import {ref} from "vue";

const modal = ref();
const changelog = ref('');

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setChangelog: (html) => changelog.value = html
});
</script>
