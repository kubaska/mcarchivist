<template>
    <div class="version-table--row py-1 px-sm-2" :class="{ 'bg-success-subtle': hasAtLeastOneFileArchived }">
        <div class="d-flex flex-column justify-content-center gap-2 d-md-contents">
            <div class="d-flex flex-row align-items-center gap-2 d-md-contents">
                <div class="align-self-center">
                    <span class="version-table--version-type text-white fw-bold d-flex justify-content-center align-items-center rounded"
                          :class="releaseType.bs_bg_class">{{ releaseType.symbol }}</span>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <div class="text-break">{{ version.name }}</div>
                </div>
            </div>
            <div class="d-flex flex-column justify-content-center gap-2 d-md-contents">
                <div class="d-flex flex-row flex-wrap align-items-center gap-1 d-xl-contents">
                    <div class="d-flex align-items-center" v-for="col in allColumns[0]">
                        <Component :is="col.c" v-bind="col.d" />
                    </div>
                </div>
                <div class="d-flex flex-row flex-sm-column justify-content-start justify-content-sm-center gap-3 gap-sm-1 d-xl-contents">
                    <div class="d-flex align-items-center gap-1 text-nowrap font-medium align-self-xl-center" v-for="col in allColumns[1]">
                        <fa-icon :icon="col.icon" class="d-xl-none" v-if="col.icon" />
                        <Component :is="col.c" v-bind="col.d" />
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-1">
            <div class="btn-group w-100">
                <button type="button" class="btn d-flex flex-column align-items-center gap-1"
                        @click="onArchiveBtnClick" :disabled="busy || (route.isArchive() && version.files.length === 0)"
                >
                    <span class="spinner-border spinner-border-sm" v-if="queueTask !== null"></span>
                    <fa-icon :icon="archiveLoadingState ? 'cog' : 'box-archive'" :spin="archiveLoadingState" v-else />
                    <span class="lh-1">{{ archiveText }}</span>
                </button>
                <button type="button" class="btn dropdown-toggle dropdown-toggle-split flex-grow-0"
                        data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside"
                        v-on="{ 'show.bs.dropdown': onArchiveDropdownShown }" ref="filesDropdownElement"
                >
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown--components dropdown-menu dropdown-menu-end p-0">
                    <li class="d-flex flex-column py-3" v-if="filesLoading"><fa-icon icon="cog" spin /></li>
                    <li class="text-center my-1" v-else-if="allFiles && allFiles.length === 0">No files!</li>
                    <template v-else>
                        <li v-for="file in allFiles" class="p-1" :key="file.id">
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" v-if="file.local && !route.isArchive()"
                                           :value="file.id" disabled checked>
                                    <input type="checkbox" class="form-check-input" v-else
                                           v-model="filesSelectedToArchive" :value="file.id">
                                    <fa-icon :icon="file.local ? 'box-archive' : 'file'"
                                             :class="{ 'text-success': file.local }"
                                             :title="file.local ? 'Archived' : 'Not archived'"
                                    /> {{ file.name }} {{ file.size ? '('+formatBytes(file.size)+')' : '' }}
                                </label>
                            </div>
                        </li>
                        <div class="d-flex gap-1 p-1">
                            <button class="btn btn-success flex-grow-1"
                                    :disabled="!filesSelectedToArchive.length || busy"
                                    @click="onArchiveComponentsConfirm">
                                <fa-icon icon="check" /> {{ route.isArchive() ? 'Download' : 'Archive Selected' }}
                            </button>
                            <button class="btn btn-danger" @click="bsFilesDropdown.hide()"><fa-icon icon="xmark" /></button>
                        </div>
                    </template>
                </ul>
            </div>

            <MDropdown :options="dropdownOptions">
                <button class="btn btn-icon">
                    <fa-icon icon="ellipsis-vertical" />
                </button>
            </MDropdown>
        </div>
    </div>
</template>

<script setup>
import {computed, ref} from "vue";
import api from "../api/api";
import MDropdown from "./base/MDropdown.vue";
import BadgeList from "./base/BadgeList.vue";
import FormattedDate from "./base/FormattedDate.vue";
import PlatformBadge from "./base/PlatformBadge.vue";
import FormattedNumber from "./base/FormattedNumber.vue";
import {
    downloadFile,
    downloadFiles,
    formatBytes, formatComponentName,
    getJobTypeName, VERSION_TYPES
} from "../utils/utils";
import {useQueueStore} from "../stores/queue";
import {useMcaRoute} from "../hooks/route";
import {useBsDropdown} from "../hooks/bootstrap";
import {showErrorNotification} from "../utils/notifications";
import {useTaskStateSpy} from "../hooks/queue";

