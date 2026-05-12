<template>
    <div class="row my-2">
        <div class="col-md-4">
            <SearchFilters v-model="filters" :sort-options="sortOptions" @reset="setupDefaultFilters({})" />
        </div>
        <div class="col-md-8">
            <div class="d-flex justify-content-end">
                <Pagination :current="pagination.page" :total="pagination.lastPage" @change="onPaginatorChange" />
            </div>

            <div v-if="error" class="text-center my-3">
                <p class="fs-5">Unexpected error occurred, check browser console for details</p>
                <button class="btn btn-primary" @click="getData()">Refresh</button>
            </div>
            <div class="d-flex flex-column gap-2 my-2" v-else-if="data?.length">
                <Project v-for="project in data" :project="project"
                         :route-name="route.getRouteForBase('project')"
                         :dropdown-options="projectDropdownOptions"
                         @archive="onArchive"
                />
            </div>
            <div class="fs-4 text-center my-4" v-else-if="data?.length === 0">
                <p>No results matching criteria.</p>
            </div>
            <LoadingSpinner class="my-5" v-else />

            <div class="d-flex justify-content-end mb-2">
                <Pagination :current="pagination.page" :total="pagination.lastPage" @change="onPaginatorChange" />
            </div>
        </div>

        <ArchiveModal ref="modal" @confirm="onArchiveConfirm" />
        <ProjectMergeModal ref="projectMergeModal" @confirm="onProjectMergeConfirm" />
    </div>
</template>

<script setup>
import {computed, onActivated, onMounted, reactive, ref, watch} from "vue";
import api from "../api/api";
import Project from '../components/Project.vue';
import Pagination from "../components/base/Pagination.vue";
import SearchFilters from "../components/SearchFilters.vue";
import ArchiveModal from "../components/modals/ArchiveModal.vue";
import LoadingSpinner from "../components/base/LoadingSpinner.vue";
import ProjectMergeModal from "../components/modals/ProjectMergeModal.vue";
import {useStore} from "../stores/store";
import {useConfigStore} from "../stores/config";
import {useRouter} from "vue-router";
import {useMcaRoute} from "../hooks/route";
import {castArray, clamp, debounce, omit, pickBy, take, truncate} from "lodash-es";
import {getLocalSortingOptions} from "../utils/utils";

const route = useMcaRoute();
const router = useRouter();
const modal = ref(null);
const store = useStore();
const config = useConfigStore();
const filters = ref({
    platform: null,
    projectType: null,
    query: '',
    gameVersions: [],
    loaders: [],
    categories: [],
    sortBy: null
});
const data = ref(null);
const loading = ref(false);
const error = ref(null);
const pagination = reactive({
    page: 1,
    lastPage: 1,
    total: null
});
const initialLoad = ref(true);
const dataSource = ref(route.getBase());
const inspectedProjectId = ref(null);
const projectMergeModal = ref(null);
const projectDropdownOptions = [
    { name: 'Merge...', onClick: onProjectMergeBtnClick }
];

const platform = computed(() => config.getPlatform(filters.value.platform));
const requestConfig = computed(() => config.getRequestInfo(filters.value.platform, 'search'));
const sortOptions = computed(() => route.isArchive() ? getLocalSortingOptions() : requestConfig.value?.sort_by?.options);

function setupDefaultPlatform() {
    if (route.isBrowse() && ! filters.value.platform) {
        if (config.availablePlatforms.length) {
            filters.value.platform = config.availablePlatforms[0].id;
            router.push({ name: route.getBase(), params: { source: config.availablePlatforms[0].slug } });
        } else {
            console.error('No platforms available!');
            error.value = 'No platforms available!';
        }
    }
}

