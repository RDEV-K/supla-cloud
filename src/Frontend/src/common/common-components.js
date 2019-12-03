import Vue from "vue";
import EmptyListPlaceholder from "./gui/empty-list-placeholder";
import Modal from "./modal";
import ModalConfirm from "./modal-confirm";
import Navbar from "../home/navbar";
import LoadingCover from "./gui/loaders/loading-cover";
import PageFooter from "./gui/page-footer";
import Flipper from "./tiles/flipper";
import SquareLink from "./tiles/square-link";
import SquareLinksGrid from "./tiles/square-links-grid";
import Toggler from "./gui/toggler";
import ButtonLoadingDots from "./gui/loaders/button-loading-dots.vue";

Vue.component('cookieWarning', () => import("./errors/cookie-warning"));
Vue.component('maintenanceWarning', () => import("./errors/maintenance-warning"));
Vue.component('pageFooter', PageFooter);
Vue.component('emptyListPlaceholder', EmptyListPlaceholder);
Vue.component('modal', Modal);
Vue.component('modalConfirm', ModalConfirm);
Vue.component('navbar', Navbar);
Vue.component('loadingCover', LoadingCover);
Vue.component('flipper', Flipper);
Vue.component('squareLink', SquareLink);
Vue.component('squareLinksGrid', SquareLinksGrid);
Vue.component('toggler', Toggler);
Vue.component('buttonLoadingDots', ButtonLoadingDots);
