<template>
    <div ref="selectRef">
        <button ref="triggerRef" class="form-select text-align-start text-truncate" :disabled="disabled" @click="toggleOpen">
            <template v-if="selectedOption">
                <span class="badge me-2" v-if="selectedOption?.badge" :class="selectedOption.badge.class ?? []"
                       :style="{ 'background-color': selectedOption.badge.color }"
                >
                    {{ selectedOption.badge.name }}
                </span>
                <span>{{ selectedOption.name }}</span>
            </template>
            <span v-else>Select an option...</span>
        </button>

        <Teleport to="body">
            <div v-if="isOpen" ref="floatingRef"
                 class="m-select--dropdown bg-body border rounded-3 p-1 overflow-y-auto shadow" :style="floatingStyles"
            >
                <div v-for="option in options" :key="option.id"
                     class="m-select--option d-flex align-items-center justify-content-between p-2 cursor-pointer fs-7"
                     :class="{ selected: model === option.id }" @click="selectOption(option)"
                >
                    <div>
                        <span class="badge me-2" v-if="option.badge" :class="option.badge.class ?? []"
                              :style="{ 'background-color': option.badge.color }"
                        >
                            {{ option.badge.name }}
                        </span>
                        <span>{{ option.name }}</span>
                    </div>
                    <fa-icon icon="check" v-if="model === option.id" class="ms-2" />
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import {ref, watch, onUnmounted, computed} from 'vue';
import {autoUpdate, flip, useFloating, size} from '@floating-ui/vue';
import {isDescendantOf} from "../../utils/utils";

const model = defineModel();
const props = defineProps({
    options: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['change']);

const selectRef = ref(null);
const triggerRef = ref(null);
const floatingRef = ref(null);

const isOpen = ref(false);
const selectedOption = computed(() => props.options.find(opt => opt.id === model.value));

function toggleOpen() {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
}

function selectOption(option) {
    model.value = option.id;
    emit('change', option.id, option);
    isOpen.value = false;
}

function handleClickOutside(event) {
    if (! (isDescendantOf(event.target, selectRef.value) || isDescendantOf(event.target, floatingRef.value))) {
        isOpen.value = false;
    }
}

const { floatingStyles } = useFloating(triggerRef, floatingRef, {
    placement: 'bottom-start',
    middleware: [
        flip({ fallbackPlacements: ['top-start'] }),
        size({
            apply({ rects, elements }) {
                Object.assign(elements.floating.style, {
                    minWidth: `${rects.reference.width}px`,
                });
            },
        })
    ],
    whileElementsMounted: autoUpdate
});

watch(isOpen, open => {
    if (open) {
        document.addEventListener('mousedown', handleClickOutside);
    } else {
        document.removeEventListener('mousedown', handleClickOutside);
    }
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<style lang="sass">
.m-select--dropdown
    max-height: 240px
    z-index: 9999

.m-select--option
    &:hover
        background-color: #f3f4f6

    &.selected
        background-color: #eff6ff
        color: #3b82f6
</style>
