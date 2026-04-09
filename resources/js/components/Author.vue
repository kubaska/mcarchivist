<template>
    <div class="d-flex gap-2">
        <img :src="author.avatar_url ?? defaultAvatarUrl"
             :alt="author.avatar_url ? author.name + '\'s avatar' : 'default avatar'"
             class="author--avatar"
        >
        <div class="d-flex flex-column">
            <span class="fw-bold">{{ author.name }}</span>
            <span class="fs-8">{{ author.role ?? '(no role)' }}</span>
        </div>
    </div>
</template>

<script setup>
import {defaultAvatarUrl} from '../defaults';

const props = defineProps({
    author: { type: Object, required: true }
});
</script>

<style lang="sass">
.author--avatar
    width: 3rem
    height: 3rem
</style>
