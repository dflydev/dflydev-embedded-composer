Embedded Composer
=================

Embed [Composer][1] into another application.


Installation
------------

Through [Composer][1] as [dflydev/embedded-composer][2].


Why Would I Want To Embed Composer?
-----------------------------------

Imagine a console application shipped as a phar. If it is desired for the
application to be extensible based on which directory it is in (say one set
of plugins should be used in one directory but an entirely different set of
plugins used in another directory) one cannot simply define a `composer.json`
in both directories and run `composer install`.

Why not? Because the application shipped with a specific set of dependencies.
Composer cannot add more dependencies without running the risk of introducing
conflicts. The answer is to embed Composer into the application so that
Composer can merge the dependencies already installed for the application
with the dependencies defined in a specific directory's `composer.json`.

The end result is a set of dependencies that satisfy the directory specific
requirements while taking into account the dependencies *already installed*
for the console application.

While this is required for a phar distributed application this technique can
be applied to any globally installed application that needs to be runtime
extensible.


Usage
-----

### Basics

The following is an example `bin/myapp` style script that can be used either
installed via Composer (`vendor/bin/myapp`) or installed globally
(`/usr/local/bin/myapp`).

#### myapp.php (bin)

A shared block of code to initialize Embedded Composer from an application.

```php
<?php
// assume $classLoader is somehow defined prior to this block of
// code and contains the Composer class loader from the command
//
// see next two blocks of code

use Dflydev\EmbeddedComposer\Core\EmbeddedComposerBuilder;
use Symfony\Component\Console\Input\ArgvInput;

$input = new ArgvInput;

$projectDir = $input->getParameterOption('--project-dir') ?: '.';

$embeddedComposerBuilder = new EmbeddedComposerBuilder(
    $classLoader,
    $projectDir
);

$embeddedComposer = $embeddedComposerBuilder
    ->setComposerFilename('myapp.json')
    ->setVendorDirectory('.myapp')
    ->build();

$embeddedComposer->processAdditionalAutoloads();

// application is now ready to be run taking both the embedded
// dependencies and directory specific dependencies into account.
```


#### myapp (bin)

Example bin script (`bin/myapp`) that requires the shared block of code
after it locates the correct autoloader.

```php
#!/usr/bin/env php
<?php

if (
    // Check where autoload would be if this is myapp included
    // as a dependency.
    (!$classLoader = @include __DIR__.'/../../../autoload.php') and

    // Check where autoload would be if this is a development version
    // of myapp. (based on actual file)
    (!$classLoader = @include __DIR__.'/../vendor/autoload.php')
) {
    die('You must set up the project dependencies, run the following commands:

    composer install

');
}

include('myapp.php');
```

#### myapp-phar-stub (bin)

Example phar stub (`bin/myapp-phar-stub`) that can be used to bootstrap
a phar application prior to requiring the shared block of code.

```php
#!/usr/bin/env php
<?php

if (!$classLoader = @include __DIR__.'/../vendor/autoload.php') {
    die ('There is something terribly wrong with your archive.
Try downloading it again?');
}

include('myapp.php');
```

### What else ...

#### Find installed package by name

One can search for any package that Composer has installed by using
the `findPackage` method:

```php
<?php
$package = $embeddedComposer->findPackage('acme/myapp');
```

Composer does not currently install the root package in the `installed.json`
that represents the local repository. There is a PR out for this to be added
to Composer core but until then the following workaround can be used.

Add the following `post-autoload-dump` script to the root package's
`composer.json`:

```json
{
    "scripts": {
        "post-autoload-dump": "Dflydev\\EmbeddedComposer\\Core\\Script::postAutoloadDump"
    }
}
```

This will write an additional repository to
`vendor/dflydev/embedded-composer/.root_package.json` and Embedded Composer
will automatically use it if it can be found. It is important to ensure
that this file is a part of any phar built if the root package needs to be
included in the distribution.


#### Create a Composer Installer instance

The Installer instance is suitable for processing `install` and `update`
operations against the external configuration. It will take the internal
(embedded) configuration into account when solving dependencies.

```php
<?php
// requires creating an IOInterface instance
$installer = $embeddedComposer->createInstaller($io);
```

#### Create a vanilla Composer instance

```php
<?php
// requires creating an IOInterface instance
$composer = $embeddedComposer->createComposer($io);
```


License
-------

MIT, see LICENSE.


Community
---------

If you have questions or want to help out, join us in the **#dflydev** channel
on **irc.freenode.net**.


Not Invented Here
-----------------

Much of the work here has been supported by the Composer team and many
people in `#composer-dev`.

The actual code started its life as a part of [Sculpin][3] and was spun
out into a standalone project.


[1]: http://getcomposer.org
[2]: https://packagist.org/packages/dflydev/embedded-composer
[3]: https://sculpin.io
