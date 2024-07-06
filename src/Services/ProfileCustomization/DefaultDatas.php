<?php

namespace Drupal\export_import_entities\Services\ProfileCustomization;

use Drupal\Core\Controller\ControllerBase;

/**
 * Contient les modules, themes et configuration par defaut.
 * Le fichier wb_horizon_generate.info.yml doit étre re-ecrit pour chaque
 * installation.
 *
 * @author stephane
 *        
 */
class DefaultDatas extends ControllerBase {

  /**
   *
   * @return string
   */
  protected function generalInformation() {
    return 'name: Site generer par Wb-Horizon
type: profile
description: "Ce profile permet de mettre en place la configuration de base permettant d\'accueillir les données exportées"
core_version_requirement: "^9 || ^10"

#############
distribution:
  name: "WB-horizon generate"
  langcode: en

# Ces modules ne peuvent etre desinstaller par l\'utilisateur
dependencies:
  - paragraphs
  - paragraph_view_mode
  - pathauto
  - formatage_models
  - layoutgenentitystyles
  - migrationwbh';
  }

  /**
   * Contient les themes par defaut.
   *
   * @return string[]
   */
  protected function defaultTheme() {
    return [
      'claro',
      'wb_universe'
    ];
  }

  /**
   *
   * @return string[]
   */
  protected function defaultCustomModule() {
    return [
      'creation_site_virtuel',
      'migrationwbh',
      'commerceformatage',
      'terms_display',
      'spaker_mod',
      'clothingslayouts',
      'get_data_field_from_url',
      'wb_horizon_public',
      'fielditem_renderby_view',
      'fullswiperoptions',
      'hot_models',
      'login_rx_vuejs',
      'layoutscommerce',
      'owlcarousel',
      'votings_renders',
      'formatter_render_field',
      'mitor',
      'mit_models',
      'blockscontent',
      'vixcon',
      'view_formatter_layouts',
      'view_filter_promotion',
      'modeldessange',
      'apivuejs',
      'filesmanager',
      'more_fields',
      'more_fields_video',
      'buildercvlayouts',
      'fast_models',
      'bestlayouts',
      'stripebyhabeuk',
      'prise_rendez_vous',
      'bookingsystem_autoecole',
      'hbkcolissimochrono'
    ];
  }

  /**
   *
   * @return string[]
   */
  protected function defaultContribCommerceModule() {
    return [
      'commerce_product',
      'commerce_promotion',
      'commerce_store',
      'commerce_stripe',
      'commerce_shipping',
      'commerce_checkout',
      'commerce_tax',
      'commerce_stock_field',
      'commerce_autosku'
    ];
  }

  /**
   *
   * @return string[]
   */
  protected function defaultContribModule() {
    return [
      'restui',
      'admin_toolbar',
      'imce',
      'inline_entity_form',
      'select2',
      'entity_block',
      'geolocation',
      'geolocation_google_maps',
      'field_formatter_class',
      'webform',
      'webform_ui',
      'webform_domain_access',
      'admin_toolbar',
      'admin_toolbar_tools',
      'menu_item_extras',
      'lang_dropdown',
      'languageicons',
      'search_api',
      'search_api_db',
      'better_exposed_filters',
      'votingapi',
      'video',
      'phone_international',
      'transliterate_filenames', // doit etre supprimer du core princ
      'ctools'
    ];
  }

  /**
   * Contient les themes par defaut.
   *
   * @return string[]
   */
  protected function defaultCoreModule() {
    return [
      'node',
      'history',
      'block',
      'breakpoint',
      'ckeditor5',
      'config',
      'comment',
      'contextual',
      'contact',
      'menu_link_content',
      'datetime',
      'block_content',
      'help',
      'image',
      'menu_ui',
      'options',
      'path',
      'page_cache',
      'dynamic_page_cache',
      'big_pipe',
      'taxonomy',
      'dblog',
      'search',
      'shortcut',
      'field_ui',
      'file',
      'views',
      'views_ui',
      'automated_cron',
      'content_translation',
      'config_translation',
      'jsonapi',
      'responsive_image'
    ];
  }
}
