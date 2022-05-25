<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityType;
use Stephane888\Debug\debugLog;
use Drupal\domain\DomainNegotiator;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;

class ExportEntities extends ControllerBase {
  protected static $field_domain_access = 'field_domain_access';
  protected $DomainNegotiator;
  protected $currentDomaine;
  protected $entityFieldManger;
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;
  /**
   * Contient la liste des entites dont les contenus pourront etre extraites si
   * elles remplissent les conditions.
   *
   * @var array
   */
  protected $validesEntities = [
    'node'
  ];
  /**
   * Contient la liste des configurations deja crees.
   *
   * @var array
   */
  protected $configEntities = [];
  
  /**
   *
   * @var LoadFormDisplays
   */
  protected $LoadFormDisplays;
  
  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;
  
  /**
   *
   * @param DomainNegotiator $DomainNegotiator
   * @param EntityFieldManager $EntityFieldManager
   * @param StorageInterface $config_storage
   * @param LoadFormDisplays $LoadFormDisplays
   * @param LoadConfigs $LoadConfigs
   */
  function __construct(DomainNegotiator $DomainNegotiator, EntityFieldManager $EntityFieldManager, StorageInterface $config_storage, LoadFormDisplays $LoadFormDisplays, LoadConfigs $LoadConfigs) {
    $this->DomainNegotiator = $DomainNegotiator;
    $this->currentDomaine = $this->DomainNegotiator->getActiveDomain();
    $this->EntityFieldManager = $EntityFieldManager;
    $this->configStorage = $config_storage;
    $this->LoadFormDisplays = $LoadFormDisplays;
    $this->LoadConfigs = $LoadConfigs;
  }
  
  function getEntites() {
    $ListEntities = $this->entityTypeManager()->getDefinitions();
    debugLog::$debug = false;
    debugLog::$path = DRUPAL_ROOT . '/profiles/contrib/wb_horizon_generate/config/install';
    // debugLog::$max_depth = 5;
    // debugLog::kintDebugDrupal($ListEntities, 'list-entites');
    foreach ($this->validesEntities as $value) {
      if (!empty($ListEntities[$value])) {
        /**
         *
         * @var ContentEntityType $ContentEntityType
         */
        $ContentEntityType = $ListEntities[$value];
        // $entity_id cest par example node.
        $entity_type = $ContentEntityType->id();
        // On recupere sont contenus.
        $contents = [];
        $bundles = [];
        $this->loadContents($entity_type, $contents, $bundles);
        // genere la configuration pour l'affichage du noeud.
        $this->LoadFormDisplays->getDisplays($entity_type, $bundles);
        // ////////
        // Pour que ce contenu puisse fonctionner, il faut que les champs par
        // defaut et ceux crée manuellement existe.
        // on doit egalement recuperer les contenus du bunble ( dans le cas des
        // nodes ce sont les types de contenus ).
        // recuperation des champs.
      }
    }
    //
    // $this->generateFieldsConfig();
    // generate custom config
    $this->generateCustomConfigs();
    dump($this->LoadConfigs->getGenerate());
    //
  }
  
  /**
   * --
   */
  function generateCustomConfigs() {
    // Themes config.
    $string = Yaml::encode([
      'admin' => 'claro',
      'default' => 'lesroisdelareno'
    ]);
    $name = 'system.theme';
    $this->LoadConfigs->addConfig($name, $string);
    // Language fr
    $name = 'language.entity.fr';
    $this->LoadConfigs->getConfigFromName($name);
    // Language en
    $name = 'language.entity.en';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.full_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.restricted_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.text_html';
    $this->LoadConfigs->getConfigFromName($name);
  }
  
  /**
   * Retourne les configurations de champs pour une entité donnée.
   */
  public function getFieldsFromEntity($entity_type_id, $bundle = null) {
    if (!$bundle)
      $bundle = $entity_type_id;
    $Allfields = $this->EntityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
  }
  
  /**
   * --
   */
  protected function generateFieldsConfig() {
    $entity_type_id = 'node';
    $bundle = 'nos_realisations';
    $Allfields = $this->EntityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $definition = $this->entityTypeManager()->getDefinition('field_config');
    $definitionBase = $this->entityTypeManager()->getDefinition('field_storage_config');
    $prefix = $definition->getConfigPrefix();
    $prefix2 = $definitionBase->getConfigPrefix();
    // dump($prefix2);
    // debugLog::kintDebugDrupal($Allfields['field_localisation'],
    // 'field_localisation');
    // dump($Allfields['field_localisation']->getFieldStorageDefinition());
    // dump($Allfields['body']->id());
    
    debugLog::$path = trim(debugLog::$path, '/');
    //
    foreach ($Allfields as $k => $value) {
      
      if (method_exists($Allfields[$k], 'id')) {
        /**
         *
         * @var \Drupal\Core\Field\Entity\BaseFieldOverride $value
         */
        $name = $prefix . '.' . $Allfields[$k]->id();
        $storageField = $Allfields[$k]->getFieldStorageDefinition();
        $name2 = $prefix2 . '.' . $storageField->getTargetEntityTypeId() . '.' . $storageField->getName();
        $string_conf = Yaml::encode($this->configStorage->read($name));
        debugLog::logger($string_conf, $name . '.yml', false, 'file');
        $string_conf2 = Yaml::encode($this->configStorage->read($name2));
        debugLog::logger($string_conf2, $name2 . '.yml', false, 'file');
        $this->configEntities[$name2] = [
          'status' => true,
          'value' => $string_conf2
        ];
        $this->configEntities[$name] = [
          'status' => true,
          'value' => $string_conf
        ];
        // Charges les dependances.
        $configs = $value->getDependencies();
        foreach ($configs as $key => $confs) {
          foreach ($confs as $conf) {
            if (empty($this->configEntities[$conf])) {
              if ($key == 'config') {
                $string_conf3 = Yaml::encode($this->configStorage->read($conf));
                debugLog::logger($string_conf3, $conf . '.yml', false, 'file');
                $this->configEntities[$conf] = [
                  'status' => true,
                  'value' => $string_conf3
                ];
                // $this->getConfig($conf);
              }
            }
          }
        }
      }
    }
  }
  
  /**
   * La
   *
   * @param string $conf
   *        : elle est toujours sur cette forme 'entity.bundle.mode'
   */
  protected function getConfig($conf) {
    dump($this->configStorage->read($conf));
    [
      $entity_type,
      $entity,
      $mode
    ] = explode(".", $conf);
    if ($entity_type == 'taxonomy') {
      $definition = null;
    }
    else {
      $definition = $this->entityTypeManager()->getDefinition($entity_type);
      // $prefix = $definition->getConfigPrefix();
    }
    // dump($definition);
  }
  
  /**
   * return les contenus et les types de contenus (bundle).
   * (example retourne les contenus pour l'entité node).
   */
  protected function loadContents(string $entity_type, &$contents, &$bundles = []) {
    $domainId = $this->currentDomaine->id();
    $contents = $this->entityTypeManager()->getStorage($entity_type)->loadByProperties([
      self::$field_domain_access => $domainId
    ]);
    foreach ($contents as $value) {
      /**
       *
       * @var Node $value
       */
      $bundle = $value->bundle();
      $name = 'node.type.' . $bundle;
      if (!$this->LoadConfigs->hasGenerate($name)) {
        $bundles[$bundle] = $bundle;
        $this->LoadConfigs->getConfigFromName($name);
      }
    }
  }
  
/**
 * \Drupal::entityManager()->getStorage('field_storage_config')->create($field)->save();
 *
 * \Drupal::entityManager()->getStorage('field_config')->create($instance)->save();
 */
}