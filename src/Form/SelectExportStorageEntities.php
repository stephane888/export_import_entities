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
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\apivuejs\Services\DuplicateEntityReference;
use Drupal\apivuejs\Services\GenerateForm;
use Stephane888\Debug\debugLog;

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
   *
   * @var DuplicateEntityReference
   */
  protected $DuplicateEntityReference;
  /**
   *
   * @var \Drupal\apivuejs\Services\GenerateForm
   */
  protected $GenerateForm;
  
  /**
   * --
   */
  public function __construct(EntityTypeManager $EntityTypeManager, LoadFormDisplays $LoadFormDisplays, LoadViewDisplays $LoadViewDisplays, LoadFormWrite $LoadFormWrite, LoadConfigs $LoadConfigs, DuplicateEntityReference $DuplicateEntityReference, GenerateForm $GenerateForm) {
    $this->EntityTypeManager = $EntityTypeManager;
    $this->LoadFormDisplays = $LoadFormDisplays;
    $this->LoadViewDisplays = $LoadViewDisplays;
    $this->LoadFormWrite = $LoadFormWrite;
    $this->LoadConfigs = $LoadConfigs;
    $this->GenerateForm = $GenerateForm;
    $this->DuplicateEntityReference = $DuplicateEntityReference;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('export_import_entities.export.form.displays'), $container->get('export_import_entities.export.view.displays'), $container->get(
      'export_import_entities.export.form.write'), $container->get("export_import_entities.export.form.LoadConfigs"), $container->get('apivuejs.duplicate_reference'), $container->get(
      'apivuejs.getform'));
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
    $entity_id = $form_state->getValue('entity_id');
    $this->LoadConfigs->setSaveIt(FALSE);
    $this->LoadConfigs->setRemoveUUID(TRUE);
    $this->LoadConfigs->setRemoveDefaultValue(FALSE);
    if ($entity_id) {
      $bundlesOptions = $this->getContentEntities($entity_id);
      $form['datas']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#required' => TRUE,
        '#options' => $bundlesOptions,
        '#ajax' => [
          'callback' => self::class . '::export_import_select_export_entity',
          'wrapper' => 'export_import_select_export_entity_id',
          'effect' => 'fade'
        ]
      ];
      $id = $form_state->getValue([
        'datas',
        'bundle'
      ]);
      if ($id) {
        /**
         *
         * @var \Drupal\node\Entity\Node $entity
         */
        $entity = $this->EntityTypeManager->getStorage($entity_id)->load($id);
        $bundle = $entity->bundle() ? $entity->bundle() : $entity_id;
        $BundleEntityType = $entity->getEntityType()->getBundleEntityType();
        $this->LoadConfigs->generateAllConfigAboutEntity($entity_id, $bundle, $BundleEntityType);
        $this->getOrthersConfig($entity);
        
        foreach ($this->LoadConfigs->getGenerate() as $key => $value) {
          $form['datas'][$key] = [
            '#type' => 'details',
            '#open' => false,
            '#title' => $key
          ];
          $form['datas'][$key]['value'] = [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => $value['value'],
            '#attributes' => [
              'style' => "word-wrap:break-word;"
            ]
          ];
        }
        //
        $form['datas']['actions'] = [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->t('Save config'),
            '#ajax' => [
              'callback' => self::class . '::export_import_submit_callback',
              'wrapper' => 'export_import_select_export_entity_id',
              'effect' => 'fade'
            ],
            '#submit' => [
              // focntionne mais la methode doit etre statique.
              // self::class .
              // '::export_import_submit_save_config'
              '::export_import_submit_save_config'
            ]
          ]
        ];
      }
    }
    return $form;
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_stat
   */
  public function export_import_submit_save_config(array &$form, FormStateInterface $form_state) {
    $entity_id = $form_state->getValue('entity_id');
    $id = $form_state->getValue([
      'datas',
      'bundle'
    ]);
    if ($entity_id && $id) {
      $this->LoadConfigs->setSaveIt(TRUE);
      $this->LoadConfigs->setRemoveUUID(TRUE);
      $this->LoadConfigs->setRemoveDefaultValue(FALSE);
      //
      /**
       *
       * @var \Drupal\node\Entity\Node $entity
       */
      $entity = $this->EntityTypeManager->getStorage($entity_id)->load($id);
      $bundle = $entity->bundle() ? $entity->bundle() : $entity_id;
      $BundleEntityType = $entity->getEntityType()->getBundleEntityType();
      $this->LoadConfigs->generateAllConfigAboutEntity($entity_id, $bundle, $BundleEntityType);
      $this->getOrthersConfig($entity);
      // debugLog::$path
      $EntitiesArray = $this->generateFormMatrice($entity_id, $entity, $bundle);
      // debugLog::logger($string, $name . '.yml', false, 'file');
      \Drupal::messenger()->addStatus(" Données de configuration exporter à l'emplacement definit. ", true);
    }
    else {
      \Drupal::messenger()->addWarning(" Aucune données definies. ", true);
    }
  }
  
  /**
   * Permet de generer les configurations liées au entites references.
   *
   * @param ContentEntityBase $entity
   */
  protected function getOrthersConfig(ContentEntityBase $entity) {
    foreach ($entity->getFieldDefinitions() as $fieldName => $field) {
      /**
       *
       * @var \Drupal\field\Entity\FieldConfig $field
       */
      if ($field instanceof \Drupal\field\Entity\FieldConfig) {
        $entity_type_id = $field->getSetting("target_type");
        if ($entity_type_id) {
          /**
           *
           * @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $FieldItemList
           */
          $FieldItemList = $entity->{$fieldName};
          foreach ($FieldItemList->getValue() as $value) {
            /**
             *
             * @var ContentEntityBase $subEntity
             */
            $subEntity = $this->EntityTypeManager->getStorage($entity_type_id)->load($value['target_id']);
            if ($subEntity) {
              $bundle = $subEntity->bundle() ? $subEntity->bundle() : $entity_type_id;
              $BundleEntityType = $subEntity->getEntityType()->getBundleEntityType();
              $this->LoadConfigs->generateAllConfigAboutEntity($entity_type_id, $bundle, $BundleEntityType);
            }
          }
        }
      }
    }
  }
  
  /**
   * * Permet de generer un tableau multi-dimentionnelle permettant de creer le
   * contenus de maniere recursive.
   * Cela permet de creer un enssemble de contenu sans pour autant surcharger
   * les ressources.
   *
   * @param string $entity_type_id
   * @param string $bundle
   * @param string $view_mode
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   */
  protected function generateFormMatrice($entity_type_id, \Drupal\Core\Entity\ContentEntityBase $entity, $bundle, $duplicate = true, $add_form = true, $view_mode = 'default') {
    $form = $this->GenerateForm->getForm($entity_type_id, $bundle, $view_mode, $entity->createDuplicate());
    // Ajout de la configuration des champs layout_builder__layout. ( il faudra
    // completer l'issue ).
    $this->DuplicateEntityReference->toArrayLayoutBuilderField($form['entity']);
    $entities = [];
    $this->DuplicateEntityReference->duplicateExistantReference($entity, $entities, $duplicate, $add_form);
    $form['entities'] = $entities;
    return $form;
  }
  
  /**
   *
   * @param string $entity_type_id
   * @return string[]
   */
  protected function getContentEntities($entity_type_id) {
    $options = [];
    $entities = $this->EntityTypeManager->getStorage($entity_type_id)->loadMultiple();
    foreach ($entities as $entity) {
      $label = $entity->id() . ' - ' . $entity->label();
      $label = $entity->bundle() ? $label . ' (' . $entity->bundle() . ')' : $label;
      $options[$entity->id()] = $label;
    }
    return $options;
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
    $this->messenger()->addStatus($this->t("The message has been sent."));
  }
}