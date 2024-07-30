<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Permet de selectionner une entité et de l'exporter.
 */
abstract class ImportBase extends FormBase {
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  static public function export_import_submit_callback(array $form, FormStateInterface $form_state) {
    return $form['datas'];
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  static public function export_import_select_import_entity(array $form, FormStateInterface $form_state) {
    return $form['datas'];
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t(' The message has been sent. '));
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    //
  }
}