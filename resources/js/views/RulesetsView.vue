<template>
    <div>
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 my-3 align-items-center">
            <h3 class="m-0">Rulesets</h3>
            <form @submit.prevent="createRuleset">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Enter ruleset name" v-model="newRulesetName" required />
                    <MButton :loading="isCreatingRuleset" @click="createRuleset">Add Ruleset</MButton>
                </div>
            </form>
        </div>
        <hr/>

        <RulesetTable :rulesets="config.rulesets" @edit="onRulesetEditBtnClick" @delete="onRulesetDeleteBtnClick" />

        <CommonDeleteModal ref="deleteModal" @delete="onRulesetDelete" />
        <RulesetEditModal ref="rulesetEditModal" @update="onRulesetUpdate" />
    </div>
</template>

<script setup>
import {ref} from "vue";
import MButton from "../components/base/MButton.vue";
import RulesetTable from "../components/rulesets/RulesetTable.vue";
import RulesetEditModal from "../components/modals/RulesetEditModal.vue";
import CommonDeleteModal from "../components/modals/CommonDeleteModal.vue";
import {useConfigStore} from "../stores/config";

const config = useConfigStore();
const deleteModal = ref(null);
const rulesetEditModal = ref(null);
const newRulesetName = ref('');
const isCreatingRuleset = ref(false);

function createRuleset() {
    if (! newRulesetName.value) return;
    isCreatingRuleset.value = true;

    config.createRuleset(newRulesetName.value)
        .finally(() => {
            newRulesetName.value = '';
            isCreatingRuleset.value = false;
        });
}

function onRulesetEditBtnClick(id) {
    const ruleset = config.rulesets.find(ruleset => ruleset.id === id);
    if (! ruleset) return;
    rulesetEditModal.value.loadRuleset(ruleset, ruleset.rules);
    rulesetEditModal.value.show();
}

function onRulesetUpdate(id, name, rules, finish) {
    config.updateRuleset(id, { name, rules })
        .then(() => finish())
        .catch(err => finish(err));
}

function onRulesetDeleteBtnClick(id) {
    deleteModal.value.setData(id, 'this ruleset');
    deleteModal.value.show();
}
function onRulesetDelete(id, finish) {
    config.deleteRuleset(id)
        .then(() => finish())
        .catch(err => finish(err));
}
</script>
