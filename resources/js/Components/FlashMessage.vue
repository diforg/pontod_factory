<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const dismissed = ref({ success: false, error: false, warning: false });

let successTimer = null;

const clearTimers = () => {
    if (successTimer) {
        clearTimeout(successTimer);
        successTimer = null;
    }
};

watch(flash, () => {
    clearTimers();
    dismissed.value = { success: false, error: false, warning: false };

    successTimer = setTimeout(() => {
        dismissed.value.success = true;
        dismissed.value.error = true;
        dismissed.value.warning = true;
    }, 5000);
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimers();
});
</script>

<template>
  <div class="space-y-2">
    <div
      v-if="flash.success && !dismissed.success"
      class="flex items-start justify-between rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
    >
      <span>{{ flash.success }}</span>
      <button
        type="button"
        class="ml-3 text-green-700 hover:text-green-900"
        @click="dismissed.success = true"
      >
        ✕
      </button>
    </div>

    <div
      v-if="flash.error && !dismissed.error"
      class="flex items-start justify-between rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <span>{{ flash.error }}</span>
      <button
        type="button"
        class="ml-3 text-red-700 hover:text-red-900"
        @click="dismissed.error = true"
      >
        ✕
      </button>
    </div>

    <div
      v-if="flash.warning && !dismissed.warning"
      class="flex items-start justify-between rounded border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
    >
      <span>{{ flash.warning }}</span>
      <button
        type="button"
        class="ml-3 text-yellow-700 hover:text-yellow-900"
        @click="dismissed.warning = true"
      >
        ✕
      </button>
    </div>
  </div>
</template>