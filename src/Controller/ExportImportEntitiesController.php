<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\export_import_entities\Services\ExportEntities;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Stephane888\DrupalUtility\HttpResponse;
use Stephane888\Debug\ExceptionExtractMessage;
use Stephane888\Debug\ExceptionDebug;

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
  
  public function SaveEntity(Request $Request, $base_directory, $key_identification, $entity_type_id) {
    $EntityStorage = $this->entityTypeManager()->getStorage($entity_type_id);
    $values = Json::decode($Request->getContent());
    if ($EntityStorage && $values) {
      try {
        /**
         *
         * @var \Drupal\export_import_entities\Plugin\ImportContents\ImportContents $plugin
         */
        $plugin = self::getPluginImportContent();
        $page = [
          'entity' => $values,
          'entities' => [],
          'target_type' => $entity_type_id,
          'target_id' => $EntityStorage->create($values)->id()
        ];
        $entity = $plugin->prepareSaveContent($page, $base_directory, $key_identification);
        $datas = [
          'id' => $entity->id(),
          'json' => $entity->toArray(),
          'label' => $entity->label(),
          'url' => '#'
        ];
        if ($entity->hasLinkTemplate('canonical'))
          $datas['url'] = $entity->toUrl()->toString();
        //
        return HttpResponse::response($datas);
      }
      catch (ExceptionDebug $e) {
        $this->getLogger('export_import_entities')->critical(ExceptionExtractMessage::errorAllToString($e));
        return HttpResponse::response(ExceptionExtractMessage::errorAll($e), $e->getErrorCode(), $e->getMessage());
      }
      catch (\Exception $e) {
        $this->getLogger('export_import_entities')->critical(ExceptionExtractMessage::errorAllToString($e));
        return HttpResponse::response(ExceptionExtractMessage::errorAll($e), 435, $e->getMessage());
      }
    }
    else {
      $this->getLogger('export_import_entities')->critical(" Impossible de creer l'entité : " . $entity_type_id);
      return HttpResponse::response([], 435, "Erreur inconnu");
    }
  }
  
  /**
   *
   * @return \Drupal\export_import_entities\Plugin\ImportContents\ImportContents
   */
  static function getPluginImportContent() {
    /**
     *
     * @var \Drupal\export_import_entities\ImportContentsPluginManager $MangerImportContent
     */
    $MangerImportContent = \Drupal::service("plugin.manager.import_content");
    return $MangerImportContent->createInstance("export_import_entities_import_contents");
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