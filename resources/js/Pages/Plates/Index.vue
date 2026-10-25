<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PlateDragList from '@/Components/PlateDragList.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    plates: {
        type: Array,
        required: true,
    },
    filaments: {
        type: Array,
        default: () => [],
    },
    parts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const showCreateModal = ref(false);
const filamentBalances = computed(() => page.props.filament_balances ?? {});

const createForm = useForm({
    filament_code: '',
    parts: [],
});

const unfinishedPlates = computed(() =>
    props.plates.filter((plate) => plate.status !== 'COMPLETED'),
);

const completedPlates = computed(() =>
    props.plates.filter((plate) => plate.status === 'COMPLETED'),
);

const activeFilaments = computed(() =>
    props.filaments.filter((filament) => filament.is_active),
);

const statusError = computed(() => page.props.errors?.status ?? null);

const openCreateModal = () => {
    createForm.reset();
    createForm.parts = [];
    createForm.clearErrors();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset();
    createForm.parts = [];
    createForm.clearErrors();
};

const addPartRow = () => {
    createForm.parts.push({
        part_id: '',
        quantity: 1,
    });
};

const removePartRow = (index) => {
    createForm.parts.splice(index, 1);
};

const submitCreate = () => {
    createForm
        .transform((data) => ({
            filament_code: data.filament_code,
            parts: data.parts
                .filter((part) => part.part_id)
                .map((part) => ({
                    id: Number(part.part_id),
                    quantity: Number(part.quantity),
                })),
        }))
        .post('/plates', {
            onSuccess: () => closeCreateModal(),
        });
};

const handleReorder = (newOrder) => {
    axios
        .post('/plates/reorder', { order: newOrder })
        .catch(() => {
            router.reload({ only: ['plates'] });
        });
};

const moveToQueued = (plateId) => {
    useForm({ status: 'QUEUED' }).post(`/plates/${plateId}/status`);
};

const moveToPrinting = (plateId) => {
    useForm({ status: 'PRINTING' }).post(`/plates/${plateId}/status`);
};

const completePlate = (plateId) => {
    useForm({ status: 'COMPLETED' }).post(`/plates/${plateId}/status`);
};

const reopenPlate = (plateId) => {
    useForm({}).post(`/plates/${plateId}/reopen`);
};

const balanceForFilament = (code) => {
    const fromList = props.filaments.find((filament) => filament.code === code);

    if (fromList?.balance !== undefined && fromList?.balance !== null) {
        return fromList.balance;
    }

    return filamentBalances.value?.[code] ?? '-';
};
</script>

<template>
  <Head title="Placas" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Placas
      </h2>
    </template>

    <div class="py-10">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-lg font-semibold text-gray-900">
            Em producao
          </h3>
          <PrimaryButton @click="openCreateModal">
            Nova placa
          </PrimaryButton>
        </div>

        <div
          v-if="statusError"
          class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
          {{ statusError }}
        </div>

        <div class="space-y-3">
          <PlateDragList
            v-if="unfinishedPlates.length > 0"
            :plates="unfinishedPlates"
            @reordered="handleReorder"
          />
          <div
            v-else
            class="rounded border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500"
          >
            Nenhuma placa em producao.
          </div>

          <div
            v-for="plate in unfinishedPlates"
            :key="`actions-${plate.id}`"
            class="flex flex-wrap items-center justify-between gap-3 rounded bg-gray-50 px-4 py-3"
          >
            <div class="flex items-center gap-2 text-sm text-gray-700">
              <span class="font-medium">#{{ plate.id }}</span>
              <StatusBadge :status="plate.status" />
            </div>

            <div class="flex gap-2">
              <button
                v-if="plate.status === 'CREATED'"
                type="button"
                class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100"
                @click="moveToQueued(plate.id)"
              >
                Mover para fila
              </button>

              <button
                v-if="plate.status === 'QUEUED'"
                type="button"
                class="rounded border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50"
                @click="moveToPrinting(plate.id)"
              >
                Iniciar impressao
              </button>

              <button
                v-if="plate.status === 'PRINTING'"
                type="button"
                class="rounded border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50"
                @click="completePlate(plate.id)"
              >
                Concluir
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <h3 class="text-lg font-semibold text-gray-900">
            Concluidas
          </h3>
          <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    ID
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Filamento
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Status
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Pecas
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Acoes
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                  v-for="plate in completedPlates"
                  :key="plate.id"
                >
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">
                    #{{ plate.id }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700">
                    {{ plate.filament_code }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700">
                    <StatusBadge :status="plate.status" />
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700">
                    {{ plate.parts?.length ?? 0 }}
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <button
                      type="button"
                      class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                      @click="reopenPlate(plate.id)"
                    >
                      Reabrir
                    </button>
                  </td>
                </tr>
                <tr v-if="completedPlates.length === 0">
                  <td
                    colspan="5"
                    class="px-4 py-6 text-center text-sm text-gray-500"
                  >
                    Nenhuma placa concluida.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <Modal
      :show="showCreateModal"
      @close="closeCreateModal"
    >
      <form
        class="space-y-4 p-6"
        @submit.prevent="submitCreate"
      >
        <h3 class="text-lg font-semibold text-gray-900">
          Nova placa
        </h3>

        <div>
          <InputLabel
            for="plate-filament-code"
            value="Filamento"
          />
          <select
            id="plate-filament-code"
            v-model="createForm.filament_code"
            class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          >
            <option value="">
              Selecione um filamento ativo
            </option>
            <option
              v-for="filament in activeFilaments"
              :key="filament.code"
              :value="filament.code"
            >
              {{ filament.code }} - saldo {{ balanceForFilament(filament.code) }} g
            </option>
          </select>
          <InputError
            class="mt-2"
            :message="createForm.errors.filament_code"
          />
        </div>

        <div class="space-y-3 rounded border border-gray-200 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-800">
              Pecas da placa
            </p>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
              @click="addPartRow"
            >
              + Adicionar peca
            </button>
          </div>

          <div
            v-for="(platePart, index) in createForm.parts"
            :key="`plate-part-${index}`"
            class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_120px_auto]"
          >
            <select
              v-model="platePart.part_id"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required
            >
              <option value="">
                Selecione uma peca
              </option>
              <option
                v-for="part in parts"
                :key="part.id"
                :value="part.id"
              >
                {{ part.code }} - {{ part.description }}
              </option>
            </select>

            <input
              v-model.number="platePart.quantity"
              type="number"
              min="1"
              class="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required
            >

            <button
              type="button"
              class="rounded border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
              @click="removePartRow(index)"
            >
              Remover
            </button>
          </div>

          <InputError
            class="mt-1"
            :message="createForm.errors.parts"
          />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <SecondaryButton
            type="button"
            @click="closeCreateModal"
          >
            Cancelar
          </SecondaryButton>
          <PrimaryButton :disabled="createForm.processing">
            Salvar
          </PrimaryButton>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>