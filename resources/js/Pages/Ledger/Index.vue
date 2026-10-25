<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    movements: {
        type: Array,
        default: () => [],
    },
    filaments: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = reactive({
    filament_code: props.filters?.filament_code ?? '',
    type: props.filters?.type ?? '',
    from_date: props.filters?.from_date ?? '',
    to_date: props.filters?.to_date ?? '',
});

const applyFilters = () => {
    router.get('/ledger', {
        filament_code: filterForm.filament_code || undefined,
        type: filterForm.type || undefined,
        from_date: filterForm.from_date || undefined,
        to_date: filterForm.to_date || undefined,
    });
};

const clearFilters = () => {
    filterForm.filament_code = '';
    filterForm.type = '';
    filterForm.from_date = '';
    filterForm.to_date = '';
    router.get('/ledger');
};

const typeClass = (type) => {
    const classes = {
        ENTRY: 'bg-green-100 text-green-800',
        CONSUMPTION: 'bg-red-100 text-red-800',
        REVERSAL: 'bg-blue-100 text-blue-800',
        RECONCILIATION_ADJUSTMENT: 'bg-yellow-100 text-yellow-800',
    };

    return classes[type] ?? 'bg-gray-100 text-gray-800';
};

const typeLabel = (type) => {
    const labels = {
        ENTRY: 'Entrada',
        CONSUMPTION: 'Consumo',
        REVERSAL: 'Reversao',
        RECONCILIATION_ADJUSTMENT: 'Ajuste de reconciliacao',
    };

    return labels[type] ?? type;
};

const sourceLabel = (movement) => {
    if (movement.source_type === 'ENTRY') {
        return 'Entrada';
    }

    if (movement.source_type === 'RECONCILIATION') {
        return 'Reconciliacao';
    }

    return 'Placa';
};
</script>

<template>
  <Head title="Ledger" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Movimentacoes
      </h2>
    </template>

    <div class="py-10">
      <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white p-4 shadow">
          <form
            class="grid grid-cols-1 gap-3 md:grid-cols-5"
            @submit.prevent="applyFilters"
          >
            <select
              v-model="filterForm.filament_code"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="">
                Todos os filamentos
              </option>
              <option
                v-for="filament in filaments"
                :key="filament.code"
                :value="filament.code"
              >
                {{ filament.code }} - {{ filament.material }} {{ filament.color }}
              </option>
            </select>

            <select
              v-model="filterForm.type"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="">
                Todos os tipos
              </option>
              <option value="ENTRY">
                ENTRY
              </option>
              <option value="CONSUMPTION">
                CONSUMPTION
              </option>
              <option value="REVERSAL">
                REVERSAL
              </option>
              <option value="RECONCILIATION_ADJUSTMENT">
                RECONCILIATION_ADJUSTMENT
              </option>
            </select>

            <input
              v-model="filterForm.from_date"
              type="date"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >

            <input
              v-model="filterForm.to_date"
              type="date"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >

            <div class="flex gap-2">
              <button
                type="submit"
                class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
              >
                Filtrar
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="clearFilters"
              >
                Limpar
              </button>
            </div>
          </form>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Data/hora
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Filamento
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Tipo
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Quantidade (g)
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Saldo anterior
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Saldo novo
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Origem
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr
                v-for="movement in movements"
                :key="movement.id"
              >
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ movement.occurred_at }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ movement.filament_code }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <span
                    class="inline-flex rounded px-2 py-1 text-xs font-medium"
                    :class="typeClass(movement.type)"
                  >
                    {{ typeLabel(movement.type) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ movement.quantity_g }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ movement.previous_balance_g }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ movement.new_balance_g }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <Link
                    v-if="movement.source_type === 'PLATE'"
                    href="/plates"
                    class="text-indigo-600 hover:text-indigo-500"
                  >
                    Placa #{{ movement.source_id }}
                  </Link>
                  <span v-else>
                    {{ sourceLabel(movement) }}
                  </span>
                </td>
              </tr>

              <tr v-if="movements.length === 0">
                <td
                  colspan="7"
                  class="px-4 py-6 text-center text-sm text-gray-500"
                >
                  Nenhuma movimentacao encontrada para os filtros selecionados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>