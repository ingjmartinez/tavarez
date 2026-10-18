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
        <!--
          Secciones de la plantilla ocultas (no se renderizan ni se cargan):
          Dashboard, Apps y Autenticacion. El codigo se conserva por si se necesita
          de nuevo: quitar este comentario para reactivarlas.

        <li class="nav-item">
          <a class="nav-link menu-link" href="#sidebarDashboards" data-bs-toggle="collapse" role="button"
            aria-expanded="false" aria-controls="sidebarDashboards">
            <i class="ri-dashboard-2-line"></i>
            <span data-key="t-dashboards"> {{ $t("t-dashboards") }}</span>
          </a>
          <div class="collapse menu-dropdown" id="sidebarDashboards">
            <ul class="nav nav-sm flex-column">
              <li class="nav-item">
                <Link href="/dashboard/analytics" class="nav-link custom-abc" data-key="t-analytics">
                {{ $t("t-analytics") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/crm" class="nav-link" data-key="t-crm">
                {{ $t("t-crm") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/" class="nav-link" data-key="t-ecommerce">
                {{ $t("t-ecommerce") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/crypto" class="nav-link" data-key="t-crypto">
                {{ $t("t-crypto") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/projects" class="nav-link" data-key="t-projects">
                {{ $t("t-projects") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/nft" class="nav-link" data-key="t-nft">
                {{ $t("t-nft") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/job" class="nav-link" data-key="t-job">
                {{ $t("t-job") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/dashboard/blog" class="nav-link" data-key="t-blog">
                  {{ $t("t-blog") }}
                  <span class="badge badge-pill bg-success">{{ $t('t-new')}}</span>
                </Link>
              </li>
            </ul>
          </div>
        </li>
        <li class="nav-item">
          <a class="nav-link menu-link" href="#sidebarApps" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarApps">
            <i class="ri-apps-2-line"></i>
            <span data-key="t-apps"> {{ $t("t-apps") }}</span>
          </a>
          <div class="collapse menu-dropdown" id="sidebarApps">
            <ul class="nav nav-sm flex-column">
              <li class="nav-item">
                <a class="nav-link menu-link" href="#sidebarCalendar" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarCalendar" data-key="t-calendar">
                  {{ $t("t-calendar") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarCalendar">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/calendar" class="nav-link" data-key="t-calendar"> {{
                        $t("t-main-calender") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/month-grid" class="nav-link" data-key="t-month-grid"> {{ $t("t-month-grid") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <Link href="/chat" class="nav-link" data-key="t-chat">
                {{ $t("t-chat") }}
                </Link>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#sidebaremail" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebaremail" data-key="t-projects">
                  {{ $t("t-email") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebaremail">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/mailbox" class="nav-link" data-key="t-mailbox">
                      {{ $t("t-mailbox") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" href="#sidebarsubemail" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarsubemail" data-key="t-projects">
                        {{ $t("t-email-templates") }}
                      </a>
                      <div class="collapse menu-dropdown" id="sidebarsubemail">
                        <ul class="nav nav-sm flex-column">
                          <li class="nav-item">
                            <Link href="/email/email-basic" class="nav-link" data-key="t-products">
                            {{ $t("t-basic-action") }}
                            </Link>
                          </li>
                          <li class="nav-item">
                            <Link href="/email/email-ecommerce" class="nav-link" data-key="t-products">
                            {{ $t("t-ecommerce-action") }}
                            </Link>
                          </li>
                        </ul>
                      </div>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarEcommerce" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarEcommerce" data-key="t-ecommerce">
                  {{ $t("t-ecommerce") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarEcommerce">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/ecommerce/products" class="nav-link" data-key="t-products">
                      {{ $t("t-products") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/product-details" class="nav-link" data-key="t-product-Details">
                      {{ $t("t-product-Details") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/add-product" class="nav-link" data-key="t-create-product">
                      {{ $t("t-create-product") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/orders" class="nav-link" data-key="t-orders">
                      {{ $t("t-orders") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/order-details" class="nav-link" data-key="t-order-details">
                      {{ $t("t-order-details") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/customers" class="nav-link" data-key="t-customers">
                      {{ $t("t-customers") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/shopping-cart" class="nav-link" data-key="t-shopping-cart">
                      {{ $t("t-shopping-cart") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/checkout" class="nav-link" data-key="t-checkout">
                      {{ $t("t-checkout") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/sellers" class="nav-link" data-key="t-sellers">
                      {{ $t("t-sellers") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/ecommerce/seller-details" class="nav-link" data-key="t-sellers-details">
                      {{ $t("t-sellers-details") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarProjects" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarProjects" data-key="t-projects">
                  {{ $t("t-projects") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarProjects">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/apps/projects-list" class="nav-link" data-key="t-list">
                      {{ $t("t-list") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/projects-overview" class="nav-link" data-key="t-overview">
                      {{ $t("t-overview") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/projects-create" class="nav-link" data-key="t-create-project">
                      {{ $t("t-create-project") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarTasks" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarTasks" data-key="t-tasks">
                  {{ $t("t-tasks") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarTasks">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/apps/tasks-kanban" class="nav-link" data-key="t-kanbanboard">
                      {{ $t("t-kanbanboard") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/tasks-list-view" class="nav-link" data-key="t-list-view">
                      {{ $t("t-list-view") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/tasks-details" class="nav-link" data-key="t-task-details">
                      {{ $t("t-task-details") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarCRM" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarCRM" data-key="t-crm">
                  {{ $t("t-crm") }}

                </a>
                <div class="collapse menu-dropdown" id="sidebarCRM">

                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/apps/crm-contacts" class="nav-link" data-key="t-contacts">
                      {{ $t("t-contacts") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/crm-companies" class="nav-link" data-key="t-companies">
                      {{ $t("t-companies") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/crm-deals" class="nav-link" data-key="t-deals">
                      {{ $t("t-deals") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/crm-leads" class="nav-link" data-key="t-leads">
                      {{ $t("t-leads") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarCrypto" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarCrypto" data-key="t-crypto">
                  {{ $t("t-crypto") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarCrypto">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/crypto/transactions" class="nav-link" data-key="t-transactions">
                      {{ $t("t-transactions") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/crypto/buy-sell" class="nav-link" data-key="t-buy-sell">
                      {{ $t("t-buy-sell") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/crypto/orders" class="nav-link" data-key="t-orders">
                      {{ $t("t-orders") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/crypto/wallet" class="nav-link" data-key="t-my-wallet">
                      {{ $t("t-my-wallet") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/crypto/ico" class="nav-link" data-key="t-ico-list">
                      {{ $t("t-ico-list") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/crypto/kyc" class="nav-link" data-key="t-kyc-application">
                      {{ $t("t-kyc-application") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarInvoices" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarInvoices" data-key="t-invoices">
                  {{ $t("t-invoices") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarInvoices">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/invoices/list" class="nav-link" data-key="t-list-view">
                      {{ $t("t-list-view") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/invoices/detail" class="nav-link" data-key="t-details">
                      {{ $t("t-details") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/invoices/create" class="nav-link" data-key="t-create-invoice">
                      {{ $t("t-create-invoice") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarTickets" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarTickets" data-key="t-supprt-tickets">
                  {{ $t("t-supprt-tickets") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarTickets">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/apps/tickets-list" class="nav-link" data-key="t-list-view">
                      {{ $t("t-list-view") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/tickets-details" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-ticket-details") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#nftmarketplace" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="nftmarketplace" data-key="t-supprt-tickets">
                  {{ $t("t-nft-marketplace") }}
                </a>
                <div class="collapse menu-dropdown" id="nftmarketplace">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/apps/nft-marketplace" class="nav-link" data-key="t-list-view">
                      {{ $t("t-marketplace") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-explore" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-explore-now") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-auction" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-live-auction") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-item-detail" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-item-details") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-collection" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-collections") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-creators" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-creators") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-ranking" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-ranking") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-wallet" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-wallet-connect") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/apps/nft-create" class="nav-link" data-key="t-ticket-details">
                      {{ $t("t-create-nft") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <Link href="/apps-file-manager" class="nav-link" data-key="t-file-manager">
                {{ $t("t-file-manager") }}
                </Link>
              </li>
              <li class="nav-item">
                <Link href="/apps-todo" class="nav-link" data-key="t-to-do">
                {{ $t("t-to-do") }}
                </Link>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#jobs" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="jobs" data-key="t-jobs">
                  {{ $t("t-jobs") }}
                </a>
                <div class="collapse menu-dropdown" id="jobs">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/jobs/statistics" class="nav-link" data-key="t-statistics">
                      {{ $t("t-statistics") }}
                      </Link>
                    </li>

                    <li class="nav-item">
                      <a class="nav-link" href="#sidebarjoblist" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarjoblist" data-key="t-projects">
                        {{ $t("t-job-lists") }}
                      </a>
                      <div class="collapse menu-dropdown" id="sidebarjoblist">
                        <ul class="nav nav-sm flex-column">
                          <li class="nav-item">
                            <Link href="/jobs/lists" class="nav-link" data-key="t-lists">
                            {{ $t("t-lists") }}
                            </Link>
                          </li>
                          <li class="nav-item">
                            <Link href="/jobs/grid-lists" class="nav-link" data-key="t-grid">
                            {{ $t("t-grid") }}
                            </Link>
                          </li>
                          <li class="nav-item">
                            <Link href="/jobs/details" class="nav-link" data-key="t-overview">
                            {{ $t("t-overview") }}
                            </Link>
                          </li>
                        </ul>
                      </div>
                    </li>

                    <li class="nav-item">
                      <a class="nav-link" href="#sidebarcandidatelists" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarcandidatelists" data-key="t-projects">
                        {{ $t("t-candidate-lists") }}
                      </a>
                      <div class="collapse menu-dropdown" id="sidebarcandidatelists">
                        <ul class="nav nav-sm flex-column">
                          <li class="nav-item">
                            <Link href="/jobs/candidate-lists" class="nav-link" data-key="t-lists">
                            {{ $t("t-list-view") }}
                            </Link>
                          </li>
                          <li class="nav-item">
                            <Link href="/jobs/candidate-grid" class="nav-link" data-key="t-grid">
                            {{ $t("t-grid-view") }}
                            </Link>
                          </li>
                        </ul>
                      </div>
                    </li>

                    <li class="nav-item">
                      <Link href="/jobs/application" class="nav-link" data-key="t-application">
                      {{ $t("t-application") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/jobs/new" class="nav-link" data-key="t-new">
                      {{ $t("t-new-job") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/jobs/companies-list" class="nav-link" data-key="t-companies-list">
                      {{ $t("t-companies-list") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/jobs/categories" class="nav-link" data-key="t-job-categories">
                      {{ $t("t-job-categories") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <Link href="/apps-api-key" class="nav-link" data-key="t-api-key">
                {{ $t("t-api-key") }}
                </Link>
              </li>
            </ul>
          </div>
        </li>


        <li class="nav-item">
          <a class="nav-link menu-link" href="#sidebarAuth" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="sidebarAuth">
            <i class="ri-account-circle-line"></i>
            <span data-key="t-authentication">{{
              $t("t-authentication")
            }}</span>
          </a>
          <div class="collapse menu-dropdown" id="sidebarAuth">
            <ul class="nav nav-sm flex-column">
              <li class="nav-item">
                <a class="nav-link" href="#sidebarSignIn" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarSignIn" data-key="t-signin">{{ $t("t-signin") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarSignIn">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/signin-basic" class="nav-link" data-key="t-basic">{{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/signin-cover" class="nav-link" data-key="t-cover">{{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarSignUp" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarSignUp" data-key="t-signup">{{ $t("t-signup") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarSignUp">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/signup-basic" class="nav-link" data-key="t-basic">{{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/signup-cover" class="nav-link" data-key="t-cover">{{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#sidebarResetPass" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarResetPass" data-key="t-password-reset">
                  {{ $t("t-password-reset") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarResetPass">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/reset-pwd-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/reset-pwd-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#sidebarcreatepassword" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarcreatepassword" data-key="t-lock-screen">
                  {{ $t("t-password-create") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarcreatepassword">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/create-pwd-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/create-pwd-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarLockScreen" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarLockScreen" data-key="t-lock-screen">
                  {{ $t("t-lock-screen") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarLockScreen">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/lockscreen-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/lockscreen-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#sidebarLogout" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarLogout" data-key="t-logout">
                  {{ $t("t-logout") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarLogout">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/logout-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/logout-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarSuccessMsg" data-bs-toggle="collapse" role="button"
                  aria-expanded="false" aria-controls="sidebarSuccessMsg" data-key="t-success-message">
                  {{ $t("t-success-message") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarSuccessMsg">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/success-msg-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/success-msg-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarTwoStep" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarTwoStep" data-key="t-two-step-verification">
                  {{ $t("t-two-step-verification") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarTwoStep">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/twostep-basic" class="nav-link" data-key="t-basic">
                      {{ $t("t-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/twostep-cover" class="nav-link" data-key="t-cover">
                      {{ $t("t-cover") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#sidebarErrors" data-bs-toggle="collapse" role="button" aria-expanded="false"
                  aria-controls="sidebarErrors" data-key="t-errors">
                  {{ $t("t-errors") }}
                </a>
                <div class="collapse menu-dropdown" id="sidebarErrors">
                  <ul class="nav nav-sm flex-column">
                    <li class="nav-item">
                      <Link href="/auth/404-basic" class="nav-link" data-key="t-404-basic">
                      {{ $t("t-404-basic") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/404-cover" class="nav-link" data-key="t-404-cover">
                      {{ $t("t-404-cover") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/404" class="nav-link" data-key="t-404-alt">
                      {{ $t("t-404-alt") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/500" class="nav-link" data-key="t-500">
                      {{ $t("t-500") }}
                      </Link>
                    </li>
                    <li class="nav-item">
                      <Link href="/auth/ofline" class="nav-link" data-key="t-offline-page">
                      {{ $t("t-offline-page") }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
          </div>
        </li>
        -->

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
