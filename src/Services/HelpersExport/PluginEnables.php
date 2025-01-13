<?php

namespace Drupal\export_import_entities\Services\HelpersExport;

use Stephane888\Debug\Repositories\ConfigDrupal;

/**
 *
 * @author stephane
 *        
 */
trait PluginEnables {
  
  /**
   * Return true si le site à exporter require un paiment.
   */
  function isRequirePaiement() {
    $plugins = $this->getPluginsEnable();
    return !empty($plugins['manage_paiement']) ? true : false;
  }
  
  /**
   *
   * @return boolean
   */
  function isRequireShipping() {
    $plugins = $this->getPluginsEnable();
    return !empty($plugins['config_shipping_methods']) ? true : false;
  }
  
  function getPluginsEnable() {
    if (!$this->enablePlugins)
      $this->enablePlugins = ConfigDrupal::config("manage_module_config.settings");
    return $this->enablePlugins['plugins'] ?? [];
  }
}
