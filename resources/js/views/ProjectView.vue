<template>
    <div>
        <div class="d-flex flex-column flex-md-row gap-md-3 justify-content-between" v-if="project">
            <div class="d-flex gap-2 my-2">
                <img :src="project.logo" alt="Project logo" style="width: 6rem; height: 6rem;">
                <div class="d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-column">
                        <div class="d-flex gap-2 align-items-center">
                            <p class="mb-0 fs-5 fw-bold">{{ project.name }}</p>
                            <a :href="project.project_url" target="_blank">
                                <button class="btn btn-icon">
                                    <fa-icon icon="arrow-up-right-from-square" />
                                </button>
                            </a>
                            <button class="btn btn-icon" @click="onProjectSelectBtnClick" v-if="route.isArchive() && project.merged_projects_count > 1">
                                <fa-icon icon="arrow-right-arrow-left" />
                            </button>
                        </div>
                        <div class="lh-md"><span>{{ project.summary }}</span></div>
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        <PlatformBadge :platform="platform" v-if="platform" />
                        <span class="badge text-bg-secondary" v-for="type in getProjectTypesById(project.project_types)">{{ type.name }}</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-icon btn-icon-lg border" :class="{ 'bg-success-subtle': project.archive_rules.length }"
                        v-tooltip="'Archive'" @click="onArchiveRulesetBtnClick"
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
                <router-link :to="{ name: route.getRouteForBase('project') }" class="nav-link" active-class="" exact-active-class="active">Description</router-link>
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
            <KeepAlive :include="['ProjectDescriptionView', 'ProjectVersionsView', 'ProjectDependenciesView']" :key="project?.id ?? route.params.id">
                <component :is="project ? Component : Placeholder" :project="project" />
            </KeepAlive>
        </router-view>

        <ArchiveModal ref="archiveRulesModal" :platform-id="platform?.id" @confirm="onArchiveRulesetConfirm" />
        <ProjectMergeModal ref="projectMergeModal" @confirm="onProjectMergeConfirm" />
        <ProjectUnmergeModal ref="projectUnmergeModal" @confirm="onProjectUnmergeConfirm" />
        <ProjectSelectModal ref="projectSelectModal" title="Select project" action-title="Select" @confirm="onProjectSelectConfirm" />
    </div>
</template>

<script setup>
import {computed, onMounted, ref, watch} from "vue";
import api from "../api/api";
import MDropdown from "../components/base/MDropdown.vue";
import Placeholder from "../components/base/Placeholder.vue";
import ArchiveModal from "../components/modals/ArchiveModal.vue";
import PlatformBadge from "../components/base/PlatformBadge.vue";
import ProjectMergeModal from "../components/modals/ProjectMergeModal.vue";
import ProjectSelectModal from "../components/modals/ProjectSelectModal.vue";
import ProjectUnmergeModal from "../components/modals/ProjectUnmergeModal.vue";
import {useStore} from "../stores/store";
import {useRouter} from "vue-router";
import {useMcaRoute} from "../hooks/route";
import {getProjectTypesById} from "../utils/utils";
import {useConfigStore} from "../stores/config";
import {displayNotFoundPage} from "../utils/errors";

const route = useMcaRoute();
const router = useRouter();
const store = useStore();
const config = useConfigStore();
const project = ref(null);
const platform = computed(() => project.value
    ? config.getPlatform(project.value.platform)
    : config.getPlatformBySlug(route.params.source)
);

const archiveRulesModal = ref(null);
const projectMergeModal = ref(null);
const projectUnmergeModal = ref(null);
const projectSelectModal = ref(null);

const dropdownOptions = computed(() => [
    { name: 'Unmerge...', disabled: project.value.merged_projects_count < 2, onClick: onUnmergeBtnClick }
]);

function getProject(options = {}) {
    if (store.project) {
        project.value = store.project;
        store.project = null;
    } else {
        project.value = null;
    }

    if (route.isBrowse()) options.platform = platform.value.id;
    return api.getProject(route.params.id, { archived_only: route.isArchive(), ...options })
        .then(response => {
            project.value = response.data.data;
        })
        .catch(err => {
            if (err.response.status === 404) {
                displayNotFoundPage('Project not found!');
            }
        });
}

function onArchiveRulesetBtnClick() {
    if (project.value.archive_rules.length) {
        archiveRulesModal.value.loadRules(project.value.archive_rules);
    }

    archiveRulesModal.value.show();
}
function onArchiveRulesetConfirm(rules, finish) {
    api.archiveProject(route.isArchive() ? project.value.project_id : project.value.remote_id, {
        platform_id: project.value.platform, archived_only: route.isArchive(), ...rules
    }).then(res => {
        finish();
        project.value.archive_rules = res.data.data;
    })
    .catch(err => {
        finish('An error occurred');
    });
}
function onMergeBtnClick() {
    projectMergeModal.value.setData(project.value);
    projectMergeModal.value.show();
}
function onUnmergeBtnClick() {
    projectUnmergeModal.value.setData(project.value);
    projectUnmergeModal.value.show();
}
function onProjectSelectBtnClick() {
    projectSelectModal.value.setData(project.value);
    projectSelectModal.value.show();
}

function onProjectMergeConfirm(finish, options) {
    api.mergeProjects(options)
        .then(() => getProject().then(() => finish()))
        .catch(err => finish(err));
}
function onProjectUnmergeConfirm(finish, unmergedProjectId) {
    api.unmergeProject(unmergedProjectId)
        .then(() => getProject().then(() => finish()))
        .catch(err => finish(err));
}
function onProjectSelectConfirm(projectId, finish) {
    getProject({ project_id: projectId });
    finish();
}

onMounted(() => {
    if (route.isBrowse() && ! platform.value) {
        return displayNotFoundPage('Platform "'+route.params.source+'" not found!');
    }

    getProject();
});
watch(() => `${route.params.source};${route.params.id}`, () => {
    getProject();
    window.scrollTo(0, 0);
});
</script>
