<?php

namespace Drupal\multisite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\country_path\Entity\CountryPathDomain;
use Drupal\domain\Entity\Domain;
use Drupal\domain_access\DomainAccessManager;

/**
 * Custom implementation of a DomainAccessManager.
 * Adds some additional methods that we use.
 *
 */
class CustomDomainAccessManager extends DomainAccessManager {

  public function __construct() {}

  /**
   * Checks if a domain has access to an entity
   *
   * @param Domain          $domain
   * @param EntityInterface $entity
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasAccessToEntity(Domain $domain, EntityInterface $entity): bool {
    $domains = $this->getAccessibleDomains($entity);

    return in_array($domain->getOriginalId(), array_keys($domains));
  }

  /**
   * Check if an entity is allowed to show on a given (mostly current) domain
   * This is used for programmatically loaded (or teasered) entities like in content- or
   * teaserlists. Domain Access only checks on {{entity}}__full
   *
   * @param CountryPathDomain $domain
   * @param EntityInterface   $entity
   *
   * @return bool
   */
  public function hasAccessByDomain(CountryPathDomain $domain, EntityInterface $entity): bool {
    $entityAccessDomains = $entity->field_domain_access->referencedEntities();
    $currentDomain = $domain->getOriginalId();
    $entityAccessDomainIds = array_map(function ($item) {
      return $item->getOriginalId();
    }, $entityAccessDomains);

    return in_array($currentDomain, $entityAccessDomainIds);

  }

  /**
   * Get all the relevant domains for a given node. note that we do not differ languages. every
   * node has the same access, no matter the language if "all affiliates" is marked, just get all
   * domains
   *
   * @param EntityInterface $entity
   *
   * @return Domain[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAccessibleDomains(EntityInterface $entity): array {
    $domainStorage = \Drupal::entityTypeManager()->getStorage('domain');

    //we will load all domains if either
    // 1. the entity does not support domain access at all
    // 2. if all affiliates value is set to true
    if (!$entity->hasField(
        'field_domain_all_affiliates'
      ) || $entity->field_domain_all_affiliates->value == 1) {
      $domainKeys = array_keys(
        array_map(function ($item) {
          return $item->get('third_party_settings')['country_path']['domain_path'];
        }, $domainStorage->loadMultiple())
      );
      //load only the domains chosen
    } else {
      $domainKeys = array_keys(self::getAccessValues($entity));
    }

    return $domainStorage->loadMultiple($domainKeys);
  }
}
