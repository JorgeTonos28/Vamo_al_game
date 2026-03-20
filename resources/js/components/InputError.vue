<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-[#FCA5A5]">
            {{ message }}
        </p>
    </div>
</template>
