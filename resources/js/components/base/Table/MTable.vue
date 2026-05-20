<template>
    <div class="m-table" :style="`--m-table-columns: ${columns[0].length - 1}; --m-table-columns-md: ${columns[1].length - 1}; --m-table-columns-xl: ${columns[2].length - 1};`">
        <div class="m-table--row py-2 d-grid fw-semibold">
            <slot name="header">
                <span v-for="column in columns[0]" class="d-block d-md-none">{{ column }}</span>
                <span v-for="column in columns[1]" class="d-none d-md-block d-lg-block d-xl-none">{{ column }}</span>
                <span v-for="column in columns[2]" class="d-none d-xl-block">{{ column }}</span>
            </slot>
        </div>

        <slot></slot>
    </div>
</template>
<script setup>
const props = defineProps({
    columns: { type: Array, required: false, default: [] }
});
</script>

<style lang="sass">
.m-table
    display: flex
    flex-direction: column

    @supports (grid-template-columns: subgrid)
        display: grid
        grid-template-columns: repeat(var(--m-table-columns, 2), auto) min-content

        @media (min-width: 768px)
            grid-template-columns: repeat(var(--m-table-columns-md, 3), auto) min-content

        @media (min-width: 1200px)
            grid-template-columns: repeat(var(--m-table-columns-xl, 4), auto) min-content

.m-table--row
    display: grid
    gap: 1rem
    grid-template-columns: repeat(var(--m-table-columns, 2), 1fr) min-content
    border-bottom: var(--bs-gray-300) 1px solid

    &:last-child
        border-bottom: none

    @supports (grid-template-columns: subgrid)
        grid-column: 1 / -1
        grid-template-columns: subgrid !important

    @media (min-width: 768px)
        grid-template-columns: repeat(var(--m-table-columns-md, 3), 1fr) min-content

    @media (min-width: 1200px)
        grid-template-columns: repeat(var(--m-table-columns-xl, 4), 1fr) min-content
</style>
