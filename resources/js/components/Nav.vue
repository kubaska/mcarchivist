<template>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <div class="d-flex gap-2 w-100 justify-content-between d-lg-contents">
                <div class="d-flex gap-2">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <router-link :to="{ name: 'home' }" class="navbar-brand">mcarchivist</router-link>
                </div>
                <div class="d-flex gap-2" style="order: 9;">
                    <QueueNotification />
                    <router-link :to="{ name: 'settings' }">
                        <button class="btn">
                            <fa-icon icon="cog" />
                        </button>
                    </router-link>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <router-link :to="{ name: 'browse' }" class="nav-link"
                                     :class="{ active: routeIsActive('/browse') }"
                        >Browse</router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'game-versions' }" class="nav-link"
                                     :class="{ active: routeIsActive('/game-versions') }"
                        >Game Versions</router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'loaders' }" class="nav-link"
                                     :class="{ active: routeIsActive('/loaders') }"
                        >Loaders</router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'rulesets' }" class="nav-link"
                                     :class="{ active: routeIsActive('/rulesets') }"
                        >Rulesets</router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'archive' }" class="nav-link"
                                     :class="{ active: routeIsActive('/archive') }"
                        >Archive</router-link>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>
<script setup>
import QueueNotification from "./QueueNotification.vue";
import {useRoute} from "vue-router";

const route = useRoute();

function routeIsActive(startUrl) {
    return route.path.startsWith(startUrl);
}
</script>
