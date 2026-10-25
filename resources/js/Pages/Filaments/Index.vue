<script setup>
import FilamentCard from '@/Components/FilamentCard.vue';
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
    filaments: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);
const activeOnly = ref(false);

const form = useForm({
    material: '',
    brand: '',
    color: '',
    weight: '',
    price: '',
});

const filteredFilaments = computed(() => {
    if (!activeOnly.value) {
        return props.filaments;
    }

    return props.filaments.filter((filament) => filament.is_active);
});

const openModal = () => {
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    form.post('/filaments', {
        onSuccess: () => closeModal(),
    });
};

const toggleActive = (filament) => {
    const endpoint = filament.is_active
        ? `/filaments/${filament.code}/deactivate`
        : `/filaments/${filament.code}/activate`;

    useForm({}).post(endpoint);
};
</script>

<template>
  <Head title="Filamentos" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Filamentos
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

          <PrimaryButton @click="openModal">
            Novo filamento
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
                  Material
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Marca
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Cor
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Saldo (g)
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Status
                </th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <FilamentCard
                v-for="filament in filteredFilaments"
                :key="filament.code"
                :filament="filament"
                @toggle-active="toggleActive"
              />
              <tr v-if="filteredFilaments.length === 0">
                <td
                  colspan="7"
                  class="px-4 py-6 text-center text-sm text-gray-500"
                >
                  Nenhum filamento encontrado.
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
          Novo filamento
        </h3>

        <div>
          <InputLabel
            for="material"
            value="Material"
          />
          <TextInput
            id="material"
            v-model="form.material"
            type="text"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.material"
          />
        </div>

        <div>
          <InputLabel
            for="brand"
            value="Marca"
          />
          <TextInput
            id="brand"
            v-model="form.brand"
            type="text"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.brand"
          />
        </div>

        <div>
          <InputLabel
            for="color"
            value="Cor"
          />
          <TextInput
            id="color"
            v-model="form.color"
            type="text"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.color"
          />
        </div>

        <div>
          <InputLabel
            for="weight"
            value="Peso (g)"
          />
          <TextInput
            id="weight"
            v-model="form.weight"
            type="number"
            min="0"
            step="0.01"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.weight"
          />
        </div>

        <div>
          <InputLabel
            for="price"
            value="Preco"
          />
          <TextInput
            id="price"
            v-model="form.price"
            type="number"
            min="0"
            step="0.01"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.price"
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
