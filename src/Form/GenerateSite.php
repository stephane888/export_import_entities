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
  protected $maxStep = 2;

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
  public function buildForm(array $form, FormStateInterface $form_state, $domaineId = null) {
    $form = parent::buildForm($form, $form_state);
    if ($domaineId) {
      $this->currentDomaine = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
      if (!$this->currentDomaine) {
        \Drupal::messenger()->addWarning('Le domaine definit ne correspond à aucun donc vous avez acces');
        return [];
      }
    }
    $baseSite = DRUPAL_ROOT . '/../sites_exports/basic_model/';
    $path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id();
    if (!file_exists($path)) {
      $Filesystem = new FilesystemSymphony();
      $Filesystem->mkdir($path);
      $Filesystem->mirror($baseSite, $path);
      $this->messenger()->addStatus(' le dossier existe || ' . $path);
    }

    //
    if (!$form_state->has('step')) {
      $form_state->set('step', 1);
    }
    $step = $form_state->get('step');
    if ($step == 1) {
      $form['generate_files'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Generer les fichiers'),
        '#default_value' => 1
      ];
      $this->actionButtons($form, $form_state);
    }
    elseif ($step == 2) {
      $form['donwload_files'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Telecharger les fichiers'),
        '#default_value' => 1
      ];
    }

    // $config = $this->config(static::$keyEditable);
    $form['#attributes']['class'][] = 'container';
    $form['actions']['submit']['#value'] = 'Generer et telecharger les fichiers de votre site';

    return $form;
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function actionButtons(array &$form, FormStateInterface $form_state, $title_next = "Suivant", $submit_next = 'nextSubmit', $title_preview = "Precedent") {
    if ($form_state->get('step') > 1)
      $form['actions']['preview'] = [
        '#type' => 'submit',
        '#value' => $title_preview,
        '#button_type' => 'secondary',
        '#submit' => [
          [
            $this,
            'previewsSubmit'
          ]
        ]
      ];
    if ($form_state->get('step') < $this->maxStep)
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $title_next,
        '#button_type' => 'secondary',
        '#submit' => [
          [
            $this,
            $submit_next
          ]
        ]
      ];
    if ($form_state->get('step') >= $this->maxStep) {
      $form = parent::buildForm($form, $form_state);
      if (!empty($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = 'Terminer le processus';
      }
    }
    else
      $form['actions']['submit']['#access'] = false;
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function nextSubmit(array &$form, FormStateInterface $form_state) {
    $nextStep = $form_state->get('step') + 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    if ($form_state->getValue('generate_files')) {
      $this->ExportEntities->getEntites();
      \Drupal::messenger()->addStatus(' Les fichiers de configurations ont été generer ');
    }
    //
    $form_state->set('step', $nextStep);
    $form_state->setRebuild();
  }

  public function previewSubmit(array &$form, FormStateInterface $form_state) {
    $pvStep = $form_state->get('step') - 1;
    if ($pvStep <= 0)
      $pvStep = 1;
    $form_state->set('step', $pvStep);
    $form_state->setRebuild();
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
    $script = " cd  ../ && ";
    // $script .= " ls ";
    // $script .= " zip -r " . $baseZip . $this->currentDomaine->id() . ".zip "
    // . $path;
    $script .= " cd sites_exports/ && ";
    // $script .= " pwd ";
    $script .= " zip -r zips/" . $this->currentDomaine->id() . ".zip  " . $this->currentDomaine->id();
    $exc = $this->excuteCmd($script, 'RunNpm');
    if ($exc['return_var']) {
      \Drupal::messenger()->addError(" Impossible de generer le fichier zip ");
      return false;
    }
    return true;
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
    if ($form_state->getValue('donwload_files')) {
      if ($this->generateZip())
        $form_state->setRedirect('export_import_entities.downloadsitezip');
    }
    //
    parent::submitForm($form, $form_state);
  }

}
