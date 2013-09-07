# ChangeLog for GitHub Updater

## 1.4.3

* Fixed a couple of non-fatal PHP errors. Thanks @jazzsequence

## 1.4.2

* Cleaned up readme's markdown.

## 1.4.1

* Fixed the README to more accurately reflect support for both plugins and themes.

## 1.4

* Fix for rename functions to be more precise, otherwise might rename wp.org repo themes.

## 1.3

* Simplify a couple of if statements.

## 1.2

* Fix to ignore renaming for wp.org plugins

## 1.1

* Sanity check for theme api uri

## 1.0

* Serialized WP_Theme object to search for added GitHub header, lots of help from Seth. No more `file_get_contents`.
* Converted plugin class and added it to make joint plugin/theme updater.

## 0.2

* Code cleanup.
* Limit `file_get_contents` to 2000 bytes.

## 0.1

* Initial commit
