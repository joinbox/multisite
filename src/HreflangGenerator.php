<?php

namespace Drupal\multisite;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\domain\Entity\Domain;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\multisite\Plugin\LanguageNegotiation\LanguageNegotiationDomainLanguageUrl;
use Drupal\taxonomy\Entity\Term;

/**
 * For SEO purposes we need to generate valid hreflang meta tags
 */
class HreflangGenerator {

  //cache base id
  const _CIDBASE = 'multisite_href';

  protected CustomDomainAccessManager $domainAccessManager;

  protected CacheBackendInterface $cache;

  protected DomainResolverService $domainResolverService;

  protected LanguageManager $languageManager;

  protected string $absoluteDomainPath;

  /**
   * @param DomainAccessManagerInterface $domainAccessManager
   * @param CacheBackendInterface        $cache
   * @param DomainResolverService        $domainResolverService
   * @param LanguageManager              $languageManager
   */
  public function __construct(
    DomainAccessManagerInterface $domainAccessManager,
    CacheBackendInterface $cache,
    DomainResolverService $domainResolverService,
    LanguageManager $languageManager
  ) {
    $this->domainAccessManager = $domainAccessManager;
    $this->cache = $cache;
    $this->domainResolverService = $domainResolverService;
    $this->languageManager = $languageManager;

    //get settings
    $absoluteDomainPath = Settings::get('multisite')['absoluteDomainPath'];
    if (!$absoluteDomainPath) {
      $message = 'Add $settings[\'multisite\'] = [\'domainPath\' => \'https://www.bystronic.com\']; to your settings.php!';
      \Drupal::logger('multisite')->error($message);
      \Drupal::messenger()->addError($message);
    }
    $this->absoluteDomainPath = $absoluteDomainPath;
  }

  /**
   * Singleton implementation for hreflangs
   *
   * @param EntityInterface $entity
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getHreflangs(EntityInterface $entity): array {
    $hreflangs = $this->getCachedHreflangs($entity);

    //cache data if needed
    if (!$hreflangs) {
      $hreflangs = $this->generateHreflangs($entity);
    }

    return $hreflangs;
  }

  /**
   * Add the entity based hreflang cache
   * Add also a tag to invalidate it later manually if needed.
   *
   * @param EntityInterface $entity
   * @param array           $data
   *
   * @return void
   */
  private function cacheHreflangs(EntityInterface $entity, array $data): void {
    $tags = [
      self::_CIDBASE,
      $entity->bundle() . ':' . $entity->id(),
    ];

    \Drupal::cache()->set(
      self::_CIDBASE . '_' . $entity->id(),
      $data,
      CacheBackendInterface::CACHE_PERMANENT,
      $tags
    );
  }

  /**
   * @param EntityInterface $entity
   *
   * @return object|null
   */
  private function getCachedHreflangs(EntityInterface $entity): ?array {
    $cache = $this->cache->get(self::_CIDBASE . '_' . $entity->id());

    return $cache ? $cache->data : NULL;
  }

  /**
   * Generate a combination of country and language and point it to the correct domain url
   * This is purely based on the domain access configuration.
   *
   * @param EntityInterface $entity
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function generateHreflangs(EntityInterface $entity): array {
    $hreflangs = [];

    //generate x-default
    $defaultUrl = $this->generateXDefaultHreflang($entity);

    //generate all other combinations
    $domains = $this->domainAccessManager->getAccessibleDomains($entity);
    foreach ($domains as $domain) {
      $countries = $this->getRelevantCountries($domain);

      foreach ($countries as $country) {
        $countryCode = strtolower($country->field_identifier->value);
        $languages = $this->languageManager->getCountryLanguages($country);

        foreach ($languages as $language) {
          $langCode = $language->getId();

          //skip if node has no translation of this language
          if (!$entity->hasTranslation($langCode)) {
            continue;
          }

          $entity = $entity->getTranslation($langCode);
          $url = $this->createHarmonizedURL($entity, $domain);

          //do not include urls, which are identical to the x-default
          if ($url == $defaultUrl) {
            continue;
          }

          //some languages already include the country code i.e. en-us
          //just return the language code for these cases
          if(preg_match('/^([a-z]{2})-[a-z]{2}$/', $langCode, $matches)) {
            $langCode = $matches[1];
          }

          //finally, generate the golden pairs
          $hreflangs[] = $this->generateHreflangHeader($url, $langCode, $countryCode);
        }
      }
    }
    //add x-default
    $hreflangs[] = $this->generateHreflangHeader($defaultUrl);

    //properly cache them
    $this->cacheHreflangs($entity, $hreflangs);

    return $hreflangs;
  }

  /**
   * Generates the x-default hreflang for a given entity
   * It also checks for proper access
   *
   * @param EntityInterface $entity
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function generateXDefaultHreflang(EntityInterface $entity): string {
    //get the globally defined "fallback" domain
    $defaultDomain = $this->domainResolverService->getGlobalDomain();

    //it could happen, that the global domain does not have access to the node.
    if (!$this->domainAccessManager->hasAccessToEntity($defaultDomain, $entity)) {
      //so we just select the first valid one
      $domainIds = array_keys($this->domainAccessManager->getAccessValues($entity));
      $defaultDomain = Domain::load(reset($domainIds));
    };

    //and then generate the x-default hreflang
    $defaultLanguage = $this->languageManager->getDefaultLanguageCode($defaultDomain);
    $defaultLanguageEntity = $entity->hasTranslation($defaultLanguage) ? $entity->getTranslation(
      $defaultLanguage
    ) : $entity;

    return $this->createHarmonizedURL($defaultLanguageEntity, $defaultDomain);
  }

  /**
   * Generates a harmonized, formatted URL of a given entity and domain
   *
   * @param EntityInterface $entity
   * @param Domain          $domain
   *
   * @return string
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function createHarmonizedURL(EntityInterface $entity, Domain $domain): string {
    //remove the domain, because it is wrong. who knows why...
    $entityUrl = preg_replace(
      '/\/[a-z]+\//',
      $domain->getThirdPartySetting('country_path', 'domain_path') . '/',
      $entity->toUrl()->toString(),
      1
    );

    //special treatment for front.
    $frontUrl = \Drupal::config('system.site')->get('page.front');
    if (str_contains($frontUrl, '/node/' . $entity->id())) {
      $entityUrl = str_replace($frontUrl, '', $entityUrl);
    }

    //reassemble it, with the correct domain.
    return $this->absoluteDomainPath . '/' . $entityUrl;
  }

  /**
   * Generates a hreflang header attribute, Drupal style
   * If no lang or country code given, we set it as x-default
   *
   * @param string $url
   * @param string|NULL $langCode
   * @param string|NULL $countryCode
   *
   * @return array
   */
  private function generateHreflangHeader(string $url, string $langCode = NULL, string $countryCode = NULL): array {
    return [
      [
        'rel' => 'alternate',
        'hreflang' => $langCode && $countryCode ? $langCode . '-' . $countryCode : 'x-default',
        'href' => $url,
      ],
      TRUE,
    ];
  }

  /**
   * Gets all relevant countries for a domain
   *
   * @param Domain $domain
   *
   * @return Term[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getRelevantCountries(Domain $domain): array {
    $query = \Drupal::database()->query(
      "
        select distinct(country.tid)
        from taxonomy_term_data as country
        join taxonomy_term__field_domain as domain on country.tid = domain.entity_id
        where domain.field_domain_target_id = :domain
      ",
      [':domain' => $domain->id()]
    );

    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple(
      $query->fetchCol()
    );
  }
}
