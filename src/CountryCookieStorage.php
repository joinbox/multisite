<?php

namespace Drupal\multisite;

use Drupal\taxonomy\Entity\Term;

/**
 * Class CountryCookieStorage.
 *
 * This class is used to save the current user's location in a cookie.
 */
class CountryCookieStorage {

  const _COOKIE_NAME = 'currentCountry';

  /**
   * Sets the current country term id for user
   *
   * @param Term $country
   *
   * @return void
   */
  public function setCurrentCountry(Term $country): void {
    setcookie(self::_COOKIE_NAME, $country->id(), 0, '/');
    $_COOKIE[self::_COOKIE_NAME] = $country->id();
  }

  /**
   * Returns the current country term for user
   *
   * @return Term|null
   */
  public function getCurrentCountry(): Term|null {
    $countryId = key_exists(self::_COOKIE_NAME, $_COOKIE) ? $_COOKIE[self::_COOKIE_NAME] : NULL;
    return $countryId ? Term::load($countryId) : NULL;
  }

}
