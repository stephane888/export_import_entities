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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityRepository;

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
   *
   * @var EntityRepository
   */
  protected $EntityRepository;
  
  /**
   * chemin de base pour les fichiers generées.
   *
   * @var string
   */
  private static $base_plugin = 'src/Plugin/ImportContents';
  private static $base_dir = null;
  protected static $base_directory = null;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, FileSystem $file_system, ExtensionPathResolver $ExtensionPathResolver, MessengerInterface $messenger, EntityTypeManager $EntityTypeManager, EntityRepository $EntityRepository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->file_system = $file_system;
    $this->ExtensionPathResolver = $ExtensionPathResolver;
    $this->messenger = $messenger;
    $this->EntityTypeManager = $EntityTypeManager;
    $this->EntityRepository = $EntityRepository;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'), $container->get('file_system'), $container->get('extension.path.resolver'), $container->get(
      'messenger'), $container->get('entity_type.manager'), $container->get('entity.repository'));
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
  function SaveIdentificationEntities(array $datas, $entity_id, $id): bool {
    if ($this->prepareDirectories()) {
      if ($datas['id']) {
        $confsEntities = $this->getIdentificationEntities();
        $request = Request::createFromGlobals();
        $filesUpload = $request->files->get('files');
        if ($filesUpload) {
          foreach ($filesUpload as $file) {
            /**
             *
             * @var UploadedFile $file
             */
            if ($file) {
              $fileContent = file_get_contents($file->getPathname());
              $datas['image'] = 'data:' . $file->getMimeType() . ';base64,' . base64_encode($fileContent);
            }
          }
        }
        else {
          $datas['image'] = $confsEntities[$entity_id . $id]['image'];
        }
        $confsEntities[$entity_id . $id] = $datas;
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
   * Recuperer les données à creer.
   *
   * @param string $base_directory
   * @param string $content_key
   */
  function getContent(string $base_directory, string $content_key) {
    self::$base_directory = $base_directory;
    $contents = $this->getJsonFile("contents");
    $page = !empty($contents[$content_key]) ? $contents[$content_key] : [];
    if ($page) {
      return $this->savePage($page);
    }
    return $page;
  }
  
  function savePage(array $page, $unique = true) {
    if (empty($page['entity']))
      throw new \ErrorException("Aucune entité n'a été definit");
    if (!empty($page['entities'])) {
      foreach ($page['entities'] as $field_name => $entities) {
        /**
         * On vide les anciennes ids.
         */
        $page['entity'][$field_name] = [];
        foreach ($entities as $entity) {
          /**
           *
           * @var \Drupal\node\Entity\Node $newEntity
           */
          $newEntity = $this->savePage($entity);
          $value = [
            'target_id' => $newEntity->id()
          ];
          $key = 'revision';
          if ($newEntity->getEntityType()->hasKey($key)) {
            $field_name_key = $newEntity->getEntityType()->getKey($key);
            $definition = $newEntity->getFieldDefinition($field_name_key);
            $property = $definition->getFieldStorageDefinition()->getMainPropertyName();
            $value['target_revision_id'] = $newEntity->get($field_name_key)->$property;
          }
          $page['entity'][$field_name][] = $value;
        }
        // dump($field_name, $page['entity'][$field_name]);
      }
    }
    /**
     *
     * @var \Drupal\node\NodeStorage $storage
     */
    $storage = $this->EntityTypeManager->getStorage($page['target_type']);
    /**
     * S'il faut recreer, il faut egalement vide le champs uuid
     */
    if (!$unique) {
      $page['entity']['uuid'] = [];
    }
    else {
      if (!empty($page['entity']['uuid'][0]['value'])) {
        $uuid = $page['entity']['uuid'][0]['value'];
        $oldEntity = $this->EntityRepository->loadEntityByUuid($page['target_type'], $uuid);
        if ($oldEntity)
          return $oldEntity;
      }
    }
    // On nettoie les revisions.
    if ($storage->getEntityType()->hasKey('revision')) {
      $revision_idKey = $storage->getEntityType()->getKey('revision');
      $page['entity'][$revision_idKey] = [];
    }
    $idKey = $storage->getEntityType()->getKey('id');
    if (!empty($page['entity'][$idKey])) {
      $page['entity'][$idKey] = [];
      $newEntity = $storage->create($page['entity']);
      $newEntity->save();
      return $newEntity;
    }
    else
      throw new \ErrorException("La clee d'id de l'entité n'a pas pu etre determinée.");
  }
  
  function getListePagesModeles() {
    $directory = $this->getBasePluginFull();
    $dirIterator = new \DirectoryIterator($directory);
    foreach ($dirIterator as $fileinfo) {
      if ($fileinfo->isDir() && !$fileinfo->isDot() && $_SERVER['HTTP_HOST'] != $fileinfo->getFilename()) {
        // dump("Répertoire trouvé : " . $fileinfo->getFilename());
      }
    }
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\export_import_entities\ImportContentsInterface::ListConfigToImport()
   */
  function ListConfigToImport() {
    $this->prepareDirectories();
    $options = [];
    //
    $directory = $this->getBasePluginFull();
    $dirIterator = new \DirectoryIterator($directory);
    foreach ($dirIterator as $fileinfo) {
      if ($fileinfo->isDir() && !$fileinfo->isDot() && $_SERVER['HTTP_HOST'] != $fileinfo->getFilename()) {
        self::$base_directory = $fileinfo->getFilename();
        foreach ($this->getIdentificationEntities() as $k => $page) {
          $page['site'] = self::$base_directory;
          $options[self::$base_directory . '--__' . $k] = $page;
        }
      }
    }
    self::$base_directory = null;
    return $options;
  }
  
  /**
   * Permet de construire un batch pour effectuer l'import.
   *
   * @param string $base_directory
   */
  function BuildBatchImportConfigs(string $base_directory) {
    $configsBatch = [];
    self::$base_directory = $base_directory;
    $pathConfig = $this->getConfigDirectory();
    $mask = '/.*\.yml$/';
    $filesConfigToImport = $this->file_system->scanDirectory($pathConfig, "$mask");
    $source = new \Drupal\Core\Config\FileStorage($pathConfig);
    /**
     *
     * @var \Drupal\Core\Config\CachedStorage $config_storage
     */
    $config_storage = \Drupal::service('config.storage');
    $configs = [];
    foreach ($filesConfigToImport as $fileConfigToImport) {
      $configs[$fileConfigToImport->name] = $source->read($fileConfigToImport->name);
    }
    /**
     *
     * @var \Drupal\export_import_entities\Services\ConfigImportCustom $import_config_custom
     */
    $import_config_custom = \Drupal::service("export_import_entities.import_config_custom");
    $import_config_custom->buildBatchImportConfigs($configs, $configsBatch);
    return $configsBatch;
  }
  
  /**
   * Import config.
   *
   * @param string $name
   * @param string $configData
   */
  function importConfig(string $name, string $configData) {
    /**
     *
     * @var \Drupal\export_import_entities\Services\ConfigImportCustom $import_config_custom
     */
    $import_config_custom = \Drupal::service("export_import_entities.import_config_custom");
    $arrayConfig = $import_config_custom->importConfig($name, $configData);
    /**
     * Les styles incluent "core.entity_view_display." ne seront pas charger car
     * cela est limité exclusivement à la MAJ du contenu au niveau du module
     * 'layoutgenentitystyles'.
     * Pour pallier à ce probleme, on ferra une sauvegarde suite à la creation
     * d'une entité de MAJ.
     */
    if (str_contains($name, "core.entity_view_display.")) {
      $entityView = $this->EntityTypeManager->getStorage("entity_view_display")->load($arrayConfig['id']);
      $entityView->save();
    }
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
        $path = $this->getBaseDirectory() . '/' . self::$name_identification_file;
        if (file_exists($path)) {
          $rawString = file_get_contents($path);
          if ($rawString) {
            return Json::decode($rawString);
          }
        }
        return [];
        break;
      case 'contents':
        $contents = [];
        $path = $this->getContentDirectory();
        $mask = '/.*/';
        $filesConfigToImport = $this->file_system->scanDirectory($path, "$mask");
        foreach ($filesConfigToImport as $fileConfigToImport) {
          $contents[$fileConfigToImport->name] = Yaml::decode(file_get_contents($fileConfigToImport->uri));
        }
        return $contents;
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
      $directoryContent = $this->getContentDirectory();
      $directoryFiles = $this->getFilesDirectory();
      $directoryConfig = $this->getConfigDirectory();
      if ($this->file_system->prepareDirectory($directoryContent, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory(
        $directoryFiles, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && $this->file_system->prepareDirectory($directoryConfig,
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
    $baseDir = $this->getBasePluginFull();
    if (!self::$base_directory) {
      self::$base_directory = $_SERVER['HTTP_HOST'];
    }
    return !empty(self::$base_directory) ? $baseDir . '/' . self::$base_directory : $baseDir;
  }
  
  protected function getBasePluginFull() {
    if (!self::$base_dir) {
      self::$base_dir = DRUPAL_ROOT . '/' . $this->ExtensionPathResolver->getPath('module', $this->getPluginDefinition()['provider']) . '/' . self::$base_plugin;
    }
    return self::$base_dir;
  }
}