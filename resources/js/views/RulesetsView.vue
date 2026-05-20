<template>
    <div class="me-2">
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

        <MTable :columns="[['Ruleset', ''], ['Name', 'Rule count', 'Date added', ''], ['Name', 'Rule count', 'Date added', '']]" class="mt-5">
            <MTableRow v-for="ruleset in config.rulesets" :key="ruleset.id">
                <MTableWrappingColumn class="gap-2 gap-md-0">
                    <MTableColumn>{{ ruleset.name }}</MTableColumn>

                    <MTableWrappingColumn>
                        <MTableColumn>{{ ruleset.rules.length }} {{ ruleset.rules.length === 1 ? 'rule' : 'rules' }}</MTableColumn>
                        <MTableColumn>
                            <fa-icon icon="calendar" class="d-sm-none me-1" />
                            <FormattedDate :date="ruleset.created_at" />
                        </MTableColumn>
                    </MTableWrappingColumn>
                </MTableWrappingColumn>
                <MTableColumn>
                    <button class="btn btn-icon" @click="onRulesetEditBtnClick(ruleset.id)">
                        <fa-icon icon="pencil" />
                    </button>
                    <button class="btn btn-icon" @click="onRulesetDeleteBtnClick(ruleset.id)">
                        <fa-icon icon="xmark" />
                    </button>
                </MTableColumn>
            </MTableRow>
        </MTable>

        <CommonDeleteModal ref="deleteModal" @delete="onRulesetDelete" />
        <RulesetEditModal ref="rulesetEditModal" @update="onRulesetUpdate" />
    </div>
</template>

<script setup>
import {ref} from "vue";
import {useConfigStore} from "../stores/config";
import MButton from "../components/base/MButton.vue";
import MTable from "../components/base/Table/MTable.vue";
import MTableRow from "../components/base/Table/MTableRow.vue";
import FormattedDate from "../components/base/FormattedDate.vue";
import MTableColumn from "../components/base/Table/MTableColumn.vue";
import RulesetEditModal from "../components/modals/RulesetEditModal.vue";
import CommonDeleteModal from "../components/modals/CommonDeleteModal.vue";
import MTableWrappingColumn from "../components/base/Table/MTableWrappingColumn.vue";

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
