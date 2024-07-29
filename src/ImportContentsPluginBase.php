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
use Drupal\Core\Entity\EntityTypeManager;

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
   *
   * @var EntityTypeManager
   */
  protected $EntityTypeManager;
  
  /**
   * chemin de base pour les fichiers generées.
   *
   * @var string
   */
  private static $base_plugin = 'src/Plugin/ImportContents';
  private static $base_dir = null;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, FileSystem $file_system, ExtensionPathResolver $ExtensionPathResolver, MessengerInterface $messenger, EntityTypeManager $EntityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->file_system = $file_system;
    $this->ExtensionPathResolver = $ExtensionPathResolver;
    $this->messenger = $messenger;
    $this->EntityTypeManager = $EntityTypeManager;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'), $container->get('file_system'), $container->get('extension.path.resolver'), $container->get(
      'messenger'), $container->get('entity_type.manager'));
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
      $this->saveFiles($datas, $dirs['files'], $id, $entity_id);
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
        $this->file_system->saveData($config['value'], $dirs['config'] . '/' . $name . '.yml', FileExists::Replace);
      }
    }
  }
  
  /**
   * Permet d'exporter les fichiers et les mettre dans un fichier json.
   *
   * @see \Drupal\export_import_entities\ImportContentsInterface::saveFiles()
   */
  protected function saveFiles(array $datas, string $path, int $id, string $entity_id): void {
    $files = [];
    $this->retriveFiles($datas, $files);
    $this->file_system->saveData(Json::encode($files), $path . '/' . $entity_id . $id . '__files.json', FileExists::Replace);
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::identificationEntities()
   */
  function SaveIdentificationEntities(array $datas): bool {
    if ($this->prepareDirectories()) {
      if ($datas['id']) {
        $confsEntities = $this->getIdentificationEntities();
        $confsEntities[$datas['id']] = $datas;
        $result = $this->file_system->saveData(Json::encode($confsEntities), $this->getBaseDirectory() . '/' . self::$name_identification_file, FileExists::Replace);
        return $result ? true : false;
      }
      else
        $this->messenger->addError(" Paramettre manquant ");
    }
    return false;
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
        $rawString = file_get_contents($this->getBaseDirectory() . '/' . self::$name_identification_file);
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
      if ($this->file_system->prepareDirectory($this->getContentDirectory(), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory(
        $this->getFilesDirectory(), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory($this->getConfigDirectory(),
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->prepareDirectories = [
          'contents' => $this->getContentDirectory(),
          'files' => $this->getFilesDirectory(),
          'config' => $this->getConfigDirectory()
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
   * Permet de recuperer les fichiers contenus dans la matrice.
   *
   * @param array $data
   * @param array $files
   */
  protected function retriveFiles(array $data, array &$files) {
    if (!empty($data['entity']) && !empty($data['target_type'])) {
      /**
       *
       * @var \Drupal\node\NodeStorage $storage
       */
      $storage = $this->EntityTypeManager->getStorage($data['target_type']);
      $idKey = $storage->getEntityType()->getKey('id');
      $id = null;
      if (!empty($data['entity'][$idKey][0])) {
        $id = $data['entity'][$idKey][0]['value'];
      }
      if ($id) {
        /**
         * On essaie de filtrer tous les champs de types files, images et on
         * recupere les fichiers.
         *
         * @var \Drupal\node\Entity\Node $entity
         */
        $entity = $storage->load($id);
        $fields = $entity->getFieldDefinitions();
        foreach ($fields as $field_name => $field) {
          /**
           *
           * @var \Drupal\Core\Field\BaseFieldDefinition $field
           */
          if ($field->getType() == 'image' || $field->getType() == 'file' || $field->getType() == 'more_fields_hbk_file') {
            $values = $data['entity'][$field_name];
            foreach ($values as $delta => $value) {
              $files[$data['target_type']][$id][$delta] = $value;
              $file = \Drupal\file\Entity\File::load($value['target_id']);
              if ($file) {
                $files[$data['target_type']][$id][$delta]["default_encode_file"] = 'data:' . $file->getMimeType() . ';base64,' . base64_encode(file_get_contents($file->getFileUri()));
                $files[$data['target_type']][$id][$delta]["default_filename"] = $file->getFilename();
              }
            }
          }
        }
        if (!empty($data['entities'])) {
          foreach ($data['entities'] as $entities) {
            foreach ($entities as $data) {
              $this->retriveFiles($data, $files);
            }
          }
        }
      }
    }
    else {
      $this->messenger->addError("entite mal definie", true);
    }
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
    return $this->getBaseDirectory() . '/default_contents';
  }
  
  /**
   * Retourne le dossier qui contiendra les images.
   */
  protected function getFilesDirectory(): string {
    return $this->getBaseDirectory() . '/default_files';
  }
  
  /**
   * Retourne le dossier qui contiendra les configurations.
   */
  protected function getConfigDirectory(): string {
    return $this->getBaseDirectory() . '/config';
  }
  
  /**
   * Recupere le dossier de base pour la sauvegarde des données.
   *
   * @return string
   */
  protected function getBaseDirectory() {
    if (!self::$base_dir) {
      $baseDir = DRUPAL_ROOT . '/' . $this->ExtensionPathResolver->getPath('module', $this->getPluginDefinition()['provider']) . '/' . self::$base_plugin;
      self::$base_dir = !empty($_SERVER['HTTP_HOST']) ? $baseDir . '/' . $_SERVER['HTTP_HOST'] : $baseDir;
    }
    return self::$base_dir;
  }
}