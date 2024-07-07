<?php

namespace Drupal\export_import_entities\Services;

use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Finder\Finder;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Entity\File;
use Drupal\Core\Extension\ExtensionPathResolver;
use Stephane888\DrupalUtility\Export\Config\ExportConfigs;

/**
 * Permet de charger les differents affichage pour une entité.
 *
 * @author stephane
 *        
 */
class LoadConfigs extends ExportConfigs {
  
  /**
   * Contient la liste des configurations deja crees.
   *
   * @var array
   */
  protected static $configEntities = [];
  
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;
  
  /**
   *
   * @var \Symfony\Component\Finder\Finder
   */
  protected $Finder;
  
  /**
   * key 'export_import_entities.settings'
   *
   * @var array
   */
  protected $settings;
  
  /**
   *
   * @var ExtensionPathResolver
   */
  protected $ExtensionPathResolver;
  /**
   * Check if config is init;
   *
   * @var boolean
   */
  private $configInit = false;
  
  /**
   *
   * @var boolean
   */
  protected static $saveIt = true;
  /**
   *
   * @var boolean
   */
  protected static $removeUUID = false;
  
  /**
   * Les valeurs par defaut pose probleme dans certaines conditions( voir
   * wb-horizon).
   *
   * @var boolean
   */
  protected static $removeDefaultValue = TRUE;
  
  function __construct(StorageInterface $config_storage, ExtensionPathResolver $ExtensionPathResolver) {
    $this->configStorage = $config_storage;
    $this->ExtensionPathResolver = $ExtensionPathResolver;
  }
  
  protected function loadConfigsViewTerms($name) {
    /**
     * On a un soucis avec les données contenus dans les termes de references.
     * On souhaite importter uniquement les affichages des termes taxo
     * utilisés.
     */
    if (str_contains($name, 'taxonomy.vocabulary.')) {
      $type = explode("taxonomy.vocabulary.", $name);
      /**
       *
       * @var \Drupal\export_import_entities\Services\LoadViewDisplays $LoadViewDisplays
       */
      if (!empty($type[1])) {
        $bundles = [
          $type[1] => $type[1]
        ];
        $LoadViewDisplays = \Drupal::service('export_import_entities.export.view.displays');
        $LoadViewDisplays->getDisplays('taxonomy_term', $bundles);
      }
    }
  }
  
  /**
   * Generre les fichiers de configuration de maniere recurssive.
   *
   * @param array $configs
   * @param array $configEntities
   */
  public function getConfig(array $configs, $entity = null) {
    $this->initExportDir();
    if (!empty($configs['config']))
      foreach ($configs['config'] as $config) {
        if (empty(self::$configEntities[$config])) {
          $name = $config;
          if ($this->filterConfig($config)) {
            $defaultConfs = $this->configStorage->read($name);
            if (str_contains($name, 'field.field')) {
              $this->addDefaultEncodeData($defaultConfs);
              $this->removeDefaultValue($defaultConfs);
            }
            $this->removeUuid($defaultConfs);
            $string = Yaml::encode($defaultConfs);
            if (self::$saveIt)
              debugLog::logger($string, $name . '.yml', false, 'file');
            self::$configEntities[$name] = [
              'status' => true,
              'value' => $string
            ];
            $this->loadConfigsViewTerms($name);
            // On essaie de charger les configurations requises.
            $this->loadDependancyConfig($name);
          }
          else {
            self::$configEntities[$name] = 'none';
          }
        }
      }
  }
  
  /**
   * Certains données de configuration ne doivent pas etre exporter:
   * true: on cree la config;
   * - field_domain_* (tous les champs contenant field_domain).
   */
  protected function filterConfig($config) {
    return true;
    if (str_contains($config, 'field_domain_')) {
      return false;
    }
    else
      return true;
  }
  
