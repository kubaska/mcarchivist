<template>
    <Modal title="Archive project" @hide="onHide" size-class="modal-lg" :dialog-scrollable="true" ref="modal">
        <div v-if="ar.getRules().length" class="d-flex flex-column gap-2">
            <ArchiveRule :rule="rule" :platform-id="props.platformId" v-for="rule in ar.getRules()" @delete="ar.removeRule(rule.id)" :key="rule.id" />
        </div>
        <div v-else>
            <div class="alert alert-info">
                <p class="text-center m-0">Click 'Add rule' button, to add and configure archive rules.</p>
                <p class="text-center m-0">Alternatively, select a previously saved ruleset below.</p>
            </div>

            <div class="ruleset--list">
                <div class="form-check" v-for="ruleset in config.rulesets">
                    <input class="form-check-input" type="radio" v-model="selectedRuleset" :value="ruleset.id" name="ruleset" :id="'ruleset-'+ruleset.id">
                    <label class="form-check-label" :for="'ruleset-'+ruleset.id">{{ ruleset.name }} ({{ ruleset.rules.length }} {{ ruleset.rules.length === 1 ? 'rule' : 'rules' }})</label>
                </div>
            </div>
        </div>

        <template #footer>
            <button class="btn btn-primary" :disabled="loading" @click="onAddRuleBtnClick">Add rule</button>
            <MButton :loading="loading" @click="onArchiveBtnClick">{{ loading ? 'Archiving Project...' : 'Archive' }}</MButton>
        </template>
    </Modal>
</template>

<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import ArchiveRule from "../ArchiveRule.vue";
import {useConfigStore} from "../../stores/config";
import {useArchiveRules} from "../../hooks/archiveRules";
import { showErrorNotification } from "../../utils/notifications";

const props = defineProps({
    platformId: { type: String, required: false }
});
const config = useConfigStore();
const modal = ref(null);
const emit = defineEmits(['confirm']);

const ar = useArchiveRules();
const selectedRuleset = ref(null);
const loading = ref(false);

function onAddRuleBtnClick() {
    ar.addRule();
    selectedRuleset.value = null;
}

function finish(err) {
    if (err) {
        showErrorNotification('There was an error trying to archive project', 'Check browser console for details');
        console.log(err);
    } else {
        modal.value.hide();
        ar.reset();
    }
    loading.value = false;
}

function onArchiveBtnClick() {
    loading.value = true;
    const data = selectedRuleset.value ? { ruleset_id: selectedRuleset.value } : { rules: ar.getRulesForApi() };

    emit('confirm', data, finish);
}

function onHide() {
    ar.reset();
    selectedRuleset.value = null;
}

const show = () => modal.value.show();
const loadRules = (rules) => ar.loadRules(rules);

defineExpose({ show, loadRules });
</script>

<style lang="sass">
.ruleset--list
    display: grid
    grid-template-columns: 1fr

    @media (min-width: 992px)
        grid-template-columns: 1fr 1fr
</style>
