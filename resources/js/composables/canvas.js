import { ref } from "vue";

export function useCanvas() { 
    const activeClass = ref('canvas-active')
    const hiddenClass = ref('overflow-hidden')
    const mobileCanvasClass = 'merchant-mobile-canvas-open'

    function syncMerchantMobileCanvasState(targetElement, isOpen) {
        if (
            !targetElement
            || targetElement.id !== "sidebar"
            || typeof window === "undefined"
            || !window.matchMedia("(max-width: 1023px)").matches
        ) {
            return;
        }

        document.body.classList.toggle(mobileCanvasClass, isOpen);
    }

    function openCanvas(targetID) {
        const targetElement = document.querySelector(`#${targetID}`);
        if (!targetElement) {
            return;
        }

        targetElement.classList.add(activeClass.value);
        document.body.classList.add(hiddenClass.value);
        syncMerchantMobileCanvasState(targetElement, true);
    }

    function closeCanvas(targetID) {
        const targetElement = document.querySelector(`#${targetID}`);
        if (!targetElement) {
            return;
        }

        targetElement.classList.remove(activeClass.value);
        document.body.classList.remove(hiddenClass.value);
        syncMerchantMobileCanvasState(targetElement, false);
    }

    function closeBackdrop(event) {
        const containerElement = event.currentTarget.firstElementChild
        const isWrapperElement = event.target.contains(containerElement)

        if(isWrapperElement) {
            event.currentTarget.classList.remove(activeClass.value)
            document.body.classList.remove(hiddenClass.value)
            syncMerchantMobileCanvasState(event.currentTarget, false);
        }
    }

    return { openCanvas, closeCanvas, closeBackdrop }
}
