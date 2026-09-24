import {defineStore} from "pinia";
import api from "../api/api";
import {useConfigStore} from "./config";
import {computed, ref, watch} from "vue";
import {useMcaRoute} from "../hooks/route";
import {castArray, clamp, pickBy, take, truncate} from "lodash-es";
import {getDefaultSearchRequestInfo, getLocalSortingOptions, getProjectTypes, toBool} from "../utils/utils";

export const useProjectsStore = defineStore('projects', () => {
    const config = useConfigStore();
    const route = useMcaRoute();

    const initialPlatform = (route.params.source
            ? config.getPlatform(route.params.source.toLowerCase())?.id
            : (route.isArchive() ? '' : null)
        ) ?? config.availablePlatforms?.[0].id;
    let requestInfo = initialPlatform ? config.getRequestInfo(initialPlatform, 'search') : getDefaultSearchRequestInfo();

    const defaultProjectType = requestInfo?.project_type?.options?.[0] ?? getProjectTypes()[0].id;
    const initialProjectType = (
        route.query.project_type
            ? requestInfo?.project_type?.options?.find(type => type === parseInt(route.query.project_type))
            : null)
        ?? defaultProjectType;

    let projectFiltersSortOptions = route.isArchive() ? getLocalSortingOptions() : (requestInfo?.sort_by?.options ?? []);

    // State
    const projects = ref([]);
    const filters = ref({
        platform: initialPlatform,
        projectType: initialProjectType,
        query: route.query.query
            ? truncate(route.query.query, { length: requestInfo?.query?.max ?? 100, omission: '' })
            : '',
        gameVersions: route.query.game_versions
            ? take(
                config.gameVersions.filter(gv => castArray(route.query.game_versions).includes(gv.name)),
                requestInfo?.game_versions?.max ?? 10
            )
            : [],
        loaders: route.query.loaders
            ? take(
                config.loaders.filter(loader => castArray(route.query.loaders).map(i => parseInt(i)).includes(loader.id)),
                requestInfo?.loaders?.max ?? 10
            )
            : [],
        categories: route.query.categories
            ? take(
                config.categories.filter(cat => cat.platform === initialPlatform && castArray(route.query.categories).includes(cat.remote_id)).map(cat => cat.remote_id),
                requestInfo?.categories?.max ?? 10
            )
            : [],
        sortBy: (route.query.sort_by
            ? projectFiltersSortOptions.find(sortOpt => sortOpt.id == route.query.sort_by)?.id
            : null) ?? projectFiltersSortOptions?.[0]?.id,
        unmergedOnly: toBool(route.query.unmerged_only)
    });

    const initialPage = route.query.page ? parseInt(route.query.page) : 1;
    const initialPageMax = requestInfo?.page?.max;
    const pagination = ref({
        page: initialPageMax ? clamp(initialPage, 1, initialPageMax) : initialPage,
        lastPage: 1,
        total: null
    });

    requestInfo = computed(() => filters.value.platform
        ? config.getRequestInfo(filters.value.platform, 'search')
        : getDefaultSearchRequestInfo()
    );
    projectFiltersSortOptions = computed(() => route.isArchive()
        ? getLocalSortingOptions()
        : (requestInfo.value?.sort_by?.options ?? [])
    );

    watch(route.getBase, () => {
        if (! route.isArchiveOrBrowse()) return;

        if (filters.value.platform === '' && route.isBrowse()) {
            filters.value.platform = config.availablePlatforms?.[0].id;
        }
    });

    // If platform changes, check if currently selected project type is still available
    watch(() => filters.value.platform, () => {
        if (! requestInfo.value?.project_type?.options?.some(type => type === filters.value.projectType)) {
            filters.value.projectType = requestInfo.value?.project_type?.options?.[0];
        }

        filters.value.categories = [];
    });

    // If sort options changes, check if currently selected option is still available
    watch(projectFiltersSortOptions, () => {
        if (! ['archive', 'browse'].includes(route.name)) return;
        if (! projectFiltersSortOptions.value.some(option => option.id === filters.value.sortBy)) {
            filters.value.sortBy = projectFiltersSortOptions.value?.[0]?.id;
        }
    });

    const project = ref(null);

    // Getters
    const filtersSnakeCased = computed(() => ({
        project_type: filters.value.projectType,
        game_versions: filters.value.gameVersions.map(v => v.name),
        ...pickBy({
            platform: filters.value.platform,
            query: filters.value.query,
            loaders: filters.value.loaders ? filters.value.loaders.map(l => l.id) : null,
            categories: filters.value.categories,
            sort_by: filters.value.sortBy,
            unmerged_only: filters.value.unmergedOnly
        }, (i) => !!i)
    }));

    // Actions
    function getProjects(options = {}) {
        return api.searchProjects({
            archived_only: route.isArchive(),
            page: pagination.value.page,
            ...filtersSnakeCased.value,
            ...options
        }).then(response => {
            projects.value = response.data.data;
            // Limit page to max value, if exists
            pagination.value.lastPage = requestInfo.value?.page?.max
                ? ((response.data.meta.last_page > requestInfo.value.page.max) ? requestInfo.value.page.max : response.data.meta.last_page)
                : response.data.meta.last_page;
            pagination.value.total = response.data.meta.total;
        }).catch(err => {
            projects.value = [];
            pagination.value.page = 1;
            pagination.value.lastPage = 1;
            pagination.value.total = null;
            throw err;
        });
    }

    function resetFilters() {
        filters.value.query = '';
        filters.value.gameVersions = [];
        filters.value.loaders = [];
        filters.value.categories = [];
    }

    function replaceProject(oldProject, newProject) {
        const index = projects.value.findIndex(p => oldProject.remote_id === p.remote_id && oldProject.platform === p.platform);
        if (index >= 0) {
            projects.value[index] = newProject;
        } else {
            console.log('Unable to replace project, as it does not exist!', oldProject);
        }
    }

    function getProject(id, options = {}) {
        return api.getProject(id, options)
            .then(response => {
                project.value = response.data.data;

                return response;
            });
    }

    function setProject(_project) {
        project.value = _project;
    }
    function resetActiveProject() {
        project.value = null;
    }

    return {
        projects, filters, pagination, project,
        requestInfo, projectFiltersSortOptions, filtersSnakeCased,
        getProjects, resetFilters, replaceProject, getProject, setProject, resetActiveProject
    };
});
