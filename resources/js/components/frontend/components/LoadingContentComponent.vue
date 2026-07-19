<template>
    <VueElementLoading spinner="bar-fade-scale" color="#F23E14" :active="isActive" :is-full-screen="false"/>
</template>

<script>
import VueElementLoading from 'vue-element-loading';

export default {
    name: "LoadingContentComponent",
    components: {VueElementLoading},
    props: ['props'],
    data() {
        return {
            isActive: false,
            timer: null
        }
    },
    mounted() {
        this.updateActive(this.props?.isActive);
    },
    beforeUnmount() {
        window.clearTimeout(this.timer);
    },
    methods: {
        updateActive(value) {
            window.clearTimeout(this.timer);

            if (!value) {
                this.isActive = false;
                return;
            }

            this.timer = window.setTimeout(() => {
                if (this.props?.isActive) {
                    this.isActive = true;
                }
            }, 220);
        }
    },
    watch: {
        'props.isActive'(value) {
            this.updateActive(value);
        }
    }
}
</script>
