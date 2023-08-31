<?php

namespace Drupal\multisite;

use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;
use Drupal\domain\DomainInterface;
use Drupal\domain\Entity\Domain;
use Drupal\taxonomy\Entity\Term;

/**
 * LanguageManager service.
 */
class LanguageManager {

  const FALLBACK_LANGUAGE = 'en';

  protected array $defaultLanguages;

  public function __construct() {
    $defaultLanguages = Settings::get('multisite')['domainDefaultLanguages'];
    if (!$defaultLanguages) {
      $message = 'Add $settings[\'multisite\'] = [\'defaultLanguages\']; to your settings.php! Check the README.md';
      \Drupal::logger('multisite')->error($message);
      \Drupal::messenger()->addError($message);
    }
    $this->defaultLanguages = $defaultLanguages[0];
  }

  /**
   * Returns the language for a given domain.
   * If no language was found, provide 'en' as default
   *
   * @param DomainInterface $domain
   *
   * @return \Drupal\Core\Language\LanguageInterface
   */
  public function getDefaultLanguage(DomainInterface $domain): \Drupal\Core\Language\LanguageInterface {
    $domainPath = $domain->getThirdPartySetting(
      'country_path',
      'domain_path'
    );

    if(!key_exists($domainPath, $this->defaultLanguages)) \Drupal::languageManager()->getLanguage(self::FALLBACK_LANGUAGE);
    return \Drupal::languageManager()->getLanguage($this->defaultLanguages[$domainPath]);
  }

  /**
   * Returns the language code for given doamin
   *
   * @param DomainInterface $domain
   *
   * @return string
   */
  public function getDefaultLanguageCode(DomainInterface $domain): string {
    return $this->getDefaultLanguage($domain)->getId();
  }

  /**
   * Return languages of country
   *
   * @param Term $country
   *
   * @return Language[]
   */
  public function getCountryLanguages(Term $country): array {
    return $country->field_language->referencedEntities();
  }

  /**
   * Return language codes of country
   *
   * @param Term $country
   *
   * @return array
   */
  public function getCountryLanguageCodes(Term $country): array {
    return array_map(function($language) {
      return $language->id();
    }, $this->getCountryLanguages($country));
  }

  /**
   * Gets languages of domain
   * This is given by the countries belonging to the domain
   *
   * @param DomainInterface $domain
   *
   * @return mixed
   */
  public function getDomainLanguageCodes(DomainInterface $domain) {
    $query = \Drupal::database()->query(
      "
        select distinct(field_language_target_id)
        from taxonomy_term_data as country
        join taxonomy_term__field_domain as domain on country.tid = domain.entity_id
        join taxonomy_term__field_language as languages on country.tid = languages.entity_id
        where domain.field_domain_target_id = :domain
      ",
      [':domain' => $domain->id()]
    );
    return $query->fetchCol();
  }

}
