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
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Repository\RepositoryInterface;

/**
 * Embedded Composer.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class EmbeddedComposer implements EmbeddedComposerInterface
{
    private $classLoader;
    private $externalRootDirectory;
    private $internalVendorDirectory;
    private $hasInternalRepository = false;

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
     * @param ClassLoader         $classLoader
     * @param string              $externalRootDirectory
     * @param RepositoryInterface $externalRepository,
     * @param string              $externalComposerFilename,
     * @param Config              $externalComposerConfig,
     * @param string              $externalVendorDirectory,
     * @param bool                $externalVendorDirectoryOverride,
     * @param RepositoryInterface $internalRepository,
     * @param string              $internalVendorDirectory,
     * @param bool                $hasInternalRepository,
     * @param RepositoryInterface $repository
     */
    public function __construct(
        ClassLoader $classLoader,
        $externalRootDirectory,
        RepositoryInterface $externalRepository,
        $externalComposerFilename,
        Config $externalComposerConfig,
        $externalVendorDirectory,
        $externalVendorDirectoryOverride,
        RepositoryInterface $internalRepository,
        $internalVendorDirectory,
        $hasInternalRepository,
        RepositoryInterface $repository
    ) {
        $this->classLoader = $classLoader;
        $this->externalRootDirectory = $externalRootDirectory;
        $this->externalRepository = $externalRepository;
        $this->externalComposerFilename = $externalComposerFilename;
        $this->externalComposerConfig = $externalComposerConfig;
        $this->externalVendorDirectory = $externalVendorDirectory;
        $this->externalVendorDirectoryOverride = $externalVendorDirectoryOverride;
        $this->internalRepository = $internalRepository;
        $this->internalVendorDirectory = $internalVendorDirectory;
        $this->hasInternalRepository = $hasInternalRepository;
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassLoader()
    {
        return $this->classLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function findPackage($name)
    {
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
        if ($this->hasInternalRepository) {
            $externalAutoload = $this->externalVendorDirectory.'/autoload.php';
            if (is_readable($externalAutoload)) {
                $this->classLoader->unregister();
                include $externalAutoload;
                $this->classLoader->register(true);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createComposer(IOInterface $io)
    {
        if (! $this->externalVendorDirectoryOverride) {
            $originalComposerVendorDir = getenv('COMPOSER_VENDOR_DIR');
            putenv('COMPOSER_VENDOR_DIR='.$this->externalVendorDirectory);
        }

        $composer = Factory::create($io, $this->externalComposerFilename);

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
    public function createInstaller(IOInterface $io)
    {
        $composer = $this->createComposer($io);
        $installer = Installer::create($io, $composer);

        if ($this->hasInternalRepository) {
            $installer->setAdditionalInstalledRepository(
                $this->internalRepository
            );

            $composer->getPluginManager()->loadRepository(
                $this->internalRepository
            );
        }

        return $installer;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalRepository()
    {
        return $this->externalRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalRootDirectory()
    {
        return $this->externalRootDirectory;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalComposerConfig()
    {
        return $this->externalComposerConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalComposerFilename()
    {
        return $this->externalComposerFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getInternalRepository()
    {
        return $this->internalRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function hasInternalRepository()
    {
        return $this->hasInternalRepository;
    }
}
