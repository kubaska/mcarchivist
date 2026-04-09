<template>
    <nav aria-label="Page navigation">
        <ul class="pagination m-0">
            <li class="page-item disabled" v-if="current === 1">
                <span class="page-link" aria-hidden="true">&laquo;</span>
            </li>
            <li class="page-item" v-else>
                <a class="page-link" href="#" rel="prev" @click.prevent="onPrevPage">&laquo;</a>
            </li>

            <li class="page-item" :class="{ active: step === current, disabled: !Number.isInteger(step) }" v-for="step in steps" :key="step">
                <a class="page-link" href="#" @click.prevent="onNavigate(step)">{{ step }}</a>
            </li>

            <li class="page-item disabled" v-if="current === total">
                <span class="page-link" aria-hidden="true">&raquo;</span>
            </li>
            <li class="page-item" v-else>
                <a class="page-link" href="#" rel="next" @click.prevent="onNextPage">&raquo;</a>
            </li>
        </ul>
    </nav>

</template>

<script setup>
import {computed} from "vue";
import {range} from "lodash-es";

const ON_EACH_SIDE = 2;

const props = defineProps({
    current: { type: Number, required: true },
    total: { type: Number, required: true }
});

const emit = defineEmits(['change', 'prev', 'next', 'navigate']);

const steps = computed(() => {
    const window = ON_EACH_SIDE + 4;

    if (props.total <= (ON_EACH_SIDE * 2) + 6) {
        // render all
        return range(1, props.total + 1);
    } else if (props.current <= window) {
        // render 1,2,3,4,5,...,30
        return [...range(1, window + ON_EACH_SIDE + 1), '...', props.total];
    } else if (props.current > (props.total - window)) {
        // render 1,...,26,27,28,29,30
        return [1, '...', ...range(props.total - (window + (ON_EACH_SIDE - 1)), props.total + 1)];
    } else {
        // render 1,...,14,15,16,...,30
        return [1, '...', ...range(props.current - ON_EACH_SIDE, props.current + ON_EACH_SIDE + 1), '...', props.total];
    }
});

function onPrevPage() {
    emit('prev');
    emit('change', props.current - 1);
}
function onNextPage() {
    emit('next');
    emit('change', props.current + 1);
}
function onNavigate(step) {
    if (props.current === step) return;
    emit('navigate', step);
    emit('change', step);
}
</script>
