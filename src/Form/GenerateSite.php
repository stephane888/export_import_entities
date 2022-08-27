<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\export_import_entities\Services\ExportEntities;

/**
 * Configure Export Import Entities settings for this site.
 */
class GenerateSite extends ConfigFormBase {
  protected static $keyEditable = "export_import_entities.generatesite";
  protected $currentDomaine;
  /**
   *
   * @var Drupal\export_import_entities\Services\ExportEntities
   */
  protected $ExportEntities;

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
      $Filesystem = new Filesystem();
      $Filesystem->mkdir($path);
      $Filesystem->mirror($baseSite, $path);
      $this->messenger()->addStatus(' le dossier existe || ' . $path);
    }

    $form['example'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Example'),
      '#default_value' => $this->config(static::$keyEditable)->get('example'),
      '#description' => 'Nombre fichier generé > 233'
    ];
    $form['#attributes']['class'][] = 'container';
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = 'Generer votre site';
    // dump($form);
    return $form;
  }

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *        The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DomainNegotiator $DomainNegotiator, ExportEntities $ExportEntities) {
    parent::__construct($config_factory);
    $this->currentDomaine = $DomainNegotiator->getActiveDomain();
    $this->ExportEntities = $ExportEntities;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('domain.negotiator'), $container->get('export_import_entities.export.entites'));
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
    $this->ExportEntities->getEntites();
    $this->config(static::$keyEditable)->set('example', $form_state->getValue('example'))->save();
    parent::submitForm($form, $form_state);
  }

}
