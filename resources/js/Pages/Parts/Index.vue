<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    parts: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);
const editingPart = ref(null);
const activeOnly = ref(false);

const form = useForm({
    code: '',
    description: '',
    size: '',
    filament_g: '',
    production_time_min: '',
});

const filteredParts = computed(() => {
    if (!activeOnly.value) {
        return props.parts;
    }

    return props.parts.filter((part) => part.is_active);
});

const openCreate = () => {
    editingPart.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (part) => {
    editingPart.value = part;
    form.code = part.code ?? '';
    form.description = part.description ?? '';
    form.size = part.size ?? '';
    form.filament_g = part.filament_g ?? '';
    form.production_time_min = part.production_time_min ?? '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingPart.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingPart.value) {
        form.patch(`/parts/${editingPart.value.id}`, {
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.post('/parts', {
        onSuccess: () => closeModal(),
    });
};

const destroyPart = (part) => {
    if (!confirm(`Desativar a peca ${part.code}?`)) {
        return;
    }

    useForm({}).delete(`/parts/${part.id}`);
};
</script>

<template>
  <Head title="Pecas" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Pecas
      </h2>
    </template>

    <div class="py-10">
      <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input
              v-model="activeOnly"
              type="checkbox"
              class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
            >
            Ativos apenas
          </label>

          <PrimaryButton @click="openCreate">
            Nova peca
          </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Codigo
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Descricao
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Tamanho
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Filamento (g)
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Tempo (min)
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Acoes
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr
                v-for="part in filteredParts"
                :key="part.id"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ part.code }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ part.description || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ part.size || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ part.filament_g }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ part.production_time_min }}
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                      @click="openEdit(part)"
                    >
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                      @click="destroyPart(part)"
                    >
                      Deletar
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredParts.length === 0">
                <td
                  colspan="6"
                  class="px-4 py-6 text-center text-sm text-gray-500"
                >
                  Nenhuma peca encontrada.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Modal
      :show="showModal"
      @close="closeModal"
    >
      <form
        class="space-y-4 p-6"
        @submit.prevent="submit"
      >
        <h3 class="text-lg font-semibold text-gray-900">
          {{ editingPart ? 'Editar peca' : 'Nova peca' }}
        </h3>

        <div>
          <InputLabel
            for="code"
            value="Codigo"
          />
          <TextInput
            id="code"
            v-model="form.code"
            type="text"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.code"
          />
        </div>

        <div>
          <InputLabel
            for="description"
            value="Descricao"
          />
          <TextInput
            id="description"
            v-model="form.description"
            type="text"
            class="mt-1 block w-full"
          />
          <InputError
            class="mt-2"
            :message="form.errors.description"
          />
        </div>

        <div>
          <InputLabel
            for="size"
            value="Tamanho"
          />
          <TextInput
            id="size"
            v-model="form.size"
            type="text"
            class="mt-1 block w-full"
          />
          <InputError
            class="mt-2"
            :message="form.errors.size"
          />
        </div>

        <div>
          <InputLabel
            for="filament_g"
            value="Filamento (g)"
          />
          <TextInput
            id="filament_g"
            v-model="form.filament_g"
            type="number"
            min="0"
            step="0.01"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.filament_g"
          />
        </div>

        <div>
          <InputLabel
            for="production_time_min"
            value="Tempo de producao (min)"
          />
          <TextInput
            id="production_time_min"
            v-model="form.production_time_min"
            type="number"
            min="1"
            step="1"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.production_time_min"
          />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <SecondaryButton
            type="button"
            @click="closeModal"
          >
            Cancelar
          </SecondaryButton>
          <PrimaryButton :disabled="form.processing">
            Salvar
          </PrimaryButton>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>
