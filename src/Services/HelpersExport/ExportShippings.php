<?php

namespace Drupal\export_import_entities\Services\HelpersExport;

use Stephane888\Debug\Repositories\ConfigDrupal;
use Drupal\export_import_entities\Services\LoadConfigs;

/**
 * Il genere les données de configuration.
 *
 * @author stephane
 *        
 */
trait ExportShippings {
  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;
  
  /**
   * Exporte la configuration en relation avec les shippings.
   */
  function exportConfigShippings() {
    if ($this->isRequireShipping()) {
      $this->exportsProfileType();
    }
  }
  
  /**
   * On exporte tous les types de profiles.
   */
  private function exportsProfileType() {
    foreach (\Drupal::entityTypeManager()->getStorage("profile_type")->loadMultiple() as $profile_type) {
      /**
       *
       * @var \Drupal\profile\Entity\ProfileType $profile_type
       */
      $this->LoadConfigs->generateAllConfigAboutEntity("profile", $profile_type->id(), "profile_type");
    }
  }
  
  /**
   * Recherche les plugins de livraison utiliser.
   */
  private function findPlugins() {
    //
  }
}