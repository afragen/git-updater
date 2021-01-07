#### 1.4.4
* Added support for extra dismissible links via `.dismiss-this` CSS class.

#### 1.4.3
* Added filter hook `pand_dismiss_notice_js_url` in case you're using this in a theme or a local environment that doesn't quite find the correct URL.
* Added filter hook `pand_theme_loader` that returns a boolean for simpler usage of the `pand_dismiss_notice_js_url` hook

#### 1.4.2
* No changes to `class PAnD`
* Updated `.gitignore` and `.gitattributes`
* Now use classmap in composer's autoloader, should be more efficient

#### 1.4.1
* Fixed the `forever` setting with options

#### 1.4.0
* WPCS 1.1.0 linting done
* Switched from storing timeout in transients to storing in the options table, this should play much better with object caching

#### 1.3.x
* Uses transients to store timeout
