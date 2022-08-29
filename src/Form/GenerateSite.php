<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem as FilesystemSymphony;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\system\Plugin\Archiver\Zip;

/**
 * Configure Export Import Entities settings for this site.
 */
class GenerateSite extends ConfigFormBase {
  protected static $keyEditable = "export_import_entities.generatesite";
  protected $currentDomaine;
  /**
   *
   * @var \Drupal\export_import_entities\Services\ExportEntities
   */
  protected $ExportEntities;

  /**
   *
   * @var FileSystem
   */
  protected $FileSystem;

  /**
   *
   * @var ArchiverManager
   */
  protected $ArchiverManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *        The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DomainNegotiator $DomainNegotiator, ExportEntities $ExportEntities, FileSystem $FileSystem, ArchiverManager $ArchiverManager) {
    parent::__construct($config_factory);
    $this->currentDomaine = $DomainNegotiator->getActiveDomain();
    $this->ExportEntities = $ExportEntities;
    $this->FileSystem = $FileSystem;
    $this->ArchiverManager = $ArchiverManager;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('domain.negotiator'), $container->get('export_import_entities.export.entites'), $container->get('file_system'), $container->get('plugin.manager.archiver'));
  }

  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'export_import_entities_generatesite';
  }

  /**
   *
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::$keyEditable
    ];
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $baseSite = DRUPAL_ROOT . '/../sites_exports/basic_model/';
    $path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id();
    if (!file_exists($path)) {
      $Filesystem = new FilesystemSymphony();
      $Filesystem->mkdir($path);
      $Filesystem->mirror($baseSite, $path);
      $this->messenger()->addStatus(' le dossier existe || ' . $path);
    }
    // $config = $this->config(static::$keyEditable);
    $form['generate_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generer les fichiers'),
      '#default_value' => 0
    ];
    $form['donwload_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Telecharger les fichiers'),
      '#default_value' => 1
    ];
    $form['#attributes']['class'][] = 'container';
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = 'Generer votre site';
    // dump($form);
    return $form;
  }

  function generateZip() {
    $pt = explode('/web', DRUPAL_ROOT);
    $baseZip = $pt[0] . '/sites_exports/zips/';
    $path = $pt[0] . '/sites_exports/' . $this->currentDomaine->id();
    if (!file_exists($path)) {
      $this->messenger()->addStatus('Vous devez generer les fichiers');
      return;
    }

    $Filesystem = new FilesystemSymphony();
    if (!file_exists($baseZip))
      $Filesystem->mkdir($baseZip);
    if (file_exists($baseZip . $this->currentDomaine->id() . ".zip"))
      $Filesystem->remove($baseZip . $this->currentDomaine->id() . ".zip");
    //
    // $archiveDir = 'public://pdf-export/';
    // $archivePath = $archiveDir . $this->currentDomaine->id() . '.zip';
    // $this->FileSystem->prepareDirectory($archiveDir,
    // FileSystemInterface::CREATE_DIRECTORY |
    // FileSystemInterface::MODIFY_PERMISSIONS);
    // $this->FileSystem->saveData('', $archivePath,
    // FileSystemInterface::EXISTS_REPLACE);

    // // On récupère l'objet Zip pointant vers l'archive que nous venons de
    // créer.
    // /**
    // *
    // * @var \Drupal\system\Plugin\Archiver\Zip $zip
    // */
    // $zip = $this->ArchiverManager->getInstance([
    // 'filepath' => $archivePath
    // ]);
    // On le fait via une commande Linux.
    // en principe on est dans web.
    // $script = " sudo ../ ";
    // $script .= " ls ";
    $script = " zip -r " . $baseZip . $this->currentDomaine->id() . ".zip  " . $path;
    $exc = $this->excuteCmd($script, 'RunNpm');
    //
    if ($exc['return_var']) {
      \Drupal::messenger()->addError(" Impossible de generer le fichier zip ");
    }
  }

  // Create zip
  function createZip(Zip $zip, $dir) {
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {

          // If file
          if (is_file($dir . $file)) {
            if ($file != '' && $file != '.' && $file != '..') {
              // $zip->addFile($dir . $file);
              $zip->add($dir . $file);
            }
          }
          else {
            // If directory
            if (is_dir($dir . $file)) {
              if ($file != '' && $file != '.' && $file != '..') {
                // Add empty directory
                $zip->addEmptyDir($dir . $file);
                $folder = $dir . $file . '/';
                // Read data of the folder
                createZip($zip, $folder);
              }
            }
          }
        }
        closedir($dh);
      }
    }
  }

  private function excuteCmd($cmd, $name = "excuteCmd") {
    ob_start();
    $return_var = '';
    $output = '';
    exec($cmd . " 2>&1", $output, $return_var);
    $result = ob_get_contents();
    ob_end_clean();
    $debug = [
      'output' => $output,
      'return_var' => $return_var,
      'result' => $result,
      'script' => $cmd
    ];
    return $debug;
  }

  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // if ($form_state->getValue('example') != 'example') {
    // $form_state->setErrorByName('example', $this->t('The value is not
    // correct.'));
    // }
    parent::validateForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('generate_files'))
      $this->ExportEntities->getEntites();
    if ($form_state->getValue('donwload_files')) {
      $this->generateZip();
      $form_state->setRedirect('export_import_entities.downloadsitezip');
    }

    //
    parent::submitForm($form, $form_state);
  }

}
