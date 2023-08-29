import Vue from "vue";
import {library} from '@fortawesome/fontawesome-svg-core'
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome'
import {faSquare} from '@fortawesome/free-regular-svg-icons'
import {
    faArrowRight,
    faCheck,
    faCheckSquare,
    faChevronDown,
    faChevronLeft,
    faChevronRight,
    faDownload,
    faGear,
    faKey,
    faPlus,
    faPuzzlePiece,
    faQuestionCircle,
    faShieldHalved,
    faSignIn,
    faSignOut,
    faTimesCircle,
    faTrash,
} from '@fortawesome/free-solid-svg-icons'

library.add(faSquare, faCheckSquare, faGear, faDownload, faSignOut, faSignIn, faShieldHalved, faPuzzlePiece, faKey, faTimesCircle, faCheck,
    faChevronLeft, faChevronRight, faChevronDown, faArrowRight, faQuestionCircle, faPlus, faTrash);

Vue.component('fa', FontAwesomeIcon)
