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
use Composer\Config;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledFilesystemRepository;
use Seld\JsonLint\ParsingException;

/**
 * Embedded Composer Builder.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class EmbeddedComposerBuilder
{
    private $classLoader;
    private $externalRootDirectory;
    private $internalVendorDirectory;
    private $hasInternalRepository = false;

    private $composerFilename;
    private $vendorDirectory;
    private $error = null;

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
     * @return EmbeddedComposerBuilder
     */
    public function setComposerFilename($composerFilename)
    {
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
     * @return EmbeddedComposerBuilder
     */
    public function setVendorDirectory($vendorDirectory)
    {
        $this->vendorDirectory = $vendorDirectory;

        return $this;
    }

    /**
     * Build
     *
     * @return EmbeddedComposerInterface
     */
    public function build()
    {
        $externalVendorDirectoryOverride = false;

        //
        // External Composer Filename
        //

        if ($this->hasInternalRepository) {
            $externalComposerFilename = $this->composerFilename ?: Factory::getComposerFile();
        } else {
            $externalComposerFilename = Factory::getComposerFile();
        }

        $pristineExternalComposerFilename = $externalComposerFilename;

        if (0 !== strpos($externalComposerFilename, '/')) {
            $externalComposerFilename = $this->externalRootDirectory.'/'.$externalComposerFilename;
        }


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
                    $externalVendorDirectoryOverride = true;
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

        //
        // External Vendor Directory
        //

        if ($this->hasInternalRepository) {
            $externalVendorDirectory = ($externalVendorDirectoryOverride || ! $this->vendorDirectory)
                ? $externalComposerConfig->get('vendor-dir')
                : $this->vendorDirectory;
        } else {
            $externalVendorDirectory = $externalComposerConfig->get('vendor-dir');
        }

        if (0 !== strpos($externalVendorDirectory, '/')) {
            $externalVendorDirectory = $this->externalRootDirectory.'/'.$externalVendorDirectory;
        }


        //
        // External Repository
        //

        $externalRepository = new InstalledFilesystemRepository(
            new JsonFile($externalVendorDirectory.'/composer/installed.json')
        );


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
            $rootPackageRepository = new InstalledFilesystemRepository(
                new JsonFile($rootPackageFilename)
            );

            $internalRepository->addRepository($rootPackageRepository);
        }


        //
        // Repository
        //

        $repository = new CompositeRepository(array(
            $externalRepository,
            $internalRepository
        ));


        return new EmbeddedComposer(
            $this->classLoader,
            $this->externalRootDirectory,
            $externalRepository,
            $externalComposerFilename,
            $externalComposerConfig,
            $externalVendorDirectory,
            $externalVendorDirectoryOverride,
            $internalRepository,
            $this->internalVendorDirectory,
            $this->hasInternalRepository,
            $repository
        );
    }
}
