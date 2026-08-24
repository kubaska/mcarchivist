<template>
    <div>
        <div>
            <h3 class="my-2">Loaders</h3>
        </div>
        <hr class="mt-0" />

        <LoaderGroup v-if="promoted.length" :loaders="promoted" />
        <LoaderGroup v-if="notPromotedButHasVersions.length" header="Has archived versions" :loaders="notPromotedButHasVersions" />
        <LoaderGroup v-if="other.length" header="Without automatic archiving support" :loaders="other" />

        <div v-if="!promoted.length && !notPromotedButHasVersions.length && !other.length">
            <p class="text-center">No loaders added yet. Try archiving some projects!</p>
        </div>
    </div>
</template>

<script setup>
import {computed} from "vue";
import {useConfigStore} from "../stores/config";
import LoaderGroup from "../components/LoaderGroup.vue";

const config = useConfigStore();

const promoted = computed(() => config.loaders.filter(l => l.promoted));
const notPromotedButHasVersions = computed(() => config.loaders.filter(l => !l.promoted && l.version_count > 0));
const other = computed(() => config.loaders.filter(l => !l.promoted && l.version_count === 0));
</script>
