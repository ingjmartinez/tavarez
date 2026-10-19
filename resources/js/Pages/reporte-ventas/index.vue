<script>
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import axios from "axios";
import Swal from "sweetalert2";
import { descargarExcel, descargarPdf, tablaVentas } from "@/reporte-export.js";

export default {
  components: {
    Layout,
    PageHeader,
  },
  props: {
    dimensiones: {
      type: Object,
      required: true,
    },
  },
  data() {
    const hoy = new Date();
    const iso = (date) => date.toISOString().slice(0, 10);

    return {
      desde: iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)),
      hasta: iso(hoy),
      agrupar: ["terminal"],
      incluirRaza: false,
      incluirClasificacion: false,
      filtros: { grupo: "", subgrupo: "", division: "", sociedad: "", tipo: "", estatus: "todos", buscar: "" },
      opciones: { grupos: [], subgrupos: [], divisiones: [], sociedades: [], tipos: [] },
      resultado: null,
      loading: false,
      busqueda: "",
      page: 1,
      perPage: 25,
    };
  },
  computed: {
    columnas() {
      if (!this.resultado) {
        return [];
      }
      return this.resultado.agrupar.flatMap((key) =>
        key === "terminal"
          ? [
              { key: "terminal", label: this.dimensiones.terminal },
              { key: "nombre", label: "Nombre" },
              ...(this.incluirClasificacion ? [{ key: "clasificacion", label: "Clasificación" }] : []),
            ]
          : [{ key, label: this.dimensiones[key] ?? "Detalle" }],
      );
    },
    filas() {
      return this.resultado?.filas ?? [];
    },
    filasFiltradas() {
      const term = this.busqueda.trim().toLowerCase();
      if (!term) {
        return this.filas;
      }
      return this.filas.filter((fila) =>
        this.columnas.some((col) => String(fila[col.key] ?? "").toLowerCase().includes(term)),
      );
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filasFiltradas.length / this.perPage));
    },
    filasPagina() {
      const start = (this.page - 1) * this.perPage;
      return this.filasFiltradas.slice(start, start + this.perPage);
    },
    rangoLabel() {
      if (!this.filasFiltradas.length) {
        return "";
      }
      const start = (this.page - 1) * this.perPage + 1;
      const end = Math.min(this.page * this.perPage, this.filasFiltradas.length);
      return `Mostrando ${start} a ${end} de ${this.filasFiltradas.length}`;
    },
    coberturaIncompleta() {
      const cobertura = this.resultado?.cobertura;
      return Boolean(cobertura) && cobertura.dias_con_datos < cobertura.dias_rango;
    },
    coberturaRazaIncompleta() {
      const cobertura = this.resultado?.cobertura_raza;
      return Boolean(cobertura) && cobertura.dias_con_datos < cobertura.dias_rango;
    },
  },
  watch: {
    perPage() {
      this.page = 1;
    },
    busqueda() {
      this.page = 1;
    },
  },
  async mounted() {
    try {
      const { data } = await axios.get(route("reporte-ventas.filtros"));
      this.opciones = data;
    } catch (error) {
      this.opciones = { grupos: [], subgrupos: [], divisiones: [], sociedades: [], tipos: [] };
    }
  },
  methods: {
    async exportar(formato) {
      if (!this.resultado) return;
      const tabla = tablaVentas(this.resultado, this.columnas, this.resultado.filas, "Ventas", `${this.resultado.desde} a ${this.resultado.hasta}`);
      const nombre = `ventas-${this.resultado.desde}-${this.resultado.hasta}`;
      try {
        if (formato === "excel") descargarExcel(tabla, nombre);
        else await descargarPdf({ titulo: tabla.titulo, periodo: tabla.periodo, tablas: [tabla] }, nombre);
      } catch (error) {
        Swal.fire({ title: "Error", text: "No se pudo exportar el reporte.", icon: "error" });
      }
    },
    errorMessage(error) {
      const errors = error.response?.data?.errors;
      return (
        (errors && Object.values(errors).flat()[0]) ||
        error.response?.data?.message ||
        error.message ||
        "No se pudo completar la solicitud."
      );
    },
    formatMonto(value) {
      return Number(value || 0).toLocaleString("es-DO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    async esperarRender() {
      await this.$nextTick();
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    },
    limpiarFiltros() {
      this.filtros = { grupo: "", subgrupo: "", division: "", sociedad: "", tipo: "", estatus: "todos", buscar: "" };
    },
    async generar() {
      if (!this.desde || !this.hasta) {
        Swal.fire({ title: "Error", text: "Seleccione la fecha desde y la fecha hasta", icon: "error" });
        return;
      }
      if (!this.agrupar.length) {
        Swal.fire({ title: "Error", text: "Seleccione al menos una opcion para agrupar", icon: "error" });
        return;
      }

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
        const filtros = Object.fromEntries(Object.entries(this.filtros).filter(([, value]) => value !== ""));
        const { data } = await axios.get(route("reporte-ventas.datos"), {
          params: { desde: this.desde, hasta: this.hasta, agrupar: this.agrupar, ...(this.incluirRaza ? { raza: 1 } : {}), ...filtros },
        });
        this.resultado = data;
        await this.esperarRender();
        Swal.close();
      } catch (error) {
        this.resultado = null;
        Swal.fire({ title: "Error", text: this.errorMessage(error), icon: "error" });
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Ventas" pageTitle="Reporte" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">Ventas por periodo</h5>
          </BCardHeader>
          <BCardBody>
            <BRow class="g-3 mb-3">
              <BCol md="2">
                <label for="desde" class="form-label">Fecha desde</label>
                <input id="desde" v-model="desde" type="date" class="form-control" />
              </BCol>
              <BCol md="2">
                <label for="hasta" class="form-label">Fecha hasta</label>
                <input id="hasta" v-model="hasta" type="date" class="form-control" />
              </BCol>
              <BCol md="8">
                <label class="form-label d-block">Agrupar por</label>
                <div class="d-flex flex-wrap gap-3">
                  <div v-for="(label, key) in dimensiones" :key="key" class="form-check">
                    <input :id="`agrupar-${key}`" v-model="agrupar" :value="key" type="checkbox" class="form-check-input" />
                    <label :for="`agrupar-${key}`" class="form-check-label">{{ label }}</label>
                  </div>
                  <div class="form-check">
                    <input id="incluir-raza" v-model="incluirRaza" type="checkbox" class="form-check-input" />
                    <label for="incluir-raza" class="form-check-label">Ventas raza</label>
                  </div>
                  <div class="form-check">
                    <input id="incluir-clasificacion" v-model="incluirClasificacion" type="checkbox" class="form-check-input" :disabled="!agrupar.includes('terminal')" />
                    <label for="incluir-clasificacion" class="form-check-label">Clasificación</label>
                  </div>
                </div>
              </BCol>
            </BRow>

            <BRow class="g-3 mb-3">
              <BCol md="2">
                <label for="f-buscar" class="form-label">Terminal</label>
                <input id="f-buscar" v-model="filtros.buscar" type="search" class="form-control" placeholder="Terminal o nombre" />
              </BCol>
              <BCol md="2">
                <label for="f-grupo" class="form-label">Grupo</label>
                <select id="f-grupo" v-model="filtros.grupo" class="form-select">
                  <option value="">Todos</option>
                  <option v-for="opcion in opciones.grupos" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="2">
                <label for="f-subgrupo" class="form-label">Subgrupo</label>
                <select id="f-subgrupo" v-model="filtros.subgrupo" class="form-select">
                  <option value="">Todos</option>
                  <option v-for="opcion in opciones.subgrupos" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="2">
                <label for="f-division" class="form-label">Division</label>
                <select id="f-division" v-model="filtros.division" class="form-select">
                  <option value="">Todas</option>
                  <option v-for="opcion in opciones.divisiones" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="2">
                <label for="f-sociedad" class="form-label">Sociedad</label>
                <select id="f-sociedad" v-model="filtros.sociedad" class="form-select">
                  <option value="">Todas</option>
                  <option v-for="opcion in opciones.sociedades" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="2">
                <label for="f-tipo" class="form-label">Tipo</label>
                <select id="f-tipo" v-model="filtros.tipo" class="form-select">
                  <option value="">Todos</option>
                  <option v-for="opcion in opciones.tipos" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="12" class="d-flex align-items-end gap-2">
                <BButton variant="primary" :disabled="loading" @click="generar">Generar reporte</BButton>
                <BButton variant="light" :disabled="loading" @click="limpiarFiltros">Limpiar filtros</BButton>
              </BCol>
            </BRow>

            <template v-if="resultado">
              <div class="d-flex flex-wrap gap-4 mb-3">
                <div>
                  <small class="text-muted d-block">Venta total</small>
                  <h5 class="mb-0 text-success">{{ formatMonto(resultado.totales.total_venta) }}</h5>
                </div>
                <div>
                  <small class="text-muted d-block">Terminales con venta</small>
                  <h5 class="mb-0">{{ resultado.totales.terminales.toLocaleString() }}</h5>
                </div>
                <div>
                  <small class="text-muted d-block">Registros</small>
                  <h5 class="mb-0">{{ resultado.totales.registros.toLocaleString() }}</h5>
                </div>
                <div>
                  <small class="text-muted d-block">Periodo</small>
                  <h5 class="mb-0">{{ resultado.desde }} a {{ resultado.hasta }}</h5>
                </div>
              </div>

              <div v-if="coberturaIncompleta" class="alert alert-warning py-2">
                Hay ventas cargadas en {{ resultado.cobertura.dias_con_datos }} de los {{ resultado.cobertura.dias_rango }} dias del periodo.
                Los totales solo incluyen los dias cargados.
              <div v-if="coberturaRazaIncompleta" class="alert alert-warning py-2">
                Ventas raza tiene datos en {{ resultado.cobertura_raza.dias_con_datos }} de los {{ resultado.cobertura_raza.dias_rango }} dias del periodo.
              </div>
              </div>
              <div v-if="resultado.truncado" class="alert alert-info py-2">
                Hay {{ resultado.total_filas.toLocaleString() }} filas; se muestran las primeras {{ filas.length.toLocaleString() }} por venta.
                Agrupe por menos opciones o use filtros para ver el detalle completo. Los totales incluyen todas las filas.
              </div>

              <BRow class="g-2 mb-2 align-items-center">
                <BCol md="6" class="d-flex align-items-center gap-2">
                  <label for="perPage" class="form-label mb-0">Mostrar</label>
                  <select id="perPage" v-model.number="perPage" class="form-select form-select-sm w-auto">
                    <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                  </select>
                  <span class="text-muted">registros</span>
                </BCol>
                <BCol md="6" class="d-flex justify-content-md-end align-items-center gap-2">
                  <label for="busqueda" class="form-label mb-0">Buscar</label>
                  <input id="busqueda" v-model="busqueda" type="search" class="form-control form-control-sm w-auto" placeholder="Buscar en el resultado..." />
                </BCol>
              </BRow>
              <div class="d-flex gap-2 mb-2">
                <BButton size="sm" variant="success" @click="exportar('excel')">Exportar Excel</BButton>
                <BButton size="sm" variant="danger" @click="exportar('pdf')">Exportar PDF</BButton>
              </div>

              <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th v-for="col in columnas" :key="col.key">{{ col.label }}</th>
                      <template v-for="tipo in resultado.tipos" :key="tipo">
                        <th class="text-end">{{ tipo }}</th>
                        <th class="text-end">Promedio {{ tipo }}</th>
                      </template>
                      <th class="text-end">Promedio total</th>
                      <th class="text-end">Venta total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(fila, index) in filasPagina" :key="(page - 1) * perPage + index">
                      <td v-for="col in columnas" :key="col.key">{{ fila[col.key] }}</td>
                      <template v-for="tipo in resultado.tipos" :key="tipo">
                        <td class="text-end">{{ formatMonto(fila.tipos[tipo]) }}</td>
                        <td class="text-end">{{ formatMonto(fila.promedios_por_tipo[tipo]) }}</td>
                      </template>
                      <td class="text-end">{{ formatMonto(fila.promedio) }}</td>
                      <td class="text-end">{{ formatMonto(fila.total_venta) }}</td>
                    </tr>
                    <tr v-if="!filasFiltradas.length">
                      <td :colspan="columnas.length + resultado.tipos.length * 2 + 2" class="text-center text-muted py-3">Sin resultados para los filtros seleccionados.</td>
                    </tr>
                  </tbody>
                  <tfoot v-if="filasFiltradas.length">
                    <tr>
                      <th :colspan="columnas.length" class="text-end">Total</th>
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

              <div v-if="filasFiltradas.length" class="d-flex align-items-center justify-content-between mt-3">
                <span class="text-muted">{{ rangoLabel }}</span>
                <div class="d-flex align-items-center gap-2">
                  <BButton size="sm" variant="light" :disabled="page <= 1" @click="page = 1">«</BButton>
                  <BButton size="sm" variant="light" :disabled="page <= 1" @click="page--">Anterior</BButton>
                  <span>Página {{ page }} de {{ totalPages }}</span>
                  <BButton size="sm" variant="light" :disabled="page >= totalPages" @click="page++">Siguiente</BButton>
                  <BButton size="sm" variant="light" :disabled="page >= totalPages" @click="page = totalPages">»</BButton>
                </div>
              </div>
            </template>
            <div v-else class="text-center text-muted py-4">Elija el periodo, como agrupar y los filtros, y pulse Generar reporte.</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
