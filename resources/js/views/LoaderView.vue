<template>
    <div v-if="loader">
        <ChangelogModal ref="changelogModal"></ChangelogModal>
        <CommonConfirmModal ref="revalidateModal" title="Confirm revalidate"></CommonConfirmModal>
        <FilesModal ref="filesModal" @delete-file="onFileDelete" />
        <VersionDeleteModal ref="deleteModal" @confirm="onVersionDeleteConfirm"></VersionDeleteModal>
        <VersionDeleteModal ref="fileDeleteModal" @confirm="onFileDeleteConfirm"></VersionDeleteModal>
        <Modal ref="settingsModal" v-if="loader.promoted" title="Loader settings" @hide="resetSettings" size-class="modal-lg">
            <div class="d-flex flex-column" v-if="settings[settingNamePrefix+'manual_archive.components'] !== undefined">
                <GameVersionComponentChooser v-model="settings[settingNamePrefix+'manual_archive.components']"
                                             :components="components" title="Archive button behavior"
                                             description="Choose which components will be archived when you click 'Archive' button."
                />
                <hr/>
            </div>

            <div class="d-flex flex-column gap-4">
                <SettingsIntervalControl v-model="settings"
                                         :action-setting-name="settingNamePrefix+'automatic_archive'"
                                         :interval-setting-name="settingNamePrefix+'automatic_archive.interval'"
                                         :interval-unit-setting-name="settingNamePrefix+'automatic_archive.interval_unit'"
                />

                <GenericRadioSetting v-if="settings[settingNamePrefix+'automatic_archive.filter'] !== undefined"
                                     v-model="settings[settingNamePrefix+'automatic_archive.filter']"
                                     :options="versionFilter" title="Version filter"
                                     description="Choose which loader versions will be automatically archived, for each Minecraft version."
                />

                <GameVersionComponentChooser v-if="settings[settingNamePrefix+'automatic_archive.components'] !== undefined"
                                             v-model="settings[settingNamePrefix+'automatic_archive.components']"
                                             :components="components" title="Components"
                                             description="Choose which components will be automatically archived."
                />

                <VersionTypeChooserSetting v-if="settings[settingNamePrefix+'automatic_archive.release_types'] !== undefined"
                                           v-model="settings[settingNamePrefix+'automatic_archive.release_types']"
                                           :types="archivableGameVersionTypes" title="Release Types"
                                           description="Choose Minecraft release types for which loader versions will be automatically archived."
                />

                <div class="row" v-if="settings[settingNamePrefix+'automatic_archive.remove_old'] !== undefined">
                    <div class="col-12 col-md-4">
                        <p class="m-0 fw-semibold">Remove old versions</p>
                        <span class="fs-7 text-muted">Should old versions be deleted if they no longer meet archiving criteria?</span>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input class="form-check-input" type="checkbox" :value="true"
                                       v-model="settings[settingNamePrefix+'automatic_archive.remove_old']"
                                />Remove old versions when they no longer meet archiving criteria
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <MButton @click="onSettingsSaveBtnClick" :loading="settingsLoading" :disabled="!areSettingsChanged">Save changes</MButton>
            </template>
        </Modal>

        <VersionableHeader :name="activeLoader.name" :can-update-index="loader.promoted"
                           :can-use-settings="loader.promoted" :can-revalidate="false"
                           :update-index-task-id="updateIndexTaskId"
                           :update-index-fn="(options) => api.updateLoaderIndex(activeLoader.id, options)"
                           @refresh-data="getData(1)" @open-settings="onSettingsBtnClick"
        />

        <ProjectVersions v-if="versions !== null" :versions="versions" :actions="onVersionAction" :pagination="pagination"
                     :columns="columns" :task-id-prefix="loader.slug" :filter-loaders="false"
                     :filter-release-types="releaseTypes.length > 1" :release-types="releaseTypes"
                     @filters="onFiltersChange"
        />
        <div v-else>Loading...</div>
    </div>
</template>

