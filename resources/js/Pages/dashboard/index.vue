<script>
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Layout from '@/Layouts/main.vue';

const fechaLocal = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

export default {
  components: { Head, Layout, Link },
  props: {
    subgruposSeleccionados: { type: Array, required: true },
    clasificacionUmbrales: { type: Object, required: true },
  },
  data() {
    const hoy = new Date();
    return {
      desde: fechaLocal(new Date(hoy.getFullYear(), hoy.getMonth(), 1)),
      hasta: fechaLocal(hoy),
      hoy: fechaLocal(hoy),
      resumen: null,
      loading: false,
      error: '',
      actualizado: '',
      modalClasificacion: false,
      detalleClasificacion: null,
      busquedaTerminal: '',
    };
  },
  computed: {
    maximoSubgrupo() {
      return Math.max(0, ...(this.resumen?.subgrupos ?? []).map((fila) => fila.total_venta));
    },
    clasificaciones() {
      return ['A', 'B', 'C', 'D'].map((letra) => ({
        letra,
        total: this.resumen?.clasificaciones?.[letra] ?? 0,
        descripcion: this.clasificacionUmbrales[letra]
          ? `Desde ${this.formatMonto(this.clasificacionUmbrales[letra])}`
          : `Menos de ${this.formatMonto(this.clasificacionUmbrales.C)}`,
      }));
    },
    terminalesFiltradas() {
      const busqueda = this.busquedaTerminal.trim().toLocaleLowerCase('es-DO');
      const terminales = this.detalleClasificacion?.terminales ?? [];
      return busqueda
        ? terminales.filter((item) => `${item.terminal} ${item.nombre ?? ''}`.toLocaleLowerCase('es-DO').includes(busqueda))
        : terminales;
    },
  },
  mounted() {
    if (this.subgruposSeleccionados.length) {
      this.cargar();
    }
  },
  methods: {
    formatMonto(value) {
      return Number(value || 0).toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    anchoBarra(value) {
      return this.maximoSubgrupo > 0 ? `${Math.max(2, value / this.maximoSubgrupo * 100)}%` : '0%';
    },
    abrirClasificacion(fila, letra) {
      this.detalleClasificacion = {
        subgrupo: fila.nombre,
        letra,
        terminales: fila.terminales_clasificadas?.[letra] ?? [],
      };
      this.busquedaTerminal = '';
      this.modalClasificacion = true;
    },
    async cargar() {
      if (!this.desde || !this.hasta || this.desde > this.hasta) {
        this.error = 'Seleccione un rango de fechas válido.';
        return;
      }
      this.loading = true;
      this.error = '';
      this.modalClasificacion = false;
      try {
        const { data } = await axios.get(route('dashboard.datos'), { params: { desde: this.desde, hasta: this.hasta } });
        this.resumen = data;
        this.actualizado = new Date().toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
      } catch (error) {
        this.error = error.response?.status === 422
          ? Object.values(error.response.data.errors ?? {}).flat()[0] ?? 'Revise el rango de fechas.'
          : 'No se pudo cargar el resumen de ventas. Intente de nuevo en unos minutos.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <Head title="Dashboard de ventas" />
    <main class="sales-dashboard">
      <section class="hero-panel">
        <div>
          <div class="eyebrow"><span class="eyebrow-dot"></span> VISTA GENERAL · VENTAS</div>
          <h1>Un vistazo claro a tus ventas</h1>
          <p>Resumen de las terminales en los subgrupos seleccionados en Mantenimiento.</p>
          <div class="hero-links">
            <Link :href="route('reporte-ventas.index')" class="hero-link">Ver reporte completo <i class="ri-arrow-right-up-line"></i></Link>
            <span v-if="actualizado" class="updated">Actualizado a las {{ actualizado }}</span>
          </div>
        </div>
        <div class="period-card">
          <span class="period-title"><i class="ri-calendar-line"></i> Período de análisis</span>
          <div class="period-fields">
            <label>Desde<input v-model="desde" type="date" :max="hoy" /></label>
            <label>Hasta<input v-model="hasta" type="date" :max="hoy" /></label>
          </div>
          <button type="button" :disabled="loading || !subgruposSeleccionados.length" @click="cargar">
            <i :class="loading ? 'ri-loader-4-line' : 'ri-refresh-line'"></i> {{ loading ? 'Cargando…' : 'Actualizar resumen' }}
          </button>
          <small class="period-hint">Hasta 31 días por consulta.</small>
        </div>
      </section>

      <div v-if="!subgruposSeleccionados.length" class="empty-panel">
        <div class="empty-icon"><i class="ri-price-tag-3-line"></i></div>
        <h2>Selecciona los subgrupos para empezar</h2>
        <p>El dashboard usa los subgrupos que ya marcas en Mantenimiento de terminales.</p>
        <Link :href="route('terminal.index')" class="empty-link">Ir a terminales <i class="ri-arrow-right-line"></i></Link>
      </div>

      <template v-else>
        <div v-if="error" class="alert alert-danger mt-4" role="alert">{{ error }}</div>
        <section class="scope-line" aria-label="Alcance del resumen">
          <span><i class="ri-filter-3-line"></i> {{ subgruposSeleccionados.length }} subgrupos seleccionados</span>
          <span v-if="resumen">{{ resumen.desde }} al {{ resumen.hasta }}</span>
          <span v-if="resumen">{{ resumen.terminales_configuradas }} terminales en catálogo</span>
          <span v-if="resumen && resumen.dias_con_datos < resumen.dias_rango" class="coverage-warning">
            <i class="ri-information-line"></i> Datos en {{ resumen.dias_con_datos }} de {{ resumen.dias_rango }} días
          </span>
        </section>

        <section class="metric-grid" aria-label="Indicadores de ventas">
          <div class="metric-card lavender">
            <span class="metric-icon"><i class="ri-funds-line"></i></span>
            <span class="metric-label">VENTA TOTAL</span>
            <strong>{{ resumen ? formatMonto(resumen.total_venta) : '—' }}</strong>
            <small>Monto acumulado en el período</small>
          </div>
          <div class="metric-card mint">
            <span class="metric-icon"><i class="ri-line-chart-line"></i></span>
            <span class="metric-label">PROMEDIO DIARIO</span>
            <strong>{{ resumen ? formatMonto(resumen.promedio) : '—' }}</strong>
            <small>Por día con ventas registradas</small>
          </div>
          <div class="metric-card peach">
            <span class="metric-icon"><i class="ri-store-2-line"></i></span>
            <span class="metric-label">TERMINALES CON VENTA</span>
            <strong>{{ resumen ? resumen.terminales_con_venta.toLocaleString('es-DO') : '—' }}</strong>
            <small>De {{ resumen?.terminales_configuradas ?? '—' }} seleccionadas</small>
          </div>
          <div class="metric-card sky">
            <span class="metric-icon"><i class="ri-node-tree"></i></span>
            <span class="metric-label">SUBGRUPOS</span>
            <strong>{{ resumen?.subgrupos_configurados ?? subgruposSeleccionados.length }}</strong>
            <small>Configurados en terminales</small>
          </div>
        </section>

        <div v-if="loading && !resumen" class="loading-panel" role="status">Preparando el resumen de ventas…</div>

        <div v-if="resumen" class="detail-grid" :class="{ 'is-refreshing': loading }">
          <section class="detail-card subgroup-card">
            <div class="section-heading">
              <div><span class="section-kicker">DISTRIBUCIÓN</span><h2>Ventas por subgrupo</h2></div>
              <span class="section-note">{{ resumen.subgrupos.length }} seleccionados</span>
            </div>
            <div v-if="!resumen.dias_con_datos" class="no-sales">No hay ventas registradas para este período.</div>
            <div v-for="(fila, index) in resumen.subgrupos" :key="fila.nombre" class="subgroup-row">
              <div class="subgroup-main"><span class="rank">{{ String(index + 1).padStart(2, '0') }}</span><strong>{{ fila.nombre }}</strong><span class="subgroup-amount">{{ formatMonto(fila.total_venta) }}</span></div>
              <div class="bar-track"><span class="bar-fill" :style="{ width: anchoBarra(fila.total_venta) }"></span></div>
              <div class="subgroup-meta"><span>{{ fila.terminales }} terminales con venta</span><span>Promedio diario {{ formatMonto(fila.promedio) }}</span><span>{{ fila.participacion }}% del total</span></div>
            </div>
          </section>

          <section class="detail-card classification-card">
            <div class="section-heading"><div><span class="section-kicker">RENDIMIENTO</span><h2>Clasificación de terminales</h2></div></div>
            <p class="classification-intro">Según el promedio diario de cada terminal en sus días con ventas.</p>
            <div v-for="clase in clasificaciones" :key="clase.letra" class="classification-row" :class="`class-${clase.letra.toLowerCase()}`">
              <span class="class-letter">{{ clase.letra }}</span>
              <div><strong>{{ clase.total }} terminales</strong><small>{{ clase.descripcion }}</small></div>
              <span class="class-share">{{ resumen.terminales_con_venta ? Math.round(clase.total / resumen.terminales_con_venta * 100) : 0 }}%</span>
            </div>
            <div class="classification-footer">Solo se clasifican terminales con ventas en el período.</div>
          </section>
        </div>

        <section v-if="resumen" class="detail-card subgroup-classification" :class="{ 'is-refreshing': loading }">
          <div class="section-heading">
            <div><span class="section-kicker">RENDIMIENTO POR SUBGRUPO</span><h2>Clasificación de terminales por subgrupo</h2></div>
          </div>
          <p class="classification-intro">Cantidad de terminales A, B, C y D en cada subgrupo seleccionado, según su promedio diario con ventas.</p>
          <div v-for="fila in resumen.subgrupos" :key="fila.nombre" class="subgroup-class-row">
            <div class="subgroup-class-name"><strong>{{ fila.nombre }}</strong><small>{{ fila.terminales }} terminales con venta</small></div>
            <div class="subgroup-class-counts">
              <button v-for="clase in clasificaciones" :key="clase.letra" type="button" class="subgroup-class-count" :class="`class-${clase.letra.toLowerCase()}`" :title="`Ver terminales clase ${clase.letra}: ${clase.descripcion}`" :aria-label="`Ver ${fila.clasificaciones[clase.letra]} terminales clase ${clase.letra} de ${fila.nombre}`" @click="abrirClasificacion(fila, clase.letra)">
                <span class="class-letter">{{ clase.letra }}</span>
                <strong>{{ fila.clasificaciones[clase.letra] }}</strong>
              </button>
            </div>
          </div>
          <div class="classification-footer">Solo se clasifican terminales con ventas en el período.</div>
        </section>
      </template>
      <BModal v-model="modalClasificacion" :title="detalleClasificacion ? `${detalleClasificacion.subgrupo} · Clase ${detalleClasificacion.letra}` : 'Terminales clasificadas'" size="lg" scrollable hide-footer>
        <template v-if="detalleClasificacion">
          <p class="modal-summary">{{ detalleClasificacion.terminales.length }} terminales con ventas en el período seleccionado.</p>
          <label class="modal-search-label" for="buscar-terminal-clasificada">Buscar terminal o nombre</label>
          <input id="buscar-terminal-clasificada" v-model="busquedaTerminal" type="search" class="form-control modal-search" placeholder="Escribe un código o nombre" />
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Terminal</th><th>Nombre</th></tr></thead>
              <tbody>
                <tr v-for="item in terminalesFiltradas" :key="item.terminal"><td>{{ item.terminal }}</td><td>{{ item.nombre || 'Sin nombre' }}</td></tr>
                <tr v-if="!terminalesFiltradas.length"><td colspan="2" class="text-muted text-center py-4">{{ busquedaTerminal ? 'No hay terminales que coincidan con la búsqueda.' : 'No hay terminales en esta clase.' }}</td></tr>
              </tbody>
            </table>
          </div>
        </template>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.sales-dashboard { max-width: 1540px; margin: 0 auto; padding: 8px 4px 42px; color: #25334b; }
.hero-panel { display: flex; align-items: center; justify-content: space-between; gap: 28px; padding: 35px 40px; border: 1px solid #e5e7f5; border-radius: 24px; background: linear-gradient(115deg, #f1efff 0%, #eff7ff 58%, #eaf8f3 100%); box-shadow: 0 16px 44px rgba(71, 83, 123, .07); }
.eyebrow { display: flex; align-items: center; gap: 9px; color: #7165ab; font-size: 11px; font-weight: 800; letter-spacing: .15em; }
.eyebrow-dot { width: 8px; height: 8px; border-radius: 50%; background: #a899e9; box-shadow: 0 0 0 4px #e3ddff; }
h1 { margin: 15px 0 9px; font-size: clamp(26px, 2.5vw, 38px); font-weight: 750; letter-spacing: -.035em; color: #26334e; }
.hero-panel p { margin: 0; color: #718098; font-size: 14px; }
.hero-links { display: flex; align-items: center; flex-wrap: wrap; gap: 18px; margin-top: 24px; }
.hero-link { font-weight: 700; color: #6556ab; text-decoration: none; }
.hero-link:hover { color: #47378d; }
.updated { color: #8793a8; font-size: 12px; }
.period-card { flex: 0 0 350px; padding: 19px; border: 1px solid rgba(255, 255, 255, .9); border-radius: 18px; background: rgba(255, 255, 255, .83); box-shadow: 0 10px 24px rgba(71, 83, 123, .07); }
.period-title { display: block; margin-bottom: 13px; font-size: 12px; font-weight: 700; color: #53637c; }
.period-title i { margin-right: 5px; color: #8f82ca; }
.period-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
.period-fields label { color: #8190a6; font-size: 11px; font-weight: 700; }
.period-fields input { display: block; width: 100%; margin-top: 4px; padding: 9px 8px; border: 1px solid #dfe4f0; border-radius: 9px; background: #fff; color: #34435c; font-size: 12px; }
.period-card button { width: 100%; margin-top: 12px; padding: 10px; border: 0; border-radius: 10px; background: #796ab9; color: #fff; font-size: 12px; font-weight: 700; }
.period-card button:hover:not(:disabled) { background: #6656aa; }
.period-card button:disabled { opacity: .65; cursor: wait; }
.period-hint { display: block; margin-top: 8px; color: #98a4b5; text-align: center; font-size: 10px; }
.scope-line { display: flex; flex-wrap: wrap; align-items: center; gap: 13px; margin: 24px 0 16px; color: #7b879b; font-size: 12px; }
.scope-line span { padding: 7px 11px; border: 1px solid #e7ebf1; border-radius: 999px; background: #fff; }
.scope-line i { margin-right: 4px; color: #8c7dc5; }
.scope-line .coverage-warning { color: #95652c; background: #fff8e9; border-color: #f4e8cb; }
.metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.metric-card { position: relative; display: flex; flex-direction: column; min-height: 174px; padding: 23px; border: 1px solid rgba(255,255,255,.8); border-radius: 19px; overflow: hidden; }
.metric-card::after { content: ''; position: absolute; width: 130px; height: 130px; top: -57px; right: -39px; border-radius: 50%; background: rgba(255,255,255,.28); }
.metric-card.lavender { background: #eeeaff; } .metric-card.mint { background: #e6f6ef; } .metric-card.peach { background: #fff0e7; } .metric-card.sky { background: #e7f2ff; }
.metric-icon { display: grid; place-items: center; width: 34px; height: 34px; margin-bottom: 17px; border-radius: 10px; background: rgba(255,255,255,.65); color: #6d60a9; font-size: 18px; }
.mint .metric-icon { color: #459477; } .peach .metric-icon { color: #c7865f; } .sky .metric-icon { color: #5f8dba; }
.metric-label { color: #748097; font-size: 10px; font-weight: 800; letter-spacing: .11em; }
.metric-card strong { margin: 6px 0 3px; color: #27354d; font-size: clamp(20px, 2vw, 29px); line-height: 1.2; letter-spacing: -.03em; }
.metric-card small { margin-top: auto; color: #8490a2; font-size: 11px; }
.detail-grid { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr); gap: 18px; margin-top: 19px; transition: opacity .2s; }
.detail-grid.is-refreshing { opacity: .55; }
.subgroup-classification { margin-top: 18px; transition: opacity .2s; }
.subgroup-classification.is-refreshing { opacity: .55; }
.subgroup-class-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 15px 0; border-top: 1px solid #f0f2f6; }
.subgroup-class-name { min-width: 0; }
.subgroup-class-name strong { display: block; color: #35435a; font-size: 13px; }
.subgroup-class-name small { display: block; margin-top: 4px; color: #9aa5b5; font-size: 11px; }
.subgroup-class-counts { display: grid; grid-template-columns: repeat(4, minmax(70px, 1fr)); gap: 8px; width: min(100%, 420px); }
.subgroup-class-count { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border: 1px solid transparent; border-radius: 11px; background: #f8f7fc; text-align: left; cursor: pointer; }
.subgroup-class-count:hover, .subgroup-class-count:focus-visible { border-color: #b9abd9; box-shadow: 0 0 0 3px #f0ebfa; }
.subgroup-class-count .class-letter { width: 27px; height: 27px; flex-basis: 27px; border-radius: 8px; font-size: 12px; }
.subgroup-class-count strong { color: #3b4860; font-size: 13px; }
.modal-summary { color: #718098; font-size: 13px; }
.modal-search-label { display: block; margin-bottom: 5px; color: #53637c; font-size: 12px; font-weight: 700; }
.modal-search { margin-bottom: 16px; }
.detail-card { padding: 26px; border: 1px solid #e9edf3; border-radius: 19px; background: #fff; box-shadow: 0 8px 28px rgba(40, 63, 96, .035); }
.section-heading { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; margin-bottom: 22px; }
.section-kicker { color: #a497cc; font-size: 10px; font-weight: 800; letter-spacing: .14em; }
h2 { margin: 4px 0 0; font-size: 19px; font-weight: 750; color: #2b3a52; }
.section-note { color: #97a3b4; font-size: 12px; }
.subgroup-row { padding: 15px 0; border-top: 1px solid #f0f2f6; }
.subgroup-main { display: flex; align-items: center; gap: 12px; }
.subgroup-main strong { min-width: 0; flex: 1; color: #35435a; font-size: 13px; }
.rank { color: #b2bbcb; font-size: 11px; font-weight: 800; }
.subgroup-amount { color: #35435a; font-size: 14px; font-weight: 750; white-space: nowrap; }
.bar-track { height: 7px; margin: 12px 0 10px 26px; border-radius: 9px; background: #f0f2f8; overflow: hidden; }
.bar-fill { display: block; height: 100%; border-radius: 9px; background: linear-gradient(90deg, #b4a8ee, #8abce6); }
.subgroup-meta { display: flex; flex-wrap: wrap; gap: 7px 17px; margin-left: 26px; color: #9aa5b5; font-size: 11px; }
.classification-intro { margin: -8px 0 21px; color: #8c98a9; font-size: 12px; }
.classification-row { display: flex; align-items: center; gap: 13px; margin: 10px 0; padding: 12px; border-radius: 12px; background: #f8f7fc; }
.class-letter { display: grid; place-items: center; width: 37px; height: 37px; flex: 0 0 37px; border-radius: 10px; background: #e1dafa; color: #7965b2; font-weight: 800; }
.classification-row > div { flex: 1; } .classification-row strong { display: block; color: #3b4860; font-size: 13px; } .classification-row small { display: block; margin-top: 2px; color: #9ca7b7; font-size: 11px; }
.class-share { color: #6d788e; font-size: 13px; font-weight: 750; }
.class-b { background: #f1faf6; } .class-b .class-letter { background: #d8f0e4; color: #538f72; }
.class-c { background: #fff9f1; } .class-c .class-letter { background: #fbe9d3; color: #b88a52; }
.class-d { background: #fff5f4; } .class-d .class-letter { background: #fae2e0; color: #ba7773; }
.classification-footer { margin-top: 18px; color: #a1aab9; font-size: 11px; }
.loading-panel, .empty-panel { margin-top: 22px; padding: 42px; border: 1px solid #e8ebf2; border-radius: 18px; background: #fff; text-align: center; color: #7d899d; }
.empty-icon { display: grid; place-items: center; width: 54px; height: 54px; margin: 0 auto 16px; border-radius: 15px; background: #eeeaff; color: #8372be; font-size: 25px; }
.empty-panel p { color: #8c98ab; } .empty-link { display: inline-block; margin-top: 7px; color: #7463b1; font-weight: 700; }
.no-sales { padding: 16px 0; color: #8c98a9; font-size: 13px; }
@media (max-width: 1100px) { .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .detail-grid { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .hero-panel { flex-direction: column; align-items: stretch; padding: 25px; } .period-card { flex-basis: auto; } .metric-grid { grid-template-columns: 1fr; } .metric-card { min-height: 145px; } .detail-card { padding: 20px; } .sales-dashboard { padding: 4px 0 28px; } }
@media (max-width: 560px) { .subgroup-class-row { align-items: stretch; flex-direction: column; gap: 10px; } .subgroup-class-counts { width: 100%; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 5px; } .subgroup-class-count { justify-content: center; gap: 5px; padding: 6px 4px; } }
</style>