function setupDefaultFilters(initialFilters) {
    if (initialFilters['project_type']) {
        const type = requestConfig.value?.project_type?.options?.find(type => type === parseInt(initialFilters['project_type']));
        if (type) {
            filters.value.projectType = type;
        }
    }
    if (filters.value.projectType === null)
        filters.value.projectType = requestConfig.value?.project_type?.options?.[0];

    if (initialFilters['query']) {
        filters.value.query = truncate(initialFilters['query'], { length: requestConfig.value?.query?.max ?? 100, omission: '' });
    } else filters.value.query = '';

    if (initialFilters['game_versions']) {
        const initialGameVersions = castArray(initialFilters['game_versions']);
        filters.value.gameVersions = take(
            config.gameVersions.filter(gv => initialGameVersions.includes(gv.name)),
            requestConfig.value?.game_versions?.max ?? 10
        );
    } else {
        filters.value.gameVersions = [];
    }

    if (initialFilters['loaders']) {
        const initialLoaders = castArray(initialFilters['loaders']).map(i => parseInt(i));
        filters.value.loaders = take(
            config.loaders.filter(loader => initialLoaders.includes(loader.id)),
            requestConfig.value?.loaders?.max ?? 10
        );
    } else {
        filters.value.loaders = [];
    }

    if (initialFilters['categories']) {
        const initialCategories = castArray(initialFilters['categories']);
        filters.value.categories = take(
            config.categories.filter(cat => cat.platform === platform.value?.id && initialCategories.includes(cat.remote_id)).map(cat => cat.remote_id),
            requestConfig.value?.categories?.max ?? 10
        );
    } else {
        filters.value.categories = [];
    }

    if (initialFilters['sort_by'] && sortOptions.value?.some(sortOpt => sortOpt.id == initialFilters['sort_by'])) {
        filters.value.sortBy = initialFilters['sort_by'];
    } else {
        filters.value.sortBy = sortOptions.value?.[0]?.id;
    }

    if (initialFilters['page']) {
        const page = parseInt(initialFilters['page']);
        if (page) {
            const max = requestConfig.value?.page?.max;
            pagination.page = max ? clamp(page, 1, max) : page;
        }
    }
}

function getFilters() {
    return {
        project_type: filters.value.projectType,
        game_versions: filters.value.gameVersions.map(v => v.name),
        ...pickBy({
            platform: filters.value.platform,
            query: filters.value.query,
            loaders: filters.value.loaders ? filters.value.loaders.map(l => l.id) : null,
            categories: filters.value.categories,
            sort_by: filters.value.sortBy
        }, (i) => !!i)
    };
}

function resetData() {
    data.value = null;
    pagination.page = 1;
    pagination.lastPage = 1;
    pagination.total = null;
}

function updateRouteQuery() {
    router.push({
        name: route.getBase(),
        params: { source: platform.value ? platform.value.slug : '' },
        query: { ...omit(getFilters(), ['platform']), page: pagination.page }
    });
}

function onPaginatorChange(step) {
    pagination.page = step;
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 1);
    getData();
    updateRouteQuery();
}

function getData(options) {
    error.value = null;
    loading.value = true;

    return api.searchProjects({
        archived_only: route.isArchive(),
        page: pagination.page,
        ...getFilters(),
        ...options
    }).then(response => {
        data.value = response.data.data;
        // Limit page to max value, if exists
        pagination.lastPage = requestConfig.value?.page?.max
            ? ((response.data.meta.last_page > requestConfig.value.page.max) ? requestConfig.value.page.max : response.data.meta.last_page)
            : response.data.meta.last_page;
        pagination.total = response.data.meta.total;
    })
    .catch(err => {
        resetData();
        console.log(err);
        error.value = 'Error!';
    })
    .finally(() => {
        loading.value = false;
    });
}

function onArchive(project) {
    inspectedProjectId.value = project.id;
    modal.value.loadRules(project);
    modal.value.show();
}
function onArchiveConfirm(isArchived) {
    const project = data.value.find(p => p.id === inspectedProjectId.value);
    if (project) {
        project.is_archiving = isArchived;
    }
    inspectedProjectId.value = null;
}

function onProjectMergeBtnClick(project) {
    inspectedProjectId.value = project.id;
    projectMergeModal.value.setData(project);
    projectMergeModal.value.show();
}
function onProjectMergeConfirm(project) {
    const index = data.value.findIndex(p => p.id === inspectedProjectId.value);
    if (index >= 0) {
        data.value[index] = project;
    }
    inspectedProjectId.value = null;
}

if (route.params.source) {
    const platform = config.platforms.filter(
        platform => platform.slug === route.params.source.toLowerCase()
    )?.[0];

    if (platform) filters.value.platform = platform.id;
}

setupDefaultPlatform();
onMounted(() => {
    setupDefaultFilters(route.query);
});
onActivated(() => { store.resetActiveProject(); });
watch(route.getBase, (val) => {
    // only this component and if data source changed
    if (['archive', 'browse'].includes(route.name) && dataSource.value !== val) {
        dataSource.value = val;
        setupDefaultPlatform();
        loading.value = true;
        resetData();
        setupDefaultFilters(omit(getFilters(), ['platform']));
    }
});
watch(filters.value, debounce(() => {
    if (! initialLoad.value) pagination.page = 1;
    getData(initialLoad.value ? {} : { page: 1 });
    updateRouteQuery();
    initialLoad.value = false;
}, 50, { trailing: true }));
</script>
