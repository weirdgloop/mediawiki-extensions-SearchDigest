# SearchDigest
MediaWiki extension which tracks failed searches on your wiki and displays them on a dedicated special page, `Special:SearchDigest`. This was originally a feature on Wikia wikis.

**Live demo**: https://runescape.wiki

## Requirements
Please use the appropriate release branch for your MediaWiki version.

## Installation

1. Clone this repository to your MediaWiki installation's `extensions` folder
2. Run the `maintenance/update.php` update script to add the necessary tables to your database
3. Modify your `LocalSettings.php` file and add:

```php
// Load the extension
wfLoadExtension( 'SearchDigest' );
```

## Configuration
| Variable | Type | Description | Default |
| --- | --- | --- | --- |
| `$wgSearchDigestCreateRedirect` | bool | Whether to show a button for quickly creating redirects on Special:SearchDigest (requires JS in browser) | `true`
| `$wgSearchDigestMinimumMisses` | int | Number of misses for a query before they should show up on Special:SearchDigest | `10`
| `$wgSearchDigestDateThreshold` | int | Seconds subtracted from the current [Unix timestamp](https://en.wikipedia.org/wiki/Unix_time). Any queries that haven't been searched before this threshold aren't displayed. | `604800` (1 week)

## Permissions
By default, this extension adds two rights:

* `searchdigest-reader` grants access to view Special:SearchDigest. It is given to all users by default.
* `searchdigest-reader-stats` grants access to view Special:SearchDigest/stats. It is given to all users by default.
* `searchdigest-block` allows trusted users to block specific queries from appearing on Special:SearchDigest, such as inappropriate language.
* `searchdigest-admin` provides maintenance tools on the Special:SearchDigest tools that are intended to be used by system administrators, including the ability to clear the database.

## Translation
This extension can be translated through the messages in the `i18n` folder if you're a developer. As a wiki administrator, you may find it a better option to edit the messages on-site in the MediaWiki namespace.

## License
This extension is licensed under GNU GPLv3, [see here](LICENSE) for more information.
