#### 1.4.2
* No changes to `class PAnD`
* updated `.gitignore` and `.gitattributes`
* now use classmap in composer's autoloader, should be more efficient

#### 1.4.1
* fixed the `forever` setting with options

#### 1.4.0
* WPCS 1.1.0 linting done
* switched from storing timeout in transients to storing in the options table, this should play much better with object caching

#### 1.3.x
* uses transients to store timeout
