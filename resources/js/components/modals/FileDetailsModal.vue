<template>
    <Modal ref="modal" size-class="modal-lg" title="File details" :show-footer="false" @hide="onHide">
        <LoadingSpinner v-if="! details" />
        <template v-else>
            <table class="table table-borderless">
                <tr>
                    <td class="pe-2 fw-semibold">Name</td>
                    <td class="text-break">{{ details.name }}</td>
                </tr>
                <tr>
                    <td class="pe-2 fw-semibold">Path</td>
                    <td class="text-break">{{ details.path }}</td>
                </tr>
                <tr>
                    <td class="pe-2 fw-semibold">Size</td>
                    <td class="text-break">{{ formatBytes(details.size) }}</td>
                </tr>
            </table>

            <table class="table table-borderless mb-0">
                <tr>
                    <td class="fw-semibold">Hashes</td>
                    <td></td>
                </tr>
                <tr v-for="(hash, name) in details.hashes">
                    <td class="pe-2 fw-semibold text-uppercase">{{ name }}</td>
                    <td class="text-break">{{ hash }}</td>
                    <td>
                        <CopyToClipboard size="sm" :data="hash" />
                    </td>
                </tr>
            </table>
        </template>
    </Modal>
</template>

<script setup>
import {ref} from "vue";
import {formatBytes} from "../../utils/utils";
import Modal from "../base/Modal.vue";
import CopyToClipboard from "../CopyToClipboard.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";

const modal = ref(null);
const details = ref(null);

function onHide() {
    details.value = null;
}

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (data) => {
        details.value = data;
    }
});
</script>
