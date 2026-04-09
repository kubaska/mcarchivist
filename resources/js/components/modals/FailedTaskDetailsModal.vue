<template>
    <Modal ref="modal" title="Task details" :show-footer="false" :dialog-scrollable="true" size-class="modal-xl">
        <pre class="task-details fs-8 m-0">{{ details }}</pre>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";

const modal = ref();
const details = ref('');

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setDetails: (_details) => details.value = _details
});
</script>

<style lang="sass">
.task-details
    overflow: initial // Reset Bootstrap style
</style>
