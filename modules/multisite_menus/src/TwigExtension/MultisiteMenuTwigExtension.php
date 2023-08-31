<?php

namespace Drupal\multisite_menus\TwigExtension;

use Drupal\multisite\DomainResolverService;
use Drupal\multisite_menus\MultisiteMenuResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
class MultisiteMenuTwigExtension extends AbstractExtension {

  protected MultisiteMenuResolver $multisiteMenuResolver;

  protected DomainResolverService $domainResolverService;


  /**
   * @param MultisiteMenuResolver $multisiteMenuResolver
   * @param DomainResolverService $domainResolverService
   */
  public function __construct(MultisiteMenuResolver $multisiteMenuResolver, DomainResolverService $domainResolverService) {
    $this->multisiteMenuResolver = $multisiteMenuResolver;
    $this->domainResolverService = $domainResolverService;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('multisite_menu', [$this, 'getMultisiteMenu']),
    ];
  }

  /**
   * @param string $identifier
   *
   * @return mixed
   */
  public function getMultisiteMenu(string $identifier) {
    $activeDomain = $this->domainResolverService->getCurrentDomain();
    $multisiteMenu = $this->multisiteMenuResolver->getMenu($identifier, $activeDomain);
    return \Drupal::service('twig_tweak.menu_view_builder')->build($multisiteMenu->id());
  }

}
