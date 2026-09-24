<template>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Filters</span>
            <span class="badge text-bg-light cursor-pointer lh-sm" @click="projects.resetFilters()"><fa-icon icon="xmark" /> Clear</span>
        </div>
        <div class="card-body d-flex flex-column gap-2">
            <div>
                <label for="platform" class="form-label fw-semibold mb-1">Platform</label>
                <select id="platform" class="form-select" v-model="projects.filters.platform">
                    <option value="" v-if="route.isArchive()">All</option>
                    <option :value="platform.id" v-for="platform in config.platforms" :disabled="!!platform.disabled">
                        {{ platform.name }}{{ platform.disabled ? ' (Disabled: '+platform.disabled+')' : '' }}
                    </option>
                </select>
            </div>

            <div>
                <label for="project-type" class="form-label fw-semibold mb-1">Project Type</label>
                <select v-model="projects.filters.projectType" id="project-type" class="form-select">
                    <option :value="type.id" v-for="type in getProjectTypesById(projects.requestInfo.project_type.options)">
                        {{ type.name_plural }}
                    </option>
                </select>
            </div>

            <div v-if="projects.requestInfo.query !== undefined">
                <label for="search-query" class="form-label fw-semibold mb-1">Search</label>
                <input type="text" id="search-query" class="form-control" :value="projects.filters.query" @input="onQueryInput" />
            </div>

            <div v-if="projects.requestInfo.game_versions !== undefined && gameVersions.length">
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
                <CheckboxList v-model="projects.filters.gameVersions" :options="gameVersions"
                              class="mb-2" :capped-size="true"
                              track-by="id" display-by="name" :max="projects.requestInfo.game_versions?.max ?? 10"
                />
            </div>

            <div v-if="projects.requestInfo.loaders !== undefined && loaders.length">
                <p class="mb-1 fw-semibold">Mod Loaders</p>
                <CheckboxList v-model="projects.filters.loaders" :options="loaders" :capped-size="true"
                              track-by="id" display-by="name" :max="projects.requestInfo.loaders?.max ?? 10"
                />
            </div>

            <div v-if="projects.requestInfo.categories !== undefined"
                 v-for="(categories, name) in config.categoriesForProjectType(projects.filters.platform, projects.filters.projectType)"
            >
                <p class="mb-1 fw-semibold">{{ name === 'null' ? 'Categories' : name }}</p>
                <CheckboxList v-model="projects.filters.categories" :options="categories"
                              track-by="id" display-by="name" :model-by="route.isBrowse() ? 'remote_id' : 'id'"
                              :sort-by="categorySortBy()" :display-children="true" :max="projects.requestInfo.categories?.max"
                />
            </div>

            <div v-if="route.isArchive() || projects.requestInfo.sort_by !== undefined">
                <label for="sort-by" class="form-label fw-semibold mb-1">Sort by</label>
                <select v-model="projects.filters.sortBy" id="sort-by" class="form-select">
                    <option :value="option.id" v-for="option in projects.projectFiltersSortOptions">{{ option.name }}</option>
                </select>
            </div>

            <div v-if="route.isArchive()">
                <div class="form-check-inline m-0">
                    <label class="form-label m-0 text-nowrap">
                        <input type="checkbox" class="form-check-input" v-model="projects.filters.unmergedOnly" />
                        Unmerged only
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed, ref} from "vue";
import CheckboxList from "./common/CheckboxList.vue";
import {useConfigStore} from "../stores/config";
import {getProjectTypesById} from '../utils/utils';
import {debounce} from "lodash-es";
import {useMcaRoute} from "../hooks/route";
import {useProjectsStore} from "../stores/projects";

const config = useConfigStore();
const projects = useProjectsStore();
const route = useMcaRoute();

const onQueryInput = debounce((e) => projects.filters.query = e.target.value, 500);
const loaders = computed(() => projects.filters.platform
    ? config.getLoadersForPlatform(projects.filters.platform, [projects.filters.projectType])
    : config.loaders
);
const gameVersionsSearchQuery = ref('');
const displayAllGameVersions = ref(false);
const gameVersions = computed(() => {
    const gv = displayAllGameVersions.value ? config.gameVersions : config.gameVersions.filter(v => v.type === 0);
    return gameVersionsSearchQuery.value ? gv.filter(v => v.name.toLowerCase().includes(gameVersionsSearchQuery.value.toLowerCase())) : gv;
});

const categorySortBy = () => {
    // Categories starting with numbers sorted on top, then everything else
    return [
        category => {
            const int = parseInt(category.name);
            return isNaN(int) ? null : int;
        },
        category => category.name
    ];
}
</script>
