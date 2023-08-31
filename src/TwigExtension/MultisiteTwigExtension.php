<?php

namespace Drupal\multisite\TwigExtension;

use Drupal\multisite\CountryCookieStorage;
use Drupal\taxonomy\Entity\Term;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\multisite\DomainResolverService;


class MultisiteTwigExtension extends AbstractExtension {

  protected DomainResolverService $domainResolver;

  protected CountryCookieStorage $countryCookieStorage;

  public function __construct(DomainResolverService $domainResolver, CountryCookieStorage $countryCookieStorage) {
    $this->domainResolver = $domainResolver;
    $this->countryCookieStorage = $countryCookieStorage;
  }

  /**
   * @return TwigFunction[]
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('getCurrentProject', [$this, 'getCurrentProject']),
      new TwigFunction('getCurrentCountryIdentifier', [$this, 'getCurrentCountryIdentifier']),
    ];
  }

  /**
   * Returns the current domain identifier / web project
   *
   * @return string
   */
  public function getCurrentProject(): string {
    return $this->domainResolver->getCurrentProject();
  }

  /**
   * Returns the current country's identifier
   *
   * @return string
   */
  public function getCurrentCountryIdentifier(): string {
    return $this->countryCookieStorage->getCurrentCountry()?->field_identifier->value;
  }

}
