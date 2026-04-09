<template>
    <Modal ref="modal" title="Edit ruleset" size-class="modal-lg" :dialog-scrollable="true">
        <div class="mb-3 row">
            <label for="ruleset-name" class="col-sm-2 col-form-label">Name</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="ruleset-name" v-model="rulesetName">
            </div>
        </div>

        <div v-if="ar.getRules().length" class="d-flex flex-column gap-2">
            <ArchiveRule :rule="rule" v-for="rule in ar.getRules()" @delete="ar.removeRule(rule.id)" :key="rule.id" />
        </div>
        <div v-else>
            <p class="text-center m-2 fw-semibold">This ruleset does not contain any rules yet!</p>
        </div>

        <template #footer>
            <button class="btn btn-primary" @click="ar.addRule()">Add rule</button>
            <MButton :loading="loading" @click="onConfirmBtnClick">Save</MButton>
        </template>
    </Modal>
</template>

<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import MButton from "../base/MButton.vue";
import ArchiveRule from "../ArchiveRule.vue";
import {useArchiveRules} from "../../hooks/archiveRules";
import {useNotificationStore} from "../../stores/notifications";

const ruleset = ref(null);
const ar = useArchiveRules();
const rulesetName = ref('');
const modal = ref(null);
const loading = ref(false);
const emit = defineEmits(['update']);
const notifications = useNotificationStore();

function onConfirmBtnClick() {
    loading.value = true;
    emit('update', ruleset.value.id, rulesetName.value, ar.getRulesForApi(), finish);
}

function finish(error) {
    if (error) {
        notifications.add('Failed to save archive rules', 'Check browser console for details.', 'danger');
        console.error(error);
    } else {
        ruleset.value = null;
        rulesetName.value = null;
        ar.reset();
        modal.value.hide();
    }

    loading.value = false;
}

const loadRuleset = (newRuleset, rules) => {
    ruleset.value = newRuleset;
    rulesetName.value = newRuleset.name;
    ar.loadRules(rules);
}
const show = () => modal.value.show();

defineExpose({ loadRuleset, show });
</script>
