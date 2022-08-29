<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Drupal\domain\DomainNegotiator;

/**
 * Returns responses for Export Import Entities routes.
 */
class ExportImportEntitiesController extends ControllerBase {
  protected $currentDomaine;

  /**
   *
   * @var ExportEntities
   */
  protected $ExportEntities;

  function __construct(ExportEntities $ExportEntities, DomainNegotiator $DomainNegotiator) {
    $this->ExportEntities = $ExportEntities;
    $this->currentDomaine = $DomainNegotiator->getActiveDomain();
  }

  static function create(ContainerInterface $container) {
    return new static($container->get('export_import_entities.export.entites'), $container->get('domain.negotiator'));
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

  public function DownloadSiteZip() {
    $pt = explode('/web', DRUPAL_ROOT);
    $baseZip = $pt[0] . '/sites_exports/zips/';
    $path = $baseZip . $this->currentDomaine->id();
    $response = new Response();
    // $response->headers->set('Content-Type',
    // 'application/zip,application/octet-stream');
    $response->headers->set('Content-Type', 'application/zip');
    $response->setContent(file_get_contents($path));
    return $response;
  }

}