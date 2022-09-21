<?php

namespace Drupal\export_import_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\domain\DomainNegotiator;
use Drupal\jsonapi\ResourceResponse;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Returns responses for Export Import Entities routes.
 * http://test-renov-wb-horizon.kksa/core/install.php?rewrite=ok&profile=wb_horizon_generate&langcode=fr
 */
class ExportEntities extends ControllerBase {

  public function process(Request $request): ResourceResponse {
    // Force the author to be included.
    // $include = $request->query->get('include');
    // $request->query->set('include', $include . (empty($include) ? '' : ',') .
    // 'logo');
    // Add ressource type:
    $all = $request->attributes->all();
    // $ressourceType = [
    // 'menu_link_content--test62_wb_horizon_kksa_main'
    // ];
    // $request->attributes->set('resource_types', $ressourceType);
    //
    //
    $cacheability = new CacheableMetadata();

    // try to load theme;
    // $confTheme = ConfigDrupal::config('system.theme');
    $entity_query = $this->getEntityQuery('menu_link_content')->condition('bundle', 'entreprise-btiment_main');
    // $query =
    // \Drupal::entityTypeManager()->getStorage('menu_link_content')->getQuery();
    // $query->condition('bundle', 'test62_wb_horizon_kksa' . '%', "LIKE");
    // dump($entity_query->execute());
    // die();

    $cacheability->addCacheContexts([
      'url.path'
    ]);

    $paginator = $this->getPaginatorForRequest($request);
    $paginator->applyToQuery($entity_query, $cacheability);

    $data = $this->loadResourceObjectDataFromEntityQuery($entity_query, $cacheability);

    $pagination_links = $paginator->getPaginationLinks($entity_query, $cacheability, TRUE);

    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

}