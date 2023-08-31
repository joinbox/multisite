<?php

namespace Drupal\multisite;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * DomainValidationMiddleware middleware.
 */
class DomainValidationMiddleware implements HttpKernelInterface {

  use StringTranslationTrait;

  protected HttpKernelInterface $httpKernel;

  protected DomainResolverService $domainResolver;

  protected LanguageManager $languageManager;

  /**
   * Constructs the DomainValidationMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
    $this->domainResolver = \Drupal::service('multisite.domain_resolver');
    $this->languageManager = \Drupal::service('multisite.language_manager');
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    //check if ip is allowed to pass domain validation
    $ipsToSkipDomainValidation = Settings::get('multisite')['skipDomainValidation'] ?? [];
    $skipDomainValidation = in_array($request->getClientIp(), $ipsToSkipDomainValidation);
    if($skipDomainValidation) return $this->httpKernel->handle($request, $type, $catch);

    //skip domain validation for all admin paths
    $requestUri = $request->getRequestUri();
    try {
      $router = \Drupal::service('router.no_access_checks')->matchRequest($request);
      $isAdminPath = \Drupal::service('router.admin_context')->isAdminRoute($router['_route_object']);
      $isUserPage = ($router['_route'] == 'user.page');
    //in case router can not match request, we will use good ol' regex
    } catch (ResourceNotFoundException $e) {
      $isAdminPath = preg_match('/^\/[a-z]{3}\/[a-z]{2}(-[a-z]{2})?\/(admin|((node|taxonomy\/term|media|user)\/(\d*)\/(add|edit|translations|delete)))/', $requestUri);
      $isUserPage = str_ends_with($requestUri, '/user') || str_ends_with($requestUri, '/user/login') || str_ends_with($requestUri, '/user/logout');
    }
    if($isAdminPath || $isUserPage) return $this->httpKernel->handle($request, $type, $catch);

    //actually do the domain / language validation
    if (preg_match('/^\/([a-z]{3})\/([a-z]{2}(?:-[a-z]{2})?)(?:\/|$)/', $requestUri, $matches)) {
      $domainCode = $matches[1];
      $languageCode = $matches[2];

      //validate domain
      $domain = $this->domainResolver->getDomain($domainCode);
      if (!$domain) {
        throw new NotFoundHttpException('No valid domain');
      }

      //validate language
      $domainLanguageCodes = $this->languageManager->getDomainLanguageCodes($domain);
      if (!in_array($languageCode, $domainLanguageCodes)) {
        //redirect to domain only, so that it passes through LanguageNegotiation again
        return new TrustedRedirectResponse("/$domainCode", 307, ['Cache-Control' => 'max-age=0']);
      }
    }

    //all good, pass on the request
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
