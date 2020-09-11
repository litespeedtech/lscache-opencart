# LiteSpeedCache for OpenCart

LiteSpeed Cache for OpenCart is a page cache extension for OpenCart sites running on LiteSpeed Web Server or OpenLiteSpeed.

* It's simple, easy to use, and will speed up your OpenCart site up to 100 times faster, after 3 minutes of setup, with no extra cost.
* LiteSpeed Page Cache will work whether logged in or logged out, with an empty cart or a full one.
* LiteSpeed Cache will automatically purge a page when the related product/category/information/manufacturer data has changed. You can set a longer cache expiration time to improve visitor experience, confident that the cache will be purged when relevant content changes.
* LiteSpeed Cache will automatically purge a cached ESI module when related product/category/information/manufacturer data has changed. 
* the advanced ESI feature and cache options for logged-in users will help run your OpenCart site as efficiently as a static file site. It will tremendously improve customer experience.

The LiteSpeed Cache extension was originally written by LiteSpeed Technologies. It is released under the GNU General Public Licence (GPLv3).

See https://www.litespeedtech.com/products/cache-plugins for more information.

## Prerequisites
This version of LiteSpeed Cache requires OpenCart 2.3 or later and either LiteSpeed LSWS Server 5.2.3 or later, or OpenLiteSpeed 1.4 or later.

## Installing
Download a specific version of the LiteSpeed Cache extension package from the GitHub **package** folder, or run `buildPackage.sh` to generate the latest package from the latest souce code in GitHub.

## Cli Command for Rebuild All Cache
Run the following command at the website host:
curl -N "http://yoursite/index.php?route=extension/module/lscache/recache&from=cli"

See https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:lscoc for more information.

