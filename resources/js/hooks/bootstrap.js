import {onMounted, onUnmounted, ref, toValue, unref, watch} from "vue";
import { Carousel, Dropdown, Modal, Toast } from 'bootstrap';

const useBs = (Component, element, options = {}) => {
    const bsComponent = ref(null);

    const unloadBsComponent = () => {
        if (options.hideOnUnmount) {
            bsComponent.value.hide();
        }

        bsComponent.value = null;
    }
    const loadBsComponent = () => {
        if (! unref(element)) {
            unloadBsComponent();
            return;
        }

        bsComponent.value = new Component(unref(element), toValue(options));
    }

    onMounted(loadBsComponent);
    onUnmounted(unloadBsComponent);
    watch(element, loadBsComponent);

    return { bsComponent };
}

export const useBsCarousel = (carousel) => {
    return useBs(Carousel, carousel);
}

export const useBsDropdown = (dropdown) => {
    return useBs(Dropdown, dropdown);
}

export const useBsModal = (modal, options = {}) => {
    return useBs(Modal, modal, { hideOnUnmount: true, ...options });
};

export const useBsToast = (element, options = {}) => {
    return useBs(Toast, element, options);
};
