<?php

namespace Drupal\multisite;

use Drupal\domain\Entity\Domain;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use function Aws\map;

/**
 * Class DataGeneratorService.
 */
class DataGeneratorService {

  private string $modulePath;

  //define the types you want to import.
  const CONTINENT = [
    'entityName' => 'continent',
    'entityType' => 'taxonomy_term',
    'filePath' => '/data/continents.csv',
  ];
  const COUNTRY = [
    'entityName' => 'country',
    'entityType' => 'taxonomy_term',
    'filePath' => '/data/countries.csv',
  ];

  //specify the order in which they will be imported
  const IMPORT_ORDER = [
    self::CONTINENT,
    self::COUNTRY,
  ];

  /**
   * Constructs a new DataGeneratorService object.
   */
  public function __construct() {
    $this->modulePath = DRUPAL_ROOT . '/' . \Drupal::service('module_handler')->getModule(
        'multisite'
      )->getPath();
  }

  /**
   * Start to import the data
   *
   * @return void
   * @throws \Exception
   */
  public function loadAllData(): void {
    foreach (self::IMPORT_ORDER as $entity) {
      $entityName = $entity['entityName'];
      $entityType = $entity['entityType'];
      $path = $entity['filePath'];

      //load data from file
      $data = array_map('str_getcsv', file($this->modulePath . $path));
      if (!$data) {
        throw new \Exception('Could not load file ' . $this->modulePath . $path);
      }

      //restructure the array and use header information as array keys within the data array
      $header = array_shift($data);
      $data = array_map(function ($row) use ($header) {
        return array_combine($header, $row);
      }, $data);

      //delete old data
      $this->delete($entityType, $entityName);

      //call the factory to create the data
      $this->create($entityName, $data);
    }
  }

  /**
   * Factory to create data
   *
   * @param $entityName
   * @param $data
   *
   * @return void
   * @throws \Exception
   */
  private function create($entityName, $data): void {
    match ($entityName) {
      self::CONTINENT['entityName'] => $this->createContinents($data),
      self::COUNTRY['entityName'] => $this->createCountries($data),
      default => \Drupal::logger('multisite.import')->error(
        'Did not find any factory for given @type',
        ['@type' => $entityName]
      )
    };
  }


  /**
   * @param array $data
   *
   * @return void
   */
  private function createContinents(array $data): void {
    foreach ($data as $row) {
      try {
        /** @var $term Term */
        $term = Term::create([
          'vid' => 'continent',
          'field_identifier' => $row['identifier'],
          'name' => $row['en'],
        ]);
        $this->createTranslations($term, $row);
        $term->save();

      } catch (\Exception $e) {
        \Drupal::logger('multisite.import')->alert(
          'Could not load row @row',
          ['@row' => serialize($row)]
        );
      }
    }
  }

  /**
   * @param array $data
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function createCountries(array $data): void {
    $nodeStorageHandler = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($data as $row) {
      try {
        /** @var $term Term */
        $term = Term::create([
          'vid' => 'country',
          'field_identifier' => $row['identifier'],
          'field_shortcut' => $row['identifier'],
          'name' => $row['en'],
          'field_domain' => $row['domain'],
          'field_continent' => $nodeStorageHandler->loadByProperties(
            ['field_identifier' => $row['continent']]
          ),
          'field_language' => explode(',', str_replace(' ', '', $row['languages'])),
        ]);
        $this->createTranslations($term, $row);
        $term->save();

      } catch (\Exception $e) {
        \Drupal::logger('multisite.import')->alert(
          'Could not load row @row',
          ['@row' => serialize($row)]
        );
      }
    }
  }

  /**
   * @param Term $term
   * @param array $row
   *
   * @return void
   */
  private function createTranslations(Term $term, array $row): void {
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $langCode = $language->getId();

      //skip if not valid
      if (!$this->translationLanguageIsValid($langCode, $term, $row)) {
        continue;
      }

      //if all good, add translation
      try {
        $term->addTranslation($language->getId(), [
          'name' => $row[$langCode],
        ]);
      } catch (\Exception $e) {
        \Drupal::logger('multisite.import')->error(
          'Could not create translation @langCode for term @termId',
          ['@termId' => $term->id(), '@langCode' => $langCode]
        );
      }

    }
  }

  /**
   * @param string $langCode
   * @param Term   $term
   * @param array  $row
   *
   * @return bool
   */
  private function translationLanguageIsValid(string $langCode, Term $term, array $row): bool {
    //skip default language
    if (\Drupal::languageManager()->getDefaultLanguage()->getId() == $langCode) {
      return FALSE;
    }

    //skip existing translations
    if ($term->hasTranslation($langCode)) {
      \Drupal::logger('multisite.import')->warning(
        'Translation @langCode already exists',
        ['@langCode' => $langCode]
      );

      return FALSE;
    }

    //skip if language is not given in csv file
    if (!key_exists($langCode, $row)) {
      \Drupal::logger('multisite.import')->warning(
        'Missing language in CSV: @langCode',
        ['@langCode' => $langCode]
      );

      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param $entityType
   * @param $entityName
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function delete($entityType, $entityName): void {
    $nodeStorageHandler = \Drupal::entityTypeManager()->getStorage($entityType);
    $field = match ($entityType) {
      'taxonomy_term' => 'vid',
      'node' => 'bundle'
    };
    $ids = \Drupal::entityQuery($entityType)->condition($field, $entityName)->execute();
    $nodeStorageHandler->delete($nodeStorageHandler->loadMultiple($ids));
  }
}
