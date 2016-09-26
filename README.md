FreezyBee/GoogleDrive
======

GoogleDrive StreamWrapper (read-only NOW).


Requirements
------------

FreezyBee/GoogleDrive requires PHP 7.0

- [Google API Client](https://github.com/google/google-api-php-client)


Installation
------------

The best way to install FreezyBee/GoogleDrive is using [Composer](http://getcomposer.org/):

```sh
$ composer require freezy-bee/google-drive
```


Example
-------

```php

    // register stream
    $gd = FreezyBee\GoogleDrive\GoogleDrive($pathToJsonFile);
    $gd->registerStreamWrapper();

    // download file
    $content = file_get_contents('gdrive://folder/file.jpg');

    // Nette Finder
    foreach (\Nette\Utils\Finder::find('*')->from('gdrive://Test') as $name => $file) {
        dump($name);
    }

    // Symfony Finder
    $finder = new \Symfony\Component\Finder\Finder;
    $finder->files()->in('gdrive://Test');

```

-----

Homepage [https://freezybee.ifire.cz](https://freezybee.ifire.cz) and repository [http://github.com/FreezyBee/GoogleDrive](http://github.com/FreezyBee/GoogleDrive).
