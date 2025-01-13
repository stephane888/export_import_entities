<?php

namespace Drupal\export_import_entities\Services\HelpersExport;

use Stephane888\Debug\Repositories\ConfigDrupal;
use Drupal\export_import_entities\Services\LoadConfigs;

/**
 * Il genere les données de configuration.
 *
 * @author stephane
 *        
 */
trait ExportPaiments {
  
  /**
   * Exporte la configuration en relation avec les shippings.
   */
  function exportConfigPaiments() {
    if ($this->isRequirePaiement()) {
      $this->getConfigCommerce();
    }
  }
  
  protected function getConfigCommerce() {
    $domaineId = $this->currentDomaine->id();
    /**
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
     */
    $entityTypeDefinition = $this->entityTypeManager()->getDefinition("commerce_payment_gateway");
    
    /**
     *
     * @var \Drupal\commerce_payment\PaymentGatewayManager $PaymentGatewayManager
     */
    $PaymentGatewayManager = \Drupal::service("plugin.manager.commerce_payment_gateway");
    // On charge les moyens qui ont été explicetement definit..
    $commerce_payment_configs = $this->entityTypeManager()->getStorage('commerce_payment_config')->loadByProperties([
      'domain_id' => $domaineId,
      'active' => 1
    ]);
    foreach ($commerce_payment_configs as $commerce_payment_config) {
      /**
       *
       * @var \Drupal\lesroidelareno\Entity\CommercePaymentConfig $commerce_payment_config
       */
      $id = $commerce_payment_config->getPaymentPluginId();
      /**
       *
       * @var \Drupal\commerce_payment\Entity\PaymentGateway $commerce_payment_gateway
       */
      $commerce_payment_gateway = $this->entityTypeManager()->getStorage("commerce_payment_gateway")->load($id);
      $pluginId = $commerce_payment_gateway->getPluginId();
      /**
       *
       * @var \Drupal\wb_horizon_public\Plugin\Commerce\PaymentGateway\stripeOverride $lesroidelareno_stripe_override
       */
      $lesroidelareno_stripe_override = $PaymentGatewayManager->createInstance($pluginId);
      $lesroidelareno_stripe_override->__wakeup();
      $conf = $lesroidelareno_stripe_override->getConfiguration();
      $name = $entityTypeDefinition->getConfigPrefix() . '.' . $id;
      // On surcharge la configuration avec les informations du domain.
      $this->LoadConfigs->getConfigFromName($name, [
        'configuration' => $conf
      ], false);
    }
  }
  
  /**
   * Charge les paiments actifs.
   */
  protected function loadActivePaiement() {
    $payment_manager = $this->entityTypeManager()->getStorage("commerce_payment_gateway");
    $commerceConfigManager = $this->entityTypeManager()->getStorage("commerce_payment_config");
    /**
     * On charge les configurations de paiment creer sur wbh
     */
    $validPayments = $payment_manager->loadByProperties([
      "status" => TRUE
    ]);
    /**
     * On charge les moyens de paiements que l'utilisateur à activer.
     */
    foreach ($validPayments as &$entity) {
      $configsLoaded = $commerceConfigManager->loadByProperties([
        'domain_id' => $this->domainNegotiator->getActiveId(),
        'payment_plugin_id' => $entity->id(),
        'active' => 1
      ]);
    }
  }
}