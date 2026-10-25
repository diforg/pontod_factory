<script setup>
defineProps({
    filament: {
        type: Object,
        required: true,
    },
});

defineEmits(['toggle-active']);
</script>

<template>
  <tr>
    <td class="px-4 py-3 text-sm font-medium text-gray-900">
      {{ filament.code }}
    </td>
    <td class="px-4 py-3 text-sm text-gray-700">
      {{ filament.material }}
    </td>
    <td class="px-4 py-3 text-sm text-gray-700">
      {{ filament.brand }}
    </td>
    <td class="px-4 py-3 text-sm text-gray-700">
      {{ filament.color }}
    </td>
    <td class="px-4 py-3 text-sm text-gray-700">
      {{ filament.balance }} g
    </td>
    <td class="px-4 py-3 text-sm text-gray-700">
      <span
        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
        :class="filament.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
      >
        {{ filament.is_active ? 'Ativo' : 'Inativo' }}
      </span>
    </td>
    <td class="px-4 py-3 text-right text-sm">
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        @click="$emit('toggle-active', filament)"
      >
        {{ filament.is_active ? 'Desativar' : 'Ativar' }}
      </button>
    </td>
  </tr>
</template>
