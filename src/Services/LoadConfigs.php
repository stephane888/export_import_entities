<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Finder\Finder;
use DrupalFinder\DrupalFinder;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Entity\File;

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
  
  /**
   *
   * @var \Symfony\Component\Finder\Finder
   */
  protected $Finder;
  
  /**
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $currentDomaine;
  
  function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }
  
  public function setNewDomain($domaineId) {
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
    if ($domain)
      $this->currentDomaine = $domain;
    else
      throw new \Exception("le Domain n'exite pas");
  }
  
  protected function getInstanceFinder() {
    if (!$this->Finder)
      $this->Finder = new Finder();
    return $this->Finder;
  }
  
  /**
   * Crrer la configuration à partir du nom donnée.
   * Recupere egalement les dependance incluse. ( si cela respecte la logique de
   * drupal ).
   *
   * @param string $name
   * @param $override //
   *        contient les données qui doivent etre surcharger.
   */
  public function getConfigFromName(string $name, array $override = []) {
    debugLog::$debug = false;
    if ($this->currentDomaine)
      debugLog::$path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id() . '/web/profiles/contrib/wb_horizon_generate/config/install';
    else
      debugLog::$path = DRUPAL_ROOT . '/../sites_exports/default_model/config/install';
    // dump(debugLog::$path);
    
    if (empty(self::$configEntities[$name])) {
      $defaultConfs = $this->configStorage->read($name);
      // if (str_contains($name, "commerce_payment.commerce_payment_gateway")) {
      // dump($defaultConfs);
      // }
      
      if ($defaultConfs) {
        if (!empty($override)) {
          $configs = NestedArray::mergeDeepArray([
            $defaultConfs,
            $override
          ]);
        }
        else
          $configs = $defaultConfs;
        $string = Yaml::encode($configs);
        debugLog::logger($string, $name . '.yml', false, 'file');
        self::$configEntities[$name] = [
          'status' => true,
          'value' => $string
        ];
        $this->loadConfigsViewTerms($name);
        // On essaie de charger les configurations requises.
        $this->loadDependancyConfig($name);
      }
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
  
  /**
   * Chage une ou toute la config qui a été generée.
   *
   * @param string $k
   * @return NULL|array
   */
  public function getGenerate($k = null) {
    if ($k)
      return isset(self::$configEntities[$k]) ? self::$configEntities[$k] : null;
    else
      return self::$configEntities;
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
    if (!empty($configs['config']))
      foreach ($configs['config'] as $config) {
        if (empty(self::$configEntities[$config])) {
          $name = $config;
          if ($this->filterConfig($config)) {
            $defaultConfs = $this->configStorage->read($name);
            //
            if (str_contains($name, 'field.field')) {
              $this->addDefaultEncodeData($defaultConfs);
            }
            $string = Yaml::encode($defaultConfs);
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
   * --
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
      // On charge les images par defaut au format base64.
      
      $definition = $this->entityTypeManager()->getDefinition('field_config');
      $name = $definition->getConfigPrefix() . '.' . $entity_type . '.' . $bundle . '.' . $fieldName;
      // dump($name);
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
  
}