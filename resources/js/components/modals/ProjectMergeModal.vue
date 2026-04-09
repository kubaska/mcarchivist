<template>
    <Modal ref="modal" title="Merge project" size-class="modal-lg" @hide="onHide" @show="onShow">
        <template v-if="!selectedProject">
            <input type="text" class="form-control mb-2" v-model="projectSearchName" @input="getProjectsDebounced">

            <LoadingSpinner v-if="loading" />
            <template v-else>
                <div class="d-flex flex-column gap-2" v-if="projects.length">
                    <Project v-for="project in projects" :project="project" route-name="archive.project" :platform-id="project.platform"
                             :show-platform-badge="true" :show-controls="false" :selectable="true"
                             @select="onProjectSelect" @navigate="onProjectNavigate"
                    />
                </div>
                <p class="text-center mt-3 mb-1" v-else>No projects found.</p>
            </template>
        </template>
        <template v-else>
            <p>Click the arrow icon to change merge direction, then confirm merge by pressing the Merge button.</p>
            <Project :project="selectedProject" route-name="archive.project" :platform-id="selectedProject.platform"
                     :show-categories="false" :show-platform-badge="true" :show-controls="false"
                     @navigate="onProjectNavigate"
            />
            <p class="text-center m-0 cursor-pointer my-2" @click="mergeDirectionReversed = !mergeDirectionReversed">
                <fa-icon :icon="mergeDirectionReversed ? 'up-long' : 'down-long'" size="xl" />
            </p>
            <Project :project="project" route-name="archive.project" :platform-id="project.platform"
                     :show-categories="false" :show-platform-badge="true" :show-controls="false"
                     @navigate="onProjectNavigate"
            />
        </template>

        <template #footer>
            <button class="btn btn-secondary" @click="modal.hide">Cancel</button>
            <MButton class="btn btn-danger" @click="onConfirmMerge" :loading="mergeConfirmLoading" :disabled="!selectedProject">
                {{ mergeConfirmLoading ? 'Merging...' : 'Merge' }}
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
import {debounce} from "lodash-es";
import {useMcaRoute} from "../../hooks/route";

const route = useMcaRoute();

const modal = ref();
const project = ref(null);
const projectSearchName = ref('');
const selectedProject = ref(null);
const projects = ref([]);
const loading = ref(false);
const mergeConfirmLoading = ref(false);
const mergeDirectionReversed = ref(false);

const emit = defineEmits(['confirm']);
const getProjectsDebounced = debounce(() => getProjects(), 300);

async function getProjects() {
    loading.value = true;
    selectedProject.value = null;

    let options = {
        query: projectSearchName.value,
        archived_only: true,
    };

    if (route.isBrowse()) options.exclude_remote = [project.value.platform, project.value.id];
    else options.exclude_ids = [project.value.id];

    const res = await api.searchProjects(options);

    projects.value = res.data.data;
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
        mergeConfirmLoading.value = false;
        modal.value.hide();
    }
}

function onConfirmMerge() {
    mergeConfirmLoading.value = true;

    emit('confirm', finish, {
        project_id: project.value.id,
        project_is_remote: route.isBrowse(),
        project_platform: project.value.platform,
        merged_project_id: selectedProject.value.id,
        merge_direction_reverse: mergeDirectionReversed.value
    });
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
        projectSearchName.value = d.name;
    }
});
</script>
