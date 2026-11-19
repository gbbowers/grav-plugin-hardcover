<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Twig\TwigFunction;

/**
 * Hardcover "Currently Reading" plugin for Grav.
 *
 * Exposes a Twig function `hardcover_reading()` that returns your
 * currently-reading books from the Hardcover GraphQL API. The API is
 * called server-side only (your token never reaches the browser), and the
 * result is cached to a JSON file. If the API is down, the last good copy
 * is served.
 */
class HardcoverPlugin extends Plugin
{
    const ENDPOINT = 'https://api.hardcover.app/v1/graphql';

    const QUERY = <<<'GQL'
query CurrentlyReading($limit: Int!) {
  me {
    user_books(where: {status_id: {_eq: 2}}, limit: $limit) {
      book {
        title
        slug
        image { url }
        contributions { author { name } }
      }
      user_book_reads(order_by: {id: desc}, limit: 1) {
        progress
      }
    }
  }
}
GQL;

    public static function getSubscribedEvents(): array
    {
        return ['onPluginsInitialized' => ['onPluginsInitialized', 0]];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigInitialized'   => ['onTwigInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
        ]);
    }

    public function onTwigSiteVariables(): void
    {
        if ($this->config->get('plugins.hardcover.built_in_css', true)) {
            $this->grav['assets']->addCss('plugin://hardcover/css/hardcover.css');
        }
    }

    /**
     * [hardcover-reading] shortcode for Markdown pages (needs the
     * shortcode-core plugin). Renders the same partial as the Twig include.
     */
    public function onShortcodeHandlers(): void
    {
        $this->grav['shortcode']->getHandlers()->add('hardcover-reading', function () {
            return $this->grav['twig']->processTemplate('partials/hardcover-reading.html.twig');
        });
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(
            new TwigFunction('hardcover_reading', [$this, 'getReading'])
        );
    }

    /** @return array<int, array{title:string, slug:?string, url:?string, cover:?string, authors:array, progress:?float}> */
    public function getReading(): array
    {
        $cfg   = $this->config->get('plugins.hardcover');
        $ttl   = max(1, (int)($cfg['cache_minutes'] ?? 60)) * 60;
        $file  = $this->cacheFile();
        $stale = null;

        if (is_file($file)) {
            $stale = json_decode((string)file_get_contents($file), true);
            if (is_array($stale) && (time() - filemtime($file)) < $ttl) {
                return $stale;
            }
        }

        $fresh = $this->fetch($cfg);
        if ($fresh === null) {
            // API failed: serve the last good copy, and touch it so we don't
            // hammer the API on every page view while it's down.
            if (is_file($file)) {
                @touch($file);
            }
            return is_array($stale) ? $stale : [];
        }

        @file_put_contents($file, json_encode($fresh), LOCK_EX);
        return $fresh;
    }

    private function fetch(array $cfg): ?array
    {
        $token = trim((string)($cfg['token'] ?? '')) ?: (string)getenv('HARDCOVER_TOKEN');
        if ($token === '') {
            $this->grav['log']->warning('hardcover: no API token configured');
            return null;
        }
        // Accept the token with or without the "Bearer " prefix Hardcover shows.
        if (stripos($token, 'bearer ') !== 0) {
            $token = 'Bearer ' . $token;
        }

        $payload = json_encode([
            'query'     => self::QUERY,
            'variables' => ['limit' => (int)($cfg['limit'] ?? 5)],
        ]);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . $token,
                'User-Agent: grav-plugin-hardcover/1.0',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            $this->grav['log']->warning("hardcover: API request failed (HTTP $status)");
            return null;
        }

        $json = json_decode($body, true);
        if (!empty($json['errors'])) {
            $this->grav['log']->warning('hardcover: ' . json_encode($json['errors']));
            return null;
        }

        // `me` comes back as an array with one user in it.
        $me = $json['data']['me'] ?? null;
        if (is_array($me) && array_key_exists(0, $me)) {
            $me = $me[0];
        }
        $rows = $me['user_books'] ?? null;
        if (!is_array($rows)) {
            return null;
        }

        $books = [];
        foreach ($rows as $row) {
            $b = $row['book'] ?? [];
            $authors = [];
            foreach ($b['contributions'] ?? [] as $c) {
                if (!empty($c['author']['name'])) {
                    $authors[] = $c['author']['name'];
                }
            }
            $progress = $row['user_book_reads'][0]['progress'] ?? null;
            $books[] = [
                'title'    => $b['title'] ?? 'Untitled',
                'slug'     => $b['slug'] ?? null,
                'url'      => !empty($b['slug']) ? 'https://hardcover.app/books/' . $b['slug'] : null,
                'cover'    => $b['image']['url'] ?? null,
                'authors'  => array_values(array_unique($authors)),
                'progress' => $progress !== null ? round((float)$progress, 1) : null,
            ];
        }
        return $books;
    }

    private function cacheFile(): string
    {
        $locator = $this->grav['locator'];
        $dir = $locator->findResource('user-data://', true) ?: (GRAV_ROOT . '/user/data');
        $dir .= '/hardcover';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/currently-reading.json';
    }
}
