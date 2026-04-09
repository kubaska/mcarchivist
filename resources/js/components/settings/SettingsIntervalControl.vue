<template>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">Automatic archiving behavior</p>
            <span class="fs-7 text-muted">Select how the automatic archiving process will run.</span>
        </div>
        <div class="col-12 col-md-8">
            <select v-model.number="model[actionSettingName]" class="form-select">
                <option value="0">Do nothing.</option>
                <option value="1">Refresh index only, according to schedule.</option>
                <option value="2">Automatically archive versions, according to schedule.</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-md-4">
            <p class="m-0 fw-semibold">Automatic archiving interval</p>
            <span class="fs-7 text-muted">Interval which determines how often archiving process will run.</span>
        </div>
        <div class="col-12 col-md-8">
            <div class="d-flex gap-1 align-items-center">
                <span>Run every</span>
                <select v-model.number="model[intervalSettingName]" class="form-select form-select-sm w-initial">
                    <option :value="i" v-for="i in intervalRange">{{ i }}</option>
                </select>
                <select v-model="model[intervalUnitSettingName]" class="form-select form-select-sm w-initial">
                    <option value="h">hour(s)</option>
                    <option value="d">day(s)</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed, watch} from "vue";
import {range} from 'lodash-es';

const model = defineModel();
const props = defineProps({
    actionSettingName: { type: String, required: true },
    intervalSettingName: { type: String, required: true },
    intervalUnitSettingName: { type: String, required: true },
});

// End number is not inclusive so we add 1
const intervalRange = computed(() => range(1, model.value[props.intervalUnitSettingName] === 'h' ? 25 : 31));

// Watch interval unit setting and react if user changes it to hours to prevent interval range field going blank
watch(() => model.value[props.intervalUnitSettingName], (newValue) => {
    if (newValue === 'h' && model.value[props.intervalSettingName] > 24) {
        model.value[props.intervalSettingName] = 24;
    }
});
</script>
