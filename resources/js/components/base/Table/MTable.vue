<template>
    <div class="m-table" :style="sizingCss">
        <div class="m-table--row py-2 fw-semibold">
            <slot name="header">
                <span v-for="column in columns" class="d-block">{{ column }}</span>
            </slot>
        </div>

        <slot></slot>
    </div>
</template>
<script setup>
import {computed} from "vue";
import {breakpointsBootstrapV5, useBreakpoints} from "@vueuse/core";
import {repeat} from "lodash-es";

const props = defineProps({
    columns: { type: Array, required: false, default: [] },
    sizing: { type: Array, required: false, default: [] }
});

const breakpoints = useBreakpoints(breakpointsBootstrapV5);

const columns = computed(() => {
    if (breakpoints.smaller('md').value) return props.columns[0] ?? props.columns;
    else if (breakpoints.smaller('xl').value) return props.columns[1] ?? props.columns;
    else return props.columns[2] ?? props.columns;
});

const sizingCss = computed(() => {
    // Get sizing for current breakpoint, if set
    const sizingForCurrentBreakpoint = props.sizing[breakpoints.smaller('md').value ? 0 : (breakpoints.smaller('xl').value ? 1 : 2)];

    const result = Array.isArray(sizingForCurrentBreakpoint)
        ? sizingForCurrentBreakpoint
        : (props.sizing.length ? props.sizing.join(' ') : repeat('1fr ', columns.value.length - 1) + 'min-content');
    // Set min-content on last column by default

    return `grid-template-columns: ${result};`;
});
</script>

<style lang="sass">
.m-table
    display: flex
    flex-direction: column

    @supports (grid-template-columns: subgrid)
        display: grid

.m-table--row
    display: grid
    gap: 1rem
    grid-template-columns: inherit
    border-bottom: var(--bs-gray-300) 1px solid

    &:last-child
        border-bottom: none

    @supports (grid-template-columns: subgrid)
        grid-column: 1 / -1
        grid-template-columns: subgrid !important
</style>
