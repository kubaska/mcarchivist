<template>
    <div class="dropdown">
        <div data-bs-toggle="dropdown" :data-bs-auto-close="closeBehavior">
            <slot></slot>
        </div>
        <div class="dropdown-menu p-0 w-fit-content">
            <slot name="beforeMenu"></slot>
            <slot name="menu">
                <ul class="dropdown-list overflow-auto">
                    <li v-for="option in options" :key="option[trackBy]">
                        <a class="dropdown-item d-flex justify-content-between align-items-center" href="#"
                           :class="{ disabled: isDisabled(option) }" @click.prevent="toggleOption(option)"
                        >
                            <span>{{ displayBy ? option[displayBy] : option }}</span>
                            <fa-icon icon="check" v-if="isSelected(option)" class="ms-2" />
                        </a>
                    </li>
                </ul>
            </slot>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Array, required: true, default: [] },
    options: { type: Array, required: false, default: [] },
    multiple: { type: Boolean, required: false, default: true },
    trackBy: { type: String, required: false },
    displayBy: { type: String, required: false },
    closeBehavior: { type: String, required: false, default: 'true' },
    max: { type: Number, required: false }
});

const emit = defineEmits(['update', 'update:modelValue']);

function isSelected(option) {
    if (props.multiple) {
        if (props.trackBy) {
            return props.modelValue.some(el => el[props.trackBy] === option[props.trackBy]);
        } else {
            return props.modelValue.includes(option);
        }
    } else {
        return props.modelValue === option;
    }
}

function isDisabled(option) {
    if (props.max && props.multiple) {
        if (props.modelValue.length >= props.max) return !isSelected(option);
    }
    return false;
}

function toggleOption(option) {
    let newValue;

    if (props.multiple) {
        if (isSelected(option)) {
            if (props.trackBy) {
                newValue = props.modelValue.filter(val => val[props.trackBy] !== option[props.trackBy]);
            } else {
                newValue = props.modelValue.filter(val => val !== option);
            }
        } else {
            newValue = [...props.modelValue, option];
        }
    } else {
        newValue = isSelected(option) ? null : option;
    }

    emit('update:modelValue', newValue);
    emit('update');
}
</script>

<style lang="sass">
.dropdown-list
    max-height: 200px
    padding: 0
    margin: 0
    list-style: none
</style>