  /**
   * Ajoute les images par defaut, mais encodé.
   */
  protected function addDefaultEncodeData(array &$defaultConfs) {
    if (!empty($defaultConfs['field_type']) && $defaultConfs['field_type'] == 'image' && !empty($defaultConfs['settings']['default_image']['uuid'])) {
      $uuid = $defaultConfs['settings']['default_image']['uuid'];
      if ($id = \Drupal::service('paragraphs_type.uuid_lookup')->get($uuid)) {
        $file = File::load($id);
        if ($file) {
          $defaultConfs["default_encode_file"] = 'data:' . $file->getMimeType() . ';base64,' . base64_encode(file_get_contents($file->getFileUri()));
          $defaultConfs["default_filename"] = $file->getFilename();
        }
      }
    }
  }
  
  /**
   * Retire les valeurs par defaut pour certains champs.
   */
  protected function removeDefaultValue(array &$defaultConfs) {
    if (self::$removeDefaultValue && !empty($defaultConfs['field_type'])) {
      $removeDefaultValue = [
        'text_with_summary',
        'string',
        'more_fields_icon_text',
        'text_long',
        'link',
        'entity_reference',
        'string_long',
        'geolocation',
        'phone_international'
      ];
      if (in_array($defaultConfs['field_type'], $removeDefaultValue))
        $defaultConfs['default_value'] = [];
    }
  }
  
  /**
   * Permet de recuperer les configurations d'un champs.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $fieldName
   */
  public function getConfigField($entity_type, $bundle, $fieldName) {
    /**
     *
     * @var \Drupal\field\Entity\FieldConfig $FieldConfig
     */
    $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $fieldName);
    if ($FieldConfig) {
      $definition = $this->entityTypeManager()->getDefinition('field_config');
      $name = $definition->getConfigPrefix() . '.' . $entity_type . '.' . $bundle . '.' . $fieldName;
      $this->getConfigFromName($name);
      $this->getConfig($FieldConfig->getDependencies());
    }
    
    /**
     *
     * @var \Drupal\field\Entity\FieldStorageConfig $FieldStorageConfig
     */
    $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->load($entity_type . '.' . $fieldName);
    if ($FieldStorageConfig) {
      $definition = $this->entityTypeManager()->getDefinition('field_storage_config');
      $this->getConfigFromName($definition->getConfigPrefix() . '.' . $entity_type . '.' . $fieldName);
      $this->getConfig($FieldStorageConfig->getDependencies());
    }
  }
  
  /**
   *
   * @param string $nameConf
   */
  private function loadDependancyConfig($nameConf) {
    $entity_type = null;
    $ar = explode(".", $nameConf);
    if (!empty($ar[0]))
      $entity_type = $ar[0];
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
      $dependencies = \Drupal::config($nameConf)->get('dependencies');
      if (!empty($dependencies['config'])) {
        $this->getConfig($dependencies);
      }
    }
  }
  
  /**
   * --
   */
  protected function initExportDir() {
    if (!$this->configInit) {
      $settings = $this->getSettings();
      debugLog::$debug = false;
      $pathFull = null;
      if (!empty($settings['save_data'])) {
        $path = $this->ExtensionPathResolver->getPath('profile', $settings['save_data']);
        if ($path) {
          if ($settings['config_is_required'])
            $pathFull = $path . "/config/install";
          else
            $pathFull = $path . "/config/optional";
        }
      }
      if ($pathFull) {
        debugLog::$path = DRUPAL_ROOT . "/" . $pathFull;
      }
      else
        debugLog::$path = DRUPAL_ROOT . '/../sites_exports/default_model/config/install';
      $this->configInit = true;
    }
  }
  
  protected function removeUuid(array &$confs) {
    if (self::$removeUUID && !empty($confs['uuid'])) {
      unset($confs['uuid']);
    }
  }
  
  /**
   * La configuration.
   *
   * @return array|number|mixed|\Drupal\Component\Render\MarkupInterface|string
   */
  protected function getSettings() {
    if (!$this->settings) {
      $this->settings = $this->config('export_import_entities.settings')->getRawData();
    }
    return $this->settings;
  }
  
  /**
   * Permet d'enregistrer ou pas les données de configurations.
   *
   * @param boolean $action
   */
  public function setSaveIt($action = true) {
    self::$saveIt = $action;
  }
  
  public function setRemoveUUID($action = true) {
    self::$removeUUID = $action;
  }
  
  public function setRemoveDefaultValue($action = true) {
    self::$removeDefaultValue = $action;
  }
}