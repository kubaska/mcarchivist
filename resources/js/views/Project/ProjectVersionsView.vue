<template>
    <ChangelogModal ref="changelogModal"></ChangelogModal>
    <DependenciesModal ref="dependenciesModal"></DependenciesModal>
    <CommonConfirmModal ref="revalidateModal" title="Confirm revalidate"></CommonConfirmModal>
    <FilesModal ref="filesModal" @delete-file="onFileDelete"></FilesModal>
    <VersionDeleteModal ref="deleteModal" @confirm="onVersionDeleteConfirm"></VersionDeleteModal>
    <VersionDeleteModal ref="fileDeleteModal" @confirm="onFileDeleteConfirm"></VersionDeleteModal>

    <div v-if="versions">
        <ProjectVersions :versions="versions" :platform-id="project.platform" :actions="onVersionAction"
                         :columns="columns" :pagination="pagination" :task-id-prefix="project.platform"
                         :game-versions="project.game_versions" :loaders="project.loaders" :project-types="project.project_types"
                         :filter-archived-only="route.isBrowse()" :filter-all-platforms="route.isArchive() && project.merged_projects_count > 1"
                         @filters="onFiltersChange"
        />
    </div>
    <p v-else>Loading..</p>
</template>

<script setup>
import {computed, ref, watch} from "vue";
import api from "../../api/api";
import ProjectVersions from "../../components/ProjectVersions.vue";
import FilesModal from "../../components/modals/FilesModal.vue";
import ChangelogModal from "../../components/modals/ChangelogModal.vue";
import DependenciesModal from "../../components/modals/DependenciesModal.vue";
import VersionDeleteModal from "../../components/modals/VersionDeleteModal.vue";
import CommonConfirmModal from "../../components/modals/CommonConfirmModal.vue";
import {useMcaRoute} from "../../hooks/route";
import {unionBy} from "lodash-es";

const props = defineProps({
    project: { type: Object, required: true }
});
const route = useMcaRoute();
const columns = computed(() => ({
    'Compatibility': { 'Game Versions': 'gameVersions', 'Loaders': 'loaders' },
    'Stats': route.isBrowse()
        ? { 'Downloads': 'downloads', 'Published': 'publishedDate' }
        : { 'Published': 'publishedDate' }
}));

const versions = ref(null);
const allVersions = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1
});
const useInBrowserVersionFiltering = ref(false);

const changelogModal = ref(null);
const deleteModal = ref(null);
const fileDeleteModal = ref(null);
const revalidateModal = ref(null);
const dependenciesModal = ref(null);
const filesModal = ref(null);

async function getVersions(filters = {}) {
    if (! props.project.id) return;
    if (route.isArchive() || ! useInBrowserVersionFiltering.value) {
        // If user is browsing the archive, we need to use project ID instead of master project ID
        const res = await api.getProjectVersions(route.isArchive() ? props.project.project_id : props.project.id, {
            archived_only: route.isArchive(),
            platform: route.isBrowse() ? props.project.platform : null,
            ...filters
        });

        if (res.data.meta) {
            allVersions.value = unionBy(allVersions.value, res.data.data, 'id');
            pagination.value = res.data.meta;
            versions.value = res.data.data;

            if (allVersions.value.length === res.data.meta.total) {
                useInBrowserVersionFiltering.value = true;
                console.log('Got all versions, switching to local filtering');
            }
        } else {
            // No pagination means we have all results, switch to local filtering
            allVersions.value = res.data.data;
            pagination.value.current_page = 1;
            pagination.value.last_page = Math.ceil(allVersions.value.length / 50);
            versions.value = allVersions.value.slice(0, 50);
            useInBrowserVersionFiltering.value = true;
        }
    }
    else {
        let newVersions = allVersions.value;

        if (filters.loaders?.length)
            newVersions = newVersions.filter(version => version.loaders.some(loader => filters.loaders.includes(loader.id)));
        if (filters.game_versions?.length)
            newVersions = newVersions.filter(version => version.game_versions.some(gv => filters.game_versions.includes(gv.name)));
        if (filters.release_types?.length)
            newVersions = newVersions.filter(version => filters.release_types.includes(version.type));
        if (filters.archived_only)
            newVersions = newVersions.filter(version => version.files.some(file => file.local));

        const filtersPage = filters.page ?? 1;
        pagination.value.last_page = Math.ceil(newVersions.length / 50);
        pagination.value.current_page = pagination.value.current_page > pagination.value.last_page
            ? 1
            : filtersPage;
        versions.value = newVersions.slice(filtersPage * 50 - 50, filtersPage * 50);
    }
}

