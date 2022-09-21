<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\domain\DomainNegotiator;

/**
 * Returns responses for Export Import Entities routes.
 * http://test-renov-wb-horizon.kksa/core/install.php?rewrite=ok&profile=wb_horizon_generate&langcode=fr
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
    $this->currentDomaine = $DomainNegotiator;
  }

  static function create(ContainerInterface $container) {
    return new static($container->get('export_import_entities.export.entites'), $container->get('domain.negotiator'));
  }

  /**
   * Builds the response.
   */
  public function build() {
    $this->ExportEntities->getEntites();
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t(' It works! .. ')
    ];
    return $build;
  }

  /**
   * --
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function DownloadSiteZip($domaineId) {
    $pt = explode('/web', DRUPAL_ROOT);
    $baseZip = $pt[0] . '/sites_exports/zips/';
    $path = $baseZip . $domaineId . '.zip';

    $response = new Response();
    // $response->headers->set('Content-Type',
    // 'application/zip,application/octet-stream');

    //
    $data = file_get_contents($path);
    if ($data) {
      $response->setContent($data);
      $response->headers->set('Content-Type', 'application/zip');
      return $response;
    }
    else {
      $this->messenger()->addWarning("Une erreur s'est produite, veiller ressayer plus tard.");
    }
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t(' Error ... ')
    ];
    return $build;
  }

}