<script setup>
import {computed, onMounted, ref} from "vue";
import api from "../api/api";
import Modal from "../components/base/Modal.vue";
import MButton from "../components/base/MButton.vue";
import ProjectVersions from "../components/ProjectVersions.vue";
import VersionableHeader from "../components/versionables/VersionableHeader.vue";
import GenericRadioSetting from "../components/settings/GenericRadioSetting.vue";
import SettingsIntervalControl from "../components/settings/SettingsIntervalControl.vue";
import VersionTypeChooserSetting from "../components/settings/VersionTypeChooserSetting.vue";
import GameVersionComponentChooser from "../components/settings/GameVersionComponentChooser.vue";
import FilesModal from "../components/modals/FilesModal.vue";
import ChangelogModal from "../components/modals/ChangelogModal.vue";
import VersionDeleteModal from "../components/modals/VersionDeleteModal.vue";
import CommonConfirmModal from "../components/modals/CommonConfirmModal.vue";
import {useRoute, useRouter} from "vue-router";
import {useConfigStore} from "../stores/config";
import {
    getLoaderArchivableGameVersionTypes,
    getLoaderArchiveFilter,
    getLoaderComponents, getLoaderReleaseTypes,
} from "../utils/utils";
import {useSettings} from "../hooks/settings";
import {displayNotFoundPage} from "../utils/errors";

const changelogModal = ref(null);
const revalidateModal = ref(null);
const filesModal = ref(null);
const deleteModal = ref(null);
const fileDeleteModal = ref(null);
const settingsModal = ref(null);

const config = useConfigStore();
const route = useRoute();
const router = useRouter();
const columns = {
    'Compatibility': { 'Game Versions': 'gameVersions', 'Components': 'components' },
    'Published': { 'Published': 'publishedDate' }
};
const loader = config.loaders.find(_loader => _loader.slug === route.params.slug);
const selectedComponent = ref(null);
const activeLoader = computed(() => selectedComponent.value ?? loader);
const versions = ref(null);
const pagination = ref(null);
const components = computed(() => getLoaderComponents(loader?.slug));
const settingNamePrefix = computed(() => `loaders.${activeLoader.value.slug}.`);
const { settings, settingsLoading, areSettingsChanged, saveSettings, resetSettings } = useSettings(settingNamePrefix);
const archivableGameVersionTypes = computed(() => getLoaderArchivableGameVersionTypes(activeLoader.value.name));
const releaseTypes = computed(() => getLoaderReleaseTypes(activeLoader.value.name));
const versionFilter = computed(() => getLoaderArchiveFilter(activeLoader.value.name));
const updateIndexTaskId = computed(() => 'loader-update-index;'+activeLoader.value.id);

function getData(page, filters = {}) {
    if (! loader) return;
    api.getLoaderVersions(activeLoader.value.id, { page, ...filters })
        .then(response => {
            versions.value = response.data.data;
            pagination.value = response.data.meta;
        });
}

function onSettingsBtnClick() {
    settingsModal.value.show();
}
function onSettingsSaveBtnClick() {
    saveSettings().then(() => settingsModal.value.hide());
}

function onFiltersChange(filters) {
    getData(1, filters);
}

function onVersionAction(type, data) {
    switch (type) {
        case 'get-archivable-components':
            return api.getLoaderFiles(activeLoader.value.id, data.id);
        case 'archive':
            return api.archiveLoaderVersion(activeLoader.value.id, data.id, {
                components: config.getSetting(settingNamePrefix.value+'manual_archive.components', ['*'])
            });
        case 'archive-components':
            return api.archiveLoaderVersion(activeLoader.value.id, data.model.id, {
                components: data.components
            });
        case 'changelog':
            changelogModal.value.setChangelog(data.changelog);
            changelogModal.value.show();
            return;
        case 'delete':
            deleteModal.value.setData(data);
            deleteModal.value.show();
            return;
        case 'files':
            filesModal.value.setData(data, 'loaders');
            filesModal.value.show();
            return;
        case 'revalidate':
            revalidateModal.value.setData('This version is already archived. Do you wish to revalidate all files?', data);
            revalidateModal.value.show();
            return revalidateModal.value.awaitChoice()
                .then(modal => {
                    return api.revalidateLoaderVersion(activeLoader.value.id, data.id)
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
            return console.log('Invalid action: ' + type);
    }
}

function onVersionDeleteConfirm(version, file, finish) {
    api.deleteLoaderVersion(activeLoader.value.id, version.id)
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
    api.deleteLoaderFile(activeLoader.value.id, version.id, file.id)
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
    if (! loader) {
        displayNotFoundPage('Loader not found!');
    }

    getData(1);
});
</script>
