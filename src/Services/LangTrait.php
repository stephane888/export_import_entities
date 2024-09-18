<?php

namespace Drupal\export_import_entities\Services;

trait LangTrait {
    /**
     *
     * @var array $languageNegotiator
     */
    protected $languageNegotiator = [];

    protected function getLanguagesNegotiatorConfigs() {
        if (!$this->languageNegotiator) {
            $languages = $this->configStorage->read('domain.language.' . $this->currentDomaine->id() . '.language.negotiation');
            if (!empty($languages['languages'])) {
                foreach ($languages['languages'] as $language_id) {
                    if (!str_contains($language_id, "LANGUAGE_site_default"))
                        $this->languageNegotiator[$language_id] = $language_id;
                }
            }
            // si l'utilisateur n'a pas configurer les langues.
            if (!$this->languageNegotiator) {
                foreach (\Drupal::languageManager()->getLanguages() as $language) {
                    $this->languageNegotiator[$language->getId()] = $language->getId();
                }
            }
        }
        return $this->languageNegotiator;
    }
}
