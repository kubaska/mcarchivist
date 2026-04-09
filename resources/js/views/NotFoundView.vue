<template>
    <div class="d-flex flex-column justify-content-center gap-3 my-5">
        <p class="fs-4 text-center m-0">404 - Not Found!</p>
        <div class="m-auto d-flex gap-3 flex-column flex-md-row">
            <button class="btn btn-primary" @click="router.go(-1)">Return to previous page</button>
            <button class="btn btn-primary" @click="router.push({ name: 'browse' })">Return to Project Browser</button>
        </div>
    </div>
</template>

<script setup>
import {useRouter} from "vue-router";

const router = useRouter();
</script>
