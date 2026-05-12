<template>
    <Modal ref="modal" title="Select project to unmerge" size-class="modal-lg" @hide="onHide" @show="onShow">
        <LoadingSpinner v-if="loading" />
        <div v-else>
            <div class="d-flex flex-column gap-2" v-if="projects.length">
                <Project v-for="project in projects" :project="project" route-name="archive.project"
                         :show-platform-badge="true" :show-controls="false" :selectable="true" :with-navigation="false"
                         :selected="`${project.platform}|${project.remote_id}` === `${selectedProject?.platform}|${selectedProject?.remote_id}`"
                         @select="onProjectSelect(project)" @navigate="onProjectNavigate"
                />
            </div>
            <p v-else>No related projects found.</p>
        </div>

        <template #footer>
            <button class="btn btn-secondary" @click="modal.hide">Cancel</button>
            <MButton class="btn btn-danger" @click="onConfirmUnmerge" :loading="unmergeConfirmLoading" :disabled="! selectedProject">
                {{ unmergeConfirmLoading ? 'Unmerging...' : 'Unmerge' }}
            </MButton>
        </template>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import api from "../../api/api";
import Project from "../Project.vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";
import {useMcaRoute} from "../../hooks/route";
import { showErrorNotification } from "../../utils/notifications";

const route = useMcaRoute();

const modal = ref();
const project = ref(null);
const projects = ref([]);
const selectedProject = ref(null);
const loading = ref(false);
const unmergeConfirmLoading = ref(false);

const emit = defineEmits(['confirm']);

async function getProjects() {
    loading.value = true;

    const res = await api.getRelatedProjects(project.value.id, {
        platform: route.isBrowse() ? project.value.platform : undefined,
    });

    projects.value = route.isBrowse()
        ? res.data.data.projects.filter(p => !(p.remote_id === project.value.remote_id && p.platform === project.value.platform))
        : res.data.data.projects.filter(p => p.project_id !== project.value.project_id);
    loading.value = false;
}

function onProjectSelect(project) {
    selectedProject.value = project;
}

function onProjectNavigate() {
    modal.value.hide();
}

function finish(err) {
    if (err) {
        showErrorNotification('There was an error while trying to unmerge project', 'Check browser console for details');
        console.log(err);
    } else {
        unmergeConfirmLoading.value = false;
        modal.value.hide();
    }
}

function onConfirmUnmerge() {
    if (! selectedProject.value) return;
    unmergeConfirmLoading.value = true;

    emit('confirm', finish, selectedProject.value.project_id);
}

function onHide() {
    selectedProject.value = null;
    projects.value = [];
}

function onShow() {
    getProjects();
}

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (d) => {
        project.value = d;
    }
});
</script>
