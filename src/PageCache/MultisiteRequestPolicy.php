<?php

namespace Drupal\multisite\PageCache;

use Drupal\Core\PageCache\DefaultRequestPolicy;
use Drupal\Core\Session\SessionConfigurationInterface;

/**
 * The default page cache request policy.
 *
 * Delivery of cached pages is denied if either the application is running from
 * the command line or the request was not initiated with a safe method (GET or
 * HEAD). Also caching is only allowed for requests without a session cookie.
 *
 * The DefaultRequestPolicy is extended, so we can add a policy which hinders the base path ("/")
 * from being cached (see https://github.com/BystronicJB/bystronic/issues/553). We then decorate
 * the original service - page_cache_request_policy - so that wherever the default policy is used
 * (e.g. http_middleware.page_cache), now this additional policy is also included (see
 * multisite.services.yml).
 */
class MultisiteRequestPolicy extends DefaultRequestPolicy {

  /**
   * Constructs the default page cache request policy.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(SessionConfigurationInterface $session_configuration) {
    parent::__construct($session_configuration);
    $this->addPolicy(new DenyBasePath());
  }
}
