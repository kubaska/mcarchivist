<template>
    <div>
        <div class="d-flex flex-column flex-md-row gap-md-3 justify-content-between" v-if="projectsStore.project">
            <div class="d-flex gap-2 my-2">
                <img :src="projectsStore.project.logo" alt="Project logo" style="width: 6rem; height: 6rem;">
                <div class="d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-column">
                        <div class="d-flex gap-2 align-items-center">
                            <p class="mb-0 fs-5 fw-bold">{{ projectsStore.project.name }}</p>
                            <a :href="projectsStore.project.project_url" target="_blank" referrerpolicy="no-referrer">
                                <button class="btn btn-icon">
                                    <fa-icon icon="arrow-up-right-from-square" />
                                </button>
                            </a>
                            <button class="btn btn-icon" @click="onProjectSelectBtnClick" v-if="route.isArchive() && projectsStore.project.merged_projects_count > 1">
                                <fa-icon icon="arrow-right-arrow-left" />
                            </button>
                        </div>
                        <div class="lh-md"><span>{{ projectsStore.project.summary }}</span></div>
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        <PlatformBadge :platform="platform" v-if="platform" />
                        <span class="badge text-bg-secondary" v-for="type in getProjectTypesById(projectsStore.project.project_types)">{{ type.name }}</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-icon btn-icon-lg border" :class="{ 'bg-success-subtle': projectsStore.project.is_archiving }"
                        v-tooltip="'Archive'" @click="onArchiveBtnClick"
                >
                    <fa-icon icon="box-archive" size="lg" />
                </button>
                <button class="btn btn-icon btn-icon-lg border" v-tooltip="'Merge...'" @click="onMergeBtnClick">
                    <fa-icon icon="object-group" size="lg" />
                </button>
                <MDropdown :options="dropdownOptions">
                    <button class="btn btn-icon btn-icon-lg border" v-tooltip="'More...'">
                        <fa-icon icon="ellipsis-vertical" size="lg" />
                    </button>
                </MDropdown>
            </div>
        </div>
        <Placeholder :small="true" v-else />

        <ul class="nav nav-underline justify-content-center">
            <li class="nav-item">
                <router-link :to="{ name: route.getRouteForBase('project') }" class="nav-link">Description</router-link>
            </li>
            <li class="nav-item">
                <router-link :to="{ name: route.getRouteForBase('project.versions') }" class="nav-link">Versions</router-link>
            </li>
            <li class="nav-item">
                <router-link :to="{ name: route.getRouteForBase('project.dependencies') }" class="nav-link">Dependencies</router-link>
            </li>
            <li class="nav-item">
                <router-link :to="{ name: route.getRouteForBase('project.dependants') }" class="nav-link">Dependants</router-link>
            </li>
        </ul>

        <router-view v-slot="{ Component }">
            <KeepAlive :include="['ProjectDescriptionView', 'ProjectVersionsView', 'ProjectDependenciesView']" :key="projectsStore.project?.project_id ?? projectsStore.project?.id ?? route.params.id">
                <component :is="projectsStore.project ? Component : Placeholder" />
            </KeepAlive>
        </router-view>

        <ArchiveModal ref="archiveModal" @confirm="onArchiveConfirm" />
        <ProjectMergeModal ref="projectMergeModal" @confirm="onProjectMergeConfirm" />
        <ProjectUnmergeModal ref="projectUnmergeModal" @confirm="onProjectUnmergeConfirm" />
        <ProjectSelectModal ref="projectSelectModal" title="Select project" action-title="Select" @confirm="onProjectSelectConfirm" />
    </div>
</template>

<script setup>
import api from "../api/api";
import {useRouter} from "vue-router";
import {useMcaRoute} from "../hooks/route";
import {useConfigStore} from "../stores/config";
import {computed, onMounted, ref, watch} from "vue";
import {getProjectTypesById} from "../utils/utils";
import {displayNotFoundPage} from "../utils/errors";
import {useProjectsStore} from "../stores/projects";
import MDropdown from "../components/base/MDropdown.vue";
import Placeholder from "../components/base/Placeholder.vue";
import ArchiveModal from "../components/modals/ArchiveModal.vue";
import PlatformBadge from "../components/base/PlatformBadge.vue";
import ProjectMergeModal from "../components/modals/ProjectMergeModal.vue";
import ProjectSelectModal from "../components/modals/ProjectSelectModal.vue";
import ProjectUnmergeModal from "../components/modals/ProjectUnmergeModal.vue";

const route = useMcaRoute();
const router = useRouter();
const config = useConfigStore();
const projectsStore = useProjectsStore();
const project = ref(null);
const platform = computed(() => projectsStore.project
    ? config.getPlatform(projectsStore.project.platform)
    : config.getPlatformBySlug(route.params.source)
);

const archiveModal = ref(null);
const projectMergeModal = ref(null);
const projectUnmergeModal = ref(null);
const projectSelectModal = ref(null);

const dropdownOptions = computed(() => [
    { name: 'Unmerge...', disabled: projectsStore.project.merged_projects_count < 2, onClick: onUnmergeBtnClick }
]);

function getProject(options = {}) {
    return projectsStore.getProject(route.params.id, {
        archived_only: route.isArchive(),
        platform: route.isBrowse() ? platform.value.id : route.params.source,
        ...options
    }).catch(err => {
        if (err.response.status === 404) {
            return displayNotFoundPage('Project not found!');
        }

        console.log(err);
    });
}

function onProjectSelectBtnClick() {
    projectSelectModal.value.setData(projectsStore.project);
    projectSelectModal.value.show();
}
function onProjectSelectConfirm(selectedProject, finish) {
    if (projectsStore.project.id === selectedProject.id && projectsStore.project.platform === selectedProject.platform) {
        getProject({ project_id: selectedProject.project_id });
    } else {
        router.replace({ route: route.name, params: { id: selectedProject.id, source: selectedProject.platform, project_id: selectedProject.project_id } });
    }
    finish();
}

function onArchiveBtnClick() {
    archiveModal.value.loadRules(projectsStore.project);
    archiveModal.value.show();
}
function onArchiveConfirm(hasRules) {
    projectsStore.project.is_archiving = hasRules;
}

function onMergeBtnClick() {
    projectMergeModal.value.setData(projectsStore.project);
    projectMergeModal.value.show();
}
function onProjectMergeConfirm(mergedProject) {
    if (route.isBrowse() || projectsStore.project.id === mergedProject.id) {
        getProject();
    } else {
        router.replace({ route: route.name, params: { id: mergedProject.id, source: mergedProject.platform } });
    }
}

function onUnmergeBtnClick() {
    projectUnmergeModal.value.setData(projectsStore.project);
    projectUnmergeModal.value.show();
}
function onProjectUnmergeConfirm(finish, unmergedProjectId) {
    api.unmergeProject(unmergedProjectId)
        .then(() => getProject().then(() => finish()))
        .catch(err => finish(err));
}

onMounted(() => {
    if (route.isBrowse() && ! platform.value) {
        return displayNotFoundPage('Platform "'+route.params.source+'" not found!');
    }

    getProject();
});
watch(() => `${route.params.source};${route.params.id}`, () => {
    projectsStore.resetActiveProject();
    getProject();
    window.scrollTo(0, 0);
});
</script>
