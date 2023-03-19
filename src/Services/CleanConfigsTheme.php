<?php

namespace Drupal\export_import_entities\Services;

use Stephane888\Debug\Repositories\ConfigDrupal;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\Exception\UninstalledExtensionException;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Extension\ThemeExtensionList;

/**
 * Permet de supprimer un theme des extentiosn installés et toutes la
 * configuration en relation avec ce dernier.
 * CONDITION :
 * Les fichiers du themes ne doivent pas exister. ( le but est de nettoyer les
 * erreurs produite lors de la creation de themes ).
 *
 * @author stephane
 *        
 */
class CleanConfigsTheme {
  /**
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   * @deprecated car cela renvoit les warning pour chaque theme manquant. (on va
   *             passer par ThemeExtensionList)
   */
  protected $ExtensionPathResolver;
  
  /**
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected $cssCollectionOptimizer;
  
  /**
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;
  /**
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;
  
  /**
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  
  /**
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;
  
  /**
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;
  
  /**
   * Contient la liste de theme qui doit etre supprimer.
   *
   * @var array
   */
  protected $themeNotAvailable = [];
  
  /**
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $ThemeExtensionList;
  
  function __construct(ExtensionPathResolver $ExtensionPathResolver, AssetCollectionOptimizerInterface $css_collection_optimizer, ThemeHandlerInterface $theme_handler, ConfigManagerInterface $config_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, RouteBuilderInterface $route_builder, ThemeExtensionList $ThemeExtensionList) {
    $this->ExtensionPathResolver = $ExtensionPathResolver;
    $this->cssCollectionOptimizer = $css_collection_optimizer;
    $this->themeHandler = $theme_handler;
    $this->configManager = $config_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->routeBuilder = $route_builder;
    $this->ThemeExtensionList = $ThemeExtensionList;
  }
  
  /**
   * Get the list of themes installed so the files no longer exist.
   */
  public function getListThemesNotavailable() {
    if (empty($this->themeNotAvailable)) {
      $config = ConfigDrupal::config('core.extension');
      if (!empty($config['theme'])) {
        foreach ($config['theme'] as $themeName => $status) {
          if (!$this->getPathThemeNotAvailable($themeName)) {
            $this->themeNotAvailable[$themeName] = $themeName;
          }
        }
      }
      else {
        $this->messenger()->addWarning("No theme available");
      }
    }
    return $this->themeNotAvailable;
  }
  
  /**
   * Permet de determiner si le theme est compatible avec la logique.
   */
  public function checkIfThemeIsCompatibleWithLogic($themeName) {
    $this->getListThemesNotavailable();
    if (!empty($this->themeNotAvailable[$themeName]))
      return true;
    else
      return false;
  }
  
  private function getPathThemeNotAvailable($themeName) {
    try {
      return $this->ThemeExtensionList->getPath($themeName);
    }
    catch (\Exception $e) {
      return null;
    }
  }
  
  /**
   * Pemet de supprimer un theme.
   */
  public function DeleteThemes(array $theme_list) {
    $this->uninstall($theme_list);
  }
  
  /**
   * La fonction \Drupal\Core\Extension\ThemeInstaller::uninstall() de drupal ne
   * pas pas desintallé un theme qui ne rempli pas les condition de base.
   * ( les fichiers doivent etre disponible ).
   */
  private function uninstall($theme_list) {
    $extension_config = $this->configFactory->getEditable('core.extension');
    $this->cssCollectionOptimizer->deleteAll();
    $list = $this->themeHandler->listInfo();
    // On se rasure que c'est un theme qui est mal configurer.
    foreach ($theme_list as $key) {
      if (isset($list[$key])) {
        throw new UninstalledExtensionException(" This theme should be disabled by the default logic : $key.");
      }
      // avant de lancer la suppresion il faut qu'on se rassure que ce dernier
      // est dans la liste des elements à supprimer.
      if (empty($this->getListThemesNotavailable()[$key])) {
        throw new UninstalledExtensionException(" This theme cannot be deleted because it is not installed : $key.");
      }
    }
    //
    foreach ($theme_list as $key) {
      // The value is not used; the weight is ignored for themes currently.
      $extension_config->clear("theme.$key");
      
      // Reset theme settings.
      $theme_settings = &drupal_static('theme_get_setting');
      unset($theme_settings[$key]);
      
      // Remove all configuration belonging to the theme.( cool )
      $this->configManager->uninstall('theme', $key);
      
      // After theme is delete, we remove that on variables.
      unset($this->themeNotAvailable[$key]);
    }
    // Don't check schema when uninstalling a theme since we are only clearing
    // keys.
    $extension_config->save(TRUE);
    
    // Refresh theme info.
    $this->resetSystem();
    $this->themeHandler->reset();
    
    $this->moduleHandler->invokeAll('themes_uninstalled', [
      $theme_list
    ]);
  }
  
  /**
   * Resets some other systems like rebuilding the route information or caches.
   *
   * @from \Drupal\Core\Extension\ThemeInstaller
   */
  protected function resetSystem() {
    if ($this->routeBuilder) {
      $this->routeBuilder->setRebuildNeeded();
    }
    
    // @todo It feels wrong to have the requirement to clear the local tasks
    // cache here.
    Cache::invalidateTags([
      'local_task'
    ]);
    $this->themeRegistryRebuild();
  }
  
  /**
   * Wraps drupal_theme_rebuild().
   *
   * @from \Drupal\Core\Extension\ThemeInstaller
   */
  protected function themeRegistryRebuild() {
    drupal_theme_rebuild();
  }
  
  /**
   * Recupere la configuration dependant du theme.
   */
  public function getConfigsDepenceForTheme(string $themeName, $full = false) {
    return ConfigDrupal::searchConfigByWord($themeName, $full);
  }
  
}