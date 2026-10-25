<script setup>
import PlateDragList from '@/Components/PlateDragList.vue';
import ProductCollapse from '@/Components/ProductCollapse.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed } from 'vue';

const page = usePage();
const activeFilaments = computed(() => page.props.active_filaments ?? []);
const unfinishedPlates = computed(() => page.props.unfinished_plates ?? []);
const productsWithParts = computed(() => page.props.products_with_parts ?? []);

const handleReorder = (newOrder) => {
    axios
        .post('/plates/reorder', { order: newOrder })
        .catch(() => {
            router.reload({ only: ['unfinished_plates'] });
        });
};
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Dashboard
      </h2>
    </template>

    <div class="py-10">
      <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
        <section class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              Filamentos
            </h3>
            <Link
              href="/filaments"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
              Ver todos
            </Link>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="filament in activeFilaments"
              :key="filament.code"
              class="rounded-lg border bg-white p-4 shadow-sm"
            >
              <div class="text-sm text-gray-600">
                {{ filament.material }} - {{ filament.brand }}
              </div>
              <div class="text-sm text-gray-500">
                {{ filament.color }}
              </div>
              <div class="mt-2 text-lg font-semibold text-gray-900">
                {{ filament.balance }} g
              </div>
            </div>
          </div>

          <Link
            href="/filaments"
            class="inline-flex rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Adicionar filamento
          </Link>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              Placas em producao
            </h3>
            <Link
              href="/plates"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
              Ver todas
            </Link>
          </div>

          <div
            v-if="unfinishedPlates.length === 0"
            class="rounded border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500"
          >
            Nenhuma placa em producao
          </div>

          <PlateDragList
            v-else
            :plates="unfinishedPlates"
            @reordered="handleReorder"
          />
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              Produtos
            </h3>
            <Link
              href="/products"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
              Ver todos
            </Link>
          </div>

          <div class="space-y-2">
            <ProductCollapse
              v-for="product in productsWithParts"
              :key="product.id"
              :product="product"
            />
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>