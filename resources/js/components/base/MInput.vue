<template>
    <div class="m-input">
        <input type="text" class="form-control" v-model="model" v-bind="$attrs">
        <span class="clear-icon" role="button" tabindex="0" aria-label="Clear text" title="Clear text" v-show="model" @click="model = ''">
            <fa-icon icon="xmark" />
        </span>
    </div>
</template>

<script setup>
const model = defineModel();
</script>

<style lang="sass">
.m-input
    position: relative

    & .form-control
        padding-right: 2.5rem

    & .clear-icon
        position: absolute
        right: 0.75rem
        top: 50%
        transform: translateY(-50%)
        color: #6c757d
        font-size: 1.1rem
        transition: color 0.15s ease-in-out
        line-height: 1
        z-index: 5

        &:hover
            color: #dc3545
</style>
