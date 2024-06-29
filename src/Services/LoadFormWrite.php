<?php

namespace Drupal\export_import_entities\Services;

/**
 * Permet de charger les diffirents affichage pour un formulaire.
 *
 * @author stephane
 *        
 */
class LoadFormWrite extends LoadBase {
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
  
  /**
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $currentDomaine;
  
  function __construct(LoadConfigs $LoadConfigs, ThirdPartySettings $ThirdPartySettings) {
    $this->LoadConfigs = $LoadConfigs;
    $this->ThirdPartySettings = $ThirdPartySettings;
  }
  
  public function setNewDomain($domaineId) {
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
    if ($domain)
      $this->currentDomaine = $domain;
    else
      throw new \Exception("le Domain n'exite pas");
    //
    $this->LoadConfigs->setNewDomain($domaineId);
  }
  
  /**
   * Permet de charger l
   *
   * @param string $entity_type
   * @param array $bundles
   * @return [\Drupal\Core\Entity\Entity\EntityFormDisplay]
   */
  function getDisplays(string $entity_type, array $bundles, &$configEntities = []) {
    foreach ($bundles as $bundle) {
      $keySearch = $entity_type . '.' . $bundle;
      self::loadConfigs($keySearch, 'entity_form_mode');
    }
  }
  
}