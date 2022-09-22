<?php

namespace Drupal\export_import_entities\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\Debug\Repositories\ConfigDrupal;

/**
 * Permet de retourner les pages en function du domaine.
 *
 * @internal
 */
class PageWeb extends EntityQueryResourceBase {

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
    $include = $request->query->get('include');
    $request->query->set('include', $include . (empty($include) ? '' : ',') . 'layout_paragraphs');

    $cacheability = new CacheableMetadata();

    // try to load theme;
    $confTheme = ConfigDrupal::config('system.theme');
    $entity_query = $this->getEntityQuery('site_internet_entity')->condition('field_domain_access', $confTheme['default']);
    // $langueFrEn = $entity_query->orConditionGroup();
    // $langueFrEn->condition('langcode', 'en');
    // $langueFrEn->condition('langcode', 'fr');
    // $entity_query->condition($langueFrEn);
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

