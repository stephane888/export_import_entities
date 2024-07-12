<?php

namespace Drupal\export_import_entities\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines style_scss annotation object.
 *
 * @Annotation
 */
class ImportContents extends Plugin {
  
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;
  
  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;
  
  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;
}
