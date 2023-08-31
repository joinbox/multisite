<?php

namespace Drupal\multisite\Plugin\LanguageNegotiation;

use Drupal\Component\Utility\UserAgent;
use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;
use Drupal\multisite\CountryCookieStorage;
use Drupal\multisite\DomainResolverService;
use Drupal\multisite\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;

/**
 * Class for identifying language via URL prefix or domain.
 *
 * @LanguageNegotiation(
 *   id =
 *   \Drupal\multisite\Plugin\LanguageNegotiation\LanguageNegotiationDomainLanguageUrl::METHOD_ID,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_URL},
 *   weight = -8,
 *   name = @Translation("Domain Language URL Handler"),
 *   description = @Translation("Custom Domain Language URL Handler."),
 * )
 */
class LanguageNegotiationDomainLanguageUrl extends LanguageNegotiationUrl {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'domain-langauge-url';

  /**
   * URL language negotiation: use the path prefix as URL language indicator.
   */
  const CONFIG_PATH_PREFIX = 'path_prefix';

  /**
   * URL language negotiation: use the domain as URL language indicator.
   */
  const CONFIG_DOMAIN = 'domain';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;
    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $config = $this->config->get('language.negotiation')->get('url');
      $language = $this->getNegotiatedLanguage($request, $languages, $config);
      $langcode = $language->getId();
    }
    return $langcode;
  }

  /**
   * Returns negotiated language based on request URI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param array                                     $languages
   *   Array of languages.
   * @param array                                     $config
   *   Language configuration.
   *
   * @return \Drupal\Core\Language\Language
   *   Negotiated language.
   */
  protected function getNegotiatedLanguage(Request $request, array $languages, array $config) {
    //get domain and language from url
    preg_match("/^\/(?'domainCode'[a-z]{3})($|(\/(?'langCode'[a-z]{2}(-[a-z]{2})?)?))/", $request->getRequestUri(), $matches);
    $domainCode = key_exists('domainCode', $matches) ? $matches['domainCode'] : null;
    $languageCode = key_exists('langCode', $matches) ? $matches['langCode'] :null;

    //return if no domainCode or languageCode gotten
    if(!$domainCode && !$languageCode) return reset($languages);

    //in case user already specified a language, return it.
    if(key_exists($languageCode, $languages)) return $languages[$languageCode];

    // we start resolving the language now
    /** @var LanguageManager $multisiteLanguageManager */
    $multisiteLanguageManager = \Drupal::service('multisite.language_manager');
    /** @var CountryCookieStorage $countryCookieStorage */
    $countryCookieStorage = \Drupal::service('multisite.country_storage');
    /** @var DomainResolverService $domainResolver */
    $domainResolver = \Drupal::service('multisite.domain_resolver');

    //browser languages
    $browserLanguageCodes = $request->server->get('HTTP_ACCEPT_LANGUAGE');
    //domain
    $domain = $domainResolver->getDomain($domainCode);
    //country
    $country = $countryCookieStorage->getCurrentCountry();

    //country based matching
    //problematic since country could be completely different from domain when user switches manually to /zaf i.e.
    //this is the reason why we added "isPartOfDomain" check
    if($country && $domain && $domainResolver->isPartOfDomain($country, $domain)) {
      //1st - try to match country with browser languages
      $countryLangCodes = $multisiteLanguageManager->getCountryLanguageCodes($country);
      $language = $this->getBestMatchingLanguage($browserLanguageCodes, $countryLangCodes);
      if($language) return $language;

      //2nd - return first language of given country
      $countryLanguages = $multisiteLanguageManager->getCountryLanguages($country);
      $language = reset($countryLanguages);
      if($language) return $language;
    }

    //sometimes user is not located or in a complete other country, so we use the domain for language resolve
    if($domain) {
      //3rd - try to match domain languages with browser languages
      $domainLanguageCodes = $multisiteLanguageManager->getDomainLanguageCodes($domain);
      $language = $this->getBestMatchingLanguage($browserLanguageCodes, $domainLanguageCodes);
      if($language) return $language;

      //4th - return default domain language
      $language = $multisiteLanguageManager->getDefaultLanguage($domain);
      if($language) return $language;
    }

    //5th - Fallback, when everything else fails.
    return reset($languages);
  }


  /**
   * Tries to match browser Languages towards possible Languages
   *
   * @param $browserLanguageCodes
   * @param $possibleLanguageCodes
   *
   * @return Language|null
   */
  private function getBestMatchingLanguage($browserLanguageCodes, $possibleLanguageCodes): Language|null {
    $languageCode = UserAgent::getBestMatchingLangcode($browserLanguageCodes, $possibleLanguageCodes, $this->config->get('language.mappings')->get('map'));
    return \Drupal::languageManager()->getLanguage($languageCode);
  }

}
