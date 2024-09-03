<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\StorageComparer;
//
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Form\FormState;

/**
 * Permet d'importer les configurations suivant une logique propre à HBK.
 *
 * @author stephane
 *        
 */
class ConfigImportCustom {
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;
  /**
   * The event dispatcher used to notify subscribers.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;
  
  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;
  
  /**
   * The used lock backend instance.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;
  
  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;
  
  /**
   * List of configuration file changes processed by the import().
   *
   * @var array
   */
  protected $processedConfiguration;
  
  /**
   * List of extension changes processed by the import().
   *
   * @var array
   */
  protected $processedExtensions;
  
  /**
   * List of extension changes to be processed by the import().
   *
   * @var array
   */
  protected $extensionChangelist;
  
  /**
   * Indicates changes to import have been validated.
   *
   * @var bool
   */
  protected $validated;
  
  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;
  
  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;
  
  /**
   * Flag set to import system.theme during processing theme install and
   * uninstalls.
   *
   * @var bool
   */
  protected $processedSystemTheme = FALSE;
  
  /**
   * A log of any errors encountered.
   *
   * If errors are logged during the validation event the configuration
   * synchronization will not occur. If errors occur during an import then best
   * efforts are made to complete the synchronization.
   *
   * @var array
   */
  protected $errors = [];
  
  /**
   * The total number of extensions to process.
   *
   * @var int
   */
  protected $totalExtensionsToProcess = 0;
  
  /**
   * The total number of configuration objects to process.
   *
   * @var int
   */
  protected $totalConfigurationToProcess = 0;
  
  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;
  
  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;
  
  /**
   * The theme extension list.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeExtensionList;
  
  /**
   * The messenger.
   *
   * @var MessengerInterface
   */
  protected $messenger;
  
  /**
   *
   * @var TranslationInterface
   */
  protected $stringTranslation;
  
  public function __construct(StorageInterface $config_storage, EventDispatcherInterface $event_dispatcher, ConfigManagerInterface $config_manager, LockBackendInterface $lock, TypedConfigManagerInterface $typed_config, ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer, ThemeHandlerInterface $theme_handler, TranslationInterface $string_translation, ModuleExtensionList $extension_list_module, ThemeExtensionList $extension_list_theme, MessengerInterface $messenger) {
    $this->configStorage = $config_storage;
    $this->moduleExtensionList = $extension_list_module;
    $this->eventDispatcher = $event_dispatcher;
    $this->configManager = $config_manager;
    $this->lock = $lock;
    $this->typedConfigManager = $typed_config;
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    $this->themeHandler = $theme_handler;
    $this->stringTranslation = $string_translation;
    $this->themeExtensionList = $extension_list_theme;
    $this->messenger = $messenger;
  }
  
  /**
   * Permet d'importer une configurations et ses depences.
   *
   * @param array $configDatas
   *        // ['name1'=>'config', 'name2'=>'config']
   */
  function importCustomConfig(array $configDatas) {
    foreach ($configDatas as $name => $configData) {
      $this->ImportConfigRecurssive($name, $configData, $configDatas);
    }
  }
  
  function buildBatchImportConfigs(array $configDatas, array &$configsBatch) {
    foreach ($configDatas as $name => $configData) {
      $this->ImportArrayBash($name, $configData, $configDatas, $configsBatch);
    }
  }
  
  /**
   * Importe les configurations.
   *
   * @param string $name
   * @param string $configData
   */
  function importConfig(string $name, string $configData) {
    $configData = Yaml::decode($configData);
    $config = \Drupal::config($name);
    if ($config->isNew()) {
      $source_storage = new StorageReplaceDataWrapper($this->configStorage);
      $source_storage->replaceData($name, $configData);
      $storage_comparer = new StorageComparer($source_storage, $this->configStorage);
      $storage_comparer->createChangelist();
      if ($storage_comparer->hasChanges()) {
        /**
         * On verifie s'il ya des dependences de module.
         */
        if (!empty($configData['dependencies']['module'])) {
          foreach ($configData['dependencies']['module'] as $module) {
            if (!$this->moduleHandler->moduleExists($module)) {
              throw new \ErrorException(" La module : '$module', n'est pas installé. ");
            }
          }
        }
        /**
         * On verifie s'il ya des dependences de config
         */
        if (!empty($configData['dependencies']['config'])) {
          foreach ($configData['dependencies']['config'] as $sub_name) {
            $Sub_config = \Drupal::config($sub_name);
            if ($Sub_config->isNew()) {
              throw new \ErrorException(" La configuration : '$sub_name', n'est pas importer. ");
            }
          }
        }
        $config_importer = new ConfigImporter($storage_comparer, $this->eventDispatcher, $this->configManager, $this->lock, $this->typedConfigManager, $this->moduleHandler, $this->moduleInstaller, $this->themeHandler, $this->getStringTranslation(), $this->moduleExtensionList, $this->themeExtensionList);
        if ($config_importer->validate()) {
          if ($config_importer->alreadyImporting()) {
            $this->messenger->addError($this->t('Another request may be importing configuration already.'));
          }
          else {
            $config_importer->import();
            /**
             * Les styles incluent dans la config des layouts ne seront pas
             * chargés, car cela se fait uniquement pendant la sauvegarde du
             * layout.
             */
            if (str_contains($name, "core.entity_view_display.")) {
              if (!empty($configData['third_party_settings']['layout_builder']['sections'])) {
                $form_state = new FormState();
                $form = [];
                /**
                 *
                 * @var \Drupal\layout_custom_style\StyleScssPluginManager $PluginManager
                 */
                $PluginManager = \Drupal::service("plugin.manager.style_scss");
                foreach ($configData['third_party_settings']['layout_builder']['sections'] as $section) {
                  if (!empty($section['layout_settings']['scss']['scss_field'])) {
                    $form_state->setValue('scss', $section['layout_settings']['scss']);
                    $storage = $section['layout_settings'];
                    $PluginManager->submitConfigurationForm($form, $form_state, $storage);
                  }
                }
              }
            }
          }
        }
      }
    }
    return $configData;
  }
  
