<template>
  <div class="import_contents_vuejs">
    <b-alert class="building-fields d-flex align-items-center" :variant="'primary'" fade :show="running">
      <h3 class="d-flex align-items-center mb-0 ml-0 mr-auto">Encours d'execution</h3>
      <b-icon icon="three-dots" animation="cylon" font-scale="4" class="ml-5"></b-icon>
    </b-alert>
    <pre> {{ run_entity }} </pre>
    <b-button @click="import_content" size="lg" variant="primary"> Import contents </b-button>
  </div>
</template>

<script>
import { mapState } from "vuex";
export default {
  name: "ImportContents",
  computed: {
    ...mapState({
      run_entity: (state) => state.run_entity,
      running: (state) => state.running,
    }),
  },
  methods: {
    import_content() {
      console.log("Run import");
      this.$store
        .dispatch("saveEntities")
        .then((resp) => {
          console.log(" Response : ", resp);
        })
        .catch((error) => {
          console.log(" Error : ", error);
        });
    },
  },
};
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style lang="scss">
.import_contents_vuejs {
  // @import "node_modules/bootstrap/scss/bootstrap.scss";
  // @import "node_modules/bootstrap-vue/src/index.scss";
}
</style>
