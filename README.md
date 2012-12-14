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


Usage
-----

The following is an example `bin/myapp` style script that can be used either
installed via Composer (`vendor/bin/myapp`) or installed globally
(`/usr/local/bin/myapp`). If a phar is built it is assumed that some value
will be defined in the phar compilation process, in this case
`MY_APP_RUNNING_AS_PHAR`.

The Package that is defined should accurately reflect the Package in which
the application resides. The version information should match as closely as
possible to reality. This will ensure that the project using your app can
require `{ "my/app": "2.0.*" }` and Composer will be able to correctly mark
it and all of its dependencies as already being installed *and for the correct
version*.


```php
<?php
if (defined('MY_APP_RUNNING_AS_PHAR')) {
    if (!$classLoader = @include __DIR__.'/../vendor/autoload.php') {
        die ('There is something terribly wrong with your archive.
Try downloading again?');
    }
} else {
    if (
        // Check where autoload would be if this is my/app included
        // as a dependency.
        (!$classLoader = @include __DIR__.'/../../../autoload.php') and

        // Check where autoload would be if this is a development version
        // of my/app. (based on actual file)
        (!$classLoader = @include __DIR__.'/../vendor/autoload.php')
    ) {
        die('You must set up the project dependencies, run the following commands:

    composer install

');
    }
}

use Composer\Package\Package;
use Dflydev\EmbeddedComposer\Core\EmbeddedComposer;
use Symfony\Component\Console\Input\ArgvInput;

$input = new ArgvInput;

$projectDir = $input->getParameterOption('--project-dir') ?: '.';

// This package should accurately represent the package that contains
// the application being run.
$package = new Package('my/app', '2.0.5', '2.0.5');

$embeddedComposer = new EmbeddedComposer($classLoader, $projectDir, $package);
$embeddedComposer->processExternalAutoloads();

// Composer is now ready to load local packages as well as the packages
// that make up the calling application.
```


License
-------

MIT, see LICENSE.


Community
---------

If you have questions or want to help out, join us in the **#dflydev** channel
on **irc.freenode.net**.


[1]: http://getcomposer.org/
[2]: https://github.com/skyzyx/mimetypes
