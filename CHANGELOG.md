# v1.1.1
## 11/20/2026

1. [](#improved)
    * Requires Grav 2.0+ (dependency now matches the declared compatibility)
    * Built-in CSS is loaded by the partial, so only pages that show the widget get it
    * Books are ordered by date added to your library, newest first
    * Admin labels are translatable (`languages.yaml`); the default heading is translated too
    * Cover images have width and height, which avoids layout shift
    * User-Agent reports the plugin version
1. [](#bugfix)
    * A failed fetch with no cached copy (missing or expired token, Hardcover outage on a fresh install) no longer calls the API on every page view: an empty result is cached and retried after 5 minutes
    * Failed fetches with a cached copy retry after 5 minutes instead of a full cache period
    * Only one request refreshes the cache at a time; others serve the cached copy meanwhile
    * Logs an error instead of a fatal when the PHP curl extension is missing
    * Cover images have alt text

# v1.1.0
## 11/18/2026

1. [](#new)
    * Built-in stylesheet (`css/hardcover.css`) themed with CSS custom properties
    * `built_in_css` option to turn it off and style from your theme
    * Declares Grav 2.0 compatibility

# v1.0.1
## 11/18/2026

1. [](#new)
    * `[hardcover-reading]` shortcode for Markdown pages (requires shortcode-core)

# v1.0.0
## 11/18/2026

1. [](#new)
    * Initial release: `hardcover_reading()` Twig function and partial for your Currently Reading shelf, with file caching and stale fallback
