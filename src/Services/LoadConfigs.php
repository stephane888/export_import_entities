<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Finder\Finder;
use DrupalFinder\DrupalFinder;
use Drupal\domain\DomainNegotiator;

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

  function __construct(StorageInterface $config_storage, DomainNegotiator $DomainNegotiator) {
    $this->configStorage = $config_storage;
    $this->currentDomaine = $DomainNegotiator->getActiveDomain();
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
   *
   * @param string $name
   */
  public function getConfigFromName(string $name) {
    debugLog::$debug = false;
    debugLog::$path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id() . '/web/profiles/contrib/wb_horizon_generate/config/install';
    // dump(debugLog::$path);
    if (empty(self::$configEntities[$name])) {
      $string = Yaml::encode($this->configStorage->read($name));
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
            $string = Yaml::encode($this->configStorage->read($name));
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
      $this->getConfigFromName($definition->getConfigPrefix() . '.' . $entity_type . '.' . $bundle . '.' . $fieldName);
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