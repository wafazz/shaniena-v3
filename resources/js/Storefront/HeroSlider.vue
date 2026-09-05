<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

/**
 * The homepage hero.
 *
 * The source's version was three hardcoded .webp files, and the whole block
 * was commented out — so the Slider Setting screen in the console has been
 * managing a table nothing on the storefront ever read. This renders that
 * table.
 */
const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const el = ref(null);
let swiper = null;

onMounted(() => {
    if (props.slides.length < 2) return;

    // One slide is a banner, not a carousel: no autoplay, no controls, no loop.
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    swiper = new Swiper(el.value, {
        modules: [Navigation, Pagination, Autoplay, Keyboard, A11y],
        loop: true,
        speed: 600,
        // The source ran a 6s rotation; kept, but never against someone who
        // has asked the OS for less motion.
        autoplay: reduced ? false : { delay: 6000, disableOnInteraction: true },
        keyboard: { enabled: true },
        a11y: { prevSlideMessage: 'Previous slide', nextSlideMessage: 'Next slide' },
        navigation: { prevEl: '.hero-slider__prev', nextEl: '.hero-slider__next' },
        pagination: { el: '.hero-slider__dots', clickable: true },
    });
});

onBeforeUnmount(() => swiper?.destroy(true, true));
</script>

<template>
    <section v-if="slides.length" class="hero-slider">
        <div ref="el" class="swiper">
            <div class="swiper-wrapper">
                <div v-for="slide in slides" :key="slide.id" class="swiper-slide">
                    <component
                        :is="slide.link ? Link : 'div'"
                        v-bind="slide.link ? { href: slide.link } : {}"
                        class="hero-slider__slide"
                    >
                        <!-- The first slide is what the page is judged on, so
                             it loads eagerly; the rest wait. -->
                        <img
                            :src="slide.image"
                            :alt="slide.title ?? ''"
                            :loading="slide.first ? 'eager' : 'lazy'"
                            :fetchpriority="slide.first ? 'high' : 'auto'"
                            decoding="async"
                        >
                        <p v-if="slide.title" class="hero-slider__caption">{{ slide.title }}</p>
                    </component>
                </div>
            </div>
        </div>

        <template v-if="slides.length > 1">
            <button type="button" class="hero-slider__prev" aria-label="Previous slide">
                <span class="fa fa-angle-left" aria-hidden="true"></span>
            </button>
            <button type="button" class="hero-slider__next" aria-label="Next slide">
                <span class="fa fa-angle-right" aria-hidden="true"></span>
            </button>
            <div class="hero-slider__dots"></div>
        </template>
    </section>
</template>

<style scoped>
.hero-slider {
    position: relative;
    background: #f3f2ee;
}

.hero-slider__slide {
    display: block;
    position: relative;
    color: inherit;
    text-decoration: none;
}

.hero-slider__slide img {
    display: block;
    width: 100%;
    height: auto;
    /* Reserves the band before the image decodes, so the rows below do not
       jump once it lands. */
    aspect-ratio: 16 / 6;
    /* On a wide, short window 16:6 alone eats the whole viewport and pushes
       everything else below the fold. */
    max-height: 60vh;
    object-fit: cover;
}

@media (max-width: 767.98px) {
    .hero-slider__slide img {
        aspect-ratio: 4 / 3;
    }
}

.hero-slider__caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    margin: 0;
    padding: 2.5rem 1.25rem 1.25rem;
    color: #ffffff;
    font-size: 1.125rem;
    font-weight: 600;
    /* A gradient, not a flat bar: the caption stays readable over any photo
       without hiding the third of the image it sits on. */
    background: linear-gradient(to top, rgba(0, 0, 0, .6), rgba(0, 0, 0, 0));
}

.hero-slider__prev,
.hero-slider__next {
    position: absolute;
    top: 50%;
    z-index: 2;
    transform: translateY(-50%);
    width: 2.75rem;
    height: 2.75rem;
    border: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, .9);
    color: #111111;
    font-size: 1.25rem;
    line-height: 1;
}

.hero-slider__prev { left: 1rem; }
.hero-slider__next { right: 1rem; }

.hero-slider__prev:hover,
.hero-slider__next:hover,
.hero-slider__prev:focus-visible,
.hero-slider__next:focus-visible {
    background: #ffffff;
}

.hero-slider__dots {
    position: absolute;
    bottom: .75rem;
    left: 0;
    right: 0;
    z-index: 2;
    text-align: center;
}

.hero-slider__dots :deep(.swiper-pagination-bullet) {
    width: .5rem;
    height: .5rem;
    background: #ffffff;
    opacity: .55;
}

.hero-slider__dots :deep(.swiper-pagination-bullet-active) {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .hero-slider :deep(.swiper-wrapper) {
        transition-duration: 0ms !important;
    }
}
</style>
