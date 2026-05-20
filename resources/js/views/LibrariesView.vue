<template>
    <div class="mb-2">
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between my-3">
            <h3 class="m-0">Libraries</h3>

            <div class="d-flex gap-2">
                <input type="text" class="form-control" placeholder="Search..." v-model="filters.query">

                <SelectableDropdown v-model="sortOption" :options="sortOptions"
                                    :multiple="false" track-by="id" display-by="name" close-behavior="outside"
                >
                    <button class="btn btn-primary">
                        <fa-icon icon="filter" />
                    </button>
                    <template #beforeMenu>
                        <div class="d-flex gap-2 align-items-center justify-content-between border-bottom px-2 py-1">
                            <span class="fw-semibold">Sort by</span>
                            <button class="btn btn-icon btn-icon-sm"
                                    :title="filters.sort_direction === 'asc' ? 'Ascending' : 'Descending'"
                                    @click="filters.sort_direction = filters.sort_direction === 'asc' ? 'desc' : 'asc'"
                            >
                                <fa-icon :icon="filters.sort_direction === 'asc' ? 'arrow-down-short-wide' : 'arrow-down-wide-short'" />
                            </button>
                        </div>
                    </template>
                </SelectableDropdown>
            </div>
        </div>
        <hr/>

        <LoadingSpinner v-if="!data" />
        <template v-else>
            <div class="d-flex justify-content-end mb-2">
                <Pagination v-if="data?.meta"
                            :current="data?.meta.current_page" :total="data?.meta.last_page" @change="onPaginatorChange"
                />
            </div>

            <MTable :columns="[['Library', ''], ['Name', 'Size', ''], ['Name', 'Size', '']]">
                <MTableRow v-for="library in data.data" :key="library.id">
                    <MTableWrappingColumn>
                        <MTableColumn>{{ library.name }}</MTableColumn>
                        <MTableColumn>{{ formatBytes(library.size) }}</MTableColumn>
                    </MTableWrappingColumn>

                    <MTableColumn>
                        <MDropdown :options="dropdownOptions" :context="library" direction="start">
                            <button class="btn btn-icon">
                                <fa-icon icon="ellipsis-vertical" />
                            </button>
                        </MDropdown>
                    </MTableColumn>
                </MTableRow>
            </MTable>

            <div class="d-flex justify-content-end my-2" v-if="data.data.length > 10">
                <Pagination v-if="data?.meta"
                            :current="data?.meta.current_page" :total="data?.meta.last_page" @change="onPaginatorChange"
                />
            </div>
        </template>

        <FileDetailsModal ref="fileDetailsModal" />
    </div>
</template>

<script setup>
import api from "../api/api";
import {ref, watch} from "vue";
import {omitBy} from "lodash-es";
import {useAxios} from "../hooks/axios";
import {formatBytes} from "../utils/utils";
import {showErrorNotification} from "../utils/notifications";
import MDropdown from "../components/base/MDropdown.vue";
import MTable from "../components/base/Table/MTable.vue";
import Pagination from "../components/base/Pagination.vue";
import MTableRow from "../components/base/Table/MTableRow.vue";
import LoadingSpinner from "../components/base/LoadingSpinner.vue";
import MTableColumn from "../components/base/Table/MTableColumn.vue";
import FileDetailsModal from "../components/modals/FileDetailsModal.vue";
import SelectableDropdown from "../components/common/SelectableDropdown.vue";
import MTableWrappingColumn from "../components/base/Table/MTableWrappingColumn.vue";

const fileDetailsModal = ref(null);

const sortOptions = [
    { id: 'name', name: 'Name' },
    { id: 'size', name: 'Size' },
    { id: 'date', name: 'Date' },
];
const dropdownOptions = [
    { name: 'Details', onClick: onDetailsOptionChoose },
];

const sortOption = ref(sortOptions[0]);
const filters = ref({
    query: '',
    sort: sortOption.value.id,
    sort_direction: 'asc'
});

const { data, execute } = useAxios('/libraries');

function onPaginatorChange(page) {
    onFiltersChanged({ page });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onFiltersChanged(options = {}) {
    execute({ params: omitBy({ ...filters.value, ...options }, i => !i) });
}

function onDetailsOptionChoose(library) {
    fileDetailsModal.value.show();
    api.getLibrary(library.id)
        .then(res => {
            fileDetailsModal.value.setData(res.data);
        })
        .catch(err => {
            fileDetailsModal.value.hide();
            showErrorNotification('Failed to fetch library details');
            console.log(err);
        });
}

watch(sortOption, option => filters.value.sort = option.id);
watch(filters.value, () => {
    onFiltersChanged({ page: 1 });
});
</script>
