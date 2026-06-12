import {useConfigStore} from "../stores/config";
import {computed, onBeforeUnmount, ref, toValue, watchEffect} from "vue";
import {isEqual, mapValues, omit} from "lodash-es";

export const useSettings = (keyNames) => {
    const store = useConfigStore();
    const origSettings = computed(() => store.getSettingsStartingWith(toValue(keyNames)));

    // Only setting values e.g. { game_versions.automatic_archive: true, ... }
    const settings = ref(mapValues(origSettings.value, setting => setting.value));
    // Everything except values e.g. { game_versions.components: { options: ['client', 'server'] }, ... }
    const settingsDetails = computed(() => mapValues(origSettings.value, setting => omit(setting, 'value')));

    const settingsLoading = ref(false);
    const settingsErrorResponse = ref(null);
    const settingsErrors = ref(null);
    const onSettingChangeCallbacks = ref([]);
    const changedSettings = computed(() => Object.fromEntries(
        Object.entries(settings.value).filter(([key, value]) => ! isEqual(value, origSettings.value[key]['value']))
    ));
    const areSettingsChanged = computed(() => Object.keys(changedSettings.value).length > 0);
    const onSettingChange = (callback) => {
        onSettingChangeCallbacks.value.push(callback);
    }
    const saveSettings = () => {
        settingsLoading.value = true;
        settingsErrors.value = null;
        settingsErrorResponse.value = null;
        return store.saveSettings(changedSettings.value)
            .then(changedSettings => {
                onSettingChangeCallbacks.value.forEach(cb => cb(changedSettings));
                return changedSettings;
            })
            .catch(err => {
                if (err.response.status === 422) {
                    settingsErrors.value = Object.values(err.response.data).flat();
                } else {
                    settingsErrors.value = [err.message];
                }

                settingsErrorResponse.value = err;
                throw err;
            })
            .finally(() => settingsLoading.value = false);
    }
    const resetSettings = () => settings.value = mapValues(origSettings.value, setting => setting.value);

    onBeforeUnmount(() => onSettingChangeCallbacks.value = []);
    watchEffect(resetSettings);

    return {
        settings, settingsDetails, settingsLoading, settingsErrorResponse, settingsErrors, changedSettings, areSettingsChanged,
        onSettingChange, saveSettings, resetSettings
    };
}
