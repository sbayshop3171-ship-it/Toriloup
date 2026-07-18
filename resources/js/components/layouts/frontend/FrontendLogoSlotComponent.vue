<template>
    <img v-if="src" class="w-full h-full object-contain" :src="src" alt="logo">
    <span v-else :class="slotClass" aria-label="Logo placeholder">
        <i class="lab-fill-image text-sm"></i>
        <span class="text-[9px] font-semibold uppercase tracking-[0.18em]">Logo</span>
    </span>
</template>

<script>
export default {
    name: "FrontendLogoSlotComponent",
    props: {
        src: {
            type: String,
            default: "",
        },
        variant: {
            type: String,
            default: "header",
        },
    },
    computed: {
        slotClass: function () {
            const base = "w-full h-full flex items-center justify-center gap-1 rounded-xl border border-dashed bg-white";

            if (this.variant === "footer") {
                return `${base} border-white/80 text-slate-400 shadow-[0_8px_24px_rgba(255,255,255,0.12)]`;
            }

            return `${base} border-slate-200 text-slate-300 shadow-xs`;
        },
    },
}
</script>
