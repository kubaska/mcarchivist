<template>
    <div class="my-3">
        <DirectorySelectModal ref="directorySelectModal" />

        <div class="alert alert-danger" v-if="settingsErrors">
            <p class="m-0"><strong>An error occurred while saving settings:</strong></p>
            <ul class="m-0">
                <li v-for="error in settingsErrors">{{ error }}</li>
            </ul>
        </div>

        <div class="d-flex flex-column gap-2">
            <DirectoryInputSetting v-model="settings['general.storage.assets']" title="Asset storage directory"
                                   description="Directory where assets should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.assets')"
            />
            <DirectoryInputSetting v-model="settings['general.storage.game']" title="Game storage directory"
                                   description="Directory where game files should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.game')"
            />
            <DirectoryInputSetting v-model="settings['general.storage.libraries']" title="Library storage directory"
                                   description="Directory where libraries should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.libraries')"
            />
            <DirectoryInputSetting v-model="settings['general.storage.loaders']" title="Loader storage directory"
                                   description="Directory where loaders should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.loaders')"
            />
            <DirectoryInputSetting v-model="settings['general.storage.projects']" title="Project storage directory"
                                   description="Directory where projects should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.projects')"
            />
            <DirectoryInputSetting v-model="settings['general.storage.temp']" title="Temporary storage directory"
                                   description="Directory where temporary files should be stored."
                                   @openDirectorySelector="onOpenDirectorySelector('general.storage.temp')"
            />
        </div>

        <hr/>
        <InputSetting title="Curseforge API Key" v-model="settings['platforms.curseforge.api_key']" />

        <div class="d-grid d-md-block float-md-end my-3">
            <MButton :loading="settingsLoading" :disabled="!areSettingsChanged" @click="onSettingsSave">Save settings</MButton>
        </div>
    </div>
</template>

<script setup>
import {ref} from "vue";
import MButton from "../components/base/MButton.vue";
import InputSetting from "../components/settings/InputSetting.vue";
import DirectorySelectModal from "../components/modals/DirectorySelectModal.vue";
import DirectoryInputSetting from "../components/settings/DirectoryInputSetting.vue";
import {useConfigStore} from "../stores/config";
import {useSettings} from "../hooks/settings";
import {showSuccessNotification} from "../utils/notifications";

const directorySelectModal = ref();
const {
    settings, settingsLoading, areSettingsChanged, settingsErrors, onSettingChange, saveSettings
} = useSettings(['general', 'platforms']);

onSettingChange(onSettingChanged);

function onOpenDirectorySelector(settingKey) {
    directorySelectModal.value.setDirectory(settings.value[settingKey]);
    directorySelectModal.value.show();
    directorySelectModal.value.awaitChoice()
        .then(dir => {
            settings.value[settingKey] = dir;
        })
        .catch(e => e);
}

function onSettingChanged(settings) {
    // refetch platform config
    if (settings['platforms.curseforge.api_key'] !== undefined) {
        const config = useConfigStore();
        config.getConfig();
    }
}

function onSettingsSave() {
    saveSettings()
        .then(data => {
            showSuccessNotification('Settings saved!');
        })
        .catch(() => {
            window.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
        });
}
</script>
