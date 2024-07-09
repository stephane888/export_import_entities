<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\export_import_entities\Services\LoadFormDisplays;
use Drupal\export_import_entities\Services\LoadViewDisplays;
use Drupal\export_import_entities\Services\LoadFormWrite;
use Drupal\export_import_entities\Services\LoadConfigs;
use Drupal\Core\Config\Entity\ConfigEntityType;

/**
 * Permet de selectionner une entité et de l'exporter.
 */
final class SelectExportStorageEntities extends ExportBase {
  
  /**
   * -
   *
   * @var EntityTypeManager
   */
  protected $EntityTypeManager;
  
  /**
   *
   * @var LoadFormDisplays
   */
  protected $LoadFormDisplays;
  
  /**
   *
   * @var LoadViewDisplays
   */
  protected $LoadViewDisplays;
  
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
   * --
   */
  public function __construct(EntityTypeManager $EntityTypeManager, LoadFormDisplays $LoadFormDisplays, LoadViewDisplays $LoadViewDisplays, LoadFormWrite $LoadFormWrite, LoadConfigs $LoadConfigs) {
    $this->EntityTypeManager = $EntityTypeManager;
    $this->LoadFormDisplays = $LoadFormDisplays;
    $this->LoadViewDisplays = $LoadViewDisplays;
    $this->LoadFormWrite = $LoadFormWrite;
    $this->LoadConfigs = $LoadConfigs;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('export_import_entities.export.form.displays'), $container->get('export_import_entities.export.view.displays'), $container->get(
      'export_import_entities.export.form.write'), $container->get("export_import_entities.export.form.LoadConfigs"));
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'export_import_entities_select_export_storage_entities';
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['entity_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity id'),
      '#required' => TRUE,
      '#options' => $this->getEntitiesListOptions(),
      '#ajax' => [
        'callback' => self::class . '::export_import_select_export_entity',
        'wrapper' => 'export_import_select_export_entity_id',
        'effect' => 'fade'
      ]
    ];
    $form['datas'] = [
      '#type' => 'details',
      '#open' => true,
      '#title' => t('datas'),
      '#attributes' => [
        'id' => 'export_import_select_export_entity_id'
      ],
      '#tree' => true
    ];
    return $form;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    //
  }
  
  /**
   *
   * @return []
   */
  protected function getEntitiesListOptions() {
    $options = [];
    foreach ($this->EntityTypeManager->getDefinitions() as $entity_id => $entity) {
      /**
       * --
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entity
       */
      if ($entity->getBaseTable())
        $options[$entity_id] = $entity->getLabel();
    }
    return $options;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The message has been sent.'));
  }
}