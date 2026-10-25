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
    products: {
        type: Array,
        required: true,
    },
    parts: {
        type: Array,
        default: () => [],
    },
});

const showModal = ref(false);
const editingProduct = ref(null);
const activeOnly = ref(false);

const form = useForm({
    code: '',
    name: '',
    description: '',
    size: '',
    version: '',
    sale_price: '',
    parts: [],
});

const filteredProducts = computed(() => {
    if (!activeOnly.value) {
        return props.products;
    }

    return props.products.filter((product) => product.is_active);
});

const resetForm = () => {
    form.reset();
    form.parts = [];
    form.clearErrors();
};

const openCreate = () => {
    editingProduct.value = null;
    resetForm();
    showModal.value = true;
};

const openEdit = (product) => {
    editingProduct.value = product;
    form.code = product.code ?? '';
    form.name = product.name ?? '';
    form.description = product.description ?? '';
    form.size = product.size ?? '';
    form.version = product.version ?? '';
    form.sale_price = product.sale_price ?? '';
    form.parts = (product.parts ?? []).map((part) => ({
      part_id: part.id,
        quantity: part.pivot?.quantity ?? 1,
    }));
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingProduct.value = null;
    resetForm();
};

const addPartRow = () => {
    form.parts.push({
    part_id: '',
        quantity: 1,
    });
};

const removePartRow = (index) => {
    form.parts.splice(index, 1);
};

const submit = () => {
    const payload = {
        code: form.code,
        name: form.name,
        description: form.description,
        size: form.size,
        version: form.version,
        sale_price: form.sale_price,
        parts: form.parts
          .filter((part) => part.part_id)
            .map((part) => ({
            id: Number(part.part_id),
            part_id: Number(part.part_id),
                quantity: Number(part.quantity),
            })),
    };

    if (editingProduct.value) {
        form.transform(() => payload).patch(`/products/${editingProduct.value.id}`, {
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.transform(() => payload).post('/products', {
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
  <Head title="Produtos" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Produtos
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
            Novo produto
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
                  Nome
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Tamanho
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Versao
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Preco
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
                v-for="product in filteredProducts"
                :key="product.id"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ product.code }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ product.name }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ product.size || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ product.version || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ product.sale_price ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  {{ product.parts?.length ?? 0 }}
                </td>
                <td class="px-4 py-3 text-sm">
                  <button
                    type="button"
                    class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    @click="openEdit(product)"
                  >
                    Editar
                  </button>
                </td>
              </tr>
              <tr v-if="filteredProducts.length === 0">
                <td
                  colspan="7"
                  class="px-4 py-6 text-center text-sm text-gray-500"
                >
                  Nenhum produto encontrado.
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
          {{ editingProduct ? 'Editar produto' : 'Novo produto' }}
        </h3>

        <div>
          <InputLabel
            for="product-code"
            value="Codigo"
          />
          <TextInput
            id="product-code"
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
            for="product-name"
            value="Nome"
          />
          <TextInput
            id="product-name"
            v-model="form.name"
            type="text"
            class="mt-1 block w-full"
            required
          />
          <InputError
            class="mt-2"
            :message="form.errors.name"
          />
        </div>

        <div>
          <InputLabel
            for="product-description"
            value="Descricao"
          />
          <TextInput
            id="product-description"
            v-model="form.description"
            type="text"
            class="mt-1 block w-full"
          />
          <InputError
            class="mt-2"
            :message="form.errors.description"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <InputLabel
              for="product-size"
              value="Tamanho"
            />
            <TextInput
              id="product-size"
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
              for="product-version"
              value="Versao"
            />
            <TextInput
              id="product-version"
              v-model="form.version"
              type="text"
              class="mt-1 block w-full"
            />
            <InputError
              class="mt-2"
              :message="form.errors.version"
            />
          </div>
        </div>

        <div>
          <InputLabel
            for="product-sale-price"
            value="Preco de venda"
          />
          <TextInput
            id="product-sale-price"
            v-model="form.sale_price"
            type="number"
            min="0"
            step="0.01"
            class="mt-1 block w-full"
          />
          <InputError
            class="mt-2"
            :message="form.errors.sale_price"
          />
        </div>

        <div class="space-y-3 rounded border border-gray-200 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-800">
              Pecas do produto
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
            v-for="(productPart, index) in form.parts"
            :key="`product-part-${index}`"
            class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_120px_auto]"
          >
            <select
              v-model="productPart.part_id"
              class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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

            <TextInput
              v-model="productPart.quantity"
              type="number"
              min="1"
              step="1"
            />

            <button
              type="button"
              class="rounded border border-red-300 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50"
              @click="removePartRow(index)"
            >
              Remover
            </button>
          </div>

          <InputError
            :message="form.errors.parts"
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
