<?php

namespace Drupal\multisite\Command;

use Drupal\domain\Access\DomainAccessCheck;
use Drupal\node\Entity\Node;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;

/**
 * Class SetAllDACommand.
 *
 * Drupal\Console\Annotations\DrupalCommand (
 *     extension="multisite",
 *     extensionType="module"
 * )
 */
class SetAllDACommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('multisite:setAllDA')
      ->setDescription($this->trans('Adds every domain to every node'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->getIo()->info('Starting');
    // we need all nodes
    $entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties();
    // we need all possible domains
    $allDomains = \Drupal::entityTypeManager()->getStorage('domain')->loadByProperties();
    $domainIds = array_keys($allDomains);

    foreach ($entities as $node) {
      if ($node->hasField('field_domain_access')) {
        try {
          $node->set('field_domain_access', $domainIds);
          $node->save();
        } catch (\Exception $e) {
          \Drupal::logger('multisite')->error(
            'Node @nodeId didn\'t get all Domains (Message: @e)',
            ['@nodeId' => $node->id(), '@e' => $e->getMessage()]
          );
        }
      }
    }
    $this->getIo()->info($this->trans('Done, buddy! What a pain... 😅'));
  }

}
