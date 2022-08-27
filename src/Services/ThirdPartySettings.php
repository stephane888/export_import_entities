<?php

namespace Drupal\export_import_entities\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\debugLog;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Permet de charger les diffirents differents mode de d'affichage pour un
 * formulaire
 * d'entité.
 *
 * @author stephane
 *
 */
class ThirdPartySettings extends ControllerBase {
  /**
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityType $definition
   */
  protected $viewDefition;
  protected $viewPrefix;

  /**
   *
   * @var LoadConfigs
   */
  protected $LoadConfigs;

  function __construct(LoadConfigs $LoadConfigs) {
    $this->LoadConfigs = $LoadConfigs;
  }

  /**
   * Pour le moment ThirdParty ne fournit pas de mecanisme pour recuprerer
   * efficassement les dependances.
   *
   * @param ConfigEntityInterface $ConfigEntity
   */
  function getConfigFromThirdParty(ConfigEntityInterface $ConfigEntity) {
    foreach ($ConfigEntity->getThirdPartyProviders() as $moduleName) {
      $confs = $ConfigEntity->getThirdPartySettings($moduleName);
      if (!empty($confs['sections'])) {
        foreach ($confs['sections'] as $section) {
          /**
           *
           * @var \Drupal\layout_builder\Section $section
           */
          $components = $section->getComponents();
          foreach ($components as $component) {
            /**
             *
             * @var \Drupal\layout_builder\SectionComponent $component
             */
            $confComponent = $component->toArray();
            if (!empty($confComponent['configuration']['formatter']['type'])) {
              // Ajout de la configuration pour le champs :
              // fielditem_renderby_view_formatter
              if ($confComponent['configuration']['formatter']['type'] == 'fielditem_renderby_view_formatter') {
                if (!empty($confComponent['configuration']['formatter']['settings']['view_name'])) {
                  $name = $this->getViewConfigPrefix() . '.' . $confComponent['configuration']['formatter']['settings']['view_name'];
                  $this->LoadConfigs->getConfigFromName($name);
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityType
   */
  protected function getViewDefinition() {
    if (!$this->viewDefition) {
      $this->viewDefition = $this->entityTypeManager()->getDefinition('view');
    }
    return $this->viewDefition;
  }

  /**
   *
   * @return string
   */
  protected function getViewConfigPrefix() {
    if (!$this->viewPrefix) {
      $this->viewPrefix = $this->getViewDefinition()->getConfigPrefix();
    }
    return $this->viewPrefix;
  }

/**
 * --
 */
}