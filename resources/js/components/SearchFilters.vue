<template>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Filters</span>
            <span class="badge text-bg-light cursor-pointer lh-sm" @click="emit('reset')"><fa-icon icon="xmark" /> Clear</span>
        </div>
        <div class="card-body d-flex flex-column gap-2">
            <div>
                <label for="platform" class="form-label fw-semibold mb-1">Platform</label>
                <select id="platform" class="form-select" :value="model.platform" @change="onPlatformChange">
                    <option :value="platform.id" v-for="platform in config.platforms" :disabled="!!platform.disabled">
                        {{ platform.name }}{{ platform.disabled ? ' (Disabled: '+platform.disabled+')' : '' }}
                    </option>
                </select>
            </div>

            <div>
                <label for="project-type" class="form-label fw-semibold mb-1">Project Type</label>
                <select v-model="model.projectType" id="project-type" class="form-select">
                    <option :value="type.id" v-for="type in getProjectTypesById(filterInfo.project_type.options)">
                        {{ type.name_plural }}
                    </option>
                </select>
            </div>

            <div v-if="filterInfo.query !== undefined">
                <label for="search-query" class="form-label fw-semibold mb-1">Search</label>
                <input type="text" id="search-query" class="form-control" :value="model.query" @input="onQueryInput" />
            </div>

            <div v-if="filterInfo.game_versions !== undefined && gameVersions.length">
                <div class="d-flex justify-content-between flex-wrap">
                    <p class="mb-1 fw-semibold">Game Versions</p>

                    <div class="form-check-inline m-0">
                        <label class="form-label m-0 text-nowrap">
                            <input type="checkbox" class="form-check-input" v-model="displayAllGameVersions" />
                            Show all
                        </label>
                    </div>
                </div>

                <input type="text" class="form-control form-control-sm mb-1" v-model="gameVersionsSearchQuery" placeholder="Filter game versions...">
                <CheckboxList v-model="model.gameVersions" :options="gameVersions"
                              class="mb-2" style="max-height: 200px; overflow-x: auto;"
                              track-by="id" display-by="name" :max="filterInfo.game_versions?.max ?? 10"
                />
            </div>

            <div v-if="filterInfo.loaders !== undefined && loaders.length">
                <p class="mb-1 fw-semibold">Mod Loaders</p>
                <CheckboxList v-model="model.loaders" :options="loaders"
                              track-by="id" display-by="name" :max="filterInfo.loaders?.max ?? 1"
                />
            </div>

            <div v-if="filterInfo.categories !== undefined"
                 v-for="(categories, name) in config.categoriesForProjectType(model.platform, model.projectType)"
            >
                <p class="mb-1 fw-semibold">{{ name === 'null' ? 'Categories' : name }}</p>
                <CheckboxList v-model="model.categories" :options="categories"
                              track-by="id" display-by="name" :model-by="route.isBrowse() ? 'remote_id' : 'id'"
                              :sort-by="categorySortBy(name)" :display-children="true" :max="filterInfo.categories?.max"
                />
            </div>

            <div v-if="route.isArchive() || filterInfo.sort_by !== undefined">
                <label for="sort-by" class="form-label fw-semibold mb-1">Sort by</label>
                <select v-model="model.sortBy" id="sort-by" class="form-select">
                    <option :value="option.id" v-for="option in sortOptions">{{ option.name }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed, ref, watch} from "vue";
import CheckboxList from "./common/CheckboxList.vue";
import {useConfigStore} from "../stores/config";
import {getProjectTypesById} from '../utils/utils';
import {debounce} from "lodash-es";
import {useMcaRoute} from "../hooks/route";

const model = defineModel();
const props = defineProps({
    sortOptions: { type: Array, required: true }
});
const config = useConfigStore();
const route = useMcaRoute();
const emit = defineEmits(['reset']);

const onQueryInput = debounce((e) => model.value.query = e.target.value, 500);
const filterInfo = computed(() => config.getRequestInfo(model.value.platform, 'search'));
const loaders = computed(() => config.getLoadersForPlatform(model.value.platform, [model.value.projectType]));
const gameVersionsSearchQuery = ref('');
const displayAllGameVersions = ref(false);
const gameVersions = computed(() => {
    const gv = displayAllGameVersions.value ? config.gameVersions : config.gameVersions.filter(v => v.type === 0);
    return gameVersionsSearchQuery.value ? gv.filter(v => v.name.toLowerCase().includes(gameVersionsSearchQuery.value.toLowerCase())) : gv;
});
const categorySortBy = (categoryName) => {
    // Special case for sorting Resource Packs Resolutions on Modrinth
    if (model.value.platform === 'modrinth' && categoryName === 'Resolutions') {
        return category => parseInt(category.name);
    }

    return 'name';
}

function onPlatformChange(e) {
    const platformId = e.target.value;
    const platform = config.getPlatform(platformId);
    if (! platform) return;
    if (!!platform.disabled) return;

    const searchRequest = config.getRequestInfo(platformId, 'search');
    model.value.platform = platformId;

    // Check if current project type & sorting option exist in platform that we're switching to.
    if (! searchRequest.project_type?.options?.some(type => type === model.value.platformType)) {
        model.value.projectType = searchRequest.project_type?.options?.[0];
    }

    model.value.categories = [];
}

watch(() => props.sortOptions, () => {
    if (! props.sortOptions.some(sortType => sortType.id === model.value.sortBy)) {
        model.value.sortBy = props.sortOptions?.[0].id;
    }
});
</script>

<style lang="sass">
.category-list
    list-style: none
    padding: 0
</style>
