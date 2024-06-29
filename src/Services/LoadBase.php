<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Drupal\export_import_entities\Services\ThirdPartySettings;

/**
 * Contient les fonction des bases.
 *
 * @author stephane
 *        
 */
class LoadBase extends ControllerBase {
  /**
   *
   * @var string
   */
  protected static $preffix = [];
  
  /**
   *
   * @param string $id
   */
  static public function loadConfigs($id, $entity_type_id = 'entity_view_display') {
    if (str_contains($id, ".")) {
      $preffix = self::getPreffix($entity_type_id);
      $query = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getQuery();
      $query->condition('id', $id, 'CONTAINS');
      $ids = $query->execute();
      if (!empty($ids)) {
        /**
         *
         * @var \Drupal\export_import_entities\Services\LoadConfigs $LoadConfigs
         */
        $LoadConfigs = \Drupal::service("export_import_entities.export.form.LoadConfigs");
        /**
         *
         * @var \Drupal\export_import_entities\Services\ThirdPartySettings $ThirdPartySettings
         */
        $ThirdPartySettings = \Drupal::service("export_import_entities.export.third_party_settings");
        foreach ($ids as $id) {
          if (!$LoadConfigs->hasGenerate($id)) {
            /**
             *
             * @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity
             */
            $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id);
            $LoadConfigs->getConfigFromName($preffix . '.' . $id);
            // On se rassure que ses dependances ont été cree ou on les crées.
            $confs = $entity->getDependencies();
            $LoadConfigs->getConfig($confs);
            // On genere egalement les configurations de third_party_settings;
            $ThirdPartySettings->getConfigFromThirdParty($entity);
          }
        }
      }
    }
    else {
      \Drupal::messenger()->addWarning("L'#ID '$id' doit etre contenir un point, i.e l'entity type et le bundle");
    }
  }
  
  /**
   *
   * @return string
   */
  static protected function getPreffix($entity_type_id = 'entity_view_display') {
    if (empty(self::$preffix[$entity_type_id])) {
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $definition
       */
      $definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
      self::$preffix[$entity_type_id] = $definition->getConfigPrefix();
    }
    return self::$preffix[$entity_type_id];
  }
  
}