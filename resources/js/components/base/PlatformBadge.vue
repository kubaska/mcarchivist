<template>
    <span class="badge" :style="`background-color:${platform.theme_color};`">{{ platform.name }}</span>
</template>

<script setup>
import {useConfigStore} from "../../stores/config";
import {computed} from "vue";

const props = defineProps({
    platformId: { type: String, required: false },
    platform: { type: Object, required: false }
});
const config = useConfigStore();
const platform = computed(() =>
    props.platform
    ?? config.getPlatform(props.platformId)
    ?? { name: 'Missing Platform', theme_color: '#ff0000' }
);
</script>
