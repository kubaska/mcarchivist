import {ref, unref} from "vue";
import {clone, omit} from "lodash-es";

export const useArchiveRules = () => {
    // This ID is only used as an internal tracker for creating new rules and is filtered out later
    const id = ref(0);
    const rules = ref([]);

    const loadRules = (newRules) => rules.value = clone(unref(newRules));
    const addRule = () => {
        rules.value.push({
            id: --id.value,
            count: 1,
            game_version_from: '*',
            game_version_to: null,
            with_snapshots: false,
            loader_id: '*',
            sorting: false,
            release_type: '*',
            release_type_priority: 0,
            dependencies: 1,
            all_files: 0
        });
    }
    const removeRule = id => rules.value = rules.value.filter(rule => rule.id !== id);
    const getRules = () => rules.value;
    const getRulesForApi = () => rules.value.map(function (rule) {
        // Remove any negative IDs, only used for internal identification
        if (rule.id <= 0) return omit(rule, 'id');
        return rule;
    });
    const reset = () => rules.value = [];

    return { loadRules, addRule, removeRule, getRules, getRulesForApi, reset };
};
