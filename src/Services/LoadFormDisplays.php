<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Permet de charger les diffirents affichage pour un formulaire.
 *
 * @author stephane
 *        
 */
class LoadFormDisplays extends ControllerBase {
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;
  
  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;
  
  function __construct(StorageInterface $config_storage, LoadConfigs $LoadConfigs) {
    $this->configStorage = $config_storage;
    $this->LoadConfigs = $LoadConfigs;
  }
  
  /**
   * Permet de charger l
   *
   * @param string $entity_type
   * @param array $bundles
   * @return [\Drupal\Core\Entity\Entity\EntityFormDisplay]
   */
  function getDisplays(string $entity_type, array $bundles, &$configEntities = []) {
    /**
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityType $definition
     */
    $definition = $this->entityTypeManager()->getDefinition('entity_form_display');
    $prefix = $definition->getConfigPrefix();
    /**
     *
     * @var \ Drupal\Core\Config\Entity\ConfigEntityStorage $entity_form_display
     */
    $entity_form_display = $this->entityTypeManager()->getStorage('entity_form_display');
    $entity_form_displays = $entity_form_display->loadByProperties();
    $configs = [];
    foreach ($entity_form_displays as $k => $value) {
      if (empty($configs[$k]))
        foreach ($bundles as $type) {
          if (\str_contains($k, $entity_type . '.' . $type)) {
            /**
             *
             * @var \Drupal\Core\Entity\Entity\EntityFormDisplay $value
             */
            $confs = $value->getDependencies();
            $this->LoadConfigs->getConfig($confs);
            $configs[$k] = $value;
            $name = $prefix . '.' . $k;
            $this->LoadConfigs->getConfigFromName($name);
          }
        }
    }
    return $configs;
  }
  
}