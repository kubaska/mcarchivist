<template>
    <div class="d-flex flex-wrap gap-1">
        <span class="badge" :class="badge.type ? 'text-bg-'+badge.type : 'text-bg-primary'"
              v-for="badge in badges"
              :key="trackBy ? badge[trackBy] : badge"
        >{{ trackBy ? badge[trackBy] : badge }}</span>
    </div>
</template>

<script setup>
const props = defineProps({
    badges: { type: Array, required: true },
    trackBy: { type: String, required: true }
});
</script>
