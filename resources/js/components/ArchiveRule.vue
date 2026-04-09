<template>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <span class="accordion-button justify-content-between" type="button" data-bs-toggle="collapse" :data-bs-target="'#archive-rule-'+rule.id" aria-expanded="true" :aria-controls="'archive-rule-'+rule.id">
                    <span>{{ ruleTitle }}</span>
                    <button type="button" class="btn-close" aria-label="Close" @click="onDeleteBtnClick"></button>
                </span>
            </h2>
            <div :id="'archive-rule-'+rule.id" class="accordion-collapse collapse show">
                <div class="accordion-body d-flex flex-column gap-2">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span>Archive</span>
                        <input class="form-control w-mc" type="number" value="1" size="5" min="1" max="999" v-model="rule['count']">
                        <select class="form-select w-mc" v-model="rule['sorting']">
                            <option :value="false">latest</option>
                            <option :value="true">oldest</option>
                        </select>
                        <select class="form-select w-mc" v-model="rule['release_type']">
                            <option value="*">* [any release type]</option>
                            <option value="0">Release</option>
                            <option value="1">Beta</option>
                            <option value="2">Alpha</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-1 gap-md-3 gap-lg-5">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" v-model="rule.release_type_priority" :id="'release-priority' + rule.id">
                            <label class="form-check-label" :for="'release-priority' + rule.id">Prioritize stable releases</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" v-model="rule.all_files" :id="'all-files' + rule.id">
                            <label class="form-check-label" :for="'all-files' + rule.id">Archive optional files</label>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span>for Minecraft</span>
                        <select class="form-select w-mc" v-model="versionOperand" @change="onVersionOperandChange">
                            <option value="=">=</option>
                            <option value="between">between</option>
                        </select>
                        <select class="form-select w-mc" v-model="rule['game_version_from']">
                            <option value="*">any</option>
                            <option v-for="version in gameVersions" :value="version.name">{{ version.name }}</option>
                        </select>
                        <template v-if="versionOperand === 'between'">
                            <span>-</span>
                            <select class="form-select w-mc" v-model="rule['game_version_to']">
                                <option value="*">any</option>
                                <option v-for="version in gameVersions" :value="version.name">{{ version.name }}</option>
                            </select>
                        </template>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" v-model="rule.with_snapshots" :id="'with-snapshots' + rule.id">
                            <label class="form-check-label" :for="'with-snapshots' + rule.id">Include snapshots</label>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span>for Loader</span>
                        <select class="form-select w-mc" v-model="rule['loader_id']">
                            <option value="*">any</option>
                            <option v-for="loader in loaders" :value="loader.id">{{ loader.name }}</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span>Dependencies</span>
                        <select class="form-select w-mc" v-model="rule.dependencies">
                            <option value="0">off</option>
                            <option value="1">required only</option>
                            <option value="2">all</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed, onBeforeMount, ref} from "vue";
import {useConfigStore} from "../stores/config";
import {VERSION_TYPES} from "../utils/utils";

const props = defineProps({
    rule: { type: Object, required: true },
    platformId: { type: String, required: false }
});
const config = useConfigStore();
const emit = defineEmits(['delete']);

const versionOperand = ref('=');
const releaseTypeTitle = computed(() => VERSION_TYPES[props.rule.release_type]?.name.toLowerCase() ?? '');

const ruleTitle = computed(() => {
    const versions = versionOperand.value === '='
        ? props.rule.game_version_from
        : `${props.rule.game_version_from} - ${props.rule.game_version_to}`;
    const quantifier = props.rule.count === 1 ? 'version' : 'versions';
    return `Archive ${props.rule.count} ${props.rule.sorting ? 'oldest' : 'latest'} ${releaseTypeTitle ? releaseTypeTitle.value+' ' : ''}${quantifier} for Minecraft ${versions}`;
});
const gameVersions = computed(() => {
    if (props.rule.with_snapshots) return config.gameVersions;
    else return config.gameVersions.filter(v => v.type !== 3);
});
const loaders = computed(() => props.platformId
    ? config.getLoadersForPlatformAnyProjectType(props.platformId)
    : config.loaders
);

function onVersionOperandChange(e) {
    if (e.target.value === 'between') {
        props.rule['game_version_to'] = '*';
    } else {
        props.rule['game_version_to'] = null;
    }
}

function onDeleteBtnClick() {
    emit('delete', props.rule.id);
}

onBeforeMount(() => {
    if (props.rule.game_version_to !== null) {
        versionOperand.value = 'between';
    }
});
</script>

<style lang="sass" scoped>
.accordion-button:after
    display: none
</style>
