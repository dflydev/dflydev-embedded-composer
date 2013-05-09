<?php

/*
 * This file is a part of dflydev/embedded-composer.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dflydev\EmbeddedComposer\Core;

use Composer\Autoload\ClassLoader;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Repository\ArrayRepository;
use Composer\Repository\CompositeRepository;
use Composer\Repository\FilesystemRepository;
use Seld\JsonLint\ParsingException;

/**
 * Embedded Composer.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class EmbeddedComposer implements EmbeddedComposerInterface
{
    private $initialized = false;
    private $classLoader;
    private $externalRootDirectory;
    private $internalVendorDirectory;
    private $composerFilename;
    private $repository;

    private $externalComposerConfig;
    private $externalComposerFilename;
    private $externalVendorDirectory;

    private $internalRepository;
    private $hasInternalRepository = false;

    /**
     * Constructor.
     *
     * @param ClassLoader $classLoader           Class loader
     * @param string      $externalRootDirectory External root directory
     */
    public function __construct(ClassLoader $classLoader, $externalRootDirectory = null)
    {
        $this->classLoader = $classLoader;
        $this->externalRootDirectory = $externalRootDirectory ?: getcwd();

        $obj = new \ReflectionClass($classLoader);
        $this->internalVendorDirectory = dirname(dirname($obj->getFileName()));

        if (0 === strpos($this->internalVendorDirectory, 'phar://') ||
            false === strpos($this->internalVendorDirectory, $this->externalRootDirectory)) {
            // If our vendor root does not contain our project root then we
            // can assume that we have an internal repository.
            $this->hasInternalRepository = true;
        }
    }

    /**
     * Set the name of the composer file
     *
     * Will default to <code>\Composer\Factory::getComposerFile()</code> if not
     * specified.
     *
     * @param string $composerFilename Composer file
     *
     * @return EmbeddedComposerInterface
     */
    public function setComposerFilename($composerFilename)
    {
        if ($this->initialized) {
            throw new \LogicException(
                "Cannot call setComposerFilename() once EmbeddedComposer's configuration has been frozen"
            );
        }

        $this->composerFilename = $composerFilename;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassLoader()
    {
        $this->init();

        return $this->classLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function findPackage($name)
    {
        $this->init();

        if ($packages = $this->getCanonicalPackages($this->repository->findPackages($name))) {
            return $packages[0];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function processAdditionalAutoloads()
    {
        $this->init();

        if ($this->hasInternalRepository) {
            if (file_exists($autoload = $this->externalVendorDirectory.'/autoload.php')) {
                require_once $autoload;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository()
    {
        $this->init();

        return $this->repository;
    }

    /**
     * Get the external repository
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getExternalRepository()
    {
        $this->init();

        return $this->externalRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalRootDirectory()
    {
        $this->init();

        return $this->externalRootDirectory;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalComposerConfig()
    {
        $this->init();

        return $this->externalComposerConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalComposerFilename()
    {
        $this->init();

        return $this->externalComposerFilename;
    }

    /**
     * Get the internal repository
     *
     * @return \Composer\Repository\RepositoryInterface;
     */
    public function getInternalRepository()
    {
        $this->init();

        return $this->internalRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function hasInternalRepository()
    {
        $this->init();

        return $this->hasInternalRepository;
    }

    private function init()
    {
        if ($this->initialized) {
            return;
        }


        //
        // External Composer Filename
        //

        $externalComposerFilename = $this->composerFilename ?: Factory::getComposerFile();
        $pristineExternalComposerFilename = $externalComposerFilename;

        if (0 !== strpos($externalComposerFilename, '/')) {
            $externalComposerFilename = $this->externalRootDirectory.'/'.$externalComposerFilename;
        }

        $this->externalComposerFilename = $externalComposerFilename;


        //
        // External Composer Config
        //

        $externalComposerConfig = Factory::createConfig();

        $configJsonFile = new JsonFile($externalComposerFilename);

        if ($configJsonFile->exists()) {
            try {
                $configJsonFile->validateSchema(JsonFile::LAX_SCHEMA);
                $externalComposerConfig->merge($configJsonFile->read());
            } catch (ParsingException $e) {
                $this->error = $e;
            }
        } else {
            if ($pristineExternalComposerFilename === 'composer.json') {
                $message = 'Composer could not find a composer.json file in '.realpath($this->externalRootDirectory);
            } else {
                $message = 'Composer could not find the config file: '.$externalComposerFilename;
            }
            $instructions = 'To initialize a project, please create a '.
                $pristineExternalComposerFilename.
                ' file as described in the http://getcomposer.org/ "Getting Started" section';

            $this->error = new \InvalidArgumentException($message.PHP_EOL.$instructions);
        }

        $this->externalComposerConfig = $externalComposerConfig;


        //
        // External Vendor Directory
        //

        $externalVendorDirectory = $externalComposerConfig->get('vendor-dir');

        if (0 !== strpos($externalVendorDirectory, '/')) {
            $externalVendorDirectory = $this->externalRootDirectory.'/'.$externalVendorDirectory;
        }

        $this->externalVendorDirectory = $externalVendorDirectory;


        //
        // External Repository
        //

        $externalRepository = new FilesystemRepository(
            new JsonFile($externalVendorDirectory.'/composer/installed.json')
        );

        $this->externalRepository = $externalRepository;


        //
        // Internal Repository
        //

        if ($this->hasInternalRepository) {
            $internalRepository = new FilesystemRepository(new JsonFile(
                $this->internalVendorDirectory.'/composer/installed.json'
            ));
        } else {
            $internalRepository = new ArrayRepository;
        }

        $this->internalRepository = $internalRepository;


        //
        // Repository
        //

        $repository = new CompositeRepository(array($externalRepository));

        if ($this->hasInternalRepository) {
            $repository->addRepository($internalRepository);
        }

        $this->repository = $repository;


        $this->initialized = true;
    }

    private function getCanonicalPackages($packages)
    {
        // get at most one package of each name, prefering non-aliased ones
        $packagesByName = array();
        foreach ($packages as $package) {
            if (!isset($packagesByName[$package->getName()]) ||
                $packagesByName[$package->getName()] instanceof AliasPackage) {
                $packagesByName[$package->getName()] = $package;
            }
        }

        $canonicalPackages = array();

        // unfold aliased packages
        foreach ($packagesByName as $package) {
            while ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            $canonicalPackages[] = $package;
        }

        return $canonicalPackages;
    }
}
