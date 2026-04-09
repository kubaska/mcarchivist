<template>
    <span v-if="date" v-tooltip="relativeDate.formatted">{{ relativeDate.relative }}</span>
    <span v-else>-</span>
</template>

<script setup>
import {useRelativeDate} from "../../hooks/date";

const props = defineProps({
    date: { type: String, required: false }
});

const relativeDate = useRelativeDate(props.date);
</script>
