<template>
    <Modal title="Archive project" @hide="onHide" size-class="modal-lg" :dialog-scrollable="true" ref="modal">
        <template #header>
            <div class="w-100 d-flex justify-content-between pe-2 gap-3 flex-column flex-sm-row">
                <h5 class="modal-title flex-shrink-0">Archive project</h5>
                <MSelect v-model="selectedProjectId" class="w-100 m-select-mw-md-300" :options="projects" :disabled="loading" />
            </div>
        </template>

        <LoadingSpinner v-if="loading" />
        <div v-else-if="ar.getRules().length" class="d-flex flex-column gap-2">
            <ArchiveRule :rule="rule" :platform-id="selectedProject.platform" v-for="rule in ar.getRules()" @delete="ar.removeRule(rule.id)" :key="rule.id" />
        </div>
        <div v-else>
            <div class="alert alert-info">
                <p class="text-center m-0">Click 'Add rule' button, to add and configure archive rules.</p>
                <p class="text-center m-0">Alternatively, select a previously saved ruleset below.</p>
            </div>

            <div class="ruleset--list">
                <div class="form-check" v-for="ruleset in config.rulesets">
                    <input class="form-check-input" type="radio" v-model="selectedRuleset" :value="ruleset.id" name="ruleset" :id="'ruleset-'+ruleset.id">
                    <label class="form-check-label" :for="'ruleset-'+ruleset.id">
                        {{ ruleset.name }} ({{ ruleset.rules.length }} {{ ruleset.rules.length === 1 ? 'rule' : 'rules' }})
                    </label>
                </div>
            </div>
        </div>

        <template #footer>
            <button class="btn btn-primary" :disabled="isArchiving" @click="onAddRuleBtnClick">Add rule</button>
            <MButton :loading="isArchiving" @click="onArchiveBtnClick">{{ isArchiving ? 'Archiving Project...' : 'Archive' }}</MButton>
        </template>
    </Modal>
</template>

<script setup>
import {computed, ref, watch} from "vue";
import {useMcaRoute} from "../../hooks/route";
import {useConfigStore} from "../../stores/config";
import {useArchiveRules} from "../../hooks/archiveRules";
import { showErrorNotification } from "../../utils/notifications";
import api from "../../api/api";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import MSelect from "../base/MSelect.vue";
import ArchiveRule from "../ArchiveRule.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";

const config = useConfigStore();
const route = useMcaRoute();
const modal = ref(null);
const emit = defineEmits(['confirm']);
const projects = ref([]);
const selectedProjectId = ref(null);
const selectedProject = computed(() => selectedProjectId.value
    ? projects.value.find(p => p.id === selectedProjectId.value)
    : null
);
const ar = useArchiveRules();
const selectedRuleset = ref(null);
const isArchiving = ref(false);
const loading = ref(false);

function makeProjectOption(project, isMaster) {
    const ruleCount = project.archive_rules?.length ?? 0;
    const option = {
        id: `${isMaster ? '_mp_' : '_pr_'}_${project.platform}_${project.remote_id}`,
        remote_id: project.remote_id,
        platform: project.platform,
        name: project.name + ` (${ruleCount} rule${ruleCount === 1 ? '' : 's'})`,
        is_master: isMaster,
        archive_rules: project.archive_rules
    }

    if (isMaster) {
        option.badge = { name: 'Global', class: 'text-bg-light' };
    } else if (project.platform) {
        const platform = config.getPlatform(project.platform);
        option.badge = { name: platform.name, color: platform.theme_color };
    }

    return option;
}

function fetchProjects(project) {
    loading.value = true;
    api.getRelatedProjects(project.remote_id, { platform: project.platform })
        .then(response => {
            const firstProject = response.data.data.projects[0];
            projects.value = [
                makeProjectOption({
                    ...response.data.data,
                    remote_id: firstProject.remote_id,
                    platform: firstProject.platform
                }, true),
                ...response.data.data.projects.map(p => makeProjectOption(p, false))
            ];

            // Select opened project
            const projectIndex = response.data.data.projects.findIndex(
                p => project.remote_id === p.remote_id && project.platform === p.platform
            );
            selectedProjectId.value = projects.value[projectIndex + 1]?.id ?? projects.value[0].id;
        })
        .catch(err => {
            if (err.response.status === 404) {
                const fakeProject = {
                    remote_id: project.remote_id, name: project.name,
                    platform: project.platform, archive_rules: []
                };

                projects.value = [
                    makeProjectOption(fakeProject, true),
                    makeProjectOption(fakeProject, false)
                ];

                selectedProjectId.value = projects.value[1].id;
                return;
            }

            showErrorNotification('Error fetching projects', 'There was an error while trying to fetch projects.');
        })
        .finally(() => {
            loading.value = false;
        });
}

function onAddRuleBtnClick() {
    ar.addRule();
    selectedRuleset.value = null;
}

function finish(err) {
    if (err) {
        showErrorNotification('There was an error trying to archive project', 'Check browser console for details');
        console.log(err);
    } else {
        modal.value.hide();
        ar.reset();
    }
    isArchiving.value = false;
}

function onArchiveBtnClick() {
    isArchiving.value = true;
    const data = selectedRuleset.value
        ? { ruleset_id: selectedRuleset.value }
        : { rules: ar.getRulesForApi() };

    if (! selectedProject.value) {
        return showErrorNotification('Invalid project selected');
    }

    api.archiveProject(selectedProject.value.remote_id, {
        for_master_project: selectedProject.value.is_master,
        platform: selectedProject.value.platform,
        ...data
    }).then(res => {
        // Check for saved rules in response and every other merged project
        const projectHasArchiveRules = res.data.data.length > 0
            || projects.value.some(p => p.id !== selectedProject.value.id && p.archive_rules.length);
        emit('confirm', projectHasArchiveRules);
        modal.value.hide();
        ar.reset();
    })
    .catch(err => {
        showErrorNotification('There was an error trying to archive project', 'Check browser console for details');
        console.log(err);
    })
    .finally(() => {
        isArchiving.value = false;
    });
}

function onHide() {
    ar.reset();
    selectedRuleset.value = null;
    projects.value = [];
    selectedProjectId.value = null;
}

const show = () => modal.value.show();
const loadRules = (project) => {
    fetchProjects(project);
};

defineExpose({ show, loadRules });
watch(() => selectedProjectId.value, (projectId) => {
    // Load new rules
    const currentProject = projects.value.find(p => p.id === projectId);
    if (currentProject) ar.loadRules(currentProject.archive_rules);
});
</script>

<style lang="sass">
.ruleset--list
    display: grid
    grid-template-columns: 1fr

    @media (min-width: 992px)
        grid-template-columns: 1fr 1fr

@media (min-width: 576px)
    .m-select-mw-md-300
        max-width: 300px
</style>
