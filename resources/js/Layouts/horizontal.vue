<script>
import { Link } from '@inertiajs/vue3';
import NavBar from "@/Components/nav-bar.vue";
import RightBar from "@/Components/right-bar.vue";
import Footer from "@/Components/footer.vue";

export default {
  mounted() {
    this.initActiveMenu();
  },
  methods: {
    initActiveMenu(ele) {
      setTimeout(() => {
        var currentPath = window.location.pathname;
        if (document.querySelector("#navbar-nav")) {
          let a = document.querySelector("#navbar-nav").querySelector('[href="' + currentPath + '"]');

          if (a) {
            a.classList.add("active");
            let parentCollapseDiv = a.closest(".collapse.menu-dropdown");
            if (parentCollapseDiv) {
              parentCollapseDiv.classList.add("show");
              parentCollapseDiv.parentElement.children[0].classList.add("active");
              parentCollapseDiv.parentElement.children[0].setAttribute("aria-expanded", "true");
              if (parentCollapseDiv.parentElement.closest(".collapse.menu-dropdown")) {
                parentCollapseDiv.parentElement.closest(".collapse").classList.add("show");
                if (parentCollapseDiv.parentElement.closest(".collapse").previousElementSibling)
                  parentCollapseDiv.parentElement.closest(".collapse").previousElementSibling.classList.add("active");
              }
            }
          }
        }
      }, 1000);
    },
  },
  components: {
    NavBar,
    RightBar,
    Footer,
    Link
  },
};
</script>

<template>
  <div>
    <div id="layout-wrapper">
      <NavBar />
      <!-- ========== App Menu ========== -->
      <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box">
          <!-- Dark Logo-->
          <Link href="/" class="logo logo-dark">
          <span class="logo-sm">
            <img src="@assets/images/logo-sm.png" alt="" height="22" />
          </span>
          <span class="logo-lg">
            <span class="fs-3 fw-bold text-dark">BI</span>
          </span>
          </Link>
          <!-- Light Logo-->
          <Link href="/" class="logo logo-light">
          <span class="logo-sm">
            <img src="@assets/images/logo-sm.png" alt="" height="22" />
          </span>
          <span class="logo-lg">
            <span class="fs-3 fw-bold text-white">BI</span>
          </span>
          </Link>
          <BButton size="sm" class="p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
          </BButton>
        </div>
        <div id="scrollbar">
          <BContainer fluid>
            <ul class="navbar-nav h-100" id="navbar-nav">
              <li class="menu-title">
                <span data-key="t-menu"> {{ $t("t-menu") }}</span>
              </li>


              <li class="nav-item">
                <a class="nav-link menu-link" href="#sidebarContabilidad" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarContabilidad">
                  <i class="ri-calculator-line"></i>
                  <span>Contabilidad</span>
                </a>
                <div class="collapse menu-dropdown" id="sidebarContabilidad">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/contabilidad-centro-costo" class="nav-link">
                      Centro de costo
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link menu-link" href="#sidebarMantenimiento" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarMantenimiento">
                  <i class="ri-tools-line"></i>
                  <span>Mantenimiento</span>
                </a>
                <div class="collapse menu-dropdown" id="sidebarMantenimiento">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/mantenimiento-terminal" class="nav-link">
                      Terminal
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link menu-link" href="#sidebarReporte" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarReporte">
                  <i class="ri-file-chart-line"></i>
                  <span>Reporte</span>
                </a>
                <div class="collapse menu-dropdown" id="sidebarReporte">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/reporte-ventas" class="nav-link">
                      Ventas
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/reporte-subgrupos" class="nav-link">Ventas por subgrupo</Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/reporte-periodo" class="nav-link">Ventas por período</Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link menu-link" href="#sidebarApi" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarApi">
                  <span>API</span>
                </a>
                <div class="collapse menu-dropdown" id="sidebarApi">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/api-procesar-todo" class="nav-link">
                      Procesar todo
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/api-ventas-usuario" class="nav-link">
                      Ventas por usuario
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/api-pagos-misma-empresa" class="nav-link">
                      Pagos misma empresa
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/api-pagos-otra-empresa" class="nav-link">
                      Pagos a otra empresa
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/api-ventas-raza" class="nav-link">
                      Ventas raza
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
          </BContainer>
          <!-- Sidebar -->
        </div>
        <!-- Left Sidebar End -->
        <!-- Vertical Overlay-->
        <div class="vertical-overlay"></div>
      </div>
      <!-- ============================================================== -->
      <!-- Start Page Content here -->
      <!-- ============================================================== -->

      <div class="main-content">
        <div class="page-content">
          <!-- Start Content-->
          <BContainer fluid>
            <slot />
          </BContainer>
        </div>
        <Footer />
      </div>
      <RightBar />
    </div>
  </div>
</template>
