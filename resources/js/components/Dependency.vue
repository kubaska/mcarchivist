<template>
    <div class="d-flex gap-2">
        <img style="width: 3rem; height: 3rem;" :src="project.logo" :alt="project.name + ' logo'">
        <div class="d-flex flex-column justify-content-between">
            <div class="d-flex flex-column">
                <a href="#" @click.prevent="$emit('action', 'navigate', project.id)">
                    <strong>{{ project.name }}</strong>
                </a>
                <span class="line-clamp-2">{{ project.summary }}</span>
            </div>
            <div>
                <div class="fw-semibold" v-for="version in project.dependency_versions">
                    <p class="m-0">{{ version.name }}<span v-if="project.dependency_type"> is {{ project.dependency_type }}</span></p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    project: { type: Object, required: true }
});

const emit = defineEmits(['action']);
</script>
