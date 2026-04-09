<template>
    <div>
        <div class="ruleset-table" v-if="rulesets.length">
            <div class="ruleset-table--row py-2 d-none d-sm-grid fw-semibold">
                <span class="d-none d-sm-block">Name</span>
                <span class="d-none d-sm-block">Rule count</span>
                <span class="d-none d-sm-block">Date added</span>
                <span class="d-none d-sm-block"></span>
            </div>

            <div class="ruleset-table--row py-1" v-for="ruleset in rulesets">
                <div class="d-flex flex-column justify-content-center gap-2 d-sm-contents">
                    <div class="d-flex flex-row align-items-center gap-2 d-sm-contents">
                        <div class="pointer-events-none position-relative z-1 d-flex flex-column justify-content-center">
                            <div class="text-xs font-medium">{{ ruleset.name }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-column justify-content-center d-sm-contents">
                        <div class="d-flex flex-row flex-wrap align-items-center gap-1 d-xl-contents">
                            <div class="d-flex align-items-center">
                                <span>{{ ruleset.rules.length }} {{ ruleset.rules.length === 1 ? 'rule' : 'rules' }}</span>
                            </div>
                        </div>
                        <div class="d-flex flex-row flex-sm-column justify-content-start justify-content-sm-center gap-3 gap-sm-1 d-xl-contents">
                            <div class="z-1 d-flex align-items-center gap-1 text-nowrap font-medium align-self-xl-center">
                                <fa-icon icon="calendar" class="d-sm-none" />
                                <FormattedDate :date="ruleset.created_at" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center align-items-sm-start justify-content-end gap-1">
                    <button class="btn btn-icon" @click="emit('edit', ruleset.id)">
                        <fa-icon icon="pencil" />
                    </button>
                    <button class="btn btn-icon" @click="emit('delete', ruleset.id)">
                        <fa-icon icon="xmark" />
                    </button>
                </div>
            </div>
        </div>
        <div v-else>
            <p>No rulesets.</p>
        </div>
    </div>
</template>

<script setup>
import FormattedDate from "../base/FormattedDate.vue";

const props = defineProps({
    rulesets: { type: Array, required: true }
});
const emit = defineEmits(['edit', 'delete']);
</script>

<style lang="sass">
.ruleset-table
    display: flex
    flex-direction: column

    @supports (grid-template-columns: subgrid)
        display: grid
        grid-template-columns: 1fr min-content

        @media (min-width: 576px)
            grid-template-columns: auto auto auto auto min-content

        @media (min-width: 1200px)
            grid-template-columns: auto auto auto auto min-content

.ruleset-table--row
    display: grid
    gap: 1rem
    grid-template-columns: 1fr min-content
    border-bottom: var(--bs-gray-300) 1px solid

    &:last-child
        border-bottom: none

    @supports (grid-template-columns: subgrid)
        grid-column: 1/-1
        grid-template-columns: subgrid !important

    @media (min-width: 576px)
        grid-template-columns: auto 1fr 1fr 1fr min-content

    @media (min-width: 1200px)
        grid-template-columns: auto 1fr 1fr 1fr min-content
</style>
