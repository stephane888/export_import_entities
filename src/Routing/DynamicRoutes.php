<?php

namespace Drupal\export_import_entities\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class DynamicRoutes {

  /**
   *
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];
    //
    $entity_query = \Drupal::entityTypeManager()->getStorage('menu')->getQuery();
    $entity_query->condition('id', '_main', 'CONTAINS');
    // $entity_query->g
    $ids = $entity_query->execute();
    foreach ($ids as $menu_id) {
      $routes['jsonapi.menu_link_content--' . $menu_id . '.individual'] = new Route('/jsonapi/export/menu-link-content', [
        '_jsonapi_resource' => 'Drupal\export_import_entities\Resource\MenuLinkContent',
        '_jsonapi_resource_types' => [
          'menu_link_content--' . $menu_id
        ]
      ]);
    }
    return $routes;
  }

}