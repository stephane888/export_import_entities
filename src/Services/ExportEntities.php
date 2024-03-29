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
use Drupal\taxonomy\Entity\Term;

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
   * Contient la liste des entites dont les configurations doivent etre
   * extraites si elles remplissent les conditions.
   * On distingue deux type d 'entité sans bundle et avec.
   * -- Les entittés avec bundle --
   * Pour ajouter une entité avec bundle, on doit chosir le bundle (C'est au
   * niveau du bundle qu'on definit la configuration, les champs, les
   * formulaires ...) avant l'ajout. Donc pour recuperer la configuration, on
   * doit recuperer à partir de ce bundle.
   * -- Les entittés sans bundle --
   *
   * @var array
   */
  protected $validesEntities = [
    'node',
    'paragraph',
    'config_theme_entity',
    'site_internet_entity',
    'block_content',
    // 'block', // le bloc n'est pas appropié pour le moment, car certains
    // fonctionnalité (le theme, plugin derivée ) ne sont pas sur le modele.
    'commerce_product'
  ];

  /**
   *
   * @var array
   */
  protected $directEntities = [
    'taxonomy_term'
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
   * @var LoadFormWrite
   */
  protected $LoadFormWrite;

  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;

  /**
   *
   * @var LoadViewDisplays
   */
  protected $LoadViewDisplays;

  /**
   *
   * @param DomainNegotiator $DomainNegotiator
   * @param EntityFieldManager $EntityFieldManager
   * @param StorageInterface $config_storage
   * @param LoadFormDisplays $LoadFormDisplays
   * @param LoadConfigs $LoadConfigs
   */
  function __construct(DomainNegotiator $DomainNegotiator, EntityFieldManager $EntityFieldManager, StorageInterface $config_storage, LoadFormDisplays $LoadFormDisplays, LoadConfigs $LoadConfigs, LoadViewDisplays $LoadViewDisplays) {
    $this->DomainNegotiator = $DomainNegotiator;
    $this->currentDomaine = $this->DomainNegotiator->getActiveDomain();
    $this->EntityFieldManager = $EntityFieldManager;
    $this->configStorage = $config_storage;
    $this->LoadFormDisplays = $LoadFormDisplays;
    $this->LoadConfigs = $LoadConfigs;
    $this->LoadViewDisplays = $LoadViewDisplays;
  }

  public function setNewDomain($domaineId) {
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
    if ($domain)
      $this->currentDomaine = $domain;
    else
      throw new \Exception("le Domain n'exite pas");
    //
    $this->LoadConfigs->setNewDomain($domaineId);
    $this->LoadFormDisplays->setNewDomain($domaineId);
    $this->LoadViewDisplays->setNewDomain($domaineId);
  }

  function getEntites() {
    $ListEntities = $this->entityTypeManager()->getDefinitions();
    // dump($this->currentDomaine->id());
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
        /**
         * Permet de recuperer les données liées à l'affichage.
         *
         * @var array $bundles
         */
        $bundles = [];
        $this->loadContents($entity_type, $contents, $bundles);
        // Genere la configuration pour l'affichage du noeud.
        $this->LoadFormDisplays->getDisplays($entity_type, $bundles);
        //
        $this->LoadViewDisplays->getDisplays($entity_type, $bundles);
        // pas de configuration disponible, pour le moment on utilise le rendu
        // par defaut.
        // $this->LoadFormWrite->getDisplays($entity_type, $bundles);
        // ////////
        // Pour que ce contenu puisse fonctionner, il faut que les champs par
        // defaut et ceux crée manuellement existe.
        // on doit egalement recuperer les contenus du bunble ( dans le cas des
        // nodes ce sont les types de contenus ).
        // recuperation des champs.
      }
    }
    // Generate custom config.
    $this->generateCustomConfigs();
    $this->generateImagesStyle();
    // $this->loadConfigFromEntities();
    //
    $this->getMenus();
    // $block =
    // $this->entityTypeManager()->getStorage('block')->load('test62_wb_horizon_kksa_breamcrumb');
    // dump($this->LoadConfigs->getGenerate());
    // dump($this->LoadConfigs->getGenerate());
    // die();
  }

  function loadConfigFromEntities() {
    foreach ($this->directEntities as $BundleEntityType) {
      /**
       * à revoir la logique ci-dessous.
       * ( pour les elements sans bundle ).
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
       */
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($BundleEntityType);
      $entityTypeDefinition->getBundleEntityType();
      $vob = $entityTypeDefinition->getBundleEntityType();
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($vob);
      //
      $entityTypes = $this->entityTypeManager()->getStorage($vob)->loadMultiple();
      $name = $entityTypeDefinition->getConfigPrefix() . '.' . $vob;
      dump($name, $vob, $entityTypes);
      if (!$this->LoadConfigs->hasGenerate($name)) {
        $this->LoadConfigs->getConfigFromName($name);
      }
    }
  }

  /**
   * ThirdPartySettings via layout_builder, ne semble pas permettre de charger
   * les depences.
   * Donc, on charge les style images
   */
  function generateImagesStyle() {
    $image_styles = $this->entityTypeManager()->getStorage('image_style')->loadMultiple();
    foreach ($image_styles as $image_style) {
      $name = 'image.style.' . $image_style->id();
      $this->LoadConfigs->getConfigFromName($name);
    }
  }

  function getMenus() {
    $domainId = $this->currentDomaine->id();
    $entityMenu = $this->entityTypeManager()->getDefinition("menu");
    $query = $this->entityTypeManager()->getStorage("menu")->getQuery();
    $query->condition('id', $domainId, 'CONTAINS');
    $ids = $query->execute();
    foreach ($ids as $id) {
      $name = $entityMenu->getConfigPrefix() . '.' . $id;
      if (!$this->LoadConfigs->hasGenerate($name)) {
        $this->LoadConfigs->getConfigFromName($name);
      }
    }
  }

  /**
   * --
   */
  function generateCustomConfigs() {
    // Themes config.
    $string = Yaml::encode([
      'admin' => 'claro',
      'default' => 'theme_reference_wbu'
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
    $name = 'language.negotiation';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'language.mappings';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'language.types';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'languageicons.settings';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.full_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.restricted_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.basic_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'filter.format.text_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'generate_style_theme.settings';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'commerce_price.commerce_currency.EUR';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'commerce_price.commerce_currency.USD';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'rest.resource.commerce_cart_add';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'editor.editor.basic_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'editor.editor.full_html';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'pathauto.pattern.taxo_term';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'pathauto.pattern.page_site_web';
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
   * Unquement pour tester
   *
   * @deprecated
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
    // dump($this->configStorage->read($conf));
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
   * Recupere la configuration % au contenus.
   * ( Config field, node, nodetype, bloc ...)
   * (example retourne les contenus pour l'entité node).
   */
  protected function loadContents(string $entity_type, &$contents, &$bundles = []) {
    $domainId = $this->currentDomaine->id();
    // Il faut mettre à jour le nom du champs pour qu'il soit validé. ( version
    // 2x).
    if ($entity_type == 'config_theme_entity') {
      $contents = $this->entityTypeManager()->getStorage($entity_type)->loadByProperties([
        'hostname' => $domainId
      ]);
    }
    elseif ($entity_type == 'block') {
      $contents = $this->entityTypeManager()->getStorage($entity_type)->loadByProperties([
        'theme' => $domainId
      ]);
    }
    else
      $contents = $this->entityTypeManager()->getStorage($entity_type)->loadByProperties([
        self::$field_domain_access => $domainId
      ]);

    foreach ($contents as $value) {
      $BundleEntityType = $value->getEntityType()->getBundleEntityType();
      if (!empty($BundleEntityType)) {
        /**
         *
         * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
         */
        $entityTypeDefinition = $this->entityTypeManager()->getDefinition($BundleEntityType);
        $bundle = $value->bundle();
        $name = $entityTypeDefinition->getConfigPrefix() . '.' . $bundle;
        $bundles[$bundle] = $bundle;
        if (!$this->LoadConfigs->hasGenerate($name)) {
          $this->LoadConfigs->getConfigFromName($name);
          // on genere si possible les configurations liées à la traduction.
          $idTranslation = 'language.content_settings.' . $value->getEntityTypeId() . '.' . $bundle;
          $this->LoadConfigs->getConfigFromName($idTranslation);
        }
        // elseif ($entity_type == "block_content") {
        // dump($name);
        // }
      }
      else {
        /**
         *
         * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
         *
         */
        $entityTypeDefinition = $this->entityTypeManager()->getDefinition($entity_type);
        if ($entityTypeDefinition instanceof \Drupal\Core\Config\Entity\ConfigEntityType) {
          $name = $entityTypeDefinition->getConfigPrefix() . '.' . $value->id();
          if (!$this->LoadConfigs->hasGenerate($name)) {
            $this->LoadConfigs->getConfigFromName($name);
          }
        }

        // ces entites n'ont pas de données de configuration à ce niveau. ils
        // sont fournir uniquement à partir d'un modele ou d'une configuration,
        // mais on peut en surcharger les configurations (formDisplays et
        // viewDisplays) qui en resulte.
        $bundles[$entity_type] = $entity_type;
      }
    }

    /**
     * Seule le type de produit contient le champs domain access, donc pour
     * chaque type de produit on doit recuperer :
     * - les types de variations
     * -
     */
    if (!empty($contents) && $entity_type == 'commerce_product') {
      $products = $contents;
      $productBundles = [];
      foreach ($products as $product) {
        /**
         *
         * @var \Drupal\commerce_product\Entity\Product $product
         */
        $variations = $product->getVariations();
        foreach ($variations as $variation) {
          $BundleEntityType = $variation->getEntityType()->getBundleEntityType();
          /**
           *
           * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
           */
          $entityTypeDefinition = $this->entityTypeManager()->getDefinition($BundleEntityType);
          $bundle = $variation->bundle();
          $name = $entityTypeDefinition->getConfigPrefix() . '.' . $bundle;
          $productBundles[$bundle] = $bundle;
          if (!$this->LoadConfigs->hasGenerate($name)) {
            $this->LoadConfigs->getConfigFromName($name);
            // On genere si possible les configurations liées à la traduction.
            $idTranslation = 'language.content_settings.' . $value->getEntityTypeId() . '.' . $bundle;
            $this->LoadConfigs->getConfigFromName($idTranslation);
            //
            $this->LoadFormDisplays->getDisplays($variation->getEntityTypeId(), $productBundles);
            $this->LoadViewDisplays->getDisplays($variation->getEntityTypeId(), $productBundles);
          }
        }
      }
    }
  }

/**
 * \Drupal::entityManager()->getStorage('field_storage_config')->create($field)->save();
 *
 * \Drupal::entityManager()->getStorage('field_config')->create($instance)->save();
 */
}