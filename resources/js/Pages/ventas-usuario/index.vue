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
    tokenExpiresAt: {
      type: String,
      default: null,
    },
    tokenValid: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      fecha: "",
      fechaDesde: "",
      fechaHasta: "",
      rangoModal: false,
      ventas: [],
      loading: false,
      page: 1,
      perPage: 25,
      expiresAt: this.tokenExpiresAt,
      isTokenValid: this.tokenValid,
    };
  },
  computed: {
    total() {
      return this.ventas.reduce((sum, item) => sum + Number(item.monto || 0), 0);
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.ventas.length / this.perPage));
    },
    ventasPagina() {
      const start = (this.page - 1) * this.perPage;
      return this.ventas.slice(start, start + this.perPage);
    },
    rangoLabel() {
      if (!this.ventas.length) {
        return "";
      }
      const start = (this.page - 1) * this.perPage + 1;
      const end = Math.min(this.page * this.perPage, this.ventas.length);
      return `Mostrando ${start} a ${end} de ${this.ventas.length}`;
    },
    tokenLabel() {
      if (!this.expiresAt) {
        return "Sin token";
      }
      const date = new Date(this.expiresAt).toLocaleString();
      return this.isTokenValid ? `Vigente hasta ${date}` : `Vencido el ${date}`;
    },
  },
  watch: {
    perPage() {
      this.page = 1;
    },
  },
  methods: {
    notify(ok, text) {
      Swal.fire({ title: ok ? "Listo" : "Error", text, icon: ok ? "success" : "error" });
    },
    errorMessage(error) {
      return (
        error.response?.data?.message ||
        error.response?.data?.errors?.fecha?.[0] ||
        error.message ||
        "No se pudo completar la solicitud."
      );
    },
    async esperarRender() {
      await this.$nextTick();
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    },
    requireFecha() {
      if (!this.fecha) {
        this.notify(false, "Por favor, selecciona una fecha");
        return false;
      }
      return true;
    },
    async generarToken() {
      this.loading = true;
      try {
        const { data } = await axios.post(route("ventas-usuario.token"));
        this.expiresAt = data.expires_at;
        this.isTokenValid = true;
        this.notify(true, data.message);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    async consultar() {
      if (!this.requireFecha()) {
        return;
      }
      this.loading = true;
      this.ventas = [];
      this.page = 1;
      Swal.fire({
        title: "Cargando datos...",
        text: "Por favor espere",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });
      try {
        const { data } = await axios.get(route("ventas-usuario.consultar"), { params: { fecha: this.fecha } });
        this.ventas = data.ventas.map((item) => ({ ...item, fecha: this.fecha }));
        await this.esperarRender();
        this.notify(true, `Datos obtenidos correctamente (${data.ventas.length} registros)`);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    async guardar() {
      if (!this.requireFecha()) {
        return;
      }
      this.loading = true;
      Swal.fire({
        title: "Guardando datos...",
        text: "Por favor espere",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });
      try {
        const { data } = await axios.post(route("ventas-usuario.guardar"), { fecha: this.fecha });
        this.notify(true, data.message);
      } catch (error) {
        if (error.response?.status === 409) {
          Swal.fire({ title: "Aviso", text: this.errorMessage(error), icon: "warning" });
        } else {
          this.notify(false, this.errorMessage(error));
        }
      } finally {
        this.loading = false;
      }
    },
    diasDelRango(desde, hasta) {
      const dias = [];
      const cursor = new Date(`${desde}T00:00:00Z`);
      const fin = new Date(`${hasta}T00:00:00Z`);
      while (cursor <= fin) {
        dias.push(cursor.toISOString().slice(0, 10));
        cursor.setUTCDate(cursor.getUTCDate() + 1);
      }
      return dias;
    },
    async procesarRango(accion) {
      if (!this.fechaDesde || !this.fechaHasta) {
        this.notify(false, "Selecciona la fecha desde y la fecha hasta");
        return;
      }
      if (this.fechaDesde > this.fechaHasta) {
        this.notify(false, "La fecha desde no puede ser mayor que la fecha hasta");
        return;
      }
      const eliminando = accion === "eliminar";
      const dias = this.diasDelRango(this.fechaDesde, this.fechaHasta);
      this.rangoModal = false;

      const confirmation = await Swal.fire({
        title: eliminando ? "Eliminar data por rango" : "Generar data por rango",
        text: eliminando
          ? `Se eliminara la data guardada dia por dia (${dias.length} dias) del ${this.fechaDesde} al ${this.fechaHasta}.`
          : `Se consultara y guardara la data dia por dia (${dias.length} dias) del ${this.fechaDesde} al ${this.fechaHasta}.`,
        icon: eliminando ? "warning" : "question",
        showCancelButton: true,
        confirmButtonText: eliminando ? "Eliminar" : "Generar",
        cancelButtonText: "Cancelar",
      });
      if (!confirmation.isConfirmed) {
        return;
      }

      this.loading = true;
      let procesados = 0;
      let omitidos = 0;
      let registros = 0;
      const errores = [];
      const detalle = [];

      Swal.fire({
        title: eliminando ? "Eliminando data..." : "Generando data...",
        html: "Preparando...",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });

      for (const [index, dia] of dias.entries()) {
        Swal.update({ html: `Procesando ${dia} (${index + 1} de ${dias.length})` });
        try {
          const { data } = eliminando
            ? await axios.delete(route("ventas-usuario.eliminar"), { params: { fecha: dia } })
            : await axios.post(route("ventas-usuario.guardar"), { fecha: dia });
          if (data.total > 0) {
            procesados++;
            registros += data.total;
            detalle.push(`${dia}: ${Number(data.total).toLocaleString()} filas`);
          } else {
            omitidos++;
            detalle.push(`${dia}: 0 filas (${eliminando ? "sin data" : "sin datos"})`);
          }
        } catch (error) {
          if (error.response?.status === 409) {
            omitidos++;
            detalle.push(`${dia}: ya hay datos guardados, omitido para evitar duplicados`);
          } else {
            errores.push(`${dia}: ${this.errorMessage(error)}`);
          }
        }
      }

      this.loading = false;
      const resumen = eliminando
        ? `Dias eliminados: ${procesados} (${registros.toLocaleString()} registros). Dias sin data: ${omitidos}.`
        : `Dias guardados: ${procesados} (${registros.toLocaleString()} registros). Dias omitidos (sin datos o ya guardados): ${omitidos}.`;
      const html =
        `<p>${resumen}</p>` +
        `<div class="text-start border rounded p-2 mb-2" style="max-height: 220px; overflow-y: auto">${detalle.join("<br>")}</div>` +
        (errores.length ? `<div class="text-danger text-start">Errores (${errores.length}):<br>${errores.slice(0, 10).join("<br>")}</div>` : "");
      Swal.fire({
        title: errores.length ? "Proceso terminado con errores" : "Listo",
        html,
        icon: errores.length ? "warning" : "success",
      });
    },
    async eliminar() {
      if (!this.requireFecha()) {
        return;
      }
      const confirmation = await Swal.fire({
        title: "Eliminar data",
        text: `Se eliminara la data guardada del ${this.fecha}.`,
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
        const { data } = await axios.delete(route("ventas-usuario.eliminar"), { params: { fecha: this.fecha } });
        this.notify(true, data.message);
      } catch (error) {
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Ventas por usuario" pageTitle="API" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Configurar token</h5>
            <span class="badge" :class="isTokenValid ? 'bg-success' : 'bg-danger'">{{ tokenLabel }}</span>
          </BCardHeader>
          <BCardBody>
            <BRow class="g-3 mb-4 align-items-end">
              <BCol md="3">
                <BButton variant="primary" :disabled="loading" @click="generarToken">Generar token</BButton>
              </BCol>
              <BCol md="3">
                <label for="fecha" class="form-label">Fecha</label>
                <input id="fecha" v-model="fecha" type="date" class="form-control" />
              </BCol>
              <BCol md="6" class="d-flex gap-2">
                <BButton variant="primary" :disabled="loading" @click="consultar">Consultar</BButton>
                <BButton variant="success" :disabled="loading" @click="guardar">Guardar data</BButton>
                <BButton variant="danger" :disabled="loading" @click="eliminar">Eliminar data</BButton>
                <BButton variant="warning" :disabled="loading" @click="rangoModal = true">Generar data por fecha</BButton>
              </BCol>
            </BRow>

            <div class="d-flex align-items-center gap-2 mb-2">
              <label for="perPage" class="form-label mb-0">Mostrar</label>
              <select id="perPage" v-model.number="perPage" class="form-select form-select-sm w-auto">
                <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">registros</span>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Consorcio</th>
                    <th>Agencia</th>
                    <th>Producto</th>
                    <th>Cedula</th>
                    <th>Descripcion</th>
                    <th>Tipo</th>
                    <th class="text-end">Monto</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in ventasPagina" :key="(page - 1) * perPage + index">
                    <td>{{ item.fecha }}</td>
                    <td>{{ item.consorcio_id }}</td>
                    <td>{{ item.agencia_id }}</td>
                    <td>{{ item.producto_id }}</td>
                    <td>{{ item.cedula }}</td>
                    <td>{{ item.descripcion }}</td>
                    <td>{{ item.tipo }}</td>
                    <td class="text-end">{{ Number(item.monto || 0).toLocaleString() }}</td>
                  </tr>
                  <tr v-if="!ventas.length">
                    <td colspan="8" class="text-center text-muted">Sin datos. Seleccione una fecha y consulte.</td>
                  </tr>
                </tbody>
                <tfoot v-if="ventas.length">
                  <tr>
                    <th colspan="7" class="text-end">Total</th>
                    <th class="text-end">{{ total.toLocaleString() }}</th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div v-if="ventas.length" class="d-flex align-items-center justify-content-between mt-3">
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

    <BModal v-model="rangoModal" title="Data por rango de fechas" size="md" hide-footer centered header-class="bg-warning-subtle p-3">
      <p class="text-muted">Selecciona el rango. El proceso se ejecuta dia por dia.</p>
      <BRow class="g-3 mb-4">
        <BCol md="6">
          <label for="fechaDesde" class="form-label">Fecha desde</label>
          <input id="fechaDesde" v-model="fechaDesde" type="date" class="form-control" />
        </BCol>
        <BCol md="6">
          <label for="fechaHasta" class="form-label">Fecha hasta</label>
          <input id="fechaHasta" v-model="fechaHasta" type="date" class="form-control" />
        </BCol>
      </BRow>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="light" @click="rangoModal = false">Cancelar</BButton>
        <BButton variant="danger" @click="procesarRango('eliminar')">Eliminar data</BButton>
        <BButton variant="success" @click="procesarRango('generar')">Generar data</BButton>
      </div>
    </BModal>
  </Layout>
</template>
