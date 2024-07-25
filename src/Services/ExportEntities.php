<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityType;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Config\StorageInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\taxonomy\Entity\Term;
use Drupal\export_import_entities\Services\ProfileCustomization\ManageProfile;

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
   * @var ManageProfile
   */
  protected $ManageProfile;
  
  /**
   *
   * @param EntityFieldManager $EntityFieldManager
   * @param StorageInterface $config_storage
   * @param LoadFormDisplays $LoadFormDisplays
   * @param LoadConfigs $LoadConfigs
   */
  function __construct(EntityFieldManager $EntityFieldManager, StorageInterface $config_storage, LoadFormDisplays $LoadFormDisplays, LoadConfigs $LoadConfigs, LoadViewDisplays $LoadViewDisplays, ManageProfile $ManageProfile) {
    $this->entityFieldManger = $EntityFieldManager;
    $this->configStorage = $config_storage;
    $this->LoadFormDisplays = $LoadFormDisplays;
    $this->LoadConfigs = $LoadConfigs;
    $this->LoadViewDisplays = $LoadViewDisplays;
    $this->ManageProfile = $ManageProfile;
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
    $this->ManageProfile->setNewDomain($domaineId);
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
    //
    $this->getConfigCommerce();
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
      // dump($name, $vob, $entityTypes);
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
    $responsive_image_styles = $this->entityTypeManager()->getStorage('responsive_image_style')->loadMultiple();
    foreach ($responsive_image_styles as $responsive_image_style) {
      $name = 'responsive_image.styles.' . $responsive_image_style->id();
      $this->LoadConfigs->getConfigFromName($name);
    }
  }
  
  protected function getMenusIds() {
    $query = $this->entityTypeManager()->getStorage("menu")->getQuery();
    if ($this->currentDomaine) {
      $or = $query->orConditionGroup();
      $or->condition('id', $this->currentDomaine->id(), 'CONTAINS');
      $or->condition('third_party_settings.wb_horizon_public.domain_id', $this->currentDomaine->id(), 'CONTAINS');
      $query->condition($or);
    }
    return $query->execute();
  }
  
  function getMenus() {
    $entityMenu = $this->entityTypeManager()->getDefinition("menu");
    $ids = $this->getMenusIds();
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
    $theme_name = $this->currentDomaine ? $this->currentDomaine->id() : 'theme_reference_wbu';
    $string = Yaml::encode([
      'admin' => 'claro',
      'default' => $theme_name
    ]);
    $name = 'system.theme';
    $this->LoadConfigs->addConfig($name, $string);
    $configNames = [
      'language.entity.fr', // Language fr
      'language.entity.en',
      'language.negotiation',
      'system.site',
      'language.mappings',
      'language.types',
      'languageicons.settings',
      'filter.format.full_html',
      'filter.format.basic_html',
      'filter.format.text_html',
      'generate_style_theme.settings',
      'commerce_price.commerce_currency.EUR',
      'commerce_price.commerce_currency.USD',
      'rest.resource.commerce_cart_add',
      'editor.editor.basic_html',
      'editor.editor.full_html',
      'pathauto.pattern.taxo_term',
      'pathauto.pattern.page_site_web',
      'core.entity_view_display.user.user.hot_models_hotlock_menu__user',
      'formatage_models.configvuejsedit',
      "views.view.commerce_cart_block"
    ];
    
    // Language fr
    $name = 'language.entity.fr';
    $this->LoadConfigs->getConfigFromName($name);
    // Language en
    $name = 'language.entity.en';
    $this->LoadConfigs->getConfigFromName($name);
    //
    $name = 'language.negotiation';
    // // Surcharger la langue par defaut, ( afin de definir la langue par
    // defaut
    // // du site d'exportation sur la langue encours )
    // $overrides = [
    // 'langcode' => $lang_code
    // ];
    $this->LoadConfigs->getConfigFromName($name);
    //
    // Pour surcharger la langue par defaut.
    $overrides = [
      'default_langcode' => $lang_code
      // 'langcode' => $lang_code
    ];
    $name = 'system.site';
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
    // en attandant le traitement des affichage user
    $name = 'core.entity_view_display.user.user.hot_models_hotlock_menu__user';
    $this->LoadConfigs->getConfigFromName($name);
    // utilise la configuration encours de l'editeur de crayon.
    $name = 'formatage_models.configvuejsedit';
    $this->LoadConfigs->getConfigFromName($name);
    // commerce_cart_block
    $name = "views.view.commerce_cart_block";
    $this->LoadConfigs->getConfigFromName($name);
    // commerce_cart_form
    $name = "views.view.commerce_cart_form";
    $this->LoadConfigs->getConfigFromName($name);
    // commerce_cart_block
    $name = "views.view.commerce_cart_block";
    $this->LoadConfigs->getConfigFromName($name);
    // commerce_cart_form
    $name = "views.view.commerce_cart_form";
    $this->LoadConfigs->getConfigFromName($name);
    // export user role administrator
    $name = "user.role.administrator";
    $this->LoadConfigs->getConfigFromName($name);
    // site useful configs
    $this->generateSiteSourcesConfig();
    
    // manage_module_config settgings
    $name = "manage_module_config.settings";
    $configs = ConfigDrupal::config($name);
    $this->LoadConfigs->getConfigFromName($name, $configs, false);
    /**
     * hbk_collissimochrono api login
     * hbkcolissimochrono.settings
     */
    $name = "hbkcolissimochrono.settings";
    $configs = ConfigDrupal::config($name);
    $this->LoadConfigs->getConfigFromName($name, $configs, false);
    /**
     * export du dashboard
     */
    $name = "core.entity_view_display.user.user.default";
    $this->LoadConfigs->getConfigFromName($name);
    /**
     * Exporter les configurations manuels et automatique des
     * booking_config_type à utiliser par le site exporté.
     * par défaut sur wb-horizon l'ajout des booking_config_type
     * est restreint dans le dashboard et les bks_autoecole_heures
     * n'utilisent que les booking_config_type qui ont été générés par
     * là.
     * Cette config permet d'utiliser uniquement les booking_config_type
     * qui vienne du site lié à wb-horizon peu importe le nombre de
     * booking_config_type
     * présent sur la page.
     * NB: Aucune logique ne prévient la suppression de l'un de ce
     * booking_config_type
     */
    $this->generateBookingConfigFile();
    //
    $this->generateShippingConfig();
    // add theme to install;
    $this->ManageProfile->addTheme($theme_name);
  }
  
  protected function generateShippingConfig() {
    $this->LoadConfigs->generateAllConfigAboutEntity("commerce_shipment_type", "commerce_shipment_type", NULL, "default_shipping");
    $this->LoadConfigs->generateAllConfigAboutEntity("commerce_shipment_type", "commerce_shipment_type", NULL, "shipping_with_creneau");
  }
  
  protected function generateBookingConfigFile() {
    $prefix = \Drupal\lesroidelareno\lesroidelareno::getCurrentPrefixDomain();
    if ($this->entityTypeManager()->getStorage("booking_config_type")->load($prefix)) {
      $config = <<<FILE
      conduite_auto: {$prefix}auto
      conduite_manuelle: $prefix
      FILE;
      $this->LoadConfigs->addConfig("wb_horizon_public.config_auto_ecole", $config);
    }
  }
  
  protected function generateSiteSourcesConfig() {
    $config_name = 'wb_horizon_public.source_site_configs';
    $Ids = $this->getMenusIds();
    $main_menu_id = reset($Ids) ?? null;
    $configs = [
      "domain_source_id" => \Drupal\lesroidelareno\lesroidelareno::getCurrentPrefixDomain()
    ];
    if ($main_menu_id) {
      $configs["main_menu_id"] = $main_menu_id;
    }
    $string = Yaml::encode($configs);
    $this->LoadConfigs->addConfig($config_name, $string);
  }
  
  protected function getConfigCommerce() {
    if ($this->currentDomaine) {
      $domaineId = $this->currentDomaine->id();
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
       */
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition("commerce_payment_gateway");
      
      /**
       *
       * @var \Drupal\commerce_payment\PaymentGatewayManager $PaymentGatewayManager
       */
      $PaymentGatewayManager = \Drupal::service("plugin.manager.commerce_payment_gateway");
      // On charge les moyens qui ont été explicetement definit pour ce
      // domaine.
      $commerce_payment_configs = $this->entityTypeManager()->getStorage('commerce_payment_config')->loadByProperties([
        'domain_id' => $domaineId
      ]);
      
      foreach ($commerce_payment_configs as $commerce_payment_config) {
        /**
         *
         * @var \Drupal\lesroidelareno\Entity\CommercePaymentConfig $commerce_payment_config
         */
        $id = $commerce_payment_config->getPaymentPluginId();
        /**
         *
         * @var \Drupal\commerce_payment\Entity\PaymentGateway $commerce_payment_gateway
         */
        $commerce_payment_gateway = $this->entityTypeManager()->getStorage("commerce_payment_gateway")->load($id);
        $pluginId = $commerce_payment_gateway->getPluginId();
        /**
         *
         * @var \Drupal\wb_horizon_public\Plugin\Commerce\PaymentGateway\stripeOverride $lesroidelareno_stripe_override
         */
        $lesroidelareno_stripe_override = $PaymentGatewayManager->createInstance($pluginId);
        $lesroidelareno_stripe_override->__wakeup();
        $conf = $lesroidelareno_stripe_override->getConfiguration();
        $name = $entityTypeDefinition->getConfigPrefix() . '.' . $id;
        // On surcharge la configuration avec les informations du domain.
        $this->LoadConfigs->getConfigFromName($name, [
          'configuration' => $conf
        ], false);
      }
    }
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
    $storage = $this->entityTypeManager()->getStorage($entity_type);
    if ($this->currentDomaine) {
      $domaineId = $this->currentDomaine->id();
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
        $query->condition('third_party_settings.webform_domain_access.field_domain_access', $domaineId);
        $result = $query->execute();
        if (!empty($result))
          $contents = $storage->loadMultiple($result);
      }
      else {
        // Pour le moment on va se contenter de ternir compte des contentEntity.
        if ($storage->getEntityType()->getBaseTable()) {
          $fields = $this->entityFieldManger->getFieldStorageDefinitions($entity_type);
          if (!empty($fields['field_domain_access'])) {
            $contents = $storage->loadByProperties([
              self::$field_domain_access => $domaineId
            ]);
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
        $this->LoadConfigs->generateAllConfigAboutEntity($value->getEntityTypeId(), $value->bundle(), $BundleEntityType, $value->id());
        // /**
        // *
        // * @var \Drupal\Core\Config\Entity\ConfigEntityType
        // $entityTypeDefinition
        // */
        // $entityTypeDefinition =
        // $this->entityTypeManager()->getDefinition($BundleEntityType);
        // $bundle = $value->bundle();
        // $name = $entityTypeDefinition->getConfigPrefix() . '.' . $bundle;
        
        // $bundles[$bundle] = $bundle;
        // if (!$this->LoadConfigs->hasGenerate($name)) {
        // $this->LoadConfigs->getConfigFromName($name);
        // // on genere si possible les configurations liées à la traduction.
        // $idTranslation = 'language.content_settings.' .
        // $value->getEntityTypeId() . '.' . $bundle;
        // $this->LoadConfigs->getConfigFromName($idTranslation);
        // }
        // // elseif ($entity_type == "block_content") {
        // // dump($name);
        // // }
      }
      else {
        $this->LoadConfigs->generateAllConfigAboutEntity($value->getEntityTypeId(), $value->getEntityTypeId(), null, $value->id());
        // /**
        // *
        // * @var \Drupal\Core\Config\Entity\ConfigEntityType
        // $entityTypeDefinition
        // *
        // */
        // $entityTypeDefinition =
        // $this->entityTypeManager()->getDefinition($entity_type);
        // if ($entityTypeDefinition instanceof
        // \Drupal\Core\Config\Entity\ConfigEntityType) {
        // $name = $entityTypeDefinition->getConfigPrefix() . '.' .
        // $value->id();
        // if (!$this->LoadConfigs->hasGenerate($name)) {
        // $this->LoadConfigs->getConfigFromName($name);
        // // il faudra peut etre gerer la traduction.
        // }
        // }
        
        // // ces entites n'ont pas de données de configuration à ce niveau. ils
        // // sont fournir uniquement à partir d'un modele ou d'une
        // configuration,
        // // mais on peut en surcharger les configurations (formDisplays et
        // // viewDisplays) qui en resulte.
        // $bundles[$entity_type] = $entity_type;
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
          
          // if (!$this->LoadConfigs->hasGenerate($name)) {
          
          /**
           * Les variations de type de produit contiennent des dependances
           * qui ne respecte pas la logique de drupal :
           * - orderItemType
           *
           * @var \Drupal\commerce_product\Entity\ProductVariationType $entityType
           */
          $entityType = $this->entityTypeManager()->getStorage($BundleEntityType)->load($bundle);
          $OrderItemTypeId = $entityType->getOrderItemTypeId();
          
          if ($OrderItemTypeId) {
            /**
             *
             * @var \Drupal\commerce_order\Entity\OrderItemType $OrderItemType
             */
            $OrderItemType = $this->entityTypeManager()->getStorage("commerce_order_item_type")->load($OrderItemTypeId);
            $entityTypeDefinition = $this->entityTypeManager()->getDefinition("commerce_order_item_type");
            $name = $entityTypeDefinition->getConfigPrefix() . '.' . $OrderItemTypeId;
            // $db = [
            // 'entiy_type_id' => $OrderItemType->getEntityTypeId(),
            // 'bundle' => $OrderItemType->bundle(),
            // 'BundleEntityType' =>
            // $OrderItemType->getEntityType()->getBundleEntityType(),
            // 'id' => $OrderItemType->id()
            // ];
            $this->LoadConfigs->generateAllConfigAboutEntity($OrderItemType->getEntityTypeId(), $OrderItemType->bundle(), $OrderItemType->getEntityType()->getBundleEntityType(), $OrderItemType->id());
            // dd($OrderItemTypeId, $db);
            //
            if (!$this->LoadConfigs->hasGenerate($name)) {
              $this->LoadConfigs->getConfigFromName($name);
              $order_item_type_bundles = [
                $OrderItemTypeId => $OrderItemTypeId
              ];
              $this->LoadFormDisplays->getDisplays("commerce_order_item", $order_item_type_bundles);
              $this->LoadViewDisplays->getDisplays("commerce_order_item", $order_item_type_bundles);
            }
            
            $OrderTypeId = $OrderItemType->getOrderTypeId();
            if ($OrderTypeId) {
              /**
               *
               * @var \Drupal\commerce_order\Entity\OrderType $OrderType
               */
              $OrderType = $this->entityTypeManager()->getStorage("commerce_order_type")->load($OrderTypeId);
              $this->LoadConfigs->generateAllConfigAboutEntity($OrderType->getEntityTypeId(), $OrderType->bundle(), $OrderType->getEntityType()->getBundleEntityType(), $OrderType->id());
              $entityTypeDefinition = $this->entityTypeManager()->getDefinition("commerce_order_type");
              $name = $entityTypeDefinition->getConfigPrefix() . '.' . $OrderTypeId;
              if (!$this->LoadConfigs->hasGenerate($name)) {
                $this->LoadConfigs->getConfigFromName($name);
                $order_type_bundles = [
                  $OrderTypeId => $OrderTypeId
                ];
                $this->LoadFormDisplays->getDisplays("commerce_order", $order_type_bundles);
                $this->LoadViewDisplays->getDisplays("commerce_order", $order_type_bundles);
              }
              // Ce paramettre semble est generer via yamp.
              // $WorkflowId = $OrderType->getWorkflowId();
              /**
               * On recupere le process de paiement.
               *
               * @var string $checkout_flow_id
               */
              $checkout_flow_id = $OrderType->getThirdPartySetting('commerce_checkout', 'checkout_flow');
              if ($checkout_flow_id) {
                /**
                 *
                 * @var \Drupal\commerce_checkout\Entity\CheckoutFlow $commerce_checkout_flow
                 */
                $commerce_checkout_flow = $this->entityTypeManager()->getStorage("commerce_checkout_flow")->load($checkout_flow_id);
                $entityTypeDefinition = $this->entityTypeManager()->getDefinition("commerce_checkout_flow");
                $name = $entityTypeDefinition->getConfigPrefix() . '.' . $checkout_flow_id;
                if (!$this->LoadConfigs->hasGenerate($name)) {
                  $this->LoadConfigs->getConfigFromName($name);
                }
                // dd($name);
              }
            }
          }
          
          //
          $this->LoadConfigs->getConfigFromName($name);
          // On genere si possible les configurations liées à la traduction.
          $idTranslation = 'language.content_settings.' . $value->getEntityTypeId() . '.' . $bundle;
          $this->LoadConfigs->getConfigFromName($idTranslation);
          //
          $this->LoadFormDisplays->getDisplays($variation->getEntityTypeId(), $productBundles);
          $this->LoadViewDisplays->getDisplays($variation->getEntityTypeId(), $productBundles);
          // }
          $this->LoadConfigs->generateAllConfigAboutEntity($variation->getEntityTypeId(), $variation->bundle(), $BundleEntityType);
        }
        $this->LoadConfigs->generateAllConfigAboutEntity($product->getEntityTypeId(), $product->bundle(), $product->getEntityType()->getBundleEntityType());
      }
    }
  }
}
