# WordPress Plugin Readme Parser

A scrape of the current [WordPress.org Plugin Readme Parser](https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme)

In my [GitHub Updater](https://github.com/afragen/github-updater) plugin I use the WP.org Plugin Directory readme parser. I created this library to allow me to more easily include the `class-parser.php` for my project by using composer.

I will try to keep this as up-to-date as possible.

The `index.php` file, when run locally, will update the `class-parser.php` file with the most current version in meta.trac.wordpress.org.

## Usage

`composer require afragen/wordpress-plugin-readme-parser:dev-master`

`class-parser.php` uses [Michelf’s Markdown_Extra](https://github.com/michelf/php-markdown) but I use a more lightweight markdown parser, [erusev’s Parsedown](https://github.com/erusev/parsedown). Parsedown is required in this `composer.json`.
