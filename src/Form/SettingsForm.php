<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Export Import Entities settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'export_import_entities_settings';
  }

  /**
   *
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'export_import_entities.settings'
    ];
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('export_import_entities.settings');
    // dump($config->getRawData());
    $form['list_entities'] = [
      '#type' => 'details',
      '#title' => $this->t(" Liste d'entités "),
      '#tree' => true
    ];
    //
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entities as $entity) {
      // if (!empty($entity->getKey('bundle'))) {
      $form['list_entities'][$entity->id()] = [
        '#type' => 'checkbox',
        '#title' => $entity->getLabel(),
        '#default_value' => $config->get('list_entities.' . $entity->id()) ? $config->get('list_entities.' . $entity->id()) : 0
      ];
      // }
    }

    //
    $form['export_orthers_entities'] = [
      '#type' => 'checkbox',
      '#title' => 'Exporter les données basique (langue, editeurs, filtre de test)',
      '#default_value' => $config->get('export_orthers_entities')
    ];
    //
    $form['export_image_styles'] = [
      '#type' => 'checkbox',
      '#title' => "Exporter les styles d'image",
      '#default_value' => $config->get('export_image_styles')
    ];
    //
    $form['export_menus'] = [
      '#type' => 'checkbox',
      '#title' => "Exporter les menus",
      '#default_value' => $config->get('export_menus')
    ];
    //

    return parent::buildForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // if ($form_state->getValue('example') != 'example') {
    // $form_state->setErrorByName('example', $this->t('The value is not
    // correct.'));
    // }
    parent::validateForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('export_import_entities.settings');
    $config->set('list_entities', $form_state->getValue('list_entities'));
    $config->set('export_orthers_entities', $form_state->getValue('export_orthers_entities'));
    $config->set('export_orthers_entities', $form_state->getValue('export_orthers_entities'));
    $config->set('export_image_styles', $form_state->getValue('export_image_styles'));
    $config->set('export_menus', $form_state->getValue('export_menus'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
