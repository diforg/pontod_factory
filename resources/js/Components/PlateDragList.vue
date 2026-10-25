<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';
import { VueDraggableNext as draggable } from 'vue-draggable-next';
import { ref, watch } from 'vue';

const props = defineProps({
    plates: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['reordered']);

const localPlates = ref([...props.plates]);

watch(() => props.plates, (newVal) => {
    localPlates.value = [...newVal];
}, { deep: true });

const onDragEnd = () => {
    emit('reordered', localPlates.value.map((plate) => plate.id));
};
</script>

<template>
  <draggable
    v-model="localPlates"
    item-key="id"
    handle=".drag-handle"
    animation="150"
    class="space-y-2"
    @end="onDragEnd"
  >
    <template #item="{ element }">
      <div class="flex items-center gap-3 rounded border bg-white p-3 shadow-sm cursor-grab">
        <span class="drag-handle text-gray-400">⋮⋮</span>
        <div class="flex-1">
          <div class="text-sm font-medium">
            {{ element.filament_code }}
          </div>
          <div class="text-xs text-gray-500">
            {{ element.parts?.length ?? 0 }} peça(s)
          </div>
        </div>
        <StatusBadge :status="element.status" />
      </div>
    </template>
  </draggable>
</template>