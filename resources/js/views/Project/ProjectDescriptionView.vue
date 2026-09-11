<template>
    <div class="row">
        <div class="col-9">
            <Gallery :images="projectsStore.project.gallery ?? []" />
            <div class="project--description mw-100">
                <div v-if="projectsStore.project.description === ''">
                    <p>This project does not have a description.</p>
                </div>
                <div v-else-if="projectsStore.project.description" v-html="projectsStore.project.description"></div>
                <div v-else>Loading...</div>
            </div>
        </div>
        <div class="col-3">
            <h5>Authors</h5>
            <hr class="mt-0" />
            <div class="d-flex flex-column gap-2" v-if="projectsStore.project.authors !== null || authors !== null">
                <Author :author="author" v-for="author in (authors ?? projectsStore.project.authors)" />
            </div>
            <div class="my-3 d-grid" v-else>
                <button class="btn btn-sm btn-outline-primary" @click="onLoadAuthorsBtnClick">Load Authors</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from "vue";
import api from "../../api/api";
import Author from "../../components/Author.vue";
import Gallery from "../../components/Gallery.vue";
import {useMcaRoute} from "../../hooks/route";
import {useProjectsStore} from "../../stores/projects";

const projectsStore = useProjectsStore();
const route = useMcaRoute();
const authors = ref(null);

function onLoadAuthorsBtnClick() {
    api.getProjectAuthors(projectsStore.project.id, { archived_only: route.isArchive(), platform: projectsStore.project.platform })
        .then(response => {
            authors.value = response.data.data;
        });
}
</script>

<style lang="sass">
.project--description img
    display: inline
    max-width: 100%
    height: auto
</style>
