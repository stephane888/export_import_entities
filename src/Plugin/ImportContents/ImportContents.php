<?php

namespace Drupal\export_import_entities\Plugin\ImportContents;

use Drupal\export_import_entities\ImportContentsPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the ImportContents.
 *
 * @ImportContents(
 *   id = "export_import_entities_import_contents",
 *   label = @Translation(" Import contents by export_import_entities "),
 *   description = @Translation("Import contents by export_import_entities")
 * )
 */
class ImportContents extends ImportContentsPluginBase {
}