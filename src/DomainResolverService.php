<?php

namespace Drupal\multisite;

use Drupal\domain\DomainNegotiator;
use Drupal\domain\Entity\Domain;
use Drupal\rondo_region\RegionService;
use Drupal\taxonomy\Entity\Term;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

/**
 * Class DomainResolverService.
 */
class DomainResolverService {

  protected DomainNegotiator $domainNegotiator;

  /**
   * RegionLanguageResolver constructor.
   */
  public function __construct(DomainNegotiator $domainNegotiator) {
    $this->domainNegotiator = $domainNegotiator;
  }


  /**
   * Get the current domain
   *
   * @return Domain
   */
  public function getCurrentDomain(): Domain {
    return $this->domainNegotiator->getActiveDomain();
  }

  /**
   * Returns the identifier of the current domain (web project)
   *
   * @return string
   */
  public function getCurrentProject(): string {
    return $this->getCurrentDomain()->getThirdPartySetting('country_path', 'domain_path');
  }

  /**
   * Get the default domain of the multisite setup
   *
   * @return Domain
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGlobalDomain(): Domain {
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadByProperties(
      ['is_default' => TRUE]
    );
    if (!$domains) {
      throw new \Exception('Please specify a default domain in your domain records!');
    }

    return array_pop($domains);
  }

  /**
   * Checks if the requested url has a valid domain as its first path element
   *
   * @param string $requestUri
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function domainPathIsValid(string $requestUri): bool {
    $pathElements = explode('/', $requestUri);
    $domainPath = array_key_exists(1, $pathElements) ? $pathElements[1] : NULL;
    // get all domains
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $domainPaths = array_map(function ($item) {
      return $item->get('third_party_settings')['country_path']['domain_path'];
    }, $domains);

    return in_array($domainPath, $domainPaths);
  }

  /**
   * Return the domain based on its identifier (country path)
   *
   * @param string $domainIdentifier
   *
   * @return Domain|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getDomain(string $domainIdentifier): Domain|null {
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $skywalker = [];
    array_walk($domains, function($domain) use (&$skywalker) {
      $skywalker[$domain->get('third_party_settings')['country_path']['domain_path']] = $domain;
    });
    return $skywalker[$domainIdentifier];
  }

  /**
   * Checks if country is part of a given domain
   *
   * @param Term   $country
   * @param Domain $domain
   *
   * @return bool
   */
  public function isPartOfDomain(Term $country, Domain $domain): bool {
    return $country->field_domain?->entity->id() == $domain->id();
  }
}
