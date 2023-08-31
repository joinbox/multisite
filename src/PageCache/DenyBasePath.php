<?php

namespace Drupal\multisite\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A policy allowing to bypass cache for requests with 'no-cache' parameter. Also don't cache front
 * without domain and language because redirect will be cached for that request.
 */
class DenyBasePath implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    if (!is_null($request->get('no-cache')) || $this->isBasePath($request)) {
      return self::DENY;
    }
  }

  /**
   * @param Request $request
   *
   * @return bool
   */
  private function isBasePath(Request $request): bool {
    return $request->getPathInfo() == "/";
  }

}
