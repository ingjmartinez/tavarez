<script>
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
  components: { Layout, PageHeader },
  props: {
    categoriasActivas: { type: Object, required: true },
  },
  data() {
    const hoy = new Date();
    const iso = (date) => date.toISOString().slice(0, 10);

    return {
      desde: iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)),
      hasta: iso(hoy),
      modo: "subgrupo",
      categoria: "",
      resultado: null,
      loading: false,
      busqueda: "",
      page: 1,
      perPage: 25,
    };
  },
  computed: {
    opcionesCategoria() {
      return this.categoriasActivas[this.modo] ?? [];
    },
    nombreDimension() {
      return this.modo === "sociedad" ? "Corporación" : "Subgrupo";
    },
    filasFiltradas() {
      const term = this.busqueda.trim().toLowerCase();
      const filas = this.resultado?.filas ?? [];
      return term ? filas.filter((fila) => String(fila[this.modo] ?? "").toLowerCase().includes(term)) : filas;
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filasFiltradas.length / this.perPage));
    },
    filasPagina() {
      return this.filasFiltradas.slice((this.page - 1) * this.perPage, this.page * this.perPage);
    },
    coberturaIncompleta() {
      const cobertura = this.resultado?.cobertura;
      return Boolean(cobertura) && cobertura.dias_con_datos < cobertura.dias_rango;
    },
  },
  watch: {
    modo() { this.categoria = ""; this.resultado = null; },
    categoria() { this.resultado = null; },
    perPage() { this.page = 1; },
    busqueda() { this.page = 1; },
  },
  methods: {
    formatMonto(value) {
      return Number(value || 0).toLocaleString("es-DO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    async esperarRender() {
      await this.$nextTick();
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    },
    async generar() {
      this.loading = true;
      this.page = 1;
      this.busqueda = "";
      Swal.fire({
        title: "Generando reporte...",
        text: "Por favor espere",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });
      try {
        const { data } = await axios.get(route("reporte-subgrupos.datos"), {
          params: { desde: this.desde, hasta: this.hasta, modo: this.modo, categoria: this.categoria },
        });
        this.resultado = data;
        await this.esperarRender();
        Swal.close();
      } catch (error) {
        this.resultado = null;
        const errors = error.response?.data?.errors;
        Swal.fire({
          title: "Error",
          text: (errors && Object.values(errors).flat()[0]) || error.response?.data?.message || "No se pudo generar el reporte.",
          icon: "error",
        });
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Ventas por subgrupo" pageTitle="Reporte" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Ventas por subgrupo</h5></BCardHeader>
          <BCardBody>
            <div class="text-muted small mb-3">Seleccione un subgrupo o una corporación marcada en Mantenimiento de terminales / Grupo.</div>
            <div v-if="!opcionesCategoria.length" class="alert alert-warning py-2">
              No hay {{ nombreDimension.toLowerCase() }} marcada. Márquela en Mantenimiento de terminales / Grupo.
            </div>
            <BRow class="g-3 align-items-end mb-3">
              <BCol md="3">
                <label for="desde-subgrupos" class="form-label">Fecha desde</label>
                <input id="desde-subgrupos" v-model="desde" type="date" class="form-control" />
              </BCol>
              <BCol md="3">
                <label for="hasta-subgrupos" class="form-label">Fecha hasta</label>
                <input id="hasta-subgrupos" v-model="hasta" type="date" class="form-control" />
              </BCol>
              <BCol md="2">
                <label for="modo-subgrupos" class="form-label">Agrupar por</label>
                <select id="modo-subgrupos" v-model="modo" class="form-select">
                  <option value="subgrupo">Subgrupo</option>
                  <option value="sociedad">Corporación</option>
                </select>
              </BCol>
              <BCol md="3">
                <label for="categoria-subgrupos" class="form-label">{{ nombreDimension }}</label>
                <select id="categoria-subgrupos" v-model="categoria" class="form-select" :disabled="!opcionesCategoria.length">
                  <option value="" disabled>Seleccione {{ nombreDimension.toLowerCase() }}</option>
                  <option v-if="opcionesCategoria.length" value="__todos__">Todos</option>
                  <option v-for="opcion in opcionesCategoria" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="1">
                <BButton variant="primary" :disabled="loading || !categoria" @click="generar">{{ loading ? "Generando..." : "Generar reporte" }}</BButton>
              </BCol>
            </BRow>
            <template v-if="resultado">
              <div class="d-flex flex-wrap gap-4 mb-3">
                <div><small class="text-muted d-block">Venta total</small><h5 class="mb-0 text-success">{{ formatMonto(resultado.totales.total_venta) }}</h5></div>
                <div><small class="text-muted d-block">{{ nombreDimension }} con venta</small><h5 class="mb-0">{{ resultado.total_filas.toLocaleString() }}</h5></div>
                <div><small class="text-muted d-block">Terminales con venta</small><h5 class="mb-0">{{ resultado.totales.terminales.toLocaleString() }}</h5></div>
              </div>
              <div v-if="coberturaIncompleta" class="alert alert-warning py-2">
                Hay ventas cargadas en {{ resultado.cobertura.dias_con_datos }} de los {{ resultado.cobertura.dias_rango }} días del período.
              </div>
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <label for="por-pagina-subgrupos" class="form-label mb-0">Mostrar</label>
                  <select id="por-pagina-subgrupos" v-model.number="perPage" class="form-select form-select-sm w-auto">
                    <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                  </select>
                </div>
                <input v-model="busqueda" type="search" class="form-control form-control-sm w-auto" :placeholder="`Buscar ${nombreDimension.toLowerCase()}...`" :aria-label="`Buscar ${nombreDimension.toLowerCase()}`" />
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>{{ nombreDimension }}</th>
                      <template v-for="tipo in resultado.tipos" :key="tipo">
                        <th class="text-end">{{ tipo }}</th>
                        <th class="text-end">Promedio {{ tipo }}</th>
                      </template>
                      <th class="text-end">Promedio total</th>
                      <th class="text-end">Venta total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="fila in filasPagina" :key="fila[modo]">
                      <td>{{ fila[modo] }}</td>
                      <template v-for="tipo in resultado.tipos" :key="tipo">
                        <td class="text-end">{{ formatMonto(fila.tipos[tipo]) }}</td>
                        <td class="text-end">{{ formatMonto(fila.promedios_por_tipo[tipo]) }}</td>
                      </template>
                      <td class="text-end">{{ formatMonto(fila.promedio) }}</td>
                      <td class="text-end">{{ formatMonto(fila.total_venta) }}</td>
                    </tr>
                    <tr v-if="!filasFiltradas.length">
                      <td :colspan="resultado.tipos.length * 2 + 3" class="text-center text-muted py-3">Sin ventas para la categoría seleccionada.</td>
                    </tr>
                  </tbody>
                  <tfoot v-if="filasFiltradas.length">
                    <tr>
                      <th>Total</th>
                      <template v-for="tipo in resultado.tipos" :key="tipo">
                        <th class="text-end">{{ formatMonto(resultado.totales.por_tipo[tipo]) }}</th>
                        <th class="text-end">{{ formatMonto(resultado.totales.promedios_por_tipo[tipo]) }}</th>
                      </template>
                      <th class="text-end">{{ formatMonto(resultado.totales.promedio) }}</th>
                      <th class="text-end">{{ formatMonto(resultado.totales.total_venta) }}</th>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div v-if="filasFiltradas.length" class="d-flex justify-content-between align-items-center mt-3">
                <span class="text-muted">Mostrando {{ (page - 1) * perPage + 1 }} a {{ Math.min(page * perPage, filasFiltradas.length) }} de {{ filasFiltradas.length }}</span>
                <div class="d-flex align-items-center gap-2">
                  <BButton size="sm" variant="light" :disabled="page <= 1" @click="page--">Anterior</BButton>
                  <span>Página {{ page }} de {{ totalPages }}</span>
                  <BButton size="sm" variant="light" :disabled="page >= totalPages" @click="page++">Siguiente</BButton>
                </div>
              </div>
            </template>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
