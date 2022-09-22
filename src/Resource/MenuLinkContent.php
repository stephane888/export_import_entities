<?php

namespace Drupal\export_import_entities\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Symfony\Component\Routing\Route;
use Drupal\jsonapi_resources\Exception\RouteDefinitionException;

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
    $entity_query = $this->getEntityQuery('menu_link_content')->condition('bundle', $this->getTypeMenu() . '_main');
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

  /**
   *
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return array_map(function ($resource_type_name) use ($route_name) {
      $resource_type_name = 'menu_link_content--entreprise-btiment_main';
      $resource_type = $this->resourceTypeRepository->getByTypeName($resource_type_name);
      if (is_null($resource_type)) {
        // @todo: try to move this exception into
        // Drupal\jsonapi_resources\Routing\ResourceRoutes::ensureResourceImplementationValid().
        throw new RouteDefinitionException("The $route_name route definition's _jsonapi_resource_types route default declares the resource type $resource_type_name but a resource type by that name does not exist.");
      }
      return $resource_type;
    }, $route->getDefault('_jsonapi_resource_types') ?: []);
  }

  /**
   *
   * @return string
   */
  protected function getTypeMenu() {
    if (!$this->typeMenu) {
      /**
       *
       * @var \Drupal\domain\DomainNegotiator $domainNeg
       */
      $domainNeg = \Drupal::service('domain.negotiator');
      if ($domaineId = $domainNeg->getActiveId()) {
        $entities = $this->entityTypeManager->getStorage("domain_ovh_entity")->loadByProperties([
          'domain_id_drupal' => $domaineId
        ]);
        if (!empty($entities)) {
          /**
           *
           * @var \Drupal\ovh_api_rest\Entity\DomainOvhEntity $entity
           */
          $entity = reset($entities);
          $this->typeMenu = $entity->getsubDomain();
        }
      }
    }
    // dump($this->typeMenu);
    return $this->typeMenu;
  }

}

