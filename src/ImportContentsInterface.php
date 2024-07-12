<?php

namespace Drupal\export_import_entities;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for style_scss plugins.
 */
interface ImportContentsInterface {
  
  /**
   * Permet de sauvegarder les données.
   * Elle est charger de recuperer le contenu qui doit etre sauvegarder, la
   * traiter et creer un fichier.
   * Example : $file_name : 1.json
   *
   * @param array $datas
   * @param int $id
   * @param string $entity_id
   */
  public function saveContents(array $datas, int $id, string $entity_id): void;
  
  /**
   * Permet de sauvegarder les fichiers contenus dans les données
   *
   * @return bool
   */
  public function saveFiles(array $datas, string $dir): void;
  
  /**
   * Permet de se rassurer que toutes les données sont valide avant la
   * sauvegarde.
   *
   * @param array $datas
   */
  public function validateContents(array $datas): bool;
}