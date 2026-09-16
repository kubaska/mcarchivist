<template>
    <div class="row my-2">
        <div class="col-md-4">
            <SearchFilters />
        </div>
        <div class="col-md-8">
            <div class="d-flex justify-content-end">
                <Pagination :current="projectsStore.pagination.page" :total="projectsStore.pagination.lastPage" @change="onPaginatorChange" />
            </div>

            <div v-if="error" class="text-center my-3">
                <p class="fs-5">Unexpected error occurred, check browser console for details</p>
                <button class="btn btn-primary" @click="getProjects()">Refresh</button>
            </div>
            <LoadingSpinner class="my-5" v-else-if="projectsLoading" />
            <div class="fs-4 text-center my-4" v-else-if="projectsStore.projects.length === 0">
                <p>No results matching criteria.</p>
            </div>
            <div class="d-flex flex-column gap-2 my-2" v-else>
                <Project v-for="project in projectsStore.projects" :project="project"
                         :route-name="route.getRouteForBase('project')"
                         :dropdown-options="projectDropdownOptions"
                         @archive="onArchive"
                />
            </div>

            <div class="d-flex justify-content-end mb-2">
                <Pagination :current="projectsStore.pagination.page" :total="projectsStore.pagination.lastPage" @change="onPaginatorChange" />
            </div>
        </div>

        <ArchiveModal ref="projectArchiveModal" @confirm="onArchiveConfirm" />
        <ProjectMergeModal ref="projectMergeModal" @confirm="onProjectMergeConfirm" />
    </div>
</template>

<script setup>
import {useRouter} from "vue-router";
import {useMcaRoute} from "../hooks/route";
import {debounce, omit} from "lodash-es";
import {useConfigStore} from "../stores/config";
import {useProjectsStore} from "../stores/projects";
import {computed, onActivated, onMounted, ref, watch} from "vue";
import Project from '../components/Project.vue';
import Pagination from "../components/base/Pagination.vue";
import SearchFilters from "../components/SearchFilters.vue";
import ArchiveModal from "../components/modals/ArchiveModal.vue";
import LoadingSpinner from "../components/base/LoadingSpinner.vue";
import ProjectMergeModal from "../components/modals/ProjectMergeModal.vue";

const route = useMcaRoute();
const router = useRouter();
const projectArchiveModal = ref(null);
const projectMergeModal = ref(null);
const config = useConfigStore();
const projectsStore = useProjectsStore();

const projectsLoading = ref(false);
const error = ref(null);
const dataSource = ref(route.getBase());
const inspectedProjectId = ref(null);

const projectDropdownOptions = [
    { name: 'Merge...', onClick: onProjectMergeBtnClick },
    { name: 'Open project page', link: project => project.project_url, linkNewTab: true }
];

const platform = computed(() => config.getPlatform(projectsStore.filters.platform));

function getProjects(options = {}) {
    projectsLoading.value = true;
    error.value = null;

    return projectsStore.getProjects(options)
        .catch(err => {
            console.log(err);
            error.value = 'Error';
        })
        .finally(() => {
            projectsLoading.value = false;
        });
}

const getProjectsDebounced = debounce(getProjects, 10, { trailing: true });

function updateRouteQuery() {
    router.push({
        name: route.getBase(),
        params: { source: platform.value ? platform.value.slug : '' },
        query: { ...omit(projectsStore.filtersSnakeCased, ['platform']), page: projectsStore.pagination.page }
    });
}

function onPaginatorChange(step) {
    projectsStore.pagination.page = step;
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 1);
    getProjects();
    updateRouteQuery();
}

function onArchive(project) {
    inspectedProjectId.value = project.id;
    projectArchiveModal.value.loadRules(project);
    projectArchiveModal.value.show();
}
function onArchiveConfirm(isArchived) {
    const project = projectsStore.projects.find(p => p.id === inspectedProjectId.value);
    if (project) {
        project.is_archiving = isArchived;
    } else {
        console.log('Unable to mark project as archived, as it is missing!');
    }
    inspectedProjectId.value = null;
}

function onProjectMergeBtnClick(project) {
    inspectedProjectId.value = project.id;
    projectMergeModal.value.setData(project);
    projectMergeModal.value.show();
}
function onProjectMergeConfirm(newProject, oldProject) {
    projectsStore.replaceProject(oldProject, newProject);
}

onMounted(() => {
    updateRouteQuery();
    getProjects();
});
onActivated(() => { projectsStore.resetActiveProject(); });

watch(projectsStore.filters, () => {
    projectsStore.pagination.page = 1;
    updateRouteQuery();
    getProjectsDebounced();
});
watch(route.getBase, (val) => {
    if (! ['archive', 'browse'].includes(route.name)) return;
    if (dataSource.value === val) return;
    dataSource.value = val;
    getProjectsDebounced();
});
</script>
