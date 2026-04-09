<template>
    <div>
        <div>
            <h3 class="my-2">Loaders</h3>
        </div>
        <hr class="mt-0" />
        <div class="row row-gap-1 gx-2">
            <div class="col-md-6 col-lg-4 col-xxl-3" v-for="loader in config.loaders">
                <router-link :to="{ name: 'loader', params: { slug: loader.slug } }" class="text-decoration-none">
                    <div class="card">
                        <div class="card-body">
                            <fa-icon icon="star" v-if="loader.promoted" style="color: gold;" /> <span class="card-title text-center">{{ loader.name }}</span>
                        </div>
                    </div>
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import {useConfigStore} from "../stores/config";

const config = useConfigStore();
</script>
