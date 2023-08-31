<?php

namespace Drupal\multisite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for multisite routes.
 */
class RegionOverlay extends ControllerBase {

  /**
   * Generates the Region / Country switcher overlay
   * TODO add some kind of caching per page
   *
   * base64_encoded URL
   * @param string $encodedUrl
   *
   * @return Response
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generateOverlay(string $encodedUrl): Response {
    $currentURL = base64_decode($encodedUrl);

    /* @var $joinboxHelpers \Drupal\joinbox_library\HelperService */
    $joinboxHelpers = \Drupal::service('joinbox_library.helpers');
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $renderable = [
      '#theme' => 'region_selector',
      '#continents' => array_map(
        function ($continent) use ($storage, $currentURL, $joinboxHelpers) {
          return [
            'title' => $joinboxHelpers->getTranslatedEntity($continent)->getName(),
            'countries' => array_map(function ($country) use ($currentURL, $joinboxHelpers) {
              //translate region
              $domain = $country->field_domain->entity;
              $domainPath = $domain->getThirdPartySetting('country_path', 'domain_path');

              return [
                'title' => $joinboxHelpers->getTranslatedTermName($country),
                'identifier' => $country->field_identifier->value,
                'languages' => array_map(
                  function ($language) use ($country, $currentURL, $domainPath) {
                    $languageCode = $language->getId();
                    $redirectUrl = "/" . $domainPath . "/" . $languageCode . $currentURL;

                    return [
                      'title' => $languageCode,
                      //TODO link directly to correct url. at the moment we call a route to set the region id. this should be done differently to remove redirect chains
                      'url' => '/multisite/redirect/' . $country->id() . '/' . base64_encode(
                          $redirectUrl
                        ),
                    ];
                  },
                  $country->field_language->referencedEntities()
                ),
              ];
            },
              $storage->loadMultiple(
                $storage->getQuery()
                  ->condition('vid', 'country')
                  ->condition('field_continent', $continent->id())
                  ->sort('name', 'ASC', \Drupal::languageManager()->getCurrentLanguage()->getId())
                  ->execute()
              )),
          ];
        },
        $storage->loadMultiple(
          $storage->getQuery()
            ->condition('vid', 'continent')
            ->sort('name', 'ASC', \Drupal::languageManager()->getCurrentLanguage()->getId())
            ->execute()
        )
      ),
      '#cache' => [
        '#max-age' => 0,
      ]
    ];

    return new Response(\Drupal::service('renderer')->render($renderable), 200);
  }
}
