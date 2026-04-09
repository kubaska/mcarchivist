<template>
    <div v-if="loading">
        <LoadingSpinner  />
    </div>
    <div class="my-3" v-else-if="error">
        <p class="alert alert-danger">{{ error }}</p>
    </div>
    <div class="my-3" v-else-if="!data.length">
        <p class="alert alert-primary">This project does not list any {{ mode === 'dependants' ? 'dependants' : 'dependencies' }}.</p>
    </div>
    <div class="dependency-container gap-2 py-2" v-else>
        <Dependency v-for="project in data" :project="project" class="border p-1" @action="onDepAction" />
    </div>
</template>
<script setup>
import {onMounted, ref, watch} from "vue";
import api from "../../api/api";
import Dependency from "../../components/Dependency.vue";
import LoadingSpinner from "../../components/base/LoadingSpinner.vue";
import {useMcaRoute} from "../../hooks/route";
import {useRouter} from "vue-router";

const props = defineProps({
    project: { type: Object, required: true },
    mode: { type: String, required: true }
});

const route = useMcaRoute();
const router = useRouter();
const error = ref(null);
const loading = ref(false);
const data = ref([]);

function getData() {
    loading.value = true;
    error.value = null;
    const apiFn = props.mode === 'dependants' ? api.getProjectDependants : api.getProjectDependencies;

    apiFn(
        route.isArchive() ? props.project.project_id : props.project.remote_id,
        { archived_only: route.isArchive(), platform: props.project.platform }
    ).then(res => {
        data.value = res.data.data;
    })
    .catch(err => {
        error.value = err.response.data?.error ?? 'There was an error fetching data.';
    })
    .finally(() => loading.value = false);
}

function onDepAction(action, data) {
    switch (action) {
        case 'navigate':
            router.push({ name: route.getRouteForBase('project'), params: { source: route.params.source, id: data } });
            break;
    }
}

onMounted(getData);
watch(() => props.mode, getData);
watch(() => props.project, getData);
</script>

<style lang="sass">
.dependency-container
    display: grid
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr))

    @media (min-width: 1400px)
        grid-template-columns: 1fr 1fr 1fr
</style>
