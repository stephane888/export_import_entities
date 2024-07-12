<?php

namespace Drupal\export_import_entities\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Permet de retourner les pages en function du domaine.
 *
 * @internal
 */
class MenuLinkContent extends EntityQueryResourceBase {
  /**
   */
  private $typeMenu;
  
  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *        The request.
   * @param \Drupal\user\UserInterface $user
   *        The user.
   *        
   * @return \Drupal\jsonapi\ResourceResponse The response.
   *        
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request): ResourceResponse {
    $cacheability = new CacheableMetadata();
    
    // try to load theme;
    // $confTheme = ConfigDrupal::config('system.theme');
    $entity_query = $this->getEntityQuery('menu_link_content');
    
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

