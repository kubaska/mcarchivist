<template>
    <Modal ref="modal" title="Merge project" size-class="modal-lg" :dialog-scrollable="true" @hide="onHide" @show="onShow">
        <template v-if="step === 1">
            <input type="text" class="form-control mb-2" v-model="projectSearchName" @input="getProjectsDebounced">

            <LoadingSpinner v-if="loading" />
            <template v-else>
                <div class="d-flex flex-column gap-2" v-if="projects.length">
                    <Project v-for="project in projects" :project="project" route-name="archive.project"
                             :show-platform-badge="true" :show-controls="false" :selectable="true"
                             @select="onProjectSelect" @navigate="onProjectNavigate"
                    />
                </div>
                <p class="text-center mt-3 mb-1" v-else>No projects found.</p>
            </template>
        </template>
        <template v-else-if="step === 2">
            <p>Click the arrow icon to change merge direction, then confirm merge by pressing the Merge button.</p>
            <Project :project="selectedProject" route-name="archive.project"
                     :show-categories="false" :show-platform-badge="true" :show-controls="false"
                     @navigate="onProjectNavigate"
            />
            <p class="text-center m-0 cursor-pointer my-2" @click="mergeDirectionReversed = !mergeDirectionReversed">
                <fa-icon :icon="mergeDirectionReversed ? 'up-long' : 'down-long'" size="xl" />
            </p>
            <Project :project="project" route-name="archive.project"
                     :show-categories="false" :show-platform-badge="true" :show-controls="false"
                     @navigate="onProjectNavigate"
            />
        </template>
        <template v-else>
            <LoadingSpinner
                v-if="loading || mergeConfirmLoading"
                :text="mergeConfirmLoading ? 'Merging...' : 'Checking merge conditions...'"
                class="my-5"
            />
            <div v-else>
                <Project :project="mergeDirectionReversed ? project : selectedProject"
                         :show-categories="false" :show-platform-badge="true" :show-controls="false"
                         @navigate="onProjectNavigate"
                />
                <p class="alert alert-danger my-2 py-2">
                    <fa-icon icon="triangle-exclamation" class="me-2" />
                    <span>Following archive rules will be <span class="fw-bold">REMOVED</span> from the merged <span class="text-decoration-underline">global</span> project as a result of merge.</span>
                </p>
                <div class="d-flex flex-column gap-2">
                    <ArchiveRule v-for="rule in archiveRulesToRemove" :rule="rule" :editable="false" />
                </div>
            </div>
        </template>

        <template #footer>
            <button class="btn btn-secondary" @click="modal.hide">Cancel</button>
            <MButton class="btn btn-primary" @click="onConfirmMerge" :loading="mergeConfirmLoading" :disabled="loading || !selectedProject">
                {{ mergeConfirmLoading ? 'Merging...' : 'Merge' }}
            </MButton>
        </template>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import {debounce} from "lodash-es";
import {useMcaRoute} from "../../hooks/route";
import {showErrorNotification} from "../../utils/notifications";
import api from "../../api/api";
import Project from "../Project.vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import ArchiveRule from "../ArchiveRule.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";

const route = useMcaRoute();
const modal = ref();
const step = ref(1);
const project = ref(null);
const projectSearchName = ref('');
const selectedProject = ref(null);
const projects = ref([]);
const loading = ref(false);
const mergeConfirmLoading = ref(false);
const mergeDirectionReversed = ref(false);
const archiveRulesToRemove = ref([]);

const emit = defineEmits(['confirm']);
const getProjectsDebounced = debounce(() => getProjects(), 300);

async function getProjects() {
    loading.value = true;
    selectedProject.value = null;

    const res = await api.searchProjects({
        archived_only: true,
        exclude_remote: [project.value.platform, project.value.remote_id],
        query: projectSearchName.value,
    });

    projects.value = res.data.data;
    loading.value = false;
}

function onProjectSelect(project) {
    selectedProject.value = project;
    step.value++;
}

function onProjectNavigate() {
    modal.value.hide();
}

function onConfirmMerge() {
    if (step.value === 3) {
        return performMerge();
    }

    loading.value = true;
    const projectToMerge = mergeDirectionReversed.value ? project : selectedProject;
    api.getRelatedProjects(projectToMerge.value.remote_id, { platform: projectToMerge.value.platform })
        .then(res => {
            if (res.data.data.archive_rules.length) {
                archiveRulesToRemove.value = res.data.data.archive_rules;
                step.value++;
            } else {
                performMerge();
            }
        })
        .catch(err => {
            if (err.response.status === 404) {
                performMerge();
                return;
            }

            showErrorNotification(
                err.response.data?.error ?? 'There was an error fetching project data',
                err.response.data?.error ? 'Check browser console for details' : null
            );
            console.log(err);
        })
        .finally(() => {
            loading.value = false;
        });
}

function performMerge() {
    mergeConfirmLoading.value = true;
    return api.mergeProjects(route.isBrowse() ? project.value.remote_id : project.value.project_id, {
        platform: route.isBrowse() ? project.value.platform : undefined,
        merged_project_id: selectedProject.value.id,
        merge_direction_reverse: mergeDirectionReversed.value
    }).then(res => {
        emit('confirm', res.data.data);
        modal.value.hide();
    }).catch(err => {
        showErrorNotification('Failed to merge projects', 'Check browser console for details');
        console.log(err);
    })
    .finally(() => {
        mergeConfirmLoading.value = false;
    });
}

function onHide() {
    step.value = 1;
    selectedProject.value = null;
    projects.value = [];
    archiveRulesToRemove.value = [];
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
