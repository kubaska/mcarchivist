<template>
    <span>{{ formatter.format(number) }}</span>
</template>

<script setup>
import {useNumberFormatter} from "../../hooks/formatter";

const props = defineProps({
    number: { type: Number, required: true }
});

const formatter = useNumberFormatter();
</script>
