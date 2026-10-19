<script>
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import axios from "axios";
import Swal from "sweetalert2";
import { descargarExcel, descargarPdf, tablasPeriodo } from "@/reporte-export.js";

export default {
  components: { Layout, PageHeader },
  props: {
    categoriasActivas: { type: Object, required: true },
  },
  data() {
    const hoy = new Date();
    const mesClave = (fecha) => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}`;

    return {
      mesDesde: mesClave(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)),
      mesHasta: mesClave(hoy),
      modo: "subgrupo",
      categoria: "",
      porTerminal: false,
      agruparMeses: false,
      resultado: null,
      loading: false,
      busqueda: "",
      page: 1,
      perPage: 25,
      ordenMes: null,
      ordenTipo: null,
      ordenDireccion: "desc",
    };
  },
  computed: {
    opcionesCategoria() {
      return this.categoriasActivas[this.modo] ?? [];
    },
    nombreDimension() {
      return this.modo === "sociedad" ? "Corporación" : "Subgrupo";
    },
    opcionesMes() {
      const hoy = new Date();
      const formato = new Intl.DateTimeFormat("es-DO", { month: "long", year: "numeric" });
      const meses = [];

      for (let indice = 0; indice < 300; indice++) {
        const mes = new Date(hoy.getFullYear(), hoy.getMonth() - indice, 1);
        const nombre = formato.format(mes);
        meses.push({ clave: `${mes.getFullYear()}-${String(mes.getMonth() + 1).padStart(2, "0")}`, nombre: nombre.charAt(0).toUpperCase() + nombre.slice(1) });
      }

      return meses;
    },
    fechaDesde() {
      return `${this.mesDesde}-01`;
    },
    fechaHasta() {
      const [anio, mes] = this.mesHasta.split("-").map(Number);
      const hoy = new Date();
      const dia = anio === hoy.getFullYear() && mes === hoy.getMonth() + 1
        ? hoy.getDate()
        : new Date(anio, mes, 0).getDate();
      return `${this.mesHasta}-${String(dia).padStart(2, "0")}`;
    },
    columnasMes() {
      return (this.resultado?.tipos.length ?? 0) * 2 + 2;
    },
    filasFiltradas() {
      const termino = this.busqueda.trim().toLowerCase();
      const filas = this.resultado?.filas ?? [];
      const filtradas = termino
        ? filas.filter((fila) => `${fila[this.modo] ?? ""} ${fila.terminal ?? ""} ${fila.nombre ?? ""}`.toLowerCase().includes(termino))
        : filas;

      if (!this.porTerminal || !this.ordenMes || !this.ordenTipo) return filtradas;

      return [...filtradas].sort((primera, segunda) => {
        const grupo = String(primera[this.modo]).localeCompare(String(segunda[this.modo]), "es", { numeric: true, sensitivity: "base" });
        if (grupo) return grupo;

        const ventaPrimera = Number(primera.meses?.[this.ordenMes]?.tipos?.[this.ordenTipo] ?? 0);
        const ventaSegunda = Number(segunda.meses?.[this.ordenMes]?.tipos?.[this.ordenTipo] ?? 0);
        const diferencia = this.ordenDireccion === "desc" ? ventaSegunda - ventaPrimera : ventaPrimera - ventaSegunda;
        return diferencia || String(primera.terminal).localeCompare(String(segunda.terminal), "es", { numeric: true });
      });
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filasFiltradas.length / this.perPage));
    },
    filasPagina() {
      return this.filasFiltradas.slice((this.page - 1) * this.perPage, this.page * this.perPage);
    },
  },
  watch: {
    mesDesde() { this.resultado = null; },
    mesHasta() { this.resultado = null; },
    modo() { this.categoria = ""; this.resultado = null; this.ordenMes = null; },
    categoria() { this.resultado = null; },
    porTerminal() { this.resultado = null; this.ordenMes = null; },
    agruparMeses() { this.resultado = null; this.ordenMes = null; },
    perPage() { this.page = 1; },
    busqueda() { this.page = 1; },
  },
  methods: {
    async exportar(formato) {
      if (!this.resultado) return;
      const tablas = tablasPeriodo(this.resultado, this.nombreDimension, this.resultado.filas, "Ventas por período");
      const nombre = `ventas-periodo-${tablas.excel.periodo.replaceAll(" a ", "-")}`;
      try {
        if (formato === "excel") descargarExcel(tablas.excel, nombre);
        else await descargarPdf(tablas.pdf, nombre);
      } catch (error) {
        Swal.fire({ title: "Error", text: "No se pudo exportar el reporte.", icon: "error" });
      }
    },
    formatMonto(valor) {
      return Number(valor || 0).toLocaleString("es-DO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    ordenarProducto(mes, tipo) {
      if (this.ordenMes === mes && this.ordenTipo === tipo) {
        this.ordenDireccion = this.ordenDireccion === "desc" ? "asc" : "desc";
      } else {
        this.ordenMes = mes;
        this.ordenTipo = tipo;
        this.ordenDireccion = "desc";
      }
      this.page = 1;
    },
    async generar() {
      this.loading = true;
      this.resultado = null;
      this.page = 1;
      this.busqueda = "";
      this.ordenMes = null;
      Swal.fire({
        title: "Generando reporte...",
        text: "Por favor espere",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });
      try {
        const { data } = await axios.get(route("reporte-periodo.datos"), {
          params: { desde: this.fechaDesde, hasta: this.fechaHasta, modo: this.modo, categoria: this.categoria, por_terminal: this.porTerminal ? 1 : 0, agrupar_meses: this.agruparMeses ? 1 : 0 },
        });
        this.resultado = data;
        await this.$nextTick();
        await new Promise((resolver) => requestAnimationFrame(() => requestAnimationFrame(resolver)));
        Swal.close();
      } catch (error) {
        const errores = error.response?.data?.errors;
        Swal.fire({
          title: "Error",
          text: (errores && Object.values(errores).flat()[0]) || error.response?.data?.message || "No se pudo generar el reporte.",
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
    <PageHeader title="Ventas por período" pageTitle="Reporte" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Ventas por período</h5></BCardHeader>
          <BCardBody>
            <div class="text-muted small mb-3">Seleccione los meses del período. Active «Agrupar por meses» para reunirlos en un solo bloque de ventas. Las categorías disponibles son las marcadas en Mantenimiento de terminales / Grupo.</div>
            <div v-if="!opcionesCategoria.length" class="alert alert-warning py-2">
              No hay {{ nombreDimension.toLowerCase() }} marcada. Márquela en Mantenimiento de terminales / Grupo.
            </div>
            <BRow class="g-3 align-items-end mb-3">
              <BCol md="3">
                <label for="desde-periodo" class="form-label">Mes desde</label>
                <select id="desde-periodo" v-model="mesDesde" class="form-select">
                  <option v-for="mes in opcionesMes" :key="mes.clave" :value="mes.clave">{{ mes.nombre }}</option>
                </select>
              </BCol>
              <BCol md="3">
                <label for="hasta-periodo" class="form-label">Mes hasta</label>
                <select id="hasta-periodo" v-model="mesHasta" class="form-select">
                  <option v-for="mes in opcionesMes" :key="mes.clave" :value="mes.clave">{{ mes.nombre }}</option>
                </select>
              </BCol>
              <BCol md="2">
                <label for="modo-periodo" class="form-label">Agrupar por</label>
                <select id="modo-periodo" v-model="modo" class="form-select">
                  <option value="subgrupo">Subgrupo</option>
                  <option value="sociedad">Corporación</option>
                </select>
              </BCol>
              <BCol md="3">
                <label for="categoria-periodo" class="form-label">{{ nombreDimension }}</label>
                <select id="categoria-periodo" v-model="categoria" class="form-select" :disabled="!opcionesCategoria.length">
                  <option value="" disabled>Seleccione {{ nombreDimension.toLowerCase() }}</option>
                  <option v-if="opcionesCategoria.length" value="__todos__">Todos</option>
                  <option v-for="opcion in opcionesCategoria" :key="opcion" :value="opcion">{{ opcion }}</option>
                </select>
              </BCol>
              <BCol md="1">
                <BButton variant="primary" :disabled="loading || !categoria || mesDesde > mesHasta" @click="generar">{{ loading ? "Generando..." : "Generar reporte" }}</BButton>
              </BCol>
            </BRow>
            <div v-if="mesDesde > mesHasta" class="text-danger small mb-3">El mes desde debe ser anterior o igual al mes hasta.</div>
            <div class="form-check mb-2">
              <input id="agrupar-meses-periodo" v-model="agruparMeses" class="form-check-input" type="checkbox" />
              <label for="agrupar-meses-periodo" class="form-check-label">Agrupar por meses</label>
            </div>
            <div class="form-check mb-3">
              <input id="terminal-periodo" v-model="porTerminal" class="form-check-input" type="checkbox" />
              <label for="terminal-periodo" class="form-check-label">Desglosar por terminal</label>
            </div>

            <template v-if="resultado">
              <div class="d-flex flex-wrap gap-4 mb-3">
                <div><small class="text-muted d-block">Venta total del período</small><h5 class="mb-0 text-success">{{ formatMonto(resultado.total_venta) }}</h5></div>
                <div v-if="!resultado.agrupar_meses"><small class="text-muted d-block">Meses</small><h5 class="mb-0">{{ resultado.meses.length }}</h5></div>
                <div><small class="text-muted d-block">{{ porTerminal ? "Filas" : nombreDimension + " con venta" }}</small><h5 class="mb-0">{{ resultado.total_filas.toLocaleString() }}</h5></div>
              </div>
              <div v-if="resultado.truncado" class="alert alert-warning py-2">El reporte alcanzó el límite de filas en al menos un mes.</div>
              <div v-for="mes in resultado.meses.filter((item) => item.cobertura.dias_con_datos < item.cobertura.dias_rango)" :key="mes.clave" class="alert alert-warning py-2">
                {{ mes.etiqueta }}: hay ventas cargadas en {{ mes.cobertura.dias_con_datos }} de {{ mes.cobertura.dias_rango }} días seleccionados.
              </div>
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <label for="por-pagina-periodo" class="form-label mb-0">Mostrar</label>
                  <select id="por-pagina-periodo" v-model.number="perPage" class="form-select form-select-sm w-auto">
                    <option v-for="cantidad in [10, 25, 50, 100]" :key="cantidad" :value="cantidad">{{ cantidad }}</option>
                  </select>
                </div>
                <input v-model="busqueda" type="search" class="form-control form-control-sm w-auto" :placeholder="`Buscar ${nombreDimension.toLowerCase()} o terminal...`" aria-label="Buscar en el resultado" />
              </div>
              <div class="d-flex gap-2 mb-2">
                <BButton size="sm" variant="success" @click="exportar('excel')">Exportar Excel</BButton>
                <BButton size="sm" variant="danger" @click="exportar('pdf')">Exportar PDF</BButton>
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle table-sm mb-0 text-nowrap periodo-table">
                  <thead class="table-light">
                    <tr>
                      <th :rowspan="2">{{ nombreDimension }}</th>
                      <th v-if="porTerminal" :rowspan="2">Terminal</th>
                      <th v-if="porTerminal" :rowspan="2">Nombre</th>
                      <th v-for="mes in resultado.meses" :key="mes.clave" :colspan="columnasMes" class="text-center table-primary">{{ mes.etiqueta }}<span v-if="mes.periodo" class="ms-2 fw-normal">· {{ mes.periodo }}</span></th>
                    </tr>
                    <tr>
                      <template v-for="mes in resultado.meses" :key="mes.clave">
                        <template v-for="tipo in resultado.tipos" :key="`${mes.clave}-${tipo}`">
                          <th class="text-end">
                            <button v-if="porTerminal" type="button" class="btn btn-link btn-sm p-0 text-reset text-decoration-none fw-semibold" :aria-label="`Ordenar ${tipo} de ${mes.etiqueta} por venta`" @click="ordenarProducto(mes.clave, tipo)">
                              {{ tipo }} <span v-if="ordenMes === mes.clave && ordenTipo === tipo">{{ ordenDireccion === "desc" ? "▼" : "▲" }}</span><span v-else class="text-muted">↕</span>
                            </button>
                            <template v-else>{{ tipo }}</template>
                          </th>
                          <th class="text-end">Prom. {{ tipo }}</th>
                        </template>
                        <th class="text-end">Prom. total</th>
                        <th class="text-end">Venta total</th>
                      </template>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="fila in filasPagina" :key="`${fila[modo]}-${fila.terminal ?? ''}`">
                      <td>{{ fila[modo] }}</td>
                      <td v-if="porTerminal">{{ fila.terminal }}</td>
                      <td v-if="porTerminal">{{ fila.nombre }}</td>
                      <template v-for="mes in resultado.meses" :key="mes.clave">
                        <template v-for="tipo in resultado.tipos" :key="`${mes.clave}-${tipo}`">
                          <td class="text-end">{{ formatMonto(fila.meses[mes.clave]?.tipos[tipo]) }}</td>
                          <td class="text-end">{{ formatMonto(fila.meses[mes.clave]?.promedios_por_tipo[tipo]) }}</td>
                        </template>
                        <td class="text-end">{{ formatMonto(fila.meses[mes.clave]?.promedio) }}</td>
                        <td class="text-end fw-semibold">{{ formatMonto(fila.meses[mes.clave]?.total_venta) }}</td>
                      </template>
                    </tr>
                    <tr v-if="!filasFiltradas.length">
                      <td :colspan="resultado.meses.length * columnasMes + 1 + (porTerminal ? 2 : 0)" class="text-center text-muted py-3">Sin ventas para los filtros seleccionados.</td>
                    </tr>
                  </tbody>
                  <tfoot v-if="filasFiltradas.length">
                    <tr>
                      <th>Total</th>
                      <th v-if="porTerminal"></th>
                      <th v-if="porTerminal"></th>
                      <template v-for="mes in resultado.meses" :key="mes.clave">
                        <template v-for="tipo in resultado.tipos" :key="`${mes.clave}-${tipo}`">
                          <th class="text-end">{{ formatMonto(mes.totales.por_tipo[tipo]) }}</th>
                          <th class="text-end">{{ formatMonto(mes.totales.promedios_por_tipo[tipo]) }}</th>
                        </template>
                        <th class="text-end">{{ formatMonto(mes.totales.promedio) }}</th>
                        <th class="text-end">{{ formatMonto(mes.totales.total_venta) }}</th>
                      </template>
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

<style scoped>
.periodo-table {
  width: 100%;
  min-width: max-content;
  font-size: 0.82rem;
}

.periodo-table th,
.periodo-table td {
  padding: 0.3rem 0.45rem;
}
</style>
