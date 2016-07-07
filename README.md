MediaWiki Feeds
===============

A tool to create RSS feeds of pages in [MediaWiki](https://mediawiki.org) categories.

## Installation

1. Clone from GitHub to a web-accessible location: `git clone https://github.com/samwilson/mediawiki-feeds.git`
2. Install dependencies: `composer install`

## Usage

Browse to `mediawiki-feeds/index.html` and fill in the form.

If you want to produce feeds of categories that have a large number of members,
the web-request may time out. In this case, use `cli.php` to populate the cache
(e.g. from a cron job):

    php cli.php --category=Category:Blog_posts --url=https://en.wikiversity.org/w/ --numItems=10

There is also a public deployment of this tool on WMFlabs:
https://tools.wmflabs.org/mediawiki-feeds/

## Upgrading

1. Update from GitHub: `git pull origin master`
2. Update dependencies: `composer install`

## Reporting issues

Please report any issues via GitHub https://github.com/samwilson/mediawiki-feeds/issues
or by contacting the maintainer [User:Samwilson](https://meta.wikimedia.org/wiki/User:Samwilson) on Meta.
