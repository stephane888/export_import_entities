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
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/**
 * Permet de selectionner une entité et de l'exporter.
 */
final class SelectExportEntity extends ExportBase {
  
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
    return 'export_import_entities_select_export_entity';
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
      
      if ($bundlesOptions = $this->entityconfigHasBundle($entity_id)) {
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
        $bundle = $form_state->getValue([
          'datas',
          'bundle'
        ]);
      }
      else {
        $bundle = $entity_id;
      }
      if ($bundle) {
        $bundleOf = $this->EntityTypeManager->getStorage($entity_id)->getEntityType()->getBundleOf();
        if (!$bundleOf)
          $bundleOf = $entity_id;
        $bundles = [
          $bundle => $bundle
        ];
        if ($entity_id != $bundle) {
          foreach ($bundles as $bundle_id) {
            $this->LoadConfigs->generateAllConfigAboutEntity($bundleOf, $bundle_id);
          }
          $this->LoadViewDisplays->getDisplays($bundleOf, $bundles);
          $this->LoadFormDisplays->getDisplays($bundleOf, $bundles);
          $this->LoadFormWrite->getDisplays($bundleOf, $bundles);
        }
        else {
          $entityType = $this->EntityTypeManager->getStorage($entity_id)->getEntityType();
          if ($entityType instanceof ConfigEntityType) {
            $bundlesOptions = $this->getEntitiesConfig($entity_id);
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
            $id_string = $form_state->getValue([
              'datas',
              'bundle'
            ]);
            if ($id_string) {
              $this->LoadConfigs->generateAllConfigAboutEntity($entity_id, $entity_id, NULL, $id_string);
            }
          }
        }
        $form_state->set('entity_type', $bundleOf);
        $form_state->set('bundles', $bundles);
        //
        // On doit affficher les depences liées au module afin de pouvoir
        // determiner les incoherences.
        $reqModules = $this->LoadConfigs->getConfigModules();
        $form['datas']['req_modules'] = [
          '#type' => 'details',
          '#open' => false,
          '#title' => 'Modules requis (' . count($reqModules) . ')'
        ];
        foreach ($reqModules as $module => $configsName) {
          $form['datas']['req_modules'][$module] = [
            '#type' => 'details',
            '#open' => false,
            '#title' => $module
          ];
          $links = [];
          foreach ($configsName as $config_name) {
            $links[] = [
              'title' => Markup::create($config_name),
              'url' => Url::fromUserInput('#')
            ];
          }
          $form['datas']['req_modules'][$module]['configs_name'] = [
            '#theme' => 'links',
            '#links' => $links,
            '#attributes' => [
              'class' => []
            ]
          ];
        }
        //
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
    $bundleOf = $form_state->get('entity_type');
    $bundles = $form_state->get('bundles');
    if ($bundleOf && $bundles) {
      $this->LoadConfigs->setSaveIt(TRUE);
      $this->LoadConfigs->setRemoveUUID(TRUE);
      $this->LoadConfigs->setRemoveDefaultValue(FALSE);
      //
      foreach ($bundles as $bundle_id) {
        $this->LoadConfigs->generateAllConfigAboutEntity($bundleOf, $bundle_id);
      }
      $this->LoadViewDisplays->getDisplays($bundleOf, $bundles);
      $this->LoadFormDisplays->getDisplays($bundleOf, $bundles);
      $this->LoadFormWrite->getDisplays($bundleOf, $bundles);
      // $this->LoadConfigs->generateAllConfigAboutEntity($entity_id, $bundle);
      \Drupal::messenger()->addStatus(" Données de configuration exporter à l'emplacement definit. ", true);
    }
    else {
      \Drupal::messenger()->addWarning(" Aucune données definies. ", true);
    }
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
      if (!$entity->getBaseTable())
        $options[$entity_id] = $entity->getLabel();
    }
    return $options;
  }
  
  /**
   *
   * @param string $entity_type_id
   * @return []
   */
  public function entityconfigHasBundle(string $entity_type_id) {
    if ($this->EntityTypeManager->getStorage($entity_type_id)->getEntityType()->getBundleOf()) {
      $options = [];
      foreach ($this->EntityTypeManager->getStorage($entity_type_id)->loadMultiple() as $bundle => $entity) {
        $options[$bundle] = $entity->label();
      }
      \asort($options);
      return $options;
    }
    return false;
  }
  
  /**
   * --
   */
  public function getEntitiesConfig($entity_id) {
    $options = [];
    foreach ($this->EntityTypeManager->getStorage($entity_id)->loadMultiple() as $bundle => $entity) {
      $options[$bundle] = $entity->label();
    }
    return $options;
  }
}
