<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Drupal\export_import_entities\Services\ThirdPartySettings;

/**
 * Permet de charger les diffirents differents mode de d'affichage pour un
 * formulaire
 * d'entité.
 *
 * @author stephane
 *
 */
class LoadViewDisplays extends ControllerBase {
  /**
   *
   * @var \Drupal\export_import_entities\Services\ThirdPartySettings
   */
  protected $ThirdPartySettings;

  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;

  function __construct(LoadConfigs $LoadConfigs, ThirdPartySettings $ThirdPartySettings) {
    $this->LoadConfigs = $LoadConfigs;
    $this->ThirdPartySettings = $ThirdPartySettings;
  }

  /**
   * Permet de charger l
   *
   * @param string $entity_type
   * @param array $bundles
   * @return [\Drupal\Core\Entity\Entity\EntityFormDisplay]
   */
  function getDisplays(string $entity_type, array $bundles) {
    /**
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityType $definition
     */
    $definition = $this->entityTypeManager()->getDefinition('entity_view_display');
    $prefix = $definition->getConfigPrefix();

    foreach ($bundles as $bundle) {
      $keySearch = $entity_type . '.' . $bundle;
      $query = $this->entityTypeManager()->getStorage('entity_view_display')->getQuery();
      $query->condition('id', $keySearch, 'CONTAINS');
      $ids = $query->execute();
      if (!empty($ids)) {
        foreach ($ids as $id) {
          if (!$this->LoadConfigs->hasGenerate($id)) {
            /**
             *
             * @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity
             */
            $entity = $this->entityTypeManager()->getStorage('entity_view_display')->load($id);
            $this->LoadConfigs->getConfigFromName($prefix . '.' . $id);
            // On se rassure que ses dependances ont été cree ou on les crées.
            $confs = $entity->getDependencies();
            $this->LoadConfigs->getConfig($confs);
            // On genere egalement les configurations de third_party_settings;
            $this->ThirdPartySettings->getConfigFromThirdParty($entity);
          }
        }
      }
    }
    // die();
  }

}