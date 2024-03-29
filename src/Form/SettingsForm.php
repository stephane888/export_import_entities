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
    $form['list_entities'] = [
      '#type' => 'details',
      '#title' => $this->t(" Liste d'entités ")
    ];
    //
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    dump($entities);
    return parent::buildForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('export_import_entities.settings')->set('example', $form_state->getValue('example'))->save();
    parent::submitForm($form, $form_state);
  }

}
