<template>
    <div class="my-1">
        <div class="mb-3 d-flex flex-wrap justify-content-between gap-2">
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <VersionSelectDropdown v-if="filterGameVersions && gameVersions.length" :versions="gameVersions" v-model="filters['game_versions']"
                                       track-by="id" display-by="name" close-behavior="outside"
                                       :max="filterInfo?.game_versions?.max ?? 10" @update="onFiltersChange(1)">
                    <button class="btn btn-outline-primary dropdown-toggle"><fa-icon icon="filter" /> Game Versions</button>
                </VersionSelectDropdown>

                <SelectableDropdown v-if="filterLoaders && loaders.length" :options="loaders" v-model="filters['loaders']"
                                    track-by="remote_id" display-by="name" close-behavior="outside"
                                    :max="filterInfo?.loaders?.max ?? 10" @update="onFiltersChange(1)">
                    <button class="btn btn-outline-primary dropdown-toggle"><fa-icon icon="filter" /> Loaders</button>
                </SelectableDropdown>

                <SelectableDropdown v-if="filterReleaseTypes" :options="releaseTypes" v-model="filters['release_types']"
                                    track-by="id" display-by="name" close-behavior="outside" @update="onFiltersChange(1)">
                    <button class="btn btn-outline-primary dropdown-toggle"><fa-icon icon="filter" /> Release Types</button>
                </SelectableDropdown>

                <div class="form-check form-switch" v-if="filterArchivedOnly">
                    <input class="form-check-input" type="checkbox" role="switch" id="version-filters__archived-only"
                           v-model="filters.archived_only" @change="onFiltersChange(1)"
                    >
                    <label class="form-check-label" for="version-filters__archived-only">Archived only</label>
                </div>

                <div class="form-check form-switch" v-if="filterAllPlatforms">
                    <input class="form-check-input" type="checkbox" role="switch" id="version-filters__all-platforms"
                           v-model="filters.all_platforms" @change="onFiltersChange(1)"
                    >
                    <label class="form-check-label" for="version-filters__all-platforms">All platforms</label>
                </div>
            </div>

            <Pagination :current="pagination.current_page" :total="pagination.last_page" @change="onPaginatorChange" />
        </div>

        <div class="d-flex align-items-center gap-2 my-2" v-if="anyFilterSelected">
            <span class="badge text-bg-light border cursor-pointer" @click="clearFilters"><fa-icon icon="xmark" /> Clear filters</span>

            <template v-for="type in ['game_versions', 'loaders', 'release_types']">
                <span class="badge cursor-pointer"
                      :class="type === 'release_types' ? 'text-'+f.bs_bg_class : 'text-bg-primary'"
                      v-for="f in filters[type]"
                      @click="removeFilter(type, f.id)"
                >{{ f.name }}</span>
            </template>
        </div>

        <div class="version-table" :style="`--version-table-columns: ${columnsNumber}`" v-if="versions.length">
            <div class="version-table--row py-2 d-none d-sm-grid fw-semibold">
                <span class="d-none d-md-block"></span>
                <span class="d-none d-md-block">Name</span>
                <span class="d-none d-xl-block" v-for="name in columnNames">{{ name }}</span>
                <span class="d-none d-md-block d-lg-block d-xl-none" v-for="name in columnNamesShort">{{ name }}</span>
                <span class="d-none d-md-block"></span>
            </div>
            <ProjectVersion :version="version" :actions="actions" :columns="columnContent"
                            :task-id-prefix="taskIdPrefix" v-for="version in versions" :key="version.id"
            />
        </div>
        <div class="my-5 fs-5 text-center" v-else>
            <p>No versions matching criteria.</p>
        </div>
        <div class="my-3 float-end" v-if="versions.length > 10">
            <Pagination :current="pagination.current_page" :total="pagination.last_page" @change="onPaginatorChange" />
        </div>
    </div>
</template>

<script setup>
import {computed, ref} from "vue";
import Pagination from "./base/Pagination.vue";
import ProjectVersion from "./ProjectVersion.vue";
import SelectableDropdown from "./common/SelectableDropdown.vue";
import VersionSelectDropdown from "./common/VersionSelectDropdown.vue";
import {useConfigStore} from "../stores/config";
import {useMcaRoute} from "../hooks/route";
import {getBaseVersionTypes} from "../utils/utils";
import {clone} from "lodash-es";

