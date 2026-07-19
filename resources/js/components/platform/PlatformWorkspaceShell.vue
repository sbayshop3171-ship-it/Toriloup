<template>
    <div class="space-y-6" dir="ltr">
        <slot />
    </div>
</template>

<script>
export default {
    name: "PlatformWorkspaceShell",
    props: {
        title: {
            type: String,
            required: true,
        },
        subtitle: {
            type: String,
            default: "",
        },
    },
};
</script>
