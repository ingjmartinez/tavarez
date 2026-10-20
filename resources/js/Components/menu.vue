<script>
import { Link, router } from '@inertiajs/vue3';
import { layoutComputed } from "@/state/helpers";
import simplebar from "simplebar-vue";

export default {
  components: {
    simplebar,
    Link
  },
  data() {
    return {
      settings: {
        minScrollbarLength: 60,
      },
    };
  },
  computed: {
    ...layoutComputed,
    layoutType: {
      get() {
        return this.$store ? this.$store.state.layout.layoutType : {} || {};
      },
    },
  },

  watch: {
    $route: {
      handler: "onRoutechange",
      immediate: true,
      deep: true,
    },
  },

  mounted() {
    this.initActiveMenu();
    if (this.rmenu == 'vertical' && this.layoutType == 'twocolumn') {
      document.documentElement.setAttribute("data-layout", "vertical");
    }
    document.getElementById('overlay').addEventListener('click', () => {
      document.body.classList.remove('vertical-sidebar-enable');
    });
    window.addEventListener("resize", () => {
      if (this.layoutType == 'twocolumn') {
        var windowSize = document.documentElement.clientWidth;
        if (windowSize < 767) {
          document.documentElement.setAttribute("data-layout", "vertical");
          this.rmenu = 'vertical';
          localStorage.setItem('rmenu', 'vertical');
        } else {
          document.documentElement.setAttribute("data-layout", "vertical");
          this.rmenu = 'twocolumn';
          localStorage.setItem('rmenu', 'twocolumn');
          setTimeout(() => {
            this.initActiveMenu();
    this.onRoutechange();
          }, 50);
        }
      }
    });
    this.onRoutechange();
    if (document.querySelectorAll(".navbar-nav .collapse")) {
      let collapses = document.querySelectorAll(".navbar-nav .collapse");

      collapses.forEach((collapse) => {
        // Hide sibling collapses on `show.bs.collapse`
        collapse.addEventListener("show.bs.collapse", (e) => {
          e.stopPropagation();
          let closestCollapse = collapse.parentElement.closest(".collapse");
          if (closestCollapse) {
            let siblingCollapses =
              closestCollapse.querySelectorAll(".collapse");
            siblingCollapses.forEach((siblingCollapse) => {
              if (siblingCollapse.classList.contains("show")) {
                siblingCollapse.classList.remove("show");
                siblingCollapse.parentElement.firstChild.setAttribute("aria-expanded", "false");
              }
            });
          } else {
            let getSiblings = (elem) => {
              // Setup siblings array and get the first sibling
              let siblings = [];
              let sibling = elem.parentNode.firstChild;
              // Loop through each sibling and push to the array
              while (sibling) {
                if (sibling.nodeType === 1 && sibling !== elem) {
                  siblings.push(sibling);
                }
                sibling = sibling.nextSibling;
              }
              return siblings;
            };
            let siblings = getSiblings(collapse.parentElement);
            siblings.forEach((item) => {
              if (item.childNodes.length > 2) {
                item.firstElementChild.setAttribute("aria-expanded", "false");
                item.firstElementChild.classList.remove("active");
              }
              let ids = item.querySelectorAll("*[id]");
              ids.forEach((item1) => {
                item1.classList.remove("show");
                item1.parentElement.firstChild.setAttribute("aria-expanded", "false");
                item1.parentElement.firstChild.classList.remove("active");
                if (item1.childNodes.length > 2) {
                  let val = item1.querySelectorAll("ul li a");

                  val.forEach((subitem) => {
                    if (subitem.hasAttribute("aria-expanded"))
                      subitem.setAttribute("aria-expanded", "false");
                  });
                }
              });
            });
          }
        });

        // Hide nested collapses on `hide.bs.collapse`
        collapse.addEventListener("hide.bs.collapse", (e) => {
          e.stopPropagation();
          let childCollapses = collapse.querySelectorAll(".collapse");
          childCollapses.forEach((childCollapse) => {
            let childCollapseInstance = childCollapse;
            childCollapseInstance.classList.remove("show");
            childCollapseInstance.parentElement.firstChild.setAttribute("aria-expanded", "false");
          });
        });
      });
    }
  },

  methods: {
    onRoutechange() {
      // this.initActiveMenu();
      setTimeout(() => {
        var currentPath = window.location.pathname;
        if (document.querySelector("#navbar-nav")) {
          let currentPosition = document.querySelector("#navbar-nav").querySelector('[href="' + currentPath + '"]')?.offsetTop;
          if (currentPosition > document.documentElement.clientHeight) {
            document.querySelector("#scrollbar .simplebar-content-wrapper") ? document.querySelector("#scrollbar .simplebar-content-wrapper").scrollTop = currentPosition + 300 : '';
          }
        }
      }, 500);
    },

    initActiveMenu() {
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
                const grandparent = parentCollapseDiv.parentElement.closest(".collapse").previousElementSibling.parentElement.closest(".collapse");
                if (grandparent && grandparent && grandparent.previousElementSibling) {
                  grandparent.previousElementSibling.classList.add("active");
                  grandparent.classList.add("show");
                }
              }
            }
          }
        }
      }, 0);
    },
  },
};
</script>

<template>
  <BContainer fluid>
    <div id="two-column-menu"></div>

    <template v-if="layoutType === 'vertical' || layoutType === 'semibox'">
      <ul class="navbar-nav h-100" id="navbar-nav">
        <li class="menu-title">
          <span data-key="t-menu"> {{ $t("t-menu") }}</span>
        </li>

        <li class="nav-item">
          <Link :href="route('dashboard')" class="nav-link menu-link">
            <i class="ri-dashboard-line"></i>
            <span>Inicio</span>
          </Link>
        </li>


          <li class="nav-item">
          <a class="nav-link menu-link" href="#sidebarContabilidad" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarContabilidad">
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
          <a class="nav-link menu-link" href="#sidebarMantenimiento" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarMantenimiento">
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
          <a class="nav-link menu-link" href="#sidebarReporte" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarReporte">
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
          <a class="nav-link menu-link" href="#sidebarApi" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarApi">
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
  </template>
</BContainer></template>
