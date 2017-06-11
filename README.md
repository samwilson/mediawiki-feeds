MediaWiki Feeds
===============

A tool to create RSS feeds of pages in [MediaWiki](https://mediawiki.org) categories.

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/samwilson/mediawiki-feeds/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/samwilson/mediawiki-feeds/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/samwilson/mediawiki-feeds/badges/build.png?b=master)](https://scrutinizer-ci.com/g/samwilson/mediawiki-feeds/build-status/master)
[![Dependency Status](https://www.versioneye.com/user/projects/593ca03a368b080048d15aef/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/593ca03a368b080048d15aef)

## Requirements

1. `php-curl`

## Installation

1. Clone from GitHub to a web-accessible location: `git clone https://github.com/samwilson/mediawiki-feeds.git`
2. Install dependencies: `composer install --no-dev`
3. Create the `var` directory, and make it writeable by the web server and CLI users
3. [*Optional*] Modify the `$defaults` array in `config.php`

## Usage

Browse to `mediawiki-feeds/index.html` and fill in the form.
Pages in the category *and all subcategories* will be items in the feed.

You can prevent the feed from being cached by passing the `nocache` URL parameter (with any or no value).

Note that there is a public deployment of this tool on WMFlabs: https://tools.wmflabs.org/mediawiki-feeds/

### Feed item formatting


The first `<time>` element found on the page will be used for the publication date.

The contents of an element with a `itemprop="description"` attribute will be used for the description,
or else just the first 400 characters of the page.

### Command Line Interface

If you want to produce feeds of categories that have a large number of members,
the web-request may time out. In this case, use `cli.php` to populate the cache
(e.g. from a cron job):

    php cli.php --category=Category:Blog_posts --url=https://en.wikiversity.org/w/ --numItems=10 --title="Other title" --verbose

The CLI always rebuilds the cache (because that's what it's for; it's up to you to not call it too often).
To find out the name of the cache file (e.g. to serve the RSS file directly), pass the `verbose` flag.

## Upgrading

1. Update from GitHub: `git pull origin master`
2. Update dependencies: `composer install`

## Reporting issues

Please report any issues via GitHub https://github.com/samwilson/mediawiki-feeds/issues
or by contacting [User:Samwilson](https://mediawiki.org/wiki/User:Samwilson).
