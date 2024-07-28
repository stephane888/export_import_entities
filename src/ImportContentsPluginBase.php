<?php

namespace Drupal\export_import_entities;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileExists;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;

/**
 * Base class for style_scss plugins.
 */
abstract class ImportContentsPluginBase extends PluginBase implements ImportContentsInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;
  /**
   *
   * @var FileSystem
   */
  protected $file_system;
  /**
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;
  /**
   *
   * @var ExtensionPathResolver
   */
  protected $ExtensionPathResolver;
  /**
   * The messenger.
   *
   * MessengerInterface
   */
  protected $messenger;
  
  /**
   * Contient les données ou seront stocques les données.
   *
   * @var array
   */
  protected $prepareDirectories = [];
  
  /**
   * chemin de base pour les fichiers generées.
   *
   * @var string
   */
  private static $base_dir = '/src/Plugin/ImportContents/';
  private static $name_identification_file = "identification.json";
  
  /**
   * Constructs a StylePluginBase object.
   *
   * @param array $configuration
   *        A configuration array containing information about the plugin
   *        instance.
   * @param string $plugin_id
   *        The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *        The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *        The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, FileSystem $file_system, ExtensionPathResolver $ExtensionPathResolver, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->file_system = $file_system;
    $this->ExtensionPathResolver = $ExtensionPathResolver;
    $this->messenger = $messenger;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'), $container->get('file_system'), $container->get('extension.path.resolver'), $container->get(
      'messenger'));
  }
  
  public function defaultConfiguration(): array {
    return [];
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::saveContents()
   */
  function saveContents(array $datas, int $id, string $entity_id): void {
    if ($this->validateContents($datas) && $dirs = $this->prepareDirectories()) {
      $this->file_system->saveData(Json::encode($datas), $dirs['contents'] . '/' . $entity_id . $id . '.json', FileExists::Replace);
      $this->saveFiles($datas, $dirs['files']);
    }
    else {
      $this->messenger->addError("Impossible de sauvegarder le contenu");
    }
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::saveFiles()
   */
  function saveConfig(array $configs): void {
    if ($dirs = $this->prepareDirectories()) {
      foreach ($configs as $name => $config) {
        $this->file_system->saveData(Json::encode($config['value']), $dirs['config'] . '/' . $name . '.yml', FileExists::Replace);
      }
    }
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::saveFiles()
   */
  function saveFiles(array $datas, string $dir): void {
    //
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::identificationEntities()
   */
  function SaveIdentificationEntities(array $datas): array {
    if ($this->prepareDirectories()) {
      if ($datas['id']) {
        $confsEntities = $this->getIdentificationEntities();
        $confsEntities[$datas['id']] = $datas;
        $path = $this->getBaseDirectory() ? self::$base_dir . '/' . $this->getBaseDirectory() : self::$base_dir;
        $this->file_system->saveData(Json::encode($confsEntities), $path . '/' . self::$name_identification_file, FileExists::Replace);
      }
      else
        $this->messenger->addError("Paramettre manquant");
    }
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::getIdentificationEntities()
   */
  function getIdentificationEntities(): array {
    return $this->getJsonFile('identification');
  }
  
  /**
   * Retourne les données contenu dans un fichier json.
   *
   * @param string $type
   * @return array
   */
  protected function getJsonFile(string $type): array {
    switch ($type) {
      case 'identification':
        $path = $this->getBaseDirectory() ? self::$base_dir . '/' . $this->getBaseDirectory() : self::$base_dir;
        $rawString = file_get_contents($path . '/' . self::$name_identification_file);
        if ($rawString) {
          return Json::decode($rawString);
        }
        else
          return [];
        break;
      
      default:
        ;
        break;
    }
  }
  
  /**
   * Verifie que les dossiers sont ok pour l'export.
   */
  protected function prepareDirectories() {
    if (!$this->prepareDirectories) {
      $baseDir = DRUPAL_ROOT . '/' . $this->ExtensionPathResolver->getPath('module', $this->getPluginDefinition()['provider']);
      $directoryContents = $baseDir . self::$base_dir . $this->getContentDirectory();
      $directoryFiles = $baseDir . self::$base_dir . $this->getFilesDirectory();
      $directoryConfig = $baseDir . self::$base_dir . $this->getConfigDirectory();
      if ($this->file_system->prepareDirectory($directoryContents, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory(
        $directoryFiles, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory($directoryConfig,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->prepareDirectories = [
          'contents' => $directoryContents,
          'files' => $directoryFiles,
          'config' => $directoryConfig
        ];
      }
      else {
        $this->messenger->addError("Impossible de creer les dossiers");
        return false;
      }
    }
    return $this->prepareDirectories;
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::validateContents()
   */
  function validateContents(array $datas): bool {
    return true;
  }
  
  /**
   * Retourne le dossier qui contiendra les données.
   */
  protected function getContentDirectory(): string {
    return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] . '/default_contents' : 'default_contents';
  }
  
  /**
   * Retourne le dossier qui contiendra les images.
   */
  protected function getFilesDirectory(): string {
    return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] . '/default_files' : 'default_files';
  }
  
  /**
   * Retourne le dossier qui contiendra les configurations.
   */
  protected function getConfigDirectory(): string {
    return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] . '/config' : 'config';
  }
  
  /**
   *
   * @return string
   */
  protected function getBaseDirectory() {
    return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
  }
}