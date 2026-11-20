# Grav Hardcover plugin

Shows your Hardcover.app "Currently reading" shelf. The API is called from
PHP on your server, so the token never reaches visitors. Results are cached
to `user/data/hardcover/currently-reading.json`, and if the API is down the
last good copy is served.

## Requirements

- Grav 2.0 or later
- PHP's curl extension (the widget stays empty and Grav logs an error without it)

## Install

1. Copy this folder to `user/plugins/hardcover/`. The folder must be named
   `hardcover`, so if you clone or download from GitHub, rename it:

   ```
   cd user/plugins
   git clone https://github.com/gbbowers/grav-plugin-hardcover.git hardcover
   ```
2. Put your token in `user/config/plugins/hardcover.yaml` (copy `hardcover.yaml`
   there), or use the Admin panel, or set `HARDCOVER_TOKEN` in the server env.
   Keep that file out of any public git repo.
3. In your theme template, wherever you want it:

   ```twig
   {% include 'partials/hardcover-reading.html.twig' %}
   ```

   To use it inside a page's Markdown instead, install the Shortcode Core
   plugin (`bin/gpm install shortcode-core`) and put this in the page body:

   ```
   [hardcover-reading]
   ```

   Grav caches rendered page content, so on that page turn caching off
   (Admin → page → Advanced → Caching: disabled, or `cache_enable: false`
   in frontmatter). Otherwise the list only refreshes when Grav's cache clears.

## Custom markup

`hardcover_reading()` returns an array you can loop over in any template:
`title`, `url`, `cover`, `authors` (array), `progress` (0–100 or null).

To override the partial, copy it to
`user/themes/<your-theme>/templates/partials/hardcover-reading.html.twig`.

## Notes

- Books are listed by when you added them to your Hardcover library, newest first.
- The built-in stylesheet is only loaded on pages that show the widget. If you
  use the shortcode on a page with caching left on, the cached copy of the
  page won't load it; that's one more reason to turn caching off there.
- If a request fails (no token, expired token, Hardcover down), the plugin
  keeps showing the last list it had, or nothing on a fresh install, and tries
  again after 5 minutes.
- Changes on Hardcover show up after `cache_minutes` (default 60). To force a
  refresh, delete the JSON file above.
- Hardcover API tokens expire periodically. When yours does, the widget keeps
  showing the last cached books, and Grav's log (`logs/grav.log`) says
  `hardcover: API request failed (HTTP 401)`.
