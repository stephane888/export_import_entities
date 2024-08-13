<?php

namespace Drupal\export_import_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Permet de selectionner une entité et de l'exporter.
 */
abstract class ImportBase extends FormBase {
  protected $steps = [
    'Import config',
    'Import page'
  ];
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function getSubmitText(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    return !empty($this->steps[$step]) ? $this->steps[$step] : 'Next';
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function buildFormByStep(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    switch ($step) {
      case 0:
        $plugin = self::getPluginImportContent();
        $allDatas = $plugin->ListConfigToImport();
        $options = [];
        foreach ($allDatas as $k => $vals) {
          if (!empty($vals['image']))
            $options[$k] = [
              "#type" => "html_tag",
              "#tag" => "div",
              "#attributes" => [
                "style" => "margin-bottom: 1rem;"
              ],
              [
                "#type" => "html_tag",
                "#tag" => "div",
                "#value" => $vals['site'] . ' : ' . $vals['name']
              ],
              [
                "#type" => "html_tag",
                "#tag" => "img",
                '#attributes' => [
                  'src' => $vals['image'],
                  'style' => "max-width:600px; height:auto; width:auto; max-height:1000px;"
                ]
              ]
            ];
        }
        $form['site_page_modele'] = [
          "#type" => "radios",
          "#title" => "Selectionner une page",
          "#options" => $options,
          '#ajax' => [
            'callback' => self::class . '::import_select_import_entity',
            'wrapper' => 'import_select_import_entity_id',
            'effect' => 'fade'
          ]
        ];
        $form['datas'] = [
          '#type' => 'details',
          '#open' => true,
          '#title' => 'datas',
          '#attributes' => [
            'id' => 'import_select_import_entity_id'
          ],
          '#tree' => true
        ];
        $site_page_modele = $form_state->getValue('site_page_modele');
        if ($site_page_modele) {
          [
            $base_directory,
            $keyIdentification
          ] = explode("--__", $site_page_modele);
          // $plugin->checkConfigToimport($base_directory);
          $form_state->set('base_directory', $base_directory);
          $form_state->set('keyIdentification', $keyIdentification);
          //
          $configs = $plugin->BuildBatchImportConfigs($base_directory);
          
          $form_state->set('BatchImportConfigs', $configs);
          $form['datas']['#title'] = 'datas (' . count($configs) . ' à importer )';
          foreach ($configs as $name => $config) {
            $form['datas'][$name] = [
              '#type' => 'details',
              '#open' => false,
              '#title' => $name
            ];
            $form['datas'][$name]['value'] = [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#value' => $config,
              '#attributes' => [
                'style' => "word-wrap:break-word;"
              ]
            ];
          }
          $form['datas']['actions'] = [
            '#type' => 'actions',
            'submit' => [
              '#type' => 'submit',
              '#value' => $this->getSubmitText($form, $form_state),
              // '#ajax' => [
              // 'callback' => self::class . '::export_import_submit_callback',
              // 'wrapper' => 'export_import_select_export_entity_id',
              // 'effect' => 'fade'
              // ],
              '#submit' => [
                // focntionne mais la methode doit etre statique.
                // self::class .
                // '::import_config_submit'
                '::import_config_submit'
              ]
            ]
          ];
        }
        break;
      
      case 1:
        $base_directory = isset($_GET['base_directory']) ? $_GET['base_directory'] : '';
        $keyIdentification = isset($_GET['keyIdentification']) ? $_GET['keyIdentification'] : '';
        $form_state->set('base_directory', $base_directory);
        $form_state->set('keyIdentification', $keyIdentification);
        /**
         * Tests rendu des images.
         */
        // $plugin = self::getPluginImportContent();
        // $allFiles = $plugin->getFiles($base_directory, $keyIdentification);
        // $options = [];
        // foreach ($allFiles as $contentFiles) {
        // foreach ($contentFiles as $files) {
        // foreach ($files as $file) {
        // $options[] = [
        // "#type" => "html_tag",
        // "#tag" => "div",
        // "#value" => $file['alt'],
        // [
        // "#type" => "html_tag",
        // "#tag" => "img",
        // '#attributes' => [
        // 'src' => $file['default_encode_file'],
        // 'style' => "max-width:600px; height:auto; width:auto;
        // max-height:1000px;"
        // ]
        // ]
        // ];
        // $pii = explode("base64,", $file['default_encode_file']);
        // $plugin->base64_to_file($pii[1], $file['default_filename']);
        // }
        // }
        // }
        // $form['datas']['files'] = $options;
        $form['datas']['actions'] = [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->getSubmitText($form, $form_state),
            // '#ajax' => [
            // 'callback' => self::class . '::export_import_submit_callback',
            // 'wrapper' => 'export_import_select_export_entity_id',
            // 'effect' => 'fade'
            // ],
            '#submit' => [
              // focntionne mais la methode doit etre statique.
              // self::class .
              // '::import_config_submit'
              '::import_page_submit'
            ]
          ]
        ];
        break;
      default:
        ;
        break;
    }
  }
  
