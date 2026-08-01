<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracts latitude/longitude from the Google Maps links our field team pastes
 * into RetailStore.google_location_url.
 *
 * We deliberately avoid the Geocoding web service here: the project's
 * GOOGLE_MAPS_API_KEY is HTTP-referrer restricted, so server-side calls to it
 * are rejected ("API keys with referer restrictions cannot be used with this
 * API"). Every strategy below works without an API key — link parsing plus a
 * plain redirect follow for shortened links.
 */
class GoogleMapsLink
{
    /** Hosts that shorten a full maps URL and must be expanded via a redirect. */
    private const SHORT_HOSTS = [
        'maps.app.goo.gl',
        'goo.gl',
        'g.co',
    ];

    /**
     * Best-effort lat/lng for a Google Maps URL.
     *
     * @return array{lat: float, lng: float, source: string}|null
     */
    public function coordinatesFor(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if ($coords = $this->parse($url)) {
            return $coords;
        }

        if ($this->isShortLink($url) && ($expanded = $this->expand($url))) {
            if ($coords = $this->parse($expanded)) {
                return $coords + ['expanded_url' => $expanded];
            }
        }

        return null;
    }

    /**
     * Pull coordinates straight out of a full maps URL.
     *
     * Ordered most-precise first: the `!3d…!4d…` data segment is the pin
     * itself, `q=`/`ll=` style params are an explicit point, and `@lat,lng`
     * is only the viewport centre so it is the last resort.
     *
     * @return array{lat: float, lng: float, source: string}|null
     */
    public function parse(string $url): ?array
    {
        $decoded = urldecode($url);

        $patterns = [
            // .../data=!3m1!4b1!4m5!3m4!1s0x…!8m2!3d27.7172!4d85.3240
            'pin' => '/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/',
            // ?q=27.7172,85.3240 · ?query=… · ?destination=… · ?daddr=… · &ll=…
            'query' => '/[?&](?:q|query|destination|daddr|saddr|ll|center|origin)=(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)/i',
            // /maps/place/27.7172,85.3240
            'place' => '#/maps/(?:place|dir|search)/(-?\d+\.?\d*),(-?\d+\.?\d*)#',
            // /@27.7172,85.3240,17z  — viewport centre, least precise
            'viewport' => '/@(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        ];

        foreach ($patterns as $source => $pattern) {
            if (preg_match($pattern, $decoded, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];

                if ($this->isPlausible($lat, $lng)) {
                    return ['lat' => $lat, 'lng' => $lng, 'source' => $source];
                }
            }
        }

        return null;
    }

    public function isShortLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array(preg_replace('/^www\./', '', $host), self::SHORT_HOSTS, true);
    }

    /**
     * Follow a shortened link's redirect chain to the canonical maps URL.
     */
    public function expand(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                // Google serves the coordinate-bearing URL only to real browsers.
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            ])
                ->withOptions(['allow_redirects' => ['track_redirects' => true]])
                ->timeout(12)
                ->get($url);

            $chain = $response->getHeader('X-Guzzle-Redirect-History');
            $final = end($chain) ?: (string) $response->effectiveUri();

            if ($final && $final !== $url) {
                return $final;
            }

            // Some short links land on a consent/interstitial page that carries
            // the real URL in the body instead of a redirect header.
            if (preg_match('#https://www\.google\.com/maps[^"\'\s\\\\]+#', $response->body(), $m)) {
                return stripslashes($m[0]);
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleMapsLink: failed to expand short link', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Reject obviously broken coordinates (0,0 included — it is never a real
     * store and is what a malformed parse usually produces).
     */
    private function isPlausible(float $lat, float $lng): bool
    {
        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}
