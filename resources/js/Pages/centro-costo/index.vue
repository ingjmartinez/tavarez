<script>
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
  components: {
    Layout,
    PageHeader,
  },
  props: {
    totalGuardados: {
      type: Number,
      default: 0,
    },
    ultimaSincronizacion: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      centros: [],
      guardados: this.totalGuardados,
      ultimaSync: this.ultimaSincronizacion,
      busqueda: "",
      loading: false,
      page: 1,
      perPage: 25,
    };
  },
  computed: {
    centrosFiltrados() {
      const term = this.busqueda.trim().toLowerCase();
      if (!term) {
        return this.centros;
      }
      return this.centros.filter((item) =>
        [item.id_centro_costo, item.descripcion, item.id_grupo, item.id_sub_grupo, item.id_division, item.id_sociedad, item.id_viejo, item.cuenta]
          .some((value) => String(value ?? "").toLowerCase().includes(term)),
      );
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.centrosFiltrados.length / this.perPage));
    },
    centrosPagina() {
      const start = (this.page - 1) * this.perPage;
      return this.centrosFiltrados.slice(start, start + this.perPage);
    },
    rangoLabel() {
      if (!this.centrosFiltrados.length) {
        return "";
      }
      const start = (this.page - 1) * this.perPage + 1;
      const end = Math.min(this.page * this.perPage, this.centrosFiltrados.length);
      return `Mostrando ${start} a ${end} de ${this.centrosFiltrados.length}`;
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
  methods: {
    notify(ok, text) {
      Swal.fire({ title: ok ? "Listo" : "Error", text, icon: ok ? "success" : "error" });
    },
    errorMessage(error) {
      return error.response?.data?.message || error.message || "No se pudo completar la solicitud.";
    },
    loadingAlert(title) {
      Swal.fire({
        title,
        text: "Por favor espere",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });
    },
    async esperarRender() {
      await this.$nextTick();
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    },
    async consultar() {
      this.loading = true;
      this.centros = [];
      this.busqueda = "";
      this.page = 1;
      this.loadingAlert("Cargando datos...");
      try {
        const { data } = await axios.get(route("centro-costo.consultar"));
        this.centros = data.centros;
        await this.esperarRender();
        this.notify(true, `Datos obtenidos correctamente (${data.centros.length.toLocaleString()} registros)`);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    async sincronizar() {
      this.loading = true;
      this.loadingAlert("Sincronizando datos...");
      try {
        const { data } = await axios.post(route("centro-costo.sincronizar"));
        this.guardados = data.total_guardados;
        this.ultimaSync = data.ultima_sincronizacion;
        this.notify(true, data.message);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    async eliminar() {
      const confirmation = await Swal.fire({
        title: "Eliminar data",
        text: "Se eliminaran todos los centros de costo guardados de esta empresa.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!confirmation.isConfirmed) {
        return;
      }
      this.loading = true;
      try {
        const { data } = await axios.delete(route("centro-costo.eliminar"));
        this.guardados = 0;
        this.ultimaSync = null;
        this.centros = [];
        this.notify(true, `${data.message} (${Number(data.total).toLocaleString()} registros)`);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    formatDate(value) {
      return value ? String(value).replace("T", " ").slice(0, 16) : "";
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Centro de costo" pageTitle="Contabilidad" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Centros de costo</h5>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-info">Guardados en base de datos: {{ guardados.toLocaleString() }}</span>
              <span class="badge" :class="ultimaSync ? 'bg-success' : 'bg-secondary'">
                {{ ultimaSync ? `Ultima sincronizacion: ${ultimaSync}` : "Sin sincronizar" }}
              </span>
            </div>
          </BCardHeader>
          <BCardBody>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <BButton variant="primary" :disabled="loading" @click="consultar">Consultar</BButton>
              <BButton variant="success" :disabled="loading" @click="sincronizar">Sincronizar</BButton>
              <BButton v-if="$page.props.permisos.eliminar" variant="danger" :disabled="loading" @click="eliminar">Eliminar data</BButton>
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
                <input id="busqueda" v-model="busqueda" type="search" class="form-control form-control-sm w-auto" placeholder="Descripcion, grupo, id..." />
              </BCol>
            </BRow>

            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle mb-0">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Descripcion</th>
                    <th>Grupo</th>
                    <th>Subgrupo</th>
                    <th>Division</th>
                    <th>Sociedad</th>
                    <th>ID viejo</th>
                    <th>Estado</th>
                    <th>Modificado por</th>
                    <th>Fecha modificado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in centrosPagina" :key="item.id_centro_costo">
                    <td>{{ item.id_centro_costo }}</td>
                    <td>{{ item.descripcion }}</td>
                    <td>{{ item.id_grupo }}</td>
                    <td>{{ item.id_sub_grupo }}</td>
                    <td>{{ item.id_division }}</td>
                    <td>{{ item.id_sociedad }}</td>
                    <td>{{ item.id_viejo }}</td>
                    <td>
                      <span class="badge" :class="item.inactivo ? 'bg-danger' : 'bg-success'">{{ item.inactivo ? "Inactivo" : "Activo" }}</span>
                    </td>
                    <td>{{ item.modificado_por }}</td>
                    <td>{{ formatDate(item.fecha_modificado) }}</td>
                  </tr>
                  <tr v-if="!centrosFiltrados.length">
                    <td colspan="10" class="text-center text-muted">Sin datos. Pulse Consultar, o Sincronizar si aun no hay datos guardados.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="centrosFiltrados.length" class="d-flex align-items-center justify-content-between mt-3">
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
  </Layout>
</template>
