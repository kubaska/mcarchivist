<template>
    <p class="placeholder-glow">
        <span v-for="size in sizes" class="placeholder" :class="size"></span>
    </p>
</template>

<script setup>
import {computed} from "vue";

const props = defineProps({
    small: { type: Boolean, required: false, default: false }
});

const sizes = computed(() => (props.small ? [5, 8, 6] : [10, 7, 9]).map(size => 'col-'+size));
</script>
