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
      loading: false,
      expiresAt: this.tokenExpiresAt,
      isTokenValid: this.tokenValid,
      logs: [],
      modulos: [
        { name: "Ventas por usuario", guardar: "ventas-usuario.guardar", eliminar: "ventas-usuario.eliminar", status: "Pendiente", detail: "-" },
        { name: "Pagos misma empresa", guardar: "pagos-misma-empresa.guardar", eliminar: "pagos-misma-empresa.eliminar", status: "Pendiente", detail: "-" },
        { name: "Pagos a otra empresa", guardar: "pagos-otra-empresa.guardar", eliminar: "pagos-otra-empresa.eliminar", status: "Pendiente", detail: "-" },
        { name: "Ventas raza", guardar: "ventas-raza.guardar", eliminar: "ventas-raza.eliminar", status: "Pendiente", detail: "-" },
      ],
    };
  },
  computed: {
    tokenLabel() {
      if (!this.expiresAt) {
        return "Sin token";
      }
      const date = new Date(this.expiresAt).toLocaleString();
      return this.isTokenValid ? `Vigente hasta ${date}` : `Vencido el ${date}`;
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
    badgeClass(status) {
      return (
        {
          OK: "bg-success",
          Error: "bg-danger",
          Omitido: "bg-warning",
          Ejecutando: "bg-info",
        }[status] || "bg-secondary"
      );
    },
    addLog(text, level = "info") {
      this.logs.unshift({ time: new Date().toLocaleString(), text, level });
    },
    resetEstados() {
      this.modulos.forEach((modulo) => {
        modulo.status = "Pendiente";
        modulo.detail = "-";
      });
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
    validarRango() {
      if (!this.fechaDesde || !this.fechaHasta) {
        this.notify(false, "Selecciona la fecha inicio y la fecha fin");
        return null;
      }
      if (this.fechaDesde > this.fechaHasta) {
        this.notify(false, "La fecha inicio no puede ser mayor que la fecha fin");
        return null;
      }
      return this.diasDelRango(this.fechaDesde, this.fechaHasta);
    },
    async generarToken() {
      this.loading = true;
      try {
        const { data } = await axios.post(route("ventas-usuario.token"));
        this.expiresAt = data.expires_at;
        this.isTokenValid = true;
        this.addLog(`Generar token: ${data.message}`);
        this.notify(true, data.message);
      } catch (error) {
        this.addLog(`Error generar token: ${this.errorMessage(error)}`, "error");
        this.notify(false, this.errorMessage(error));
      } finally {
        this.loading = false;
      }
    },
    /**
     * Ejecuta todos los modulos en orden para una fecha y devuelve el conteo de resultados.
     */
    async ejecutarFecha(dia, accion) {
      const eliminando = accion === "eliminar";
      const conteo = { ok: 0, omitidos: 0, errores: 0 };
      this.resetEstados();
      this.addLog(`${eliminando ? "Iniciando eliminacion" : "Iniciando procesamiento"} para ${dia}`);

      for (const modulo of this.modulos) {
        modulo.status = "Ejecutando";
        modulo.detail = eliminando ? "Eliminando..." : "Procesando...";
        try {
          const { data } = eliminando
            ? await axios.delete(route(modulo.eliminar), { params: { fecha: dia } })
            : await axios.post(route(modulo.guardar), { fecha: dia });
          const total = Number(data.total ?? 0).toLocaleString();
          modulo.status = "OK";
          modulo.detail = eliminando
            ? `Filas eliminadas: ${total}`
            : data.total > 0
              ? `Filas guardadas: ${total}`
              : "Sin datos para esa fecha";
          conteo.ok++;
          this.addLog(`OK ${modulo.name}: ${modulo.detail}`);
        } catch (error) {
          if (error.response?.status === 409) {
            modulo.status = "Omitido";
            modulo.detail = "Ya hay datos guardados";
            conteo.omitidos++;
            this.addLog(`OMITIDO ${modulo.name}: ${this.errorMessage(error)}`);
          } else {
            modulo.status = "Error";
            modulo.detail = this.errorMessage(error);
            conteo.errores++;
            this.addLog(`ERROR ${modulo.name}: ${modulo.detail}`, "error");
          }
        }
      }

      this.addLog(`Finalizado ${dia}: OK=${conteo.ok} Omitidos=${conteo.omitidos} Errores=${conteo.errores}`);
      return conteo;
    },
    async ejecutarDias(dias, accion) {
      const eliminando = accion === "eliminar";
      const total = { ok: 0, omitidos: 0, errores: 0 };
      this.loading = true;

      Swal.fire({
        title: eliminando ? "Eliminando data..." : "Procesando data...",
        html: "Preparando...",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      });

      for (const [index, dia] of dias.entries()) {
        Swal.update({ html: `Procesando ${dia} (${index + 1} de ${dias.length})` });
        const conteo = await this.ejecutarFecha(dia, accion);
        total.ok += conteo.ok;
        total.omitidos += conteo.omitidos;
        total.errores += conteo.errores;
      }

      this.loading = false;
      Swal.fire({
        title: eliminando ? "Resumen eliminacion" : "Resumen",
        html: `Dias: <strong>${dias.length}</strong><br>OK: <strong>${total.ok}</strong><br>Omitidos: <strong>${total.omitidos}</strong><br>Errores: <strong>${total.errores}</strong>`,
        icon: total.errores > 0 ? "warning" : "success",
      });
    },
    async procesarFecha() {
      if (!this.fecha) {
        this.notify(false, "Por favor, selecciona una fecha");
        return;
      }
      await this.ejecutarDias([this.fecha], "procesar");
    },
    async eliminarFecha() {
      if (!this.fecha) {
        this.notify(false, "Por favor, selecciona una fecha");
        return;
      }
      const confirmation = await Swal.fire({
        title: "Eliminar data",
        text: `Se eliminara toda la data guardada del ${this.fecha} en los ${this.modulos.length} modulos.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!confirmation.isConfirmed) {
        return;
      }
      await this.ejecutarDias([this.fecha], "eliminar");
    },
    async procesarRango(accion) {
      const dias = this.validarRango();
      if (!dias) {
        return;
      }
      this.rangoModal = false;

      if (accion === "eliminar") {
        const confirmation = await Swal.fire({
          title: "Eliminar data por rango",
          text: `Se eliminara la data de ${dias.length} dias (${this.fechaDesde} al ${this.fechaHasta}) en los ${this.modulos.length} modulos.`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Eliminar",
          cancelButtonText: "Cancelar",
        });
        if (!confirmation.isConfirmed) {
          return;
        }
      }
      await this.ejecutarDias(dias, accion);
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Procesar todo" pageTitle="API" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Ejecutar todas las tareas por fecha</h5>
            <span class="badge" :class="isTokenValid ? 'bg-success' : 'bg-danger'">{{ tokenLabel }}</span>
          </BCardHeader>
          <BCardBody>
            <BRow class="g-3 mb-4 align-items-end">
              <BCol md="2">
                <BButton variant="secondary" :disabled="loading" @click="generarToken">Generar token</BButton>
              </BCol>
              <BCol md="2">
                <label for="fecha" class="form-label">Fecha</label>
                <input id="fecha" v-model="fecha" type="date" class="form-control" />
              </BCol>
              <BCol md="8" class="d-flex flex-wrap gap-2">
                <BButton variant="primary" :disabled="loading" @click="procesarFecha">Procesar fecha</BButton>
                <BButton v-if="$page.props.permisos.eliminar" variant="danger" :disabled="loading" @click="eliminarFecha">Eliminar fecha</BButton>
                <BButton variant="warning" :disabled="loading" @click="rangoModal = true">Procesar por rango</BButton>
                <BButton v-if="$page.props.permisos.eliminar" variant="danger" :disabled="loading" @click="rangoModal = true">Eliminar por rango</BButton>
              </BCol>
            </BRow>

            <BRow>
              <BCol lg="5" class="mb-3">
                <BCard no-body class="mb-0">
                  <BCardBody>
                    <h6>Estados por modulo</h6>
                    <div class="table-responsive" style="max-height: 360px; overflow: auto">
                      <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                          <tr>
                            <th style="width: 1%">#</th>
                            <th>Modulo</th>
                            <th style="width: 1%">Estado</th>
                            <th>Detalle</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(modulo, index) in modulos" :key="modulo.name">
                            <td>{{ index + 1 }}</td>
                            <td>{{ modulo.name }}</td>
                            <td><span class="badge" :class="badgeClass(modulo.status)">{{ modulo.status }}</span></td>
                            <td>{{ modulo.detail }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </BCardBody>
                </BCard>
              </BCol>

              <BCol lg="7">
                <BCard no-body class="mb-0">
                  <BCardBody>
                    <h6>Registro de ejecucion</h6>
                    <div class="bg-light rounded p-2" style="max-height: 360px; overflow: auto">
                      <div v-if="!logs.length" class="text-muted">Sin ejecuciones todavia.</div>
                      <div v-for="(log, index) in logs" :key="index" class="py-1 border-bottom">
                        <strong>[{{ log.time }}]</strong>
                        <span :class="log.level === 'error' ? 'text-danger' : ''"> {{ log.text }}</span>
                      </div>
                    </div>
                  </BCardBody>
                </BCard>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BModal v-model="rangoModal" title="Procesar / Eliminar rango de fechas" size="md" hide-footer centered header-class="bg-warning-subtle p-3">
      <p class="text-muted">El proceso se ejecuta dia por dia, con los {{ modulos.length }} modulos en orden.</p>
      <BRow class="g-3 mb-4">
        <BCol md="6">
          <label for="fechaDesde" class="form-label">Fecha inicio</label>
          <input id="fechaDesde" v-model="fechaDesde" type="date" class="form-control" />
        </BCol>
        <BCol md="6">
          <label for="fechaHasta" class="form-label">Fecha fin</label>
          <input id="fechaHasta" v-model="fechaHasta" type="date" class="form-control" />
        </BCol>
      </BRow>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="light" @click="rangoModal = false">Cerrar</BButton>
        <BButton v-if="$page.props.permisos.eliminar" variant="danger" @click="procesarRango('eliminar')">Eliminar rango</BButton>
        <BButton variant="primary" @click="procesarRango('procesar')">Procesar rango</BButton>
      </div>
    </BModal>
  </Layout>
</template>
