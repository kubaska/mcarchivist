<template>
    <Modal ref="modal" :title="type+' List'" :show-footer="false" @hide="data = null" size-class="modal-lg">
        <LoadingSpinner v-if="data === null" />
        <div class="my-2 text-center" v-else-if="data.length === 0">
            <p class="m-0">This version does not list any {{ type.toLowerCase() }}.</p>
        </div>
        <div class="d-flex flex-column gap-2" v-else>
            <Dependency v-for="project in data" :project="project" @action="onDepAction" />
        </div>
    </Modal>
</template>
<script setup>
import {ref} from "vue";
import Modal from "../base/Modal.vue";
import Dependency from "../Dependency.vue";
import LoadingSpinner from "../base/LoadingSpinner.vue";
import {useRouter} from "vue-router";
import {useMcaRoute} from "../../hooks/route";

const modal = ref();
const type = ref('');
const data = ref(null);
const route = useMcaRoute();
const router = useRouter();

function onDepAction(action, data) {
    switch (action) {
        case 'navigate':
            router.push({ name: route.getRouteForBase('project'), params: { source: route.params.source, id: data } });
            modal.value.hide();
            break;
    }
}

defineExpose({
    hide: () => modal.value.hide(),
    show: () => modal.value.show(),
    setData: (t, d) => {
        type.value = t;
        data.value = d;
    }
});
</script>
