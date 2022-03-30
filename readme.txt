=== dupes ===
Contributors: bobbingwide, vsgloik
Donate link: https://www.oik-plugins.com/oik/oik-donate/
Tags: oik, plugin, unloader
Requires at least: 5.8.1
Tested up to: 5.9.2
Stable tag: 0.1.0

Black box / round trip testing for content with duplicated values in the post_name field.

== Description ==
Use the dupes plugin to test scenarios that cause duplicate values in the post_name field
leading to incorrectly displayed content, from the user's point of view.

For many years WordPress has allowed the user to define a permalink structure such that permalinks can be duplicated between posts.
This can lead to the user being shown a link to some content which, when clicked, doesn't actually deliver the content proposed.

WordPress TRAC issue #13459 covers the initial problem, raised for a permalink structure of /%postname%/.

Deeper investigation has indicated there are a number of other problems that could be fixed.

v0.1.0 of this plugin doesn't yet do anything to fix the problem.
It only contains PHPUnit tests to demonstrate where the problem occurs.

The tests currently produce two failures.

1. The original problem, where a page is returned instead of the post.
2. The inability to retrieve the post by its post ID, when there's a duplicate page.

The tests don't give a clear indication of the actual test coverage and results.
This would be nice to have.
In the meantime, you'll have to read the tests and the phpunit.json.

== Installation ==

The tests have been developed to run either In Situ, which means they can be run in an existing installation,
or as block box testing using WordPress develop.

In both cases the tests use `wp_remote_get()` to access the duplicated posts in the same way that an end user would.
The `website` therefore needs to be accessible.

In my development environment the installations are within a subdirectory within `/apache/htdocs`.
Rather than using `localhost` I use a domain name of `s.b`.

Installation type | WordPress core in | Home URL / Site URL
----------------- | ----------------- | -------------------
WordPress develop | wordpress-develop/src | https://s.b/wordpress-develop/src/
In Situ           | wordpress-develop/src | https://s.b/wordpress-develop/src/
In Situ           | cwiccer           | https://s.b/cwiccer/

For In Situ testing

1. Upload the contents of the dupes plugin to the `/wp-content/plugins/dupes' directory
1. Install oik-batch and wordpress-develop-tests plugins.
1. Change directory to the dupes plugin.
1. Run the In Situ PHPUnit tests using oik-batch's `oik-phpunit.php`

eg. Using `phpunit.bat`
```
php ..\..\plugins\oik-batch\oik-phpunit.php "--verbose" "--disallow-test-output" "--log-junit=phpunit.json" %*
```

For black box testing using WordPress-develop

1. Install the latest WordPress/wordpress-develop into a local environment
1. Check the content is accessible from a browser
1. Enable the WordPress core PHPUnit tests. See https://github.com/bobbingwide/dupes/issues/2
1. Copy or symlink the `tests/test-issue-13459.php` file from `dupes` to `tests/phpunit/tests`
1. Change directory to the root directory of the local environment
1. Run PHPUnit with `--filter Tests-issue-13459`

eg. Using `phpempty.bat`
```
@echo Run PHPUnit against WordPress core.
rem See https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/#setup
cd \apache\htdocs\wordpress-develop
set WP_TESTS_SKIP_INSTALL=1
php c:\apache\htdocs\phpLibraries\phpunit\phpunit-9.5.5.phar "--verbose" "--disallow-test-output" "--log-junit=phpunit.json" %*
```

Note: `WP_TEST_SKIP_INSTALL=1` prevents the bootstrap routine from running install.php


== Screenshots ==
1. None

== Upgrade Notice ==
= 0.1.0 =
Now supports testing In Situ testing using oik-batch and/or black box testing in a WordPress-develop environment.

= 0.0.0 = 
First version of In Situ PHP Unit tests for WordPress TRAC issue #13459

== Changelog ==
= 0.1.0 =

= 0.0.0 = 
* Added: Initial PHPUnit tests for WordPress TRAC 13459 #1
* Tested: With WordPress 5.9.2
* Tested: With PHP 8.0
* Tested: With PHPUnit 9 

== Further reading ==
The In Situ tests are dependent upon a number of other plugins:

- oik-batch - to drive the In Situ tests
- WordPress develop tests - providing the BW_UnitTestCase class developed to support in situ testing

References
- [oik-batch](https://github.com/bobbingwide/oik-batch)
- [wordpress-develop-tests](https://github.com/bobbingwide/wordpress-develop-tests)
- [How to run PHPUnit tests for WordPress plugins in situ](https://herbmiller.me/run-phpunit-tests-wordpress-plugins-situ/)