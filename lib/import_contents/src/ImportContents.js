import Vue from "vue";
//import App from "./App.vue";
import App from "./App/ImportContents.vue";
import store from "./store";
/**
 * On desactive le retour car ce dernier ajoute #/editentity sur les URLS.
 */
// import router from "./router";
import "./plugins/AppCmpts.js";
Vue.config.productionTip = false;
//
import buttons from "components_h_vuejs/src/components/Buttons/index.js";
import cards from "components_h_vuejs/src/components/Cards/index.js";
Vue.use(buttons);
Vue.use(cards);
//
import ContainerPage from "./views/ContainerPage.vue";
Vue.component("ContainerPage", ContainerPage);
//
import { ValidationObserver, ValidationProvider, extend } from "vee-validate";
Vue.component("ValidationObserver", ValidationObserver);
Vue.component("ValidationProvider", ValidationProvider);
//import "drupal-vuejs/src/App/components/vee-validate-custom.js";
import { required, email, alpha } from "vee-validate/dist/rules";
extend("required", {
  ...required,
  message: "Ce champs est requis",
});
extend("email", email);
extend("alpha", alpha);
(function (Drupal) {
  var once = window.once;
  Drupal.behaviors.export_import_entities = {
    attach: function (context, settings) {
      once("export_import_entities_importcts", "#import_contents_by_js", context).forEach((item) => {
        if (settings.export_import_entities.import_contents) {
          store.commit("SET_CURRENT_ENTITY_FORM", settings.export_import_entities.import_contents);
          store.commit("SET_BASE_DIRECTORY", settings.export_import_entities.base_directory);
          store.commit("SET_KEY_IDENTIFICATION", settings.export_import_entities.key_identification);
        }
        new Vue({
          store,
          render: (h) => h(App),
        }).$mount(item);
      });
    },
  };
})(window.Drupal);
