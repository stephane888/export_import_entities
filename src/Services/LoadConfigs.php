<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;
use Google\Service\MyBusinessLodging\Sustainability;

/**
 * Permet de charger les diffirents affichage pour une entité.
 *
 * @author stephane
 *        
 */
class LoadConfigs extends ControllerBase {
  
  /**
   * Contient la liste des configurations deja crees.
   *
   * @var array
   */
  protected static $configEntities = [];
  
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;
  
  function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }
  
  public function getConfigFromName(string $name, $entity = null) {
    debugLog::$debug = false;
    debugLog::$path = DRUPAL_ROOT . '/profiles/contrib/wb_horizon_generate/config/install';
    if (empty(self::$configEntities[$name])) {
      $string = Yaml::encode($this->configStorage->read($name));
      debugLog::logger($string, $name . '.yml', false, 'file');
      self::$configEntities[$name] = [
        'status' => true,
        'value' => $string
      ];
    }
  }
  
  public function addConfig(string $name, $string) {
    debugLog::logger($string, $name . '.yml', false, 'file');
    self::$configEntities[$name] = [
      'status' => true,
      'value' => $string
    ];
  }
  
  public function hasGenerate($k) {
    return isset(self::$configEntities[$k]) ? true : false;
  }
  
  public function getGenerate($k = null) {
    if ($k)
      return isset(self::$configEntities[$k]) ? self::$configEntities[$k] : null;
    else
      return self::$configEntities;
  }
  
  /**
   * Generre les fichiers de configuration de maniere recurssive.
   *
   * @param array $configs
   * @param array $configEntities
   */
  public function getConfig(array $configs, $entity = null) {
    if (!empty($configs['config']))
      foreach ($configs['config'] as $config) {
        if (empty($this->configEntities[$config])) {
          $name = $config;
          $string = Yaml::encode($this->configStorage->read($name));
          debugLog::logger($string, $name . '.yml', false, 'file');
          self::$configEntities[$name] = [
            'status' => true,
            'value' => $string
          ];
          // On essaie de charger les configurations requises.
          $this->loadDependancyConfig($name);
        }
      }
  }
  
  /**
   *
   * @param string $nameConf
   */
  private function loadDependancyConfig($nameConf) {
    [
      $entity_type,
      $entity,
      $mode
    ] = explode(".", $nameConf);
    // - Determiner ses dependances.
    if ($entity_type == 'field') {
      $fieldsKeys = explode(".", $nameConf);
      if (count($fieldsKeys) == 5) {
        $entity_type = $fieldsKeys[2];
        $bundle = $fieldsKeys[3];
        $fieldName = $fieldsKeys[4];
        /**
         *
         * @var \Drupal\field\Entity\FieldConfig $FieldConfig
         */
        $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $fieldName);
        $this->getConfig($FieldConfig->getDependencies());
        /**
         *
         * @var \Drupal\field\Entity\FieldStorageConfig $FieldStorageConfig
         */
        $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->load($entity_type . '.' . $fieldName);
        $this->getConfig($FieldStorageConfig->getDependencies());
        //
      }
    }
    else {
      // dump($nameConf);
    }
  }
  
}