const props = defineProps({
    versions: { type: Object, required: true },
    platformId: { type: String, required: false },
    actions: { type: Function, required: true },
    columns: { type: Object, required: true },
    pagination: { type: Object, required: true },
    taskIdPrefix: { type: [String, Number], required: true },

    gameVersions: { type: Array, required: false },
    loaders: { type: Array, required: false },
    releaseTypes: { type: Array, required: false, default() { return getBaseVersionTypes() } },
    projectTypes: { type: Array, required: false },

    filterAllPlatforms: { type: Boolean, required: false, default: false },
    filterArchivedOnly: { type: Boolean, required: false, default: true  },
    filterLoaders:      { type: Boolean, required: false, default: true  },
    filterGameVersions: { type: Boolean, required: false, default: true  },
    filterReleaseTypes: { type: Boolean, required: false, default: true  }
});

const route = useMcaRoute();
const config = useConfigStore();

// Add platform column when all platforms filter is selected
const columns = computed(() => {
    const c = clone(props.columns);
    if (filters.value.all_platforms) {
        c['Compatibility'] = { 'Platform': 'platform', ...c['Compatibility'] };
    }
    return c;
});
const columnNamesShort = computed(() => Object.keys(columns.value));
const columnNames = computed(() => Object.values(columns.value).map(group => Object.keys(group)).flat());
const columnContent = computed(() => Object.values(columns.value).map(group => Object.values(group)));
const columnsNumber = computed(() => columnNames.value.length + 1);

const filterInfo = computed(() => {
    return route.isBrowse() && props.platformId
        ? (config.getRequestInfo(props.platformId, 'get_versions') ?? {})
        : {}
});
const sortDesc = ref(true);
const gameVersions = computed(() => {
    return props.gameVersions
        ? config.gameVersions.filter(v => props.gameVersions.includes(v.name))
        : config.gameVersions;
});
const loaders = computed(() => {
    return props.loaders ?? config.getLoadersRemoteIdsForPlatform(props.platformId, props.projectTypes);
});

const emit = defineEmits(['filters']);

const filters = ref({
    archived_only: route.isArchive(),
    all_platforms: false,
    loaders: [],
    game_versions: [],
    release_types: [],
    sort_desc: true
});
const anyFilterSelected = computed(() =>
    filters.value.loaders.length
    || filters.value.game_versions.length
    || filters.value.release_types.length
);

function onPaginatorChange(page) {
    onFiltersChange(page);
}

function removeFilter(filter, id) {
    filters.value[filter] = filters.value[filter].filter(t => t.id !== id);
    onFiltersChange(1);
}
function clearFilters() {
    filters.value.loaders = [];
    filters.value.game_versions = [];
    filters.value.release_types = [];
    onFiltersChange(1);
}

function onFiltersChange(page) {
    const mapped = {
        archived_only: +!!filters.value.archived_only,
        all_platforms: filters.value.all_platforms,
        loaders: filters.value.loaders.map(loader => loader.id),
        game_versions: filters.value.game_versions.map(gv => gv.name),
        release_types: filters.value.release_types.map(type => type.id),
        sort_desc: +!!sortDesc.value
    }
    emit('filters', { page, ...mapped });
}
</script>

<style lang="sass">
.version-table
    display: flex
    flex-direction: column

    @supports (grid-template-columns: subgrid)
        display: grid
        grid-template-columns: 1fr min-content

        @media (min-width: 768px)
            grid-template-columns: min-content auto auto auto min-content

        @media (min-width: 1200px)
            grid-template-columns: min-content repeat(var(--version-table-columns, 5), auto) min-content

.version-table--row
    display: grid
    gap: 1rem
    grid-template-columns: 1fr min-content
    border-bottom: var(--bs-gray-300) 1px solid

    &:last-child
        border-bottom: none

    @supports (grid-template-columns: subgrid)
        grid-column: 1 / -1
        grid-template-columns: subgrid !important

    @media (min-width: 768px)
        grid-template-columns: min-content 1fr 1fr 1fr min-content

    @media (min-width: 1200px)
        grid-template-columns: min-content repeat(var(--version-table-columns, 5), 1fr) min-content
</style>
