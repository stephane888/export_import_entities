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
final class SelectImportStorageEntities extends ImportBase {
  
  /**
   * -
   *
   * @var EntityTypeManager
   */
  protected $EntityTypeManager;
  
  /**
   * --
   */
  public function __construct(EntityTypeManager $EntityTypeManager) {
    $this->EntityTypeManager = $EntityTypeManager;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'export_import_entities_import_storage_entities';
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $step = isset($_GET['step']) ? $_GET['step'] : 0;
    if (!$form_state->has('step')) {
      $form_state->set('step', $step);
    }
    $this->buildFormByStep($form, $form_state);
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t("The message has been sent."));
  }
}