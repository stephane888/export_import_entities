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
    $this->routeForMainMenu($routes);
    $this->routeForEvreryParagraph($routes);
    $this->routeForBlocksContents($routes);
    return $routes;
  }
  
  /**
   * _role: 'gerant_de_site_web+administrator'
   *
   * @param array $routes
   */
  public function routeForBlocksContents(array &$routes) {
    if (\Drupal::moduleHandler()->moduleExists('blockscontent')) {
      /**
       * Routes pour tous les produits.
       */
      $blocks_contents_types = \Drupal::entityTypeManager()->getStorage('blocks_contents_type')->loadMultiple();
      $resource_types = [];
      foreach ($blocks_contents_types as $blocks_contents_type) {
        $resource_types[] = 'blocks_contents--' . $blocks_contents_type->id();
      }
      $routes['export_import_entities.blocks_contents'] = new Route('/%jsonapi%/export/blocks_contents', [
        '_jsonapi_resource' => 'Drupal\export_import_entities\Resource\BlocksContents',
        '_jsonapi_resource_types' => $resource_types,
        'requirements' => [
          '_permission' => 'access content',
          '_role' => 'administrator',
          '_user_is_logged_in' => TRUE,
          '_auth' => 'basic_auth'
        ]
      ]);
    }
  }
  
  /**
   * Route permettant de retourner tous les paragraphes.
   *
   * @param array $routes
   */
  public function routeForEvreryParagraph(array &$routes) {
    /**
     * Routes pour tous les paragraphes.
     */
    $paragraphs_type = \Drupal::entityTypeManager()->getStorage('paragraphs_type')->loadMultiple();
    $resource_types = [];
    foreach ($paragraphs_type as $paragraph_type) {
      $resource_types[] = 'paragraph--' . $paragraph_type->id();
    }
    $routes['export_import_entities.paragraph'] = new Route('/%jsonapi%/export/paragraph', [
      '_jsonapi_resource' => 'Drupal\export_import_entities\Resource\ParagraphContent',
      '_jsonapi_resource_types' => $resource_types,
      'requirements' => [
        '_permission' => 'access content',
        '_role' => 'administrator',
        '_user_is_logged_in' => TRUE,
        '_auth' => 'basic_auth'
      ]
    ]);
    if (\Drupal::moduleHandler()->moduleExists('commerce_product')) {
      /**
       * Routes pour tous les produits.
       */
      $commerce_product_types = \Drupal::entityTypeManager()->getStorage('commerce_product_type')->loadMultiple();
      $resource_types = [];
      foreach ($commerce_product_types as $commerce_product_type) {
        $resource_types[] = 'commerce_product--' . $commerce_product_type->id();
      }
      $routes['export_import_entities.commerce_product'] = new Route('/%jsonapi%/export/commerce_product', [
        '_jsonapi_resource' => 'Drupal\export_import_entities\Resource\CommerceProduct',
        '_jsonapi_resource_types' => $resource_types,
        'requirements' => [
          '_permission' => 'access content',
          '_role' => 'administrator',
          '_user_is_logged_in' => TRUE,
          '_auth' => 'basic_auth'
        ]
      ]);
    }
  }
  
  /**
   * Permet de generer une route d'export pour chaque menu principal.
   *
   * @param array $routes
   */
  protected function routeForMainMenu(array &$routes) {
    $entity_query = \Drupal::entityTypeManager()->getStorage('menu')->getQuery();
    $ids = $entity_query->execute();
    $resource_types = [];
    foreach ($ids as $id) {
      $resource_types[] = 'menu_link_content--' . $id;
    }
    $routes['jsonapi.menu_link_content--menu.individual'] = new Route('/%jsonapi%/export/menu-link-content', [
      '_jsonapi_resource' => 'Drupal\export_import_entities\Resource\MenuLinkContent',
      '_jsonapi_resource_types' => $resource_types,
      'requirements' => [
        '_permission' => 'access content',
        '_role' => 'administrator',
        '_user_is_logged_in' => TRUE,
        '_auth' => 'basic_auth'
      ]
    ]);
  }
  
}