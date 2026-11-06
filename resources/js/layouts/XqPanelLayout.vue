<script setup lang="ts">
import { ref } from 'vue';

const mountedAt = ref(Date.now());
</script>

<template>
    <div data-xq-panel-layout :data-mounted-at="mountedAt"><slot /></div>
</template>
