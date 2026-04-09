<template>
    <div class="carousel slide" ref="carousel" id="carousel">
        <div class="carousel-inner">
            <div class="carousel-item" :class="{ 'active': i === 0 }" v-for="(image, i) in images">
                <img :src="image.url" class="d-block w-100" alt="">
                <div class="carousel-caption d-none d-md-block" v-if="image.title">
                    <h5>{{ image.title }}</h5>
                    <p v-if="image.description">{{ image.description }}</p>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</template>

<script setup>
import {ref} from "vue";
import {useBsCarousel} from "../hooks/bootstrap";

const props = defineProps({
    images: { type: Array, required: true }
});

const carousel = ref(null);
const { bsComponent: bsCarousel } = useBsCarousel(carousel);
</script>

<style lang="sass">
.carousel
    background-color: var(--bs-dark)

.carousel-item > img
    object-fit: scale-down
    object-position: center
    height: 480px
    overflow: hidden

.carousel-item:before
    content: ""
    background-image: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.5))
    display: block
    position: absolute
    bottom: 0
    width: 100%
    height: 25%

.carousel-caption
    bottom: 0
    padding-bottom: 0.25rem
</style>