  /**
   * Il est important de construire l'import des configs dans un bash afin de ne
   * pas saturer l'environnement d'import.
   *
   * @param string $name
   * @param array $configData
   * @param array $configDatas
   */
  protected function ImportArrayBash(string $name, array $configData, array $configDatas, array &$configsBatch) {
    $config = \Drupal::config($name);
    if ($config->isNew() && empty($configsBatch[$name])) {
      $source_storage = new StorageReplaceDataWrapper($this->configStorage);
      $source_storage->replaceData($name, $configData);
      $storage_comparer = new StorageComparer($source_storage, $this->configStorage);
      $storage_comparer->createChangelist();
      if ($storage_comparer->hasChanges()) {
        /**
         * On verifie s'il ya des dependences de module.
         */
        if (!empty($configData['dependencies']['module'])) {
          foreach ($configData['dependencies']['module'] as $module) {
            if (!$this->moduleHandler->moduleExists($module)) {
              throw new \ErrorException(" La module : '$module', n'est pas installé. ");
            }
          }
        }
        /**
         * On verifie s'il ya des dependences de config
         */
        if (!empty($configData['dependencies']['config'])) {
          foreach ($configData['dependencies']['config'] as $sub_name) {
            $Sub_config = \Drupal::config($sub_name);
            if ($Sub_config->isNew()) {
              if (!empty($configDatas[$sub_name]))
                $this->ImportArrayBash($sub_name, $configDatas[$sub_name], $configDatas, $configsBatch);
              else {
                throw new \ErrorException(" La configuration : '$sub_name', n'est pas definit dans la liste des configurations à importer. ");
              }
            }
          }
        }
        //
        $configsBatch[$name] = Yaml::encode($configDatas[$name]);
      }
    }
  }
  
  /**
   * Cette matrice permet de construire un tableau multi-dimensionnelle
   * permettant d'instammer les configs n'ayant pas de depence ou celle donc les
   * depence existe deja.
   */
  protected function ImportConfigRecurssive(string $name, array $configData, array $configDatas) {
    $config = \Drupal::config($name);
    if ($config->isNew()) {
      $source_storage = new StorageReplaceDataWrapper($this->configStorage);
      $source_storage->replaceData($name, $configData);
      $storage_comparer = new StorageComparer($source_storage, $this->configStorage);
      $storage_comparer->createChangelist();
      if ($storage_comparer->hasChanges()) {
        /**
         * On verifie s'il ya des dependences de module.
         */
        if (!empty($configData['dependencies']['module'])) {
          foreach ($configData['dependencies']['module'] as $module) {
            if (!$this->moduleHandler->moduleExists($module)) {
              throw new \ErrorException(" La module : '$module', n'est pas installé. ");
            }
          }
        }
        /**
         * On verifie s'il ya des dependences de config.
         */
        if (!empty($configData['dependencies']['config'])) {
          foreach ($configData['dependencies']['config'] as $sub_name) {
            $Sub_config = \Drupal::config($sub_name);
            if ($Sub_config->isNew()) {
              if (!empty($configDatas[$sub_name]))
                $this->ImportConfigRecurssive($sub_name, $configDatas[$sub_name], $configDatas);
              else {
                throw new \ErrorException(" La configuration : '$sub_name', n'est pas definit dans la liste des configurations à importer. ");
              }
            }
          }
        }
        $config_importer = new ConfigImporter($storage_comparer, $this->eventDispatcher, $this->configManager, $this->lock, $this->typedConfigManager, $this->moduleHandler, $this->moduleInstaller, $this->themeHandler, $this->getStringTranslation(), $this->moduleExtensionList, $this->themeExtensionList);
        if ($config_importer->validate()) {
          if ($config_importer->alreadyImporting()) {
            $this->messenger->addError($this->t('Another request may be importing configuration already.'));
          }
          else {
            $config_importer->import();
          }
        }
      }
    }
  }
  
  /**
   * Gets the string translation service.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface The string
   *         translation service.
   */
  protected function getStringTranslation() {
    if (!$this->stringTranslation) {
      $this->stringTranslation = \Drupal::service('string_translation');
    }
    
    return $this->stringTranslation;
  }
}