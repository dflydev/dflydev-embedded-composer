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
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Repository\ArrayRepository;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledFilesystemRepository;
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
    private $hasInternalRepository = false;

    private $composerFilename;
    private $vendorDirectory;

    private $repository;

    private $externalRepository;
    private $externalComposerConfig;
    private $externalComposerFilename;
    private $externalVendorDirectory;
    private $externalVendorDirectoryOverride = false;

    private $internalRepository;

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
     * @param string $composerFilename Composer filename
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
     * Set the vendor directory
     *
     * Will default to <code>\Composer\Config::get('vendor-dir')</code> if not
     * specified.
     *
     * @param string $vendorDirectory Vendor directory
     *
     * @return EmbeddedComposerInterface
     */
    public function setVendorDirectory($vendorDirectory)
    {
        if ($this->initialized) {
            throw new \LogicException(
                "Cannot call setVendorDirectory() once EmbeddedComposer's configuration has been frozen"
            );
        }

        $this->vendorDirectory = $vendorDirectory;

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

        $package = null;

        $foundPackages = $this->repository->findPackages($name);
        foreach ($foundPackages as $foundPackage) {
            if (null === $package || $foundPackage instanceof AliasPackage) {
                $package = $foundPackage;
            }
        }

        return $package;
    }

    /**
     * {@inheritdoc}
     */
    public function processAdditionalAutoloads()
    {
        $this->init();

        if ($this->hasInternalRepository) {
            if (file_exists($autoload = $this->externalVendorDirectory.'/autoload.php')) {
                $this->classLoader->unregister();
                require_once $autoload;
                $this->classLoader->register(true);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function createComposer(IOInterface $io)
    {
        $this->init();

        if (! $this->externalVendorDirectoryOverride) {
            $originalComposerVendorDir = getenv('COMPOSER_VENDOR_DIR');
            putenv('COMPOSER_VENDOR_DIR='.$this->externalVendorDirectory);
        }

        $composer = Factory::create($io, $this->externalComposerFilename);

        /*
        $composer->getRepositoryManager()->setLocalRepository(
            $this->externalRepository
        );
        */

        /*
        $composer->getConfig()->merge(array(
            'config' => array(
                'vendor-dir' => $this->externalVendorDirectory,
            )
        ));
        */

        if (! $this->externalVendorDirectoryOverride) {
            if (false !== $originalComposerVendorDir) {
                putenv('COMPOSER_VENDOR_DIR='.$originalComposerVendorDir);
            }
        }

        return $composer;
    }

    /**
     * {@inheritdoc}
     */
    public function configureInstaller(Installer $installer)
    {
        $this->init();

        if ($this->hasInternalRepository) {
            $installer->setAdditionalInstalledRepository(
                $this->internalRepository
            );
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
                $localConfig = $configJsonFile->read();
                if (isset($localConfig['config']['vendor-dir'])) {
                    $this->externalVendorDirectoryOverride = true;
                }
                $externalComposerConfig->merge($localConfig);
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

        $externalVendorDirectory = ($this->externalVendorDirectoryOverride || ! $this->vendorDirectory)
            ? $externalComposerConfig->get('vendor-dir')
            : $this->vendorDirectory;

        if (0 !== strpos($externalVendorDirectory, '/')) {
            $externalVendorDirectory = $this->externalRootDirectory.'/'.$externalVendorDirectory;
        }

        $this->externalVendorDirectory = $externalVendorDirectory;


        //
        // External Repository
        //

        $externalRepository = new InstalledFilesystemRepository(
            new JsonFile($externalVendorDirectory.'/composer/installed.json')
        );

        $this->externalRepository = $externalRepository;


        //
        // Internal Repository
        //

        $internalRepository = new CompositeRepository(array());

        if ($this->hasInternalRepository) {
            $internalInstalledRepository = new InstalledFilesystemRepository(
                new JsonFile(
                    $this->internalVendorDirectory.'/composer/installed.json'
                )
            );

            $internalRepository->addRepository($internalInstalledRepository);
        }

        $rootPackageFilename = $this->internalVendorDirectory.'/dflydev/embedded-composer/.root_package.json';
        if (file_exists($rootPackageFilename)) {
            $rootPackageRepository = new FilesystemRepository(
                new JsonFile($rootPackageFilename)
            );

            $internalRepository->addRepository($rootPackageRepository);
        }

        $this->internalRepository = $internalRepository;


        //
        // Repository
        //

        $repository = new CompositeRepository(array(
            $externalRepository,
            $internalRepository
        ));

        $this->repository = $repository;


        $this->initialized = true;
    }
}
