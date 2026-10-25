<script setup>
import { ref } from 'vue';

defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const expanded = ref(false);
</script>

<template>
  <div class="rounded border bg-white">
    <button
      class="flex w-full items-center justify-between p-4 text-left"
      @click="expanded = !expanded"
    >
      <div>
        <div class="font-medium">
          {{ product.name }} ({{ product.code }})
        </div>
        <div class="text-xs text-gray-500">
          {{ product.parts?.length ?? 0 }} peça(s)
        </div>
      </div>
      <span class="text-gray-400">{{ expanded ? '▾' : '▸' }}</span>
    </button>

    <div
      v-if="expanded"
      class="space-y-2 border-t p-4"
    >
      <div
        v-for="part in product.parts"
        :key="part.id"
        class="flex justify-between text-sm"
      >
        <span>{{ part.code }} - {{ part.description }}</span>
        <span :class="part.items_count > 0 ? 'font-medium text-green-700' : 'text-gray-400'">
          {{ part.items_count ?? 0 }} disponível(is)
        </span>
      </div>

      <div
        v-if="!product.parts?.length"
        class="text-sm text-gray-500"
      >
        Nenhuma peça associada.
      </div>
    </div>
  </div>
</template>