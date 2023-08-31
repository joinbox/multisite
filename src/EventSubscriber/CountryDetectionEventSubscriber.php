<?php

namespace Drupal\multisite\EventSubscriber;

use Drupal\multisite\CountryCookieStorage;
use Drupal\multisite\UserDetection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Locates the user and saves its country in the session
 */
class CountryDetectionEventSubscriber implements EventSubscriberInterface {

  protected CountryCookieStorage $countryCookieStorage;

  protected UserDetection $userDetection;

  /**
   * @param CountryCookieStorage $countryCookieStorage
   * @param UserDetection        $userDetection
   */
  public function __construct(
    CountryCookieStorage $countryCookieStorage,
    UserDetection $userDetection,
  ) {
    $this->countryCookieStorage = $countryCookieStorage;
    $this->userDetection = $userDetection;
  }

  /**
   * Detect the users country if not done yet.
   *
   * @param RequestEvent $event
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function detectUser(RequestEvent $event): void {
    if($this->countryCookieStorage->getCurrentCountry()) return;

    $request = $event->getRequest();
    $clientIp = $request->getClientIp();
    $country = $this->userDetection->detectCountry($clientIp);

    $this->countryCookieStorage->setCurrentCountry($country);
  }

  /**
   * @return \string[][]
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['detectUser'],
    ];
  }

}
