<?php

namespace Drupal\multisite_menus;

use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\system\Entity\Menu;

/**
 * MultisiteMenuResolver service.
 */
class MultisiteMenuResolver {

  const CONFIG_NAME = 'multisite_menus.settings';

  /**
   * Return the domain specific menu.
   *
   * @param string $menuIdentifier
   * @param Domain $domain
   *
   * @return Menu
   */
  public function getMenu(string $menuIdentifier, Domain $domain): Menu {
    $config = \Drupal::config(self::CONFIG_NAME)->get('menus');

    //check if menu exists - if not, return fallback
    if (!isset($config[$domain->getDomainId()][$menuIdentifier])) {
      \Drupal::logger('multisite_menus')->error(
        'Make sure to fill in multisite menu form: @url',
        ['@url' => Url::fromRoute('multisite_menus.settings_form')->toString()]
      );
      $fallbackMenu = Menu::load($menuIdentifier);
      return $fallbackMenu ?? Menu::load('main');
    }

    return Menu::load($config[$domain->getDomainId()][$menuIdentifier]);
  }

}
