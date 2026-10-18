<script>
import { Head } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";

/**
 * Pagina principal en blanco.
 * El dashboard original de la plantilla sigue en ./ecommerce (sin uso) por si se necesita.
 */
export default {
  components: {
    Layout,
    Head,
  },
};
</script>

<template>
  <Layout>
    <Head title="Inicio" />
  </Layout>
</template>