  public function import_page_submit(array &$form, FormStateInterface $form_state) {
    $base_directory = $form_state->get('base_directory');
    $keyIdentification = $form_state->get('keyIdentification');
    if ($base_directory && $keyIdentification) {
      $plugin = self::getPluginImportContent();
      // On sauvegarde directecment les contenus. ( On na plus de temps, on
      // pourra ameliorer plus tard ).
      $entity = $plugin->getContent($base_directory, $keyIdentification);
      // $this->messenger()->addMessage("base_directory : " .
      // $form_state->get("base_directory"));
      $this->messenger()->addMessage(" La nouvelle page a été generer ou mise à jour : " . $entity->id());
    }
    else {
      $this->messenger()->addError(" Paramettre d'import non definie ");
    }
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_stat
   */
  public function import_config_submit(array &$form, FormStateInterface $form_state) {
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 1;
    $form_state->set('step', $nextStep);
    $configs = $form_state->get('BatchImportConfigs');
    if ($configs) {
      $operations = [];
      foreach ($configs as $name => $value) {
        $operations[] = [
          self::class . '::import_single_config',
          [
            $name,
            $value
          ]
        ];
      }
      $batch = [
        'operations' => $operations,
        'finished' => self::class . '::import_single_config_batch_finished',
        'title' => "Import de la configuration",
        'init_message' => "Debut de l'import de la configuration",
        'progress_message' => t('Processed @current out of @total.'),
        'error_message' => t('Batch has encountered an error.'),
        'message' => "Import config : " . $name
      ];
      batch_set($batch);
    }
    $form_state->setRedirect('export_import_entities.select_import_storage_entities', [],
      [
        'query' => [
          'step' => $nextStep,
          'base_directory' => $form_state->get("base_directory"),
          'keyIdentification' => $form_state->get("keyIdentification")
        ]
      ]);
  }
  
  static public function import_single_config($name, $config, &$context) {
    $context['message'] = "Import config : " . $name;
    $plugin = self::getPluginImportContent();
    $plugin->importConfig($name, $config);
  }
  
  static public function import_single_config_batch_finished() {
    \Drupal::messenger()->addMessage("Run import_single_config_batch_finished");
  }
  
  /**
   *
   * @return \Drupal\export_import_entities\Plugin\ImportContents\ImportContents
   */
  static function getPluginImportContent() {
    /**
     *
     * @var \Drupal\export_import_entities\ImportContentsPluginManager $MangerImportContent
     */
    $MangerImportContent = \Drupal::service("plugin.manager.import_content");
    return $MangerImportContent->createInstance("export_import_entities_import_contents");
  }
  
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
  static public function import_select_import_entity(array $form, FormStateInterface $form_state) {
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