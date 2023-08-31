<?php

namespace Drupal\multisite;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\redirect\RedirectChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * DomainRedirectMiddleware middleware.
 */
class DomainRedirectMiddleware implements HttpKernelInterface {

  protected HttpKernelInterface $httpKernel;

  protected DomainResolverService $domainResolver;

  protected UserDetection $userDetection;

  protected CountryCookieStorage $countrySessionStorage;

  /**
   * @param HttpKernelInterface   $http_kernel
   * @param DomainResolverService $domainResolver
   * @param UserDetection         $userDetection
   * @param CountryCookieStorage  $countryCookieStorage
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    DomainResolverService $domainResolver,
    UserDetection $userDetection,
    CountryCookieStorage $countryCookieStorage,
  ) {
    $this->httpKernel = $http_kernel;
    $this->domainResolver = $domainResolver;
    $this->userDetection = $userDetection;
    $this->countrySessionStorage = $countryCookieStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $requestUri = $request->getRequestUri();

    //check if uri should be passed through
    if ($requestUri != '/' && ($this->domainResolver->domainPathIsValid($requestUri) || preg_match('/^\/sites\/.+/', $requestUri))) {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    $clientIp = $request->getClientIp();
    $country = $this->countrySessionStorage->getCurrentCountry() ?? $this->userDetection->detectCountry($clientIp);

    //get domain for region
    $domain = $country->field_domain->entity;
    $domainPath = '/' . $domain->getThirdPartySetting('country_path', 'domain_path');

    // if we came from a redirect, add path structure after domainPath (which is the region)
    $desiredPath = $domainPath . $request->getRequestUri();

    //write logs
    \Drupal::logger('multisite')->info('Redirect @ip to DomainPath @desiredPath', [
      '@ip' => $clientIp,
      '@desiredPath' => $desiredPath,
    ]);

    return new TrustedRedirectResponse($desiredPath, 307, ['Cache-Control' => 'max-age=0']);
  }

}
