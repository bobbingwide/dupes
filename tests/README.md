### README for the tests folder

The PHPUnit tests in this folder were written to be run as In Situ tests.
This means that they can be run in an existing WordPress site.
Whatever's already in the database should remain unchanged by the tests.
It's not completely emptied at the start of processing.

To achieve this the tests are dependent upon two plugins
1. oik-batch - a WP-cli like implementation of WordPress on the command line
2. wordpress-develop-tests - a subset of wordpress-develop, with a couple of additions for In Situ testing

Since the tests are also intended to be run as part of WordPress core
they should also work in the WordPress core PHPUnit test environment.

To run the tests create a batch file similar to this ( pu.bat ):

```
@echo off
setlocal
set PRE_PHPUNIT_CD=%CD%
set PHPUNIT=C:\apache\htdocs\phpLibraries\phpunit\phpunit-9.5.5.phar
php ..\..\plugins\oik-batch\oik-phpunit.php "--verbose" "--disallow-test-output" "--stop-on-error" "--stop-on-failure" "--log-junit=phpunit.json" %*
endlocal
```

The environment variables are:
PRE_PHPUNIT_CD - Current directory - ie the plugin directory
PHPUNIT - the source of the PHPUnit PHAR

If you run this command and oik-batch is not installed then you'll get:
```
Could not open input file: ..\..\plugins\oik-batch\oik-phpunit.php
```

`oik-phpunit.php` searches for the `wp-config.php` file in the folders leading to the current directory.
This should be in the same directory as the WordPress source code.
If not then `wp-settings.php` won't be found. 

```
C:\apache\htdocs\wordpress-develop\src\wp-content\plugins\dupes>pu

C:\apache\htdocs\phpLibraries\phpunit\phpunit-9.5.5.phar
Searching for wp-config.php in directories leading to: C:\apache\htdocs\wordpress-develop\src\wp-content\plugins\dupes
Found wp-config.php in: C:\apache\htdocs\wordpress-develop/

Warning: require_once(C:\apache\htdocs\wordpress-develop/wp-settings.php): Failed to open stream: No such file or directory in C:\apache\htdocs\wordpress-develop\wp-config.php on line 97
PHPUnit 9.5.5 by Sebastian Bergmann and contributors.

Error in bootstrap script: Error:
Failed opening required 'C:\apache\htdocs\wordpress-develop/wp-settings.php' (include_path='.;C:\php\pear')
```
Solution: move `wp-config.php` to the `src` folder.

If the wordpress-develop-tests plugin isn't installed then you get another message.
```
C:\apache\htdocs\wordpress-develop\src\wp-content\plugins\dupes>pu

C:\apache\htdocs\phpLibraries\phpunit\phpunit-9.5.5.phar
Searching for wp-config.php in directories leading to: C:\apache\htdocs\wordpress-develop\src\wp-content\plugins\dupes
Found wp-config.php in: C:\apache\htdocs\wordpress-develop\src/
oik-wp running WordPress 6.0-alpha-52448-src
C:\apache\htdocs\wordpress\wp-content\plugins\dupes
cli
WordPress develop tests bootstrap.php not found
What shall we do now then?
Tests cannot be run without the WordPress develop test suite.
```




