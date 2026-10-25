<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const label = computed(() => {
    const labels = {
        CREATED: 'Criado',
        QUEUED: 'Fila',
        PRINTING: 'Imprimindo',
    COMPLETED: 'Concluído',
    };

    return labels[props.status] ?? props.status;
});

const classes = computed(() => {
    const statusClasses = {
        CREATED: 'bg-gray-100 text-gray-800',
        QUEUED: 'bg-yellow-100 text-yellow-800',
        PRINTING: 'bg-blue-100 text-blue-800',
        COMPLETED: 'bg-green-100 text-green-800',
    };

    return statusClasses[props.status] ?? 'bg-red-100 text-red-800';
});
</script>

<template>
  <span
    class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
    :class="classes"
  >
    {{ label }}
  </span>
</template>
