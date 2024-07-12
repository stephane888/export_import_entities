<?php
declare(strict_types = 1);

namespace Drupal\export_import_entities\Resource;

use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;

/**
 * Defines basic functionality for an entity-oriented JSON:API Resource.
 */
abstract class EntityQueryResource extends EntityQueryResourceBase {

  function test() {
    $this->getRouteResourceTypes($route, $route_name);
  }

}
