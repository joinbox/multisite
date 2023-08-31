<?php

namespace Drupal\multisite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\multisite\CountryCookieStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Class RegionSelectorRedirectController.
 */
class RegionSelectorRedirectController extends ControllerBase {

  protected CountryCookieStorage $countryCookieStorage;

  /**
   * @param ContainerInterface $container
   *
   * @return RegionSelectorRedirectController
   */
  public static function create(ContainerInterface $container): RegionSelectorRedirectController {
    $instance = parent::create($container);
    $instance->countryCookieStorage = $container->get('multisite.country_storage');

    return $instance;
  }

  /**
   *
   *
   * @param int    $regionId
   * @param string $redirectUrl
   *
   * @return TrustedRedirectResponse
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function redirectToRegion(int $regionId, string $redirectUrl): TrustedRedirectResponse {
    $this->countryCookieStorage->setCurrentCountry(Term::load($regionId));

    return new TrustedRedirectResponse(base64_decode($redirectUrl));
  }

}
