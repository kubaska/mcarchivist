<template>
    <div class="border d-flex gap-2 align-items-center" :class="{ 'cursor-pointer': selectable, 'border-black': selected }" @click="onSelect">
        <img :src="project.logo" alt="Project logo" class="mx-2" style="width: 4rem; height: 4rem;" loading="lazy" decoding="async" :key="project.logo">
        <div class="d-flex flex-column flex-grow-1 justify-content-evenly py-1">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <router-link class="text-reset text-decoration-none" v-if="withNavigation"
                                 :to="{ name: routeName, params: { id: project[props.routeName.startsWith('archive') ? 'id' : 'remote_id'], source: project.platform } }"
                    >
                        <p class="m-0 fs-6 fw-bold" @click="onNavigate">{{ project.name }}</p>
                    </router-link>
                    <p class="m-0 fs-6 fw-bold" v-else>{{ project.name }}</p>
                    <fa-icon icon="arrow-right-arrow-left" v-if="showMergedProjects && project.merged_projects_count > 1"
                             v-tooltip="project.merged_projects_count + ' merged projects'"
                    />
                    <span class="badge text-bg-primary fs-9" v-if="showDefault && project.default">Default</span>
                </div>
                <p class="m-0 fs-7 lh-md">{{ project.summary }}</p>
            </div>
            <div class="d-flex gap-1 flex-wrap mt-1 fs-7">
                <span class="badge" :style="`background-color:${platform.theme_color};`" v-if="showPlatformBadge">{{ platform.name }}</span>
                <span class="badge text-bg-secondary" v-for="type in getProjectTypesById(project.project_types)">{{ type.name }}</span>
                <span class="badge text-bg-primary" v-if="project.downloads !== null">{{ numberFormatter.format(project.downloads) }} downloads</span>
                <span class="badge text-bg-success" v-if="project.local_version_count">{{ project.local_version_count }} version(s) archived</span>
                <span class="badge text-bg-info" v-for="category in project.categories" v-if="showCategories">{{ category.name }}</span>
            </div>
        </div>
        <div class="d-flex flex-column justify-content-between align-self-stretch px-2 py-1" v-if="showControls">
            <MDropdown :options="allDropdownOptions" :context="project" direction="start">
                <button class="btn btn-icon">
                    <fa-icon icon="ellipsis-vertical" />
                </button>
            </MDropdown>

            <button class="btn btn-icon" v-if="showArchiveButton" :class="{ 'bg-success-subtle': project.is_archiving }"
                    @click="$emit('archive', project)"
            >
                <fa-icon icon="box-archive" />
            </button>
        </div>
    </div>
</template>

<script setup>
import {computed} from "vue";
import {useRouter} from "vue-router";
import {useStore} from "../stores/store";
import {useMcaRoute} from "../hooks/route";
import {useConfigStore} from "../stores/config";
import {useNumberFormatter} from "../hooks/formatter";
import {getProjectTypesById, isDescendantOf} from "../utils/utils";
import MDropdown from "./base/MDropdown.vue";

const route = useMcaRoute();
const props = defineProps({
    project: { type: Object, required: true },
    routeName: { type: String, required: false, default(props) {
        return props.project.project_id ? 'archive.project' : 'browse.project'
    } },
    showMergedProjects: { type: Boolean, required: false, default: true },
    showCategories: { type: Boolean, required: false, default: true },
    showPlatformBadge: { type: Boolean, default: false },
    showControls: { type: Boolean, default: true },
    showArchiveButton: { type: Boolean, required: false, default: true },
    showDefault: { type: Boolean, required: false, default: false },
    withNavigation: { type: Boolean, required: false, default: true },
    dropdownOptions: { type: Array, required: false, default: [] },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false }
});
const config = useConfigStore();
const router = useRouter();
const store = useStore();
const numberFormatter = useNumberFormatter();
const platform = computed(() => config.getPlatform(props.project.platform));
const allDropdownOptions = computed(() => [
    { name: 'Open project page', link: props.project.project_url, linkNewTab: true },
    ...props.dropdownOptions
]);

const emit = defineEmits(['archive', 'select', 'navigate']);

function onSelect(e) {
    // Don't select when clicking buttons
    if (isDescendantOf(e.target, element => element.classList.contains('dropdown'))) return;
    emit('select', props.project);
}

function onNavigate() {
    if (! props.withNavigation) return;
    emit('navigate', props.project);
    store.setProject(props.project);
}
</script>
