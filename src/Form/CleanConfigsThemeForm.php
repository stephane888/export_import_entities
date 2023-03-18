<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\export_import_entities\Services\CleanConfigsTheme;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire permettant de gerer les themes qui doivent etre supprimer car les
 * fichiers n'existe plus.
 *
 * @author stephane
 *        
 */
class CleanConfigsThemeForm extends FormBase {
  /**
   *
   * @var \Drupal\export_import_entities\Services\CleanConfigsTheme
   */
  protected $CleanConfigsTheme;
  
  function __construct(CleanConfigsTheme $CleanConfigsTheme) {
    $this->CleanConfigsTheme = $CleanConfigsTheme;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('export_import_entities.clean_configs_theme'));
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\Core\Form\FormInterface::getFormId()
   */
  public function getFormId() {
    return 'export_import_entities_clean_configs_heme_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    $themes_not_available = $this->CleanConfigsTheme->getListThemesNotavailable();
    $form['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => count($themes_not_available) . ' themes to delete '
    ];
    $form['themes_not_available'] = [
      '#type' => 'details',
      '#title' => 'Theme non disponible',
      '#tree' => true,
      '#open' => true
    ];
    foreach ($themes_not_available as $themeName) {
      $configsDependencies = $this->CleanConfigsTheme->getConfigsDepenceForTheme($themeName);
      $form['themes_not_available'][$themeName] = [
        '#type' => 'details',
        '#title' => $themeName,
        '#open' => true
      ];
      /**
       * si la configuration n'est pas vide et est superieur à 1 ou si le
       * premier element est different de 'core.extension' alors cette
       * configuration a des dependances.
       */
      if (!empty($configsDependencies) && (count($configsDependencies) > 1 || $configsDependencies[0]['name'] != 'core.extension')) {
        $form['themes_not_available'][$themeName]['configs'] = [
          '#type' => 'details',
          '#title' => "Liste de configuration en relation",
          '#open' => false
        ];
        foreach ($configsDependencies as $config) {
          $form['themes_not_available'][$themeName]['configs'][$config['name']] = [
            '#type' => 'textfield',
            '#default_value' => $config['name'],
            '#attributes' => [
              // 'read-only' => true,
              'readonly' => true
            ]
          ];
        }
      }
      // sinon elle peut etre supprimer directement.
      else {
        $form['themes_not_available'][$themeName]['delete'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete this theme')
        ];
      }
    }
    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete themes'),
      '#button_type' => 'primary',
      '#ajax' => []
    ];
    return $form;
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\Core\Form\FormInterface::submitForm()
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $themes_not_available = $values['themes_not_available'];
    $themesRemoves = [];
    foreach ($themes_not_available as $themeName => $value) {
      if (!empty($value['delete'])) {
        $themesRemoves[$themeName] = $themeName;
      }
    }
    $this->CleanConfigsTheme->DeleteThemes($themesRemoves);
    // $this->messenger()->addStatus('Save form');
  }
  
}