# Installation
1. The following dependent modules (and submodules) need to be installed and activated:
   - [Domain](https://www.drupal.org/project/domain)
     - domain_access
     - domain_alias
     - domain_config
     - domain_config_ui
     - domain_content
     - domain_source
   - [Country Path](https://www.drupal.org/project/country_path)
   - [Smart IP](https://www.drupal.org/project/smart_ip)
     - smart_ip_maxmind_geoip2_bin_db

# Configuration
Domains and Languages need be configured manually in Drupal. Besides that, configure the modules as follows:

## ERP
Please add the following entities manually at the moment.
We will add this to the module soon.

### Contitent
Machine name: **continent**

| Field name | Type |
| -------- | ------- |
| field_identifier  | Text (plain) |

### Country
Machine name: **country**

| Field name | Type |
| -------- | ------- |
| field_continent  | Entity reference to Term |
| field_domain  | Entity reference to Domain |
| field_identifier  | Text (plain) |
| field_language  | Entity reference to Language |

## Loading Data
There is a very basic import service which at the moment loads continents and its countries.
Put all the data you want to load in the /data folder, or adapt the already exisiting files.
If you want to load more than just continents and countries, add it to `DataGeneratorService.php`.

## SmartIP
- Use MaxMinds opensource GeoIP2 binary database: [Download](https://www.maxmind.com/en/accounts/717496/geoip/downloads)
- Put the file (GeoLite2-Country.mmdb) into the folder `smart_ip/` of your private files path. and  this folder `smart_ip/`.
- Automatic MaxMind GeoIP2 binary database update: **NO**
- Roles to Geolocate: **Guest**

Any questions? See README of smart_ip module.

## Domains
If ever a new domain is added or the URL of it changes, make sure to edit it here:
`/admin/config/domain` and here `/admin/config/domain/alias/{domainname}`.

## settings.php
The following settings are needed for the module to run properly.
- `skipDomainValidation` is a whitelist of IPs, to allow to bypass domain validation.
- `absoluteDomainPath` is used for the href language tag generation.
- `domainDefaultLanguages` is used to define the last fallback for language resolvement.
```
//MULTISITE SETTINGS
$settings['multisite'] = [
  'skipDomainValidation' => [],
  'absoluteDomainPath' => 'http://local.bystronic.com',
  'domainDefaultLanguages' => [
    [
      'int' => 'en',
      'aus' => 'en',
      'blx' => 'nl',
      'bra' => 'pt',
      'can' => 'en',
      'cze' => 'cs',
      'kor' => 'ko',
      'sca' => 'se',
      'deu' => 'de',
      'esp' => 'es',
      'fra' => 'fr',
      'hun' => 'hu',
      'ind' => 'en',
      'ita' => 'it',
      'jpn' => 'ja',
      'mex' => 'es',
      'aut' => 'de',
      'pol' => 'pl',
      'rou' => 'ro',
      'che' => 'de',
      'sgp' => 'en',
      'zaf' => 'en',
      'twn' => 'tw',
      'tha' => 'th',
      'tur' => 'tr',
      'ukr' => 'uk',
      'gbr' => 'en',
      'usa' => 'en-us',
      'vnm' => 'vi',
      'chn' => 'zh',
    ]
  ]
];
```

# Troubleshooting

## Access seems to be off
If you ever change some Domain configuration, when data was already fed to the system, make sure to rebuild node access
```
vendor/bin/drupal node:access:rebuild
```

## Domain Redirection does not work
Check your domain settings and make sure, that the URLs are correct.