import {onUnmounted, ref, toValue, unref, watch} from "vue";
import { Carousel, Dropdown, Modal, Toast } from 'bootstrap';

const useBs = (Component, element, options) => {
    const bsComponent = ref(null);

    const unloadBsComponent = () => bsComponent.value = null;
    const loadBsComponent = () => {
        if (! unref(element)) {
            unloadBsComponent();
            return;
        }

        bsComponent.value = new Component(unref(element), toValue(options));
    }

    watch(element, loadBsComponent, { immediate: true });
    onUnmounted(unloadBsComponent);

    return { bsComponent };
}

export const useBsCarousel = (carousel) => {
    return useBs(Carousel, carousel);
}

export const useBsDropdown = (dropdown) => {
    return useBs(Dropdown, dropdown);
}

export const useBsModal = (modal) => {
    return useBs(Modal, modal);
};

export const useBsToast = (element, options = {}) => {
    return useBs(Toast, element, options);
};
