<template>
    <div class="dropdown" :class="directionCss">
        <div data-bs-toggle="dropdown">
            <slot></slot>
        </div>
        <ul class="dropdown-menu dropdown-menu-end">
            <slot name="menu">
                <template v-for="option in options.filter(opt => !opt.hidden)">
                    <li v-if="option.separator"><hr class="dropdown-divider"></li>
                    <li v-else><a class="dropdown-item"
                                  :class="{ disabled: typeof option.disabled === 'function' ? option.disabled(context) : option.disabled }"
                                  :href="option.link ? option.link : '#'"
                                  :target="option.linkNewTab ? '_blank' : null"
                                  :referrerpolicy="option.linkNewTab ? 'no-referrer' : null"
                                  @click="(e) => { if(! option.link) e.preventDefault(); option.onClick ? option.onClick(context) : null }"
                    ><span>{{ option.name }}</span><fa-icon icon="arrow-up-right-from-square" class="ms-2" v-if="option.linkNewTab" /></a></li>
                </template>
            </slot>
        </ul>
    </div>
</template>

<script setup>
import {computed} from "vue";

const props = defineProps({
    options: { type: Array, required: false, default: [] },
    context: { type: Object, required: false, default: undefined },
    direction: { type: String, required: false }
});

const directionCss = computed(() => {
    switch (props.direction) {
        case 'up': return 'dropup';
        case 'start': return 'dropstart';
        case 'end': return 'dropend';
        default: return '';
    }
});
</script>
