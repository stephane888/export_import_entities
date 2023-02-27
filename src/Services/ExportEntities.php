<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityType;
use Stephane888\Debug\debugLog;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\taxonomy\Entity\Term;

class ExportEntities extends ControllerBase {
  protected static $field_domain_access = 'field_domain_access';
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
   * @deprecated
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
   * key 'export_import_entities.settings'
   *
   * @var array
   */
  protected $settings;
  
  /**
   *
   * @param EntityFieldManager $EntityFieldManager
   * @param StorageInterface $config_storage
   * @param LoadFormDisplays $LoadFormDisplays
   * @param LoadConfigs $LoadConfigs
   */
  function __construct(EntityFieldManager $EntityFieldManager, StorageInterface $config_storage, LoadFormDisplays $LoadFormDisplays, LoadConfigs $LoadConfigs, LoadViewDisplays $LoadViewDisplays) {
    $this->entityFieldManger = $EntityFieldManager;
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
      throw new \Exception("Le Domain n'exite pas");
    //
    $this->LoadConfigs->setNewDomain($domaineId);
    $this->LoadFormDisplays->setNewDomain($domaineId);
    $this->LoadViewDisplays->setNewDomain($domaineId);
  }
  
  public function getCurentDomain() {
    if (\Drupal::moduleHandler()->moduleExists('domain')) {
      $this->currentDomaine = \Drupal::service('domain.negotiator')->getActiveDomain();
      $this->setNewDomain($this->currentDomaine->id());
    }
  }
  
  protected function getValidesEntities() {
    $config = $this->getConfigs();
    $validesEntities = [];
    if (!empty($config['list_entities'])) {
      foreach ($config['list_entities'] as $key => $value) {
        if ($value)
          $validesEntities[] = $key;
      }
    }
    return $validesEntities;
  }
  
  /**
   * --
   *
   * @return array|number|mixed|\Drupal\Component\Render\MarkupInterface|string
   */
  protected function getConfigs() {
    if (!$this->settings) {
      $this->settings = $this->config('export_import_entities.settings')->getRawData();
    }
    return $this->settings;
  }
  
  function getEntites() {
    $ListEntities = $this->entityTypeManager()->getDefinitions();
    if (empty($this->currentDomaine)) {
      $this->getCurentDomain();
    }
    $settings = $this->getConfigs();
    //
    foreach ($this->getValidesEntities() as $value) {
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
    if ($settings['export_orthers_entities'])
      $this->generateCustomConfigs();
    if ($settings['export_image_styles'])
      $this->generateImagesStyle();
    // $this->loadConfigFromEntities();
    if ($settings['export_menus'])
      $this->getMenus();
    // $block =
    // $this->entityTypeManager()->getStorage('block')->load('test62_wb_horizon_kksa_breamcrumb');
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
    $entityMenu = $this->entityTypeManager()->getDefinition("menu");
    $query = $this->entityTypeManager()->getStorage("menu")->getQuery();
    if ($this->currentDomaine)
      $query->condition('id', $this->currentDomaine->id(), 'CONTAINS');
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
    $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();
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
    // Surcharger la langue par defaut, ( afin de definir la langue par defaut
    // du site d'exportation sur la langue encours)
    $overrides = [
      'langcode' => $lang_code
    ];
    $this->LoadConfigs->getConfigFromName($name, $overrides);
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
    $Allfields = $this->entityFieldManger->getFieldDefinitions($entity_type_id, $bundle);
  }
  
  /**
   * Recupere la configuration % au contenus.
   * ( Config field, node, nodetype, bloc ...)
   * (example retourne les contenus pour l'entité node).
   */
  protected function loadContents(string $entity_type, &$contents, &$bundles = []) {
    if ($this->currentDomaine) {
      $domaineId = $this->currentDomaine->id();
      $storage = $this->entityTypeManager()->getStorage($entity_type);
      if ($entity_type == 'config_theme_entity') {
        $contents = $storage->loadByProperties([
          'hostname' => $domaineId
        ]);
      }
      elseif ($entity_type == 'block') {
        $contents = $storage->loadByProperties([
          'theme' => $domaineId
        ]);
      }
      elseif ($entity_type == 'webform') {
        /**
         *
         * @var \Drupal\Core\Entity\Query\QueryInterface $query
         */
        $query = $storage->getQuery();
        $query->condition('third_party_settings.webform_domain_access.field_domain_access', $this->currentDomaine->id());
        $result = $query->execute();
        if (!empty($result))
          $contents = $storage->loadMultiple($result);
      }
      else {
        // Pour le moment on va se contenter de ternir compte des contentEntity.
        if ($storage->getEntityType()->getBaseTable()) {
          $fields = $this->entityFieldManger->getFieldStorageDefinitions($entity_type);
          
          if ($fields['field_domain_access']) {
            $contents = $storage->loadByProperties([
              self::$field_domain_access => $domaineId
            ]);
            // if ($entity_type == 'block_content') {
            // dump($storage);
            // }
          }
          else {
            $this->messenger()->addWarning(" Le type d'entité '" . $entity_type . "' n'a pas de champs field_domain_access ");
          }
          // dump($this->EntityFieldManager->getFieldDefinitions($entity_type,
          // $bundle));
          // si l'entité a des bundles.
          // if (!empty($storage->getEntityType()->getKey('bundle'))) {
          // // dump($storage->getEntityType()->getBundleEntityType());
          // dump($this->entityFieldManger->getFieldStorageDefinitions($entity_type));
          // }
        }
        else {
          $this->messenger()->addWarning(" Le type d'entité '" . $entity_type . "' n'est pas pris en compte car c'est une entité de configuration ");
        }
      }
    }
    else {
      $contents = $storage->loadMultiple();
    }
    
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