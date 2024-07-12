<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Stephane888\DrupalUtility\HttpResponse;

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
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t(' It works! .. ')
    ];
    return $build;
  }
  
  /**
   * Permet de partager la configuration d'un site.
   * ( cette logique n'est pas optimal).
   */
  public function ShowSiteConfig() {
    /**
     * Get default langue :
     * Lorsqu'on generer un site ce dernier n'est pas reelement dans la bonne
     * langue, mais ces contenus y sont.
     */
    $config = ConfigDrupal::config('system.site');
    // à patir de la page d'accueil on determinerer la langue par defaut.
    if (!empty($config['page']['front'])) {
      $page = explode("/", $config['page']['front']);
      if ($page[1] == 'site-internet-entity') {
        /**
         *
         * @var \Drupal\creation_site_virtuel\Entity\SiteInternetEntity $homePage
         */
        $homePage = $this->entityTypeManager()->getStorage('site_internet_entity')->load($page[2]);
      }
      if (!empty($homePage)) {
        $config['langcode'] = $homePage->language()->getId();
        $config['default_langcode'] = $homePage->language()->getId();
      }
    }
    return HttpResponse::response([
      'system.site' => $config
    ]);
  }
  
  /**
   * --
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function DownloadSiteZip($domaineId) {
    // On regenerer les routes juste avant de telecharger
    \Drupal::service('router.builder')->rebuild();
    //
    $pt = explode('/web', DRUPAL_ROOT);
    $baseZip = $pt[0] . '/sites_exports/zips/';
    $path = $baseZip . $domaineId . '.zip';
    
    $response = new Response();
    // $response->headers->set('Content-Type',
    // 'application/zip,application/octet-stream');
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