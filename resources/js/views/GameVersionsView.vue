<template>
    <div>
        <ChangelogModal ref="changelogModal"></ChangelogModal>
        <CommonConfirmModal ref="revalidateModal" title="Confirm revalidate"></CommonConfirmModal>
        <FilesModal ref="filesModal" @delete-file="onFileDelete" />
        <VersionDeleteModal ref="deleteModal" @confirm="onVersionDeleteConfirm"></VersionDeleteModal>
        <VersionDeleteModal ref="fileDeleteModal" @confirm="onFileDeleteConfirm"></VersionDeleteModal>
        <Modal ref="settingsModal" title="Settings" size-class="modal-lg" @hide="resetSettings">
            <GameVersionComponentChooser v-model="settings[settingNamePrefix+'manual_archive.components']"
                                         :components="GAME_VERSION_COMPONENTS" title="Archive button behavior"
                                         description="Choose which components will be archived when you click 'Archive' button."
            />
            <hr/>

            <div class="d-flex flex-column gap-4">
                <SettingsIntervalControl v-model="settings"
                                         :action-setting-name="settingNamePrefix+'automatic_archive'"
                                         :interval-setting-name="settingNamePrefix+'automatic_archive.interval'"
                                         :interval-unit-setting-name="settingNamePrefix+'automatic_archive.interval_unit'"
                />

                <GameVersionComponentChooser v-model="settings[settingNamePrefix+'automatic_archive.components']"
                                             :components="GAME_VERSION_COMPONENTS" title="Components"
                                             description="Choose which components will be automatically archived."
                />

                <VersionTypeChooserSetting v-model="settings[settingNamePrefix+'automatic_archive.release_types']"
                                           :types="getMojangVersionTypes()" title="Release Types"
                                           description="Choose which release types will be automatically archived."
                />
            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <MButton @click="onSettingsSaveBtnClick" :loading="settingsLoading" :disabled="!areSettingsChanged">Save changes</MButton>
            </template>
        </Modal>

        <VersionableHeader name="Game Versions" :can-update-index="true" :can-use-settings="true"
                           :update-index-task-id="updateIndexTaskId"
                           :update-index-fn="api.updateGameVersionIndex"
                           @refresh-data="getVersions()" @open-settings="onSettingsBtnClick"
        />

        <div v-if="versions && versions.length">
            <ProjectVersions :versions="versions" :actions="onVersionAction" :pagination="pagination"
                         :columns="columns" task-id-prefix="mc:je" :filter-game-versions="false"
                         :filter-loaders="false" :release-types="getMojangVersionTypes()" @filters="onFiltersChange"
            />
        </div>
        <div v-else-if="versions && versions.length === 0">
            <p>No versions found, try updating index</p>
        </div>
        <p v-else>Loading..</p>
    </div>
</template>

<script setup>
import {onMounted, ref} from "vue";
import api from "../api/api";
import Modal from "../components/base/Modal.vue";
import MButton from "../components/base/MButton.vue";
import FilesModal from "../components/modals/FilesModal.vue";
import ProjectVersions from "../components/ProjectVersions.vue";
import ChangelogModal from "../components/modals/ChangelogModal.vue";
import CommonConfirmModal from "../components/modals/CommonConfirmModal.vue";
import VersionDeleteModal from "../components/modals/VersionDeleteModal.vue";
import VersionableHeader from "../components/versionables/VersionableHeader.vue";
import SettingsIntervalControl from "../components/settings/SettingsIntervalControl.vue";
import VersionTypeChooserSetting from "../components/settings/VersionTypeChooserSetting.vue";
import GameVersionComponentChooser from "../components/settings/GameVersionComponentChooser.vue";
import {useSettings} from "../hooks/settings";
import {useConfigStore} from "../stores/config";
import {GAME_VERSION_COMPONENTS, getMojangVersionTypes} from "../utils/utils";

const changelogModal = ref(null);
const revalidateModal = ref(null);
const filesModal = ref(null);
const deleteModal = ref(null);
const fileDeleteModal = ref(null);
const settingsModal = ref(null);

const columns = {
    'Components': { 'Components': 'components' },
    'Published': { 'Published': 'publishedDate' }
};
const config = useConfigStore();
const versions = ref(null);
const pagination = ref(null);
const settingNamePrefix = 'game_versions.';
const updateIndexTaskId = 'game-versions-update-index';
const { settings, settingsLoading, areSettingsChanged, saveSettings, resetSettings } = useSettings(settingNamePrefix);

async function getVersions(filters = {}) {
    const r = await api.getGameVersions(filters);

    versions.value = r.data.data;
    pagination.value = r.data.meta;
}

function onFiltersChange(filters) {
    getVersions(filters);
}

function onSettingsBtnClick() {
    settingsModal.value.show();
}
function onSettingsSaveBtnClick() {
    saveSettings().then(() => settingsModal.value.hide());
}

function onVersionAction(type, data) {
    switch (type) {
        case 'get-archivable-components':
            return api.getGameVersionFiles(data.id);
        case 'archive':
            return api.archiveGameVersion(data.id, {
                components: config.getSetting(settingNamePrefix+'manual_archive.components', ['*'])
            });
        case 'archive-components':
            return api.archiveGameVersion(data.model.id, { components: data.components });
        case 'changelog':
            changelogModal.value.setChangelog(data.changelog);
            changelogModal.value.show();
            return;
        case 'delete':
            deleteModal.value.setData(data);
            deleteModal.value.show();
            return;
        case 'files':
            filesModal.value.setData(data, 'game');
            return filesModal.value.show();
        case 'revalidate':
            revalidateModal.value.setData('This version is already archived. Do you wish to revalidate all files?', data);
            revalidateModal.value.show();
            return revalidateModal.value.awaitChoice()
                .then(modal => {
                    return api.revalidateGameVersion(data.id)
                        .then(() => {
                            modal.finish();
                            return true;
                        }).catch(e => {
                            modal.finish(e);
                            return false;
                        });
                })
                .catch(() => { /* cancelled... */ });
        default:
            console.log('unknown action type: ', type);
    }
}

function onVersionDeleteConfirm(version, file, finish) {
    api.deleteGameVersion(version.id)
        .then(r => {
            finish();

            const ver = versions.value.find(v => v.id === version.id);
            if (ver) {
                ver.files = [];
                ver.local = false;
            }
        })
        .catch(err => finish(err));
}

function onFileDelete(version, file) {
    filesModal.value.hide();
    fileDeleteModal.value.setData(version, file);
    fileDeleteModal.value.show();
}

function onFileDeleteConfirm(version, file, finish) {
    api.deleteGameVersionFile(version.id, file.id)
        .then(res => {
            finish();

            const ver = versions.value.find(v => v.id === version.id);
            if (ver) {
                ver.files = ver.files.filter(f => f.id !== file.id);
            }
        })
        .catch(err => finish(err));
}

onMounted(() => {
    getVersions();
});
</script>
