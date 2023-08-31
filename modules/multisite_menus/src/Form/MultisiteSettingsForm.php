<?php

namespace Drupal\multisite_menus\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multisite_menus\MultisiteMenuResolver;
use Drupal\system\Entity\Menu;

/**
 * Configure multisite_menus settings for this site.
 */
class MultisiteSettingsForm extends ConfigFormBase {

  /**
   * Define here which menus could differ from domain to domain
   * TODO could be defined somewhere else...
   */
  const MENUS = ['footer', 'footer-legal'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multisite_menus_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [MultisiteMenuResolver::CONFIG_NAME];
  }

  /**
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    //values
    $configValues = $this->config(MultisiteMenuResolver::CONFIG_NAME)->get('menus');

    //loop domains
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadByProperties();
    foreach ($domains as $domain) {
      $domainName = $domain->get('name');
      $domainId = $domain->getDomainId();

      $form[$domainId] = [
        '#type' => 'fieldset',
        '#title' => $domainName,
      ];

      //loop menus
      foreach (self::MENUS as $menu) {
        $form[$domainId][$menu] = [
          '#title' => $menu,
          '#type' => 'entity_autocomplete',
          '#target_type' => 'menu',
          '#default_value' => isset($configValues[$domainId][$menu]) ? Menu::load($configValues[$domainId][$menu]): NULL,
          '#required' => TRUE,
        ];
      }
   }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(MultisiteMenuResolver::CONFIG_NAME)
      ->set('menus', $form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

}
