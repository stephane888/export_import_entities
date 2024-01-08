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
class BaseEntities extends EntityQueryResourceBase {
  
  /**
   *
   * @var string
   */
  protected $entity_id;
  
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
    $entity_query = $this->getEntityQuery($this->getEntityId());
    $cacheability->addCacheContexts([
      'url.path'
    ]);
    
    $paginator = $this->getPaginatorForRequest($request);
    $paginator->applyToQuery($entity_query, $cacheability);
    /**
     *
     * @var \Drupal\jsonapi\JsonApiResource\ResourceObjectData $data
     */
    $data = $this->loadResourceObjectDataFromEntityQuery($entity_query, $cacheability);
    $pagination_links = $paginator->getPaginationLinks($entity_query, $cacheability, TRUE);
    /**
     *
     * @var \Drupal\jsonapi\CacheableResourceResponse $response
     */
    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);
    
    return $response;
  }
  
  /**
   * Permet de recuperer l'entité.
   */
  public function getEntityId() {
    if (empty($this->entity_id))
      throw new \Exception("L'entity_id  n'est pas definit");
    return $this->entity_id;
  }
  
  public function setEntityId($entity_id) {
    $this->entity_id = $entity_id;
  }
  
}

