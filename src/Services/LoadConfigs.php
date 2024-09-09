<?php

namespace Drupal\export_import_entities\Services;

use Stephane888\Debug\debugLog;
use Drupal\Core\Config\StorageInterface;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\Finder\Finder;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Entity\File;

/**
 * Permet de charger les diffirents affichage pour une entité.
 * @todo export the configurations in all available languages
 * @author stephane
 *        
 */
class LoadConfigs extends LoadBase {

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

  /**
   *
   * @param StorageInterface $config_storage
   */
  function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

  /**
   *
   * @param string $domaineId
   */
  public function setNewDomain($domaineId) {
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
    if ($domain)
      $this->currentDomaine = $domain;
    else
      throw new \Exception(" Le Domain n'exite pas ");
  }

  protected function getInstanceFinder() {
    if (!$this->Finder)
      $this->Finder = new Finder();
    return $this->Finder;
  }


  /**
   * Get the translated configuration set.
   *
   * This configuration set is complete with all keys that the original language
   * has to offer. Every key that has a translation will have the translated
   * value in its place. Merging is done using array_replace_recursive().
   *
   * @param string $configName
   *   The name of the configuration file.
   * @param string $langCode
   *   The language id. Leave empty for current Drupal language.
   *
   * @return array
   *   Returns the combined translated configuration as an object.
   */
  public function getTranslatedConfig($configName, $langcode = NULL, $default = false) {
    if (empty($langcode)) {
      $langcode = $this->languageManager()->getDefaultLanguage()->getId();
    }
    $originalConfig = $this->configStorage->read($configName);
    if (!$originalConfig) {
      return false;
    } elseif ($default) {
      /**
       * On retourne les configurations d'origine si le paramètre default est à true
       */
      return $originalConfig;
    }
    $translatedConfig = $this->languageManager()->getLanguageConfigOverride($langcode, $configName)->get();
    if (strpos($configName, 'webform') === 0 && isset($translatedConfig["elements"]) && isset($originalConfig["elements"])) {
      $elements = Yaml::decode($originalConfig["elements"]);
      $translatedElements = Yaml::decode($translatedConfig["elements"]);
      foreach ($translatedElements as $key => $element) {
        $this->deepFoundAndMerge($key, $element, $elements);
      }
      $configs["elements"] = Yaml::encode($elements);
      // dd($translatedConfig, $configs, $translatedElements, $configName);
    } else {
      // $configs = array_replace_recursive($originalConfig, $translatedConfig);
      $configs = $translatedConfig;

      // if ($langcode == "en") {
      //   if (empty($translatedConfig)) {
      //     // dump([$configName]);
      //   } else {
      //     dump([$configName => $translatedConfig]);
      //   }
      // }
    }
    if ($configs) {
      $configs["langcode"] = $langcode;
    }
    return  $configs;
  }

