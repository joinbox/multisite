<?php

namespace Drupal\multisite;

use Drupal\smart_ip\SmartIp;
use Drupal\taxonomy\Entity\Term;

/**
 * UserDetection service.
 */
class UserDetection {

  /**
   * Saves the current user region based on his IP address
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function detectCountry($ip): ?Term {
    //get smart Ip location based on IP
    $location = SmartIp::query($ip);
    $countryCode = isset($location['countryCode']) && !empty($location['countryCode']) ? $location['countryCode']: 'ch';
    //get country for countryCode
    $countries = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(
      [
        'vid' => 'country',
        'field_identifier' => $countryCode,
      ]
    );
    $country = array_pop($countries);

    if (!$country) {
      \Drupal::logger('multisite')->error(
        'Did not find any country term for code: @countryCode',
        ['@countryCode' => $countryCode]
      );

      //F10840 - use CH als fallback
      $countries = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(
        ['field_identifier' => 'CH']
      );
      $country = array_pop($countries);
    }

    //write logs
    \Drupal::logger('multisite')->info(
      'Detected: IP @ip; CountryCode @countryCode; Country @country',
      [
        '@ip' => $ip,
        '@countryCode' => $countryCode,
        '@country' => $country->getName(),
      ]
    );

    return $country;
  }

}
