<?php

namespace Drupal\export_import_entities\Services\ProfileCustomization;

use Stephane888\Debug\debugLog;
use Drupal\Component\Utility\NestedArray;

/**
 * On a besoin d'ajutser les modules et themes à installer.
 * L'idée est d'avoir tous les modules present mais d'installer uniquement ceux
 * qui sont necessaire.
 *
 * @author stephane
 *        
 */
class ManageProfile extends DefaultDatas {
  /**
   * Contient la liste des themes à installer.
   *
   * @var array
   */
  protected $themes = [];
  protected $modules = [];
  
  /**
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $currentDomaine;
  
  public function addTheme(string $theme_name, $build_file = true) {
    if (!in_array($theme_name, $this->defaultTheme()) && !in_array($theme_name, $this->themes)) {
      $this->themes[] = $theme_name;
      if ($build_file)
        $this->buildFileINFOprofile();
    }
  }
  
  protected function buildModules() {
    $modules = array_unique(NestedArray::mergeDeepArray([
      $this->defaultCoreModule(),
      $this->defaultContribModule(),
      $this->defaultContribCommerceModule(),
      $this->defaultCustomModule(),
      $this->modules
    ]));
    $string = "\n";
    $string .= '############################################################' . "\n";
    $string .= '### NE PAS EDITER :: CE FICHIER EST AUTOMATIQUEMENT GENERE' . "\n";
    $string .= "### install modules core, contrib and custom \n";
    $string .= "install: \n";
    foreach ($modules as $module_name) {
      $string .= '  - ' . $module_name . "\n";
    }
    return $string;
  }
  
  /**
   * --
   *
   * @return string
   */
  protected function buildThemes() {
    $themes = array_unique(NestedArray::mergeDeepArray([
      $this->defaultTheme(),
      $this->themes
    ]));
    $string = "\n";
    $string .= '### install themes' . "\n";
    $string .= 'themes:' . "\n";
    foreach ($themes as $theme_name) {
      $string .= '  - ' . $theme_name . "\n";
    }
    return $string;
  }
  
  /**
   * Construit le fichier file wb_horizon_generate.info.yml
   */
  public function buildFileINFOprofile() {
    debugLog::$path = DRUPAL_ROOT . '/../sites_exports/' . $this->currentDomaine->id() . '/web/profiles/contrib/wb_horizon_generate/';
    $string = '';
    $string .= $this->generalInformation();
    $string .= "\n";
    $string .= $this->buildModules();
    $string .= '###' . "\n";
    $string .= $this->buildThemes();
    $name = 'wb_horizon_generate.info.yml';
    debugLog::logger($string, $name, false, 'file');
  }
  
  /**
   *
   * @param string $domaineId
   */
  public function setNewDomain($domaineId) {
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($domaineId);
    if ($domain)
      $this->currentDomaine = $domain;
    else
      throw new \Exception("le Domain n'exite pas");
  }
  
}