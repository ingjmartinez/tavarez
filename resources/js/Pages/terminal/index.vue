<script>
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";

const MODOS = {
  inactivas: {
    ruta: "terminal.inactivas",
    titulo: "Terminales inactivas",
    descripcion: "Terminales desactivadas actualmente en el catalogo de centros de costo",
    vacio: "No hay terminales desactivadas.",
    badge: "bg-danger-subtle text-danger-emphasis",
  },
  sin_venta: {
    ruta: "terminal.sin-venta-30-dias",
    titulo: "Terminales activas sin venta",
    descripcion: "Terminales activas sin venta positiva en los ultimos 30 dias",
    vacio: "No hay terminales activas sin venta en los ultimos 30 dias.",
    badge: "bg-warning-subtle text-warning-emphasis",
  },
  inactivas_con_venta: {
    ruta: "terminal.inactivas-con-venta-30-dias",
    titulo: "Terminales inactivas con venta",
    descripcion: "Terminales desactivadas con venta positiva en los ultimos 30 dias",
    vacio: "No hay terminales inactivas con venta en los ultimos 30 dias.",
    badge: "bg-info-subtle text-info-emphasis",
  },
};

export default {
  components: {
    Layout,
    PageHeader,
    Link,
  },
  props: {
    puedeConfigurarReportes: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      terminales: [],
      conteos: { total: 0, activas: 0, inactivas: 0 },
      cargando: true,
      error: "",
      busqueda: "",
      filtroEstado: "todos",
      page: 1,
      perPage: 25,
      modal: false,
      modo: "inactivas",
      detalle: null,
      detalleCargando: false,
      detalleError: "",
      modos: MODOS,
      modalCategorias: false,
      categorias: { grupo: [], subgrupo: [], division: [], sociedad: [] },
      categoriaActual: "grupo",
      busquedaCategoria: "",
      cargandoCategorias: false,
      guardandoCategorias: false,
      errorCategorias: "",
      dimensionesCategorias: { grupo: "Grupos", subgrupo: "Subgrupos", division: "Divisiones", sociedad: "Corporaciones" },
    };
  },
  computed: {
    terminalesFiltradas() {
      const term = this.busqueda.trim().toLowerCase();
      return this.terminales.filter((item) => {
        if (this.filtroEstado === "activas" && !item.activa) {
          return false;
        }
        if (this.filtroEstado === "inactivas" && item.activa) {
          return false;
        }
        if (!term) {
          return true;
        }
        return [item.terminal, item.nombre, item.grupo, item.subgrupo, item.division, item.sociedad].some((value) =>
          String(value ?? "").toLowerCase().includes(term),
        );
      });
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.terminalesFiltradas.length / this.perPage));
    },
    terminalesPagina() {
      const start = (this.page - 1) * this.perPage;
      return this.terminalesFiltradas.slice(start, start + this.perPage);
    },
    rangoLabel() {
      if (!this.terminalesFiltradas.length) {
        return "";
      }
      const start = (this.page - 1) * this.perPage + 1;
      const end = Math.min(this.page * this.perPage, this.terminalesFiltradas.length);
      return `Mostrando ${start} a ${end} de ${this.terminalesFiltradas.length}`;
    },
    config() {
      return MODOS[this.modo];
    },
    detalleTerminales() {
      return this.detalle?.terminales ?? [];
    },
    detalleConVenta() {
      return this.modo === "inactivas_con_venta";
    },
    coberturaIncompleta() {
      const cobertura = this.detalle?.cobertura;
      return Boolean(cobertura) && !cobertura.completa;
    },
    categoriasFiltradas() {
      const term = this.busquedaCategoria.trim().toLowerCase();
      return this.categorias[this.categoriaActual].filter((categoria) => categoria.valor.toLowerCase().includes(term));
    },
    totalCategoriasActivas() {
      return Object.values(this.categorias).flat().filter((categoria) => categoria.activo).length;
    },
  },
  watch: {
    perPage() {
      this.page = 1;
    },
    busqueda() {
      this.page = 1;
    },
    filtroEstado() {
      this.page = 1;
    },
  },
  mounted() {
    this.cargar();
  },
  methods: {
    errorMessage(error) {
      return error.response?.data?.message || error.message || "No se pudo completar la solicitud.";
    },
    async cargar() {
      this.cargando = true;
      this.error = "";
      try {
        const { data } = await axios.get(route("terminal.resumen"));
        this.terminales = data.terminales;
        this.conteos = { total: data.total, activas: data.activas, inactivas: data.inactivas };
      } catch (error) {
        this.terminales = [];
        this.conteos = { total: 0, activas: 0, inactivas: 0 };
        this.error = this.errorMessage(error);
      } finally {
        this.cargando = false;
      }
    },
    async abrirDetalle(modo) {
      this.modal = true;
      await this.cargarDetalle(modo);
    },
    async cargarDetalle(modo) {
      this.modo = modo;
      this.detalle = null;
      this.detalleError = "";
      this.detalleCargando = true;
      try {
        const { data } = await axios.get(route(MODOS[modo].ruta));
        if (this.modo === modo) {
          this.detalle = data;
        }
      } catch (error) {
        if (this.modo === modo) {
          this.detalleError = this.errorMessage(error);
        }
      } finally {
        if (this.modo === modo) {
          this.detalleCargando = false;
        }
      }
    },
    formatMonto(value) {
      return Number(value || 0).toLocaleString("es-DO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    async abrirCategorias() {
      this.modalCategorias = true;
      this.cargandoCategorias = true;
      this.errorCategorias = "";
      try {
        const { data } = await axios.get(route("terminal.categorias-reporte"));
        this.categorias = data.categorias;
      } catch (error) {
        this.errorCategorias = this.errorMessage(error);
      } finally {
        this.cargandoCategorias = false;
      }
    },
    marcarCategoria(activo) {
      this.categorias[this.categoriaActual].forEach((categoria) => { categoria.activo = activo; });
    },
    async guardarCategorias() {
      this.guardandoCategorias = true;
      this.errorCategorias = "";
      const activas = Object.entries(this.categorias).flatMap(([dimension, categorias]) =>
        categorias.filter((categoria) => categoria.activo).map((categoria) => ({ dimension, valor: categoria.valor })),
      );
      try {
        await axios.put(route("terminal.guardar-categorias-reporte"), { activas });
        this.modalCategorias = false;
      } catch (error) {
        this.errorCategorias = this.errorMessage(error);
      } finally {
        this.guardandoCategorias = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Terminal" pageTitle="Mantenimiento" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0">Lista de terminales</h5>
            <div class="d-flex gap-2">
              <BButton v-if="puedeConfigurarReportes" size="sm" variant="outline-primary" @click="abrirCategorias">Grupo</BButton>
              <BButton size="sm" variant="primary" :disabled="cargando" @click="cargar">Actualizar</BButton>
            </div>
          </BCardHeader>
          <BCardBody>
            <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
              <div>
                <small class="text-muted d-block">Terminales activas</small>
                <h5 class="mb-0 text-success">{{ conteos.activas.toLocaleString() }}</h5>
              </div>
              <div>
                <small class="text-muted d-block">Terminales inactivas</small>
                <h5
                  class="mb-0 text-danger"
                  role="button"
                  title="Ver detalle"
                  style="cursor: pointer; text-decoration: underline; text-decoration-style: dotted"
                  @click="abrirDetalle('inactivas')"
                >
                  {{ conteos.inactivas.toLocaleString() }}
                </h5>
              </div>
              <div class="d-flex gap-2 ms-auto">
                <BButton size="sm" :variant="filtroEstado === 'todos' ? 'secondary' : 'outline-secondary'" @click="filtroEstado = 'todos'">Todas</BButton>
                <BButton size="sm" :variant="filtroEstado === 'activas' ? 'success' : 'outline-success'" @click="filtroEstado = 'activas'">Activas</BButton>
                <BButton size="sm" :variant="filtroEstado === 'inactivas' ? 'danger' : 'outline-danger'" @click="filtroEstado = 'inactivas'">Inactivas</BButton>
              </div>
            </div>

            <div v-if="error" class="alert alert-danger d-flex align-items-center justify-content-between">
              <span>{{ error }}</span>
              <BButton size="sm" variant="danger" @click="cargar">Reintentar</BButton>
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
                <input id="busqueda" v-model="busqueda" type="search" class="form-control form-control-sm w-auto" placeholder="Terminal, nombre, grupo..." />
              </BCol>
            </BRow>

            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Terminal</th>
                    <th>Nombre</th>
                    <th>Grupo</th>
                    <th>Subgrupo</th>
                    <th>Division</th>
                    <th>Sociedad</th>
                    <th class="text-center">Estatus</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="cargando">
                    <td colspan="7" class="text-center text-muted py-3">Cargando terminales...</td>
                  </tr>
                  <template v-else>
                    <tr v-for="item in terminalesPagina" :key="item.terminal">
                      <td>{{ item.terminal }}</td>
                      <td>{{ item.nombre }}</td>
                      <td>{{ item.grupo }}</td>
                      <td>{{ item.subgrupo }}</td>
                      <td>{{ item.division }}</td>
                      <td>{{ item.sociedad }}</td>
                      <td class="text-center">
                        <span class="badge" :class="item.activa ? 'bg-success' : 'bg-danger'">{{ item.activa ? "Activa" : "Inactiva" }}</span>
                      </td>
                    </tr>
                    <tr v-if="!terminalesFiltradas.length">
                      <td colspan="7" class="text-center text-muted py-3">
                        <template v-if="!terminales.length && !error">
                          No hay terminales. Sincronice primero los centros de costo en
                          <Link :href="route('centro-costo.index')">Contabilidad / Centro de costo</Link>.
                        </template>
                        <template v-else-if="!error">Ninguna terminal coincide con el filtro.</template>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div v-if="terminalesFiltradas.length" class="d-flex align-items-center justify-content-between mt-3">
              <span class="text-muted">{{ rangoLabel }}</span>
              <div class="d-flex align-items-center gap-2">
                <BButton size="sm" variant="light" :disabled="page <= 1" @click="page = 1">«</BButton>
                <BButton size="sm" variant="light" :disabled="page <= 1" @click="page--">Anterior</BButton>
                <span>Página {{ page }} de {{ totalPages }}</span>
                <BButton size="sm" variant="light" :disabled="page >= totalPages" @click="page++">Siguiente</BButton>
                <BButton size="sm" variant="light" :disabled="page >= totalPages" @click="page = totalPages">»</BButton>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BModal v-model="modal" :title="config.titulo" size="xl" scrollable hide-footer>
      <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <span class="text-muted small mt-1">
          {{ config.descripcion }}
          <template v-if="detalle?.desde"> | Rango: {{ detalle.desde }} a {{ detalle.hasta }}</template>
        </span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge" :class="config.badge">{{ (detalle?.total ?? 0).toLocaleString() }} terminales</span>
          <BButton size="sm" :variant="modo === 'inactivas' ? 'secondary' : 'outline-secondary'" :disabled="detalleCargando" @click="cargarDetalle('inactivas')">Inactivas</BButton>
          <BButton size="sm" :variant="modo === 'sin_venta' ? 'danger' : 'outline-danger'" :disabled="detalleCargando" @click="cargarDetalle('sin_venta')">Activas sin venta</BButton>
          <BButton size="sm" :variant="modo === 'inactivas_con_venta' ? 'warning' : 'outline-warning'" :disabled="detalleCargando" @click="cargarDetalle('inactivas_con_venta')">Inactivas con venta</BButton>
        </div>
      </div>

      <div v-if="coberturaIncompleta" class="alert alert-warning py-2">
        Solo hay ventas cargadas en {{ detalle.cobertura.dias_con_datos }} de los {{ detalle.cobertura.dias_periodo }} dias del periodo.
        El resultado puede no ser confiable hasta cargar las ventas completas.
      </div>

      <div v-if="detalleError" class="alert alert-danger d-flex align-items-center justify-content-between">
        <span>{{ detalleError }}</span>
        <BButton size="sm" variant="danger" @click="cargarDetalle(modo)">Reintentar</BButton>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Terminal</th>
              <th>Nombre</th>
              <th>Grupo</th>
              <th>Subgrupo</th>
              <th>Division</th>
              <th>Sociedad</th>
              <th class="text-center">Estatus</th>
              <template v-if="detalleConVenta">
                <th class="text-end">Venta 30 dias</th>
                <th class="text-end">Dias con venta</th>
                <th>Ultima venta</th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-if="detalleCargando">
              <td :colspan="detalleConVenta ? 10 : 7" class="text-center text-muted py-3">Consultando datos...</td>
            </tr>
            <template v-else>
              <tr v-for="item in detalleTerminales" :key="item.terminal">
                <td>{{ item.terminal }}</td>
                <td>{{ item.nombre }}</td>
                <td>{{ item.grupo }}</td>
                <td>{{ item.subgrupo }}</td>
                <td>{{ item.division }}</td>
                <td>{{ item.sociedad }}</td>
                <td class="text-center">
                  <span class="badge" :class="item.activa ? 'bg-success' : 'bg-danger'">{{ item.activa ? "Activa" : "Inactiva" }}</span>
                </td>
                <template v-if="detalleConVenta">
                  <td class="text-end">{{ formatMonto(item.total_venta) }}</td>
                  <td class="text-end">{{ item.dias_con_venta }}</td>
                  <td>{{ item.ultima_fecha }}</td>
                </template>
              </tr>
              <tr v-if="detalle && !detalleTerminales.length">
                <td :colspan="detalleConVenta ? 10 : 7" class="text-center text-muted py-3">{{ config.vacio }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">
        Total de terminales en esta consulta: <strong>{{ (detalle?.total ?? 0).toLocaleString() }}</strong>
      </div>
    </BModal>

    <BModal v-model="modalCategorias" title="Categorías visibles en reportes" size="xl" scrollable hide-footer>
      <p class="text-muted small mb-2">
        Los reportes incluyen las terminales que coincidan con cualquier categoría marcada. Una corporación marcada incluye sus terminales aunque sus grupos no estén marcados.
        Sin categorías marcadas, se muestran todas las terminales como antes.
      </p>
      <div v-if="errorCategorias" class="alert alert-danger py-2">{{ errorCategorias }}</div>
      <div v-if="cargandoCategorias" class="text-muted py-4 text-center">Cargando categorías...</div>
      <template v-else>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <BButton
            v-for="(label, dimension) in dimensionesCategorias"
            :key="dimension"
            size="sm"
            :variant="categoriaActual === dimension ? 'primary' : 'outline-primary'"
            @click="categoriaActual = dimension; busquedaCategoria = ''"
          >
            {{ label }} ({{ categorias[dimension].filter((item) => item.activo).length }})
          </BButton>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <input v-model="busquedaCategoria" type="search" class="form-control form-control-sm" style="max-width: 300px" placeholder="Buscar categoría..." />
          <BButton size="sm" variant="outline-secondary" @click="marcarCategoria(true)">Marcar todas</BButton>
          <BButton size="sm" variant="outline-secondary" @click="marcarCategoria(false)">Desmarcar todas</BButton>
          <span class="text-muted small ms-auto">{{ totalCategoriasActivas }} categorías marcadas</span>
        </div>
        <div class="border rounded p-2 overflow-auto" style="max-height: 360px">
          <div v-for="categoria in categoriasFiltradas" :key="categoria.valor" class="form-check py-1">
            <input :id="`categoria-${categoriaActual}-${categoria.valor}`" v-model="categoria.activo" type="checkbox" class="form-check-input" />
            <label :for="`categoria-${categoriaActual}-${categoria.valor}`" class="form-check-label">{{ categoria.valor }}</label>
          </div>
          <div v-if="!categoriasFiltradas.length" class="text-muted text-center py-3">No hay categorías para mostrar.</div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <BButton variant="light" :disabled="guardandoCategorias" @click="modalCategorias = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="guardandoCategorias" @click="guardarCategorias">
            {{ guardandoCategorias ? "Guardando..." : "Guardar" }}
          </BButton>
        </div>
      </template>
    </BModal>
  </Layout>
</template>
