<?php

namespace Drupal\multisite\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\multisite\DataGeneratorService;

/**
 * Class GenerateCommand.
 *
 * Drupal\Console\Annotations\DrupalCommand (
 *     extension="multisite",
 *     extensionType="module"
 * )
 */
class GenerateCommand extends Command {

  protected DataGeneratorService $multisiteDataGenerator;

  /**
   * Constructs a new GenerateCommand object.
   */
  public function __construct(DataGeneratorService $multisiteDataGenerator) {
    $this->multisiteDataGenerator = $multisiteDataGenerator;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('multisite:generateData')
      ->setDescription($this->trans('Loads data from csv files'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->multisiteDataGenerator->loadAllData();
    $this->getIo()->info($this->trans('done'));
  }

}
