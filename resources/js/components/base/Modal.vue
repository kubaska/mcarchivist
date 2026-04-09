<template>
    <div class="modal" tabindex="-1" ref="modal" v-on="eventMap">
        <div class="modal-dialog" :class="[ { 'modal-dialog-scrollable': dialogScrollable }, { 'modal-dialog-centered': dialogCentered }, { [sizeClass]: sizeClass } ]">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <slot></slot>
                </div>
                <div class="modal-footer" v-if="showFooter">
                    <slot name="footer"></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from "vue";
import {useBsModal} from "../../hooks/bootstrap";

const modal = ref(null);
const { bsComponent: bsModal } = useBsModal(modal);
const props = defineProps({
    title: { type: String, required: false },
    showFooter: { type: Boolean, required: false, default: true },
    sizeClass: { type: String, required: false },
    dialogCentered: { type: Boolean, required: false, default: false },
    dialogScrollable: { type: Boolean, required: false, default: false },
});
const emit = defineEmits(['hide', 'hidden', 'show', 'shown']);

const handleHideEvent = () => emit('hide');
const handleHiddenEvent = () => emit('hidden');
const handleShowEvent = () => emit('show');
const handleShownEvent = () => emit('shown');

const eventMap = {
    'hide.bs.modal': handleHideEvent,
    'hidden.bs.modal': handleHiddenEvent,
    'show.bs.modal': handleShowEvent,
    'shown.bs.modal': handleShownEvent,
}

const hide = () => bsModal.value.hide();
const show = () => bsModal.value.show();

defineExpose({ hide, show });
</script>