const props = defineProps({
    version: { type: Object, required: true },
    actions: { type: Function, required: true },
    columns: { type: Array, required: true },
    taskIdPrefix: { type: [String, Number], required: true }
});
const route = useMcaRoute();
const queue = useQueueStore();
const filesDropdownElement = ref(null);
const { bsComponent: bsFilesDropdown } = useBsDropdown(filesDropdownElement);
const components = computed(() => {
    if (props.version.components === null || props.version.components.length === 0) return [];
    return props.version.components.map(component => {
        const hasComponent = props.version.files.some(file => file.component === component);
        return { name: formatComponentName(component), type: hasComponent ? 'success' : 'danger' };
    });
});
const componentResolver = (componentName) => {
    // c: component, d: data
    switch (componentName) {
        case 'components': return { c: BadgeList, d: { badges: components.value, trackBy: 'name' } };
        case 'platform': return { c: PlatformBadge, d: { 'platformId': props.version.platform } };
        case 'gameVersions': return { c: BadgeList, d: { badges: props.version.game_versions, trackBy: 'name' } };
        case 'loaders': return { c: BadgeList, d: { badges: props.version.loaders, trackBy: 'name' } };
        case 'publishedDate': return { c: FormattedDate, d: { date: props.version.published_at }, icon: 'calendar' };
        case 'downloads': return { c: FormattedNumber, d: { number: props.version.downloads }, icon: 'download' };
        default: return { c: BadgeList, d: {} };
    }
};
const allColumns = computed(() => {
    return props.columns.map(columnGroup => {
        return columnGroup.map(column => componentResolver(column));
    });
});
const hasAtLeastOneFileArchived = computed(() => props.version.files.some(file => file.local));
const frontendId = computed(() => [props.taskIdPrefix, props.version.remote_id].join(';'));
const archiveLoadingState = ref(false);
const { queueTask, attachTaskStateSpy } = useTaskStateSpy(frontendId, () => {
    // update list of archived files
    // project ID not needed here (sorry!)
    api.getVersionFiles(123, props.version.id, {
        archived_only: true,
        id_is_remote: route.isBrowse(),
        platform: route.isArchiveOrBrowse() ? props.version.platform : undefined
    }).then(res => {
        res.data.forEach(file => {
            const versionFile = props.version.files.find(vf => vf.id === file.remote_id);
            if (versionFile) {
                versionFile.local = true;
            } else {
                props.version.files.push(file);
            }

            if (allFiles.value) {
                const _file = allFiles.value.find(f => f.remote_id === file.remote_id);
                if (_file) _file.local = true;
            }
        });
    }).catch(err => console.log('Failed to update local files', err));
});
const busy = computed(() => archiveLoadingState.value || !!queueTask.value);
const archiveText = computed(() => {
    if (queueTask.value) {
        switch (queueTask.value.state) {
            case 0: return 'Queued';
            case 1: return getJobTypeName(queueTask.value.job_type);
        }
    }
    if (hasAtLeastOneFileArchived.value) return 'Download';
    return 'Archive';
});
const releaseType = computed(() => VERSION_TYPES[props.version.type]);

const dropdownOptions = computed(() => [
    { name: 'See changelog', onClick: () => props.actions('changelog', props.version), disabled: !props.version.changelog },
    { name: 'See dependencies', onClick: () => props.actions('dependencies', props.version), disabled: !props.version.dependencies.length, hidden: !route.isArchiveOrBrowse() },
    { name: 'See dependants', onClick: () => props.actions('dependants', props.version), disabled: route.isBrowse(), hidden: !route.isArchiveOrBrowse() },
    { name: 'See archived files...', onClick: () => props.actions('files', props.version), disabled: ! hasAtLeastOneFileArchived.value },
    { name: 'Revalidate...', onClick: () => props.actions('revalidate', props.version).then(onRevalidateConfirm), hidden: !hasAtLeastOneFileArchived.value },
    { name: 'Delete...', onClick: () => props.actions('delete', props.version), hidden: !hasAtLeastOneFileArchived.value }
]);

const filesLoading = ref(false);
const allFiles = ref(null);
const filesSelectedToArchive = ref([]);

function onArchiveBtnClick() {
    if (archiveLoadingState.value) return;

    if (hasAtLeastOneFileArchived.value) {
        if (props.version.files.length) {
            downloadFile(props.version.files[0].url);
        }
    } else {
        archiveLoadingState.value = true;

        props.actions('archive', props.version)
            .then(() => {
                attachTaskStateSpy();
            })
            .catch(error => {
                showErrorNotification('Failed to archive version', 'Check browser console for details');
                console.log('Failed to archive version', error);
            })
            .finally(() => {
                archiveLoadingState.value = false;
            });
    }
}

function onArchiveDropdownShown() {
    if (allFiles.value !== null) return;

    filesLoading.value = true;
    props.actions('get-archivable-components', props.version)
        .then(res => {
            allFiles.value = res.data;

            // Fill components if missing. This should be done on server side too.
            if (! components.value.length) {
                props.version.components = res.data.map(file => file.component).filter(file => !!file);
            }
        })
        .catch(error => {
            showErrorNotification('Failed to fetch available files', 'Check browser console for details');
            console.log('Failed to fetch available files', error);
        })
        .finally(() => filesLoading.value = false);
}

function onArchiveComponentsConfirm() {
    bsFilesDropdown.value.hide();
    if (archiveLoadingState.value) return;

    if (route.isArchive()) {
        downloadFiles(allFiles.value.filter(f => filesSelectedToArchive.value.includes(f.id)).map(f => f.url));
        return;
    }

    archiveLoadingState.value = true;
    props.actions('archive-components', { model: props.version, components: filesSelectedToArchive.value })
        .then(() => {
            attachTaskStateSpy();
        })
        .catch(error => {
            showErrorNotification('Failed to archive files', 'Check browser console for details');
            console.log('Failed to archive files', error);
        })
        .finally(() => {
            archiveLoadingState.value = false;
        });
}

function onRevalidateConfirm(result) {
    if (! result) return;

    attachTaskStateSpy();
}
</script>

<style lang="sass">
.version-table--version-type
    height: 2rem
    width: 2rem

.dropdown--components
    width: max-content
    max-width: 350px

.btn:disabled
    border: none
</style>
