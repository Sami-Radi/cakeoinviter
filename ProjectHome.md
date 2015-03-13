# Open Inviter for CakePHP #

## Purpose ##

This project is meant to be an adaptation of the `"OpenInviter"` ( http://openinviter.com/ ) project as a CakePHP vendor / component / controller.

`"OpenInviter"` is a GNU GPL v2 third party library.

The plugins are the same as provided by the official website.

File `_base.php` has been modified to remove all references to any hosted / remote solutions to prevent sensitivie informations from your website from being disclosed to a third party.

`openinviter.php` has been reengineered as a working CakePHP component class. All references to hosted / remote solutions are disabled.

There are no need for an api key or whatever password to get it working. I found it strange for an open source (GNU GPL v2) project to have such requirements... This is why I removed any references to remote / hosted solutions.

## Requirements ##

**CakePHP 1.1.19.6305 (later versions not tested yet)**

**A new folder in CakePHP directory structure (in CakePHP app folder) :**

  1. add "/tmp/oiconf" folder
  1. must be writable : chmod to 777 or 755


**The files in the archive are meant to be deployed as the their inner directory structure suggest it.**

## How to use ##

Check the article on the bakery for more informations about it ( http://bakery.cakephp.org/articles/view/openinviter-for-cakephp-2 )

## Working projects ##

**unaneem.com uses this vendor / component / controller set :**

  1. Check it at : http://www.unaneem.com (must be registred to give it a try)
  1. Read the article on the bakery at : http://bakery.cakephp.org/articles/view/unaneem-com-a-community-website-built-on-cakephp-with-extensive-use-of-ajax

## Version ##

(`[download date from openinviter website][release date on the google code repositery]`)

**[here](http://cakeoinviter.googlecode.com/files/%5B20091117%5D%5B20100224%5D%20Open%20Inviter.7z) : `[20091117][20100224] CakePHP OpenInviter`**

## License ##

**GNU GPL v2 for this component / vendor (as the original open inviter source).**

## Issues ##

`OpenInviter` evolves very fast.

There is an update every 2 or 3 days. So if we want to have a stable or up-to-date cakephp version there's a need for regular updates that i can't provide alone.

I hope that you won't hesitate to improve it and disclose any useful modifications to the community.