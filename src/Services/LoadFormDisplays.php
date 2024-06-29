<?php

namespace Drupal\export_import_entities\Services;

/**
 * Permet de charger les diffirents differents mode de sasie pour un formulaire
 * d'entité.
 *
 * @author stephane
 *        
 */
class LoadFormDisplays extends LoadBase {
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
   *        Content entity : example node, blocks_contents
   * @param array $bundles
   * @return [\Drupal\Core\Entity\Entity\EntityFormDisplay]
   */
  function getDisplays(string $entity_type, array $bundles, &$configEntities = []) {
    foreach ($bundles as $bundle) {
      $keySearch = $entity_type . '.' . $bundle;
      self::loadConfigs($keySearch, 'entity_form_display');
      // $query =
      // $this->entityTypeManager()->getStorage('entity_form_display')->getQuery();
      // $query->condition('id', $keySearch, 'CONTAINS');
      // $ids = $query->execute();
      // // if ($entity_type == 'commerce_order_type') {
      // // dd($ids);
      // // }
      // if (!empty($ids)) {
      // foreach ($ids as $id) {
      // if (!$this->LoadConfigs->hasGenerate($id)) {
      // /**
      // *
      // * @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity
      // */
      // $entity =
      // $this->entityTypeManager()->getStorage('entity_form_display')->load($id);
      // $this->LoadConfigs->getConfigFromName($prefix . '.' . $id);
      // // On se rassure que ses dependances ont été cree ou on les crées.
      // $confs = $entity->getDependencies();
      // $this->LoadConfigs->getConfig($confs);
      // // On cree les champs.
      // $fields = $entity->get('content');
      // foreach ($fields as $fieldName => $v) {
      // $this->LoadConfigs->getConfigField($entity_type, $bundle, $fieldName);
      // }
      // }
      // }
      // }
    }
    // dump($this->LoadConfigs->getGenerate());
  }
  
}