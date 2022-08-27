<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Core\Url;

/**
 * Returns responses for Export Import Entities routes.
 */
class ExportImportEntitiesController extends ControllerBase {

  /**
   *
   * @var ExportEntities
   */
  protected $ExportEntities;

  function __construct(ExportEntities $ExportEntities) {
    $this->ExportEntities = $ExportEntities;
  }

  static function create(ContainerInterface $container) {
    return new static($container->get('export_import_entities.export.entites'));
  }

  /**
   * Builds the response.
   */
  public function build() {
    $this->ExportEntities->getEntites();
    // dump($this->entityTypeManager()->getStorage('entity_form_display')->loadMultiple());
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works! ..')
    ];
    return $build;
  }

}