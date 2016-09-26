<?php

namespace Tests\Integration;

use FreezyBee\GoogleDrive\FileDuplicatedException;
use FreezyBee\GoogleDrive\GoogleDrive;
use Nette\Utils\Finder as NetteFinder;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Class StreamTest
 * @package Tests\Integration
 */
class StreamTest extends TestCase
{
    /**
     * @var GoogleDrive
     */
    protected $gd;

    /**
     *
     */
    public function setUp()
    {
        if (!$this->gd) {
            $this->gd = new GoogleDrive(__DIR__ . '/../data/auth.json');
            $this->gd->registerStreamWrapper();
        }
    }

    public function testIsDir()
    {
        Assert::true(is_dir('gdrive://Test'));
    }

    public function testIsFile()
    {
        Assert::true(is_file('gdrive://Test/frog.jpg'));
    }

    /**
     *
     */
    public function testReadDirFrom()
    {
        $expected = [
            'gdrive://Test/goat.jpg',
            'gdrive://Test/FolderInner',
            'gdrive://Test/FolderInner/snake.jpg',
            'gdrive://Test/FolderInner/sheep.jpg',
            'gdrive://Test/spider.jpg',
            'gdrive://Test/goat.jpg',
            'gdrive://Test/frog.jpg',
            'gdrive://Test/fish.jpg',
            'gdrive://Test/duck.jpg',
            'gdrive://Test/dog.jpg',
            'gdrive://Test/cat.jpg',
            'gdrive://Test/animals.jpg',
            'gdrive://Test/animal.jpg',
        ];

        $actual = [];

        foreach (NetteFinder::find('*')->from('gdrive://Test') as $name => $file) {
            $actual[] = $name;
        }

        Assert::equal($expected, $actual);
    }

    /**
     *
     */
    public function testReadDirInSymfony()
    {
        $expected = [
            'gdrive://Test/goat.jpg',
            'gdrive://Test/FolderInner/snake.jpg',
            'gdrive://Test/FolderInner/sheep.jpg',
            'gdrive://Test/spider.jpg',
            'gdrive://Test/goat.jpg',
            'gdrive://Test/frog.jpg',
            'gdrive://Test/fish.jpg',
            'gdrive://Test/duck.jpg',
            'gdrive://Test/dog.jpg',
            'gdrive://Test/cat.jpg',
            'gdrive://Test/animals.jpg',
            'gdrive://Test/animal.jpg',
        ];

        $actual = [];

        $finder = new SymfonyFinder;

        foreach ($finder->files()->in('gdrive://Test') as $name => $file) {
            $actual[] = $name;
        };

        Assert::equal($expected, $actual);
    }

    /**
     *
     */
    public function testReadDirIn()
    {
        $expected = [
            'gdrive://testsheet',
            'gdrive://Test',
            'gdrive://Getting started'
        ];

        $actual = [];

        foreach (NetteFinder::find('*')->in('gdrive://') as $name => $file) {
            $actual[] = $name;
        }

        Assert::equal($expected, $actual);
    }

    /**
     *
     */
    public function testReadFile()
    {
        $expected = file_get_contents(__DIR__ . '/../data/spider.jpg');
        $actual = file_get_contents('gdrive://Test/spider.jpg');

        Assert::same($expected, $actual);
    }

    /**
     *
     */
    public function testDuplicateFile()
    {
        Assert::exception(function () {
            file_get_contents('gdrive://Test/goat.jpg');
        }, FileDuplicatedException::class);
    }
}

(new StreamTest)->run();