  /**
   * replace recursively the value of the key once in the array
   * it will replace the first element it will find deeply
   */
  protected function deepFoundAndMerge(string|int $key, mixed &$value, array &$array) {
    if (isset($array[$key])) {
      $array[$key] = array_replace_recursive($array[$key], $value);
      return true;
    }
    $found = false;
    foreach ($array as &$subArray) {
      if (gettype($subArray) == "array") {
        if ($this->deepFoundAndMerge($key, $value, $subArray)) {
          $found = true;
          break;
        }
      }
    }
    return $found;
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
  public function getConfigFromName(string $name, array $override = [], $merge = true) {

    if (empty(self::$configEntities[$name])) {
      /**
       * @todo import the different available lang of the webform config.
       */
      $availableLanguages = $this->configStorage->read('domain.language.' . $this->currentDomaine->id() . '.language.negotiation');
      $defaultLangcode = $this->configStorage->read('system.site')["default_langcode"];
      $langcodes = $availableLanguages["languages"] ?? [$defaultLangcode];
      $string = "";
      foreach ($langcodes as $key => $langcode) {
        $pathLanguageSuffix = $langcode == $defaultLangcode ? "" : "/language/" . $langcode;

        if ($this->currentDomaine)
          debugLog::$path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id() . '/web/profiles/contrib/wb_horizon_generate/config/install' . $pathLanguageSuffix;
        else
          debugLog::$path = DRUPAL_ROOT . '/../sites_exports/default_model/config/install' . $pathLanguageSuffix;
        $isDefaultLanguage = $langcode == $defaultLangcode;
        $defaultConfs =  $this->getTranslatedConfig($name, $langcode, $isDefaultLanguage);
        if ($defaultConfs) {
          if (str_contains($name, 'field.field')) {
            $this->addDefaultEncodeData($defaultConfs);
            $this->removeDefaultValue($defaultConfs);
          }

          if (!empty($override)) {
            if ($merge) {
              $configs = NestedArray::mergeDeepArray([
                $defaultConfs,
                $override
              ]);
            } else {
              // on remplace les cles
              foreach ($override as $k => $value) {
                $defaultConfs[$k] = $value;
              }
              $configs = $defaultConfs;
            }
          } else
            $configs = $defaultConfs;
          $string = Yaml::encode($configs);
          if ($name == "webform.webform.contact2022024Sep05368578") {
            // dd($string, $configs);
          }
          debugLog::logger($string, $name . '.yml', false, 'file');

          if ($langcode == $defaultLangcode) {
            $this->loadConfigsViewTerms($name);
            // On essaie de charger les configurations requises.
            $this->loadDependancyConfig($name);
          }
        }
      }
      if ($this->currentDomaine)
        debugLog::$path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id() . '/web/profiles/contrib/wb_horizon_generate/config/install';
      else
        debugLog::$path = DRUPAL_ROOT . '/../sites_exports/default_model/config/install' . $pathLanguageSuffix;
      self::$configEntities[$name] = [
        'status' => true,
        'value' => $string
      ];
      $this->tryGetDependencies($name);
    }
  }

  /**
   * ( Cette logique peut avoir des comportements inatendu ).
   * Vise à supprimer toutes les dependances liées au module domaine et au
   * modules coeurs.
   */
  protected function removeDependenciesDomain(array &$defaultConfs, $name) {
    // cas des menus.
    if (str_contains($name, "system.menu.")) {
      $this->removeModulesDependancies($defaultConfs);
      if ($defaultConfs['third_party_settings']['lesroidelareno'])
        unset($defaultConfs['third_party_settings']['lesroidelareno']);
    }
  }

  protected function removeModulesDependancies(array &$defaultConfs) {
    $modules = [
      'lesroidelareno' => 'lesroidelareno'
    ];
    if (!empty($defaultConfs['dependencies']['module']))
      foreach ($defaultConfs['dependencies']['module'] as $key => $moduleName) {
        if (in_array($moduleName, $modules))
          unset($defaultConfs['dependencies']['module'][$key]);
      }
  }

  public function addConfig(string $name, $string) {
    if (empty(self::$configEntities[$name])) {
      debugLog::logger($string, $name . '.yml', false, 'file');
      self::$configEntities[$name] = [
        'status' => true,
        'value' => $string
      ];
      $this->tryGetDependencies($name);
    }
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
              $this->removeDefaultValue($defaultConfs);
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
          } else {
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
    } else
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
    if (!empty($defaultConfs['field_type'])) {
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

  public function getConfigFields(array $ids) {
    foreach ($ids as $id) {
      $keys = explode(".", $id);
      if (isset($keys[2]))
        $this->getConfigField($keys[0], $keys[1], $keys[2]);
      else {
        $this->messenger()->addWarning(" Les champs doivent contenir : 'entity_type','bundle' et 'field_name' ");
      }
    }
  }

  /**
   *
   * @param string $nameConf
   */
  private function loadDependancyConfig($nameConf) {
    $this->tryGetDependencies($nameConf);
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
    /**
     * on determine les dependences lies à la variation de produit, car
     * actuelement le code ne e permet pas de maniere automatique.
     */
    elseif (str_contains($nameConf, "commerce_product.commerce_product_type.")) {
      $defaultConfs = $this->configStorage->read($nameConf);
      foreach ($defaultConfs['variationTypes'] as $variationType) {
        // On charge le type de produit.
        $variationName = "commerce_product.commerce_product_variation_type." . $variationType;
        $this->getConfigFromName($variationName);
        // On charge le rendu d'affichage et du formulaire.
        // ( permet de charger les champs de type FieldConfig, definie en
        // configuration, cela est utile dans ce cas de figure )
        $queryField = $this->entityTypeManager()->getStorage('field_config')->getQuery();
        $queryField->accessCheck(TRUE);
        $queryField->condition('entity_type', 'commerce_product_variation');
        $queryField->condition('bundle', $variationType);
        $ids = $queryField->execute();
        $this->getConfigFields($ids);
      }
    }
  }

  /**
   * Permet de generer toutes les configurations en relations avec une entité.
   * example :
   * $BundleEntityType = blocks_contents_type
   * $entiy_type_id = blocks_contents
   * $bundle = clothings_hero
   */
  public function generateAllConfigAboutEntity($entiy_type_id, $bundle, $BundleEntityType = null, $id = null) {
    /**
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
     */
    // Cas des entités avec bundle.
    if ($BundleEntityType) {

      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($BundleEntityType);
      $name = $entityTypeDefinition->getConfigPrefix() . '.' . $bundle;
      $this->getConfigFromName($name);
      $idTranslation = 'language.content_settings.' . $entiy_type_id . '.' . $bundle;
      $this->getConfigFromName($idTranslation);

      $this->getFields($entiy_type_id, $bundle);
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_display');
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_mode');
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_view_display');
    } else {
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
       */
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($entiy_type_id);
      if ($entityTypeDefinition instanceof \Drupal\Core\Config\Entity\ConfigEntityType) {
        if (!$id) {
          throw new \Exception(" Pour l'entite ($entiy_type_id) de configuration l'id doit etre definit ");
        }
        $name = $entityTypeDefinition->getConfigPrefix() . '.' . $id;
        $this->getConfigFromName($name);
        // il faudra peut etre gerer la traduction.

        /**
         * Les entités de configurations n'ont pas de champs.
         * Mais il faut essayer de charger la configuration de l'entite de
         * content issue de BundleOf.
         */
        $entity_content_id = $this->entityTypeManager()->getStorage($entiy_type_id)->getEntityType()->getBundleOf();
        if ($entity_content_id) {
          $this->generateAllConfigAboutEntity($entity_content_id, $id, $entiy_type_id);
        }
      } else {
        // Ces entites n'ont pas de données de configuration à ce niveau. Ils
        // sont fournir uniquement à partir d'un modele ou d'une configuration,
        // mais on peut en surcharger les configurations ( formDisplays et
        // viewDisplays ) qui en resulte.
        $this->getFields($entiy_type_id, $bundle);
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_display');
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_mode');
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_view_display');
      }
    }
  }

  /**
   * example :
   * $entiy_type_id = commerce_product_variation
   * $bundle = vetements
   *
   * @param string $entiy_type_id
   * @param string $bundle
   */
  public function getFields($entiy_type_id, $bundle) {
    $queryField = $this->entityTypeManager()->getStorage('field_config')->getQuery();
    $queryField->accessCheck(TRUE);
    $queryField->condition('entity_type', $entiy_type_id);
    $queryField->condition('bundle', $bundle);
    $ids = $queryField->execute();
    $this->getConfigFields($ids);
  }

  /**
   * à partir de toute configuration
   *
   * @param string $nameConf
   */
  protected function tryGetDependencies(string $nameConf) {
    $conf = \Drupal::config($nameConf);
    if ($conf) {
      $dependencies = $conf->get('dependencies');
      if (!empty($dependencies['config'])) {
        $this->getConfig($dependencies);
      }
    }
  }

  /**
   * Permet de faire du debug
   */
  protected function findOccurence($search, $string, $fonction_name, $datas = []) {
    if (str_contains($string, $search)) {
      dd($fonction_name, $string, $datas);
    }
  }
}