function onFiltersChange(filters) {
    getVersions(filters);
}

function onVersionAction(type, data) {
    switch (type) {
        case 'get-archivable-components':
            return api.getVersionFiles(route.params.id, data.id, {
                archived_only: route.isArchive(), platform: props.project.platform
            });
        case 'archive':
            return api.archiveProjectVersion(route.params.id, data.id, {
                platform: props.project.platform, project_name: props.project.name,
                project_version: data.name, file_ids: ['*']
            });
        case 'archive-components':
            return api.archiveProjectVersion(route.params.id, data.model.id, {
                platform: props.project.platform, project_name: props.project.name,
                project_version: data.model.name, file_ids: data.components
            });
        case 'changelog':
            changelogModal.value.setChangelog(data.changelog);
            changelogModal.value.show();
            return;
        case 'delete':
            deleteModal.value.setData(data);
            deleteModal.value.show();
            return;
        case 'dependants':
            dependenciesModal.value.show();
            api.getVersionDependants(route.params.id, data.id, { archived_only: route.isArchive(), platform: props.project.platform })
                .then(response => {
                    dependenciesModal.value.setData('Dependants', response.data.data);
                });
            return;
        case 'dependencies':
            dependenciesModal.value.show();
            api.getVersionDependencies(route.params.id, data.id, { archived_only: route.isArchive(), platform: props.project.platform })
                .then(response => {
                    dependenciesModal.value.setData('Dependencies', response.data.data);
                });
            return;
        case 'files':
            filesModal.value.setData(data, 'projects');
            filesModal.value.show();
            return;
        case 'revalidate':
            revalidateModal.value.setData('This version is already archived. Do you wish to revalidate all files?', data);
            revalidateModal.value.show();
            return revalidateModal.value.awaitChoice()
                .then(modal => {
                    return api.revalidateVersion(props.project.project_id, data.id, {
                        platform: data.platform, id_is_remote: route.isBrowse()
                    }).then(() => {
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
    api.deleteVersion(props.project.project_id, version.id, { platform: version.platform, id_is_remote: !version.local })
        .then(res => {
            finish();

            if (route.isArchive()) {
                versions.value = versions.value.filter(v => v.id !== version.id);
                allVersions.value = allVersions.value.filter(v => v.id !== version.id);
            } else {
                [
                    versions.value.find(v => v.id === version.id),
                    allVersions.value.find(v => v.id === version.id)
                ].filter(i => i).forEach(localVersion => {
                    localVersion.files = [];
                    localVersion.local = false;
                });
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
    api.deleteVersionFile(props.project.project_id, version.id, file.id, { platform: version.platform, id_is_remote: !version.local })
        .then(res => {
            finish();

            const v1 = versions.value.find(v => v.id === version.id);
            const v2 = allVersions.value.find(v => v.id === version.id);
            const all = [v1, v2].filter(i => i);

            if (route.isArchive()) {
                all.forEach(v => v.files = v.files.filter(f => f.id !== file.id));
                // if there's no more files remove the version as well
                if (v1 && !v1.files.length) versions.value = versions.value.filter(v => v.id !== version.id);
                if (v2 && !v2.files.length) allVersions.value = allVersions.value.filter(v => v.id !== version.id);
            } else {
                all.forEach(localVersion => {
                    const localFile = localVersion.files.find(f => f.id === file.id);
                    if (localFile) localFile.local = false;
                });
            }
        })
        .catch(err => finish(err));
}

getVersions();
watch(() => `${props.project.id};${props.project.project_id};${props.project.platform}`, () => {
    useInBrowserVersionFiltering.value = false;
    getVersions();
});
</script>
