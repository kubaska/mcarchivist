<template>
    <Modal ref="modal" :title="title" size-class="modal-lg" @hide="onHide" @show="onShow">
        <LoadingSpinner v-if="loading" />
        <div v-else>
            <div class="d-flex flex-column gap-2" v-if="projects.length">
                <Project v-for="project in projects" :project="project" route-name="archive.project" :platform-id="project.platform"
                         :show-platform-badge="true" :show-controls="true" :selectable="true" :show-archive-button="false"
                         :dropdown-options="dropdownOptions" :show-default="true" :with-navigation="false"
                         :selected="`${project.platform}|${project.remote_id}` === `${selectedProject?.platform}|${selectedProject?.remote_id}`"
                         @select="onProjectSelect(project)" @navigate="onProjectNavigate"
                />
            </div>
            <p v-else>No related projects found.</p>
        </div>

        <template #footer>
            <button class="btn btn-secondary" @click="modal.hide">Cancel</button>
            <button class="btn btn-primary" @click="onConfirm">{{ actionTitle }}</button>
        </template>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import api from "../../api/api";
import Project from "../Project.vue";
import Modal from "../base/Modal.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";
import {useConfigStore} from "../../stores/config";
import {useMcaRoute} from "../../hooks/route";
import {showErrorNotification} from "../../utils/notifications";

const props = defineProps({
    title: { type: String, required: true },
    actionTitle: { type: String, required: true }
});
const config = useConfigStore();
const route = useMcaRoute();

const modal = ref();
const project = ref(null);
const projects = ref([]);
const selectedProject = ref(null);
const loading = ref(false);
const dropdownOptions = [
    { name: 'Set as default', onClick: onProjectSelectDefault, disabled: project => project.default }
];

const emit = defineEmits(['confirm']);

async function getProjects() {
    loading.value = true;

    const res = await api.getRelatedProjects(project.value.id, {
        platform: route.isBrowse() ? project.value.platform : undefined
    });

    projects.value = res.data.data;
    loading.value = false;
}

function onProjectSelect(project) {
    selectedProject.value = project;
}

function onProjectSelectDefault(project) {
    api.setDefaultProject(project.project_id)
        .then(() => getProjects())
        .catch(err => {
            showErrorNotification('Error updating default project', 'Check browser console for details');
            console.log(err);
        });
}

function onProjectNavigate() {
    modal.value.hide();
}

function finish(err) {
    if (err) console.log('error', err);

    modal.value.hide();
}

function onConfirm() {
    emit('confirm', selectedProject.value.project_id, finish);
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
        selectedProject.value = d;
    }
});
</script>
