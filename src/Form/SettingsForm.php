<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ProfileExtensionList;

/**
 * Configure Export Import Entities settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  
  /**
   *
   * @var ProfileExtensionList
   */
  protected $ProfileExtensionList;
  
  public function __construct(ConfigFactoryInterface $config_factory, ProfileExtensionList $ProfileExtensionList, protected $typedConfigManager = NULL) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->ProfileExtensionList = $ProfileExtensionList;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('extension.list.profile'), $container->get('config.typed'));
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'export_import_entities_settings';
  }
  
  /**
   *
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'export_import_entities.settings'
    ];
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('export_import_entities.settings');
    //
    $profilesOptions = [];
    foreach ($this->ProfileExtensionList->getList() as $name => $profile) {
      /**
       *
       * @var $profile \Drupal\Core\Extension\Extension $profile
       */
      $profilesOptions[$name] = $profile->getName();
    }
    $profilesOptions['custom_callback'] = "Definie automatiquement au niveau du code";
    $form['save_data'] = [
      '#type' => 'select',
      '#title' => $this->t(" Selectionner le profile ou seront stocker les données "),
      '#required' => TRUE,
      '#options' => $profilesOptions,
      '#default_value' => $config->get('save_data')
    ];
    
    $form['config_is_required'] = [
      '#type' => 'checkbox',
      '#title' => "Ces données sont t'elles requises",
      '#default_value' => $config->get('config_is_required')
    ];
    
    // dump($config->getRawData());
    $form['list_entities'] = [
      '#type' => 'details',
      '#title' => $this->t(" Liste d'entités "),
      '#tree' => true
    ];
    //
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entities as $entity) {
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entity
       */
      $table = $entity->getBaseTable();
      if ($table) {
        $table = ' => ContentEntity (' . $table . ')';
      }
      
      $form['list_entities'][$entity->id()] = [
        '#type' => 'checkbox',
        '#title' => $entity->getLabel() . $table,
        '#default_value' => $config->get('list_entities.' . $entity->id()) ? $config->get('list_entities.' . $entity->id()) : 0
      ];
    }
    //
    $form['export_orthers_entities'] = [
      '#type' => 'checkbox',
      '#title' => 'Exporter les données basique (langue, editeurs, filtre de test)',
      '#default_value' => $config->get('export_orthers_entities')
    ];
    //
    $form['export_image_styles'] = [
      '#type' => 'checkbox',
      '#title' => "Exporter les styles d'image",
      '#default_value' => $config->get('export_image_styles')
    ];
    //
    $form['export_menus'] = [
      '#type' => 'checkbox',
      '#title' => "Exporter les menus",
      '#default_value' => $config->get('export_menus')
    ];
    //
    return parent::buildForm($form, $form_state);
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
    $config = $this->config('export_import_entities.settings');
    $config->set('list_entities', $form_state->getValue('list_entities'));
    $config->set('export_orthers_entities', $form_state->getValue('export_orthers_entities'));
    $config->set('export_orthers_entities', $form_state->getValue('export_orthers_entities'));
    $config->set('export_image_styles', $form_state->getValue('export_image_styles'));
    $config->set('export_menus', $form_state->getValue('export_menus'));
    $config->set('save_data', $form_state->getValue('save_data'));
    $config->set('config_is_required', $form_state->getValue('config_is_required'));
    $config->save();
    parent::submitForm($form, $form_state);
  }
  
}
