<?php

namespace App\Services;

use App\Models\Animal;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WikipediaImageService
{
    /**
     * @return array{token: string, preview_url: string, source_url: string, alt: string, credit: ?string, license: string}|null
     */
    public function candidateFor(Animal $animal): ?array
    {
        $page = $this->findPage($animal);

        if ($page === null) {
            return null;
        }

        $pageTitle = trim((string) ($page['title'] ?? $animal->common_name));

        return $this->candidateFromFile(
            (string) $page['pageimage'],
            'Photo of a '.$animal->common_name,
            ['kind' => 'animal', 'animal_id' => $animal->id],
            $pageTitle,
        );
    }

    /**
     * Build a candidate for a scanned species that has not been saved yet.
     * The token is bound to the Wikidata id instead of an animal id.
     *
     * @return array<string, mixed>|null
     */
    public function candidateForSpecies(string $qid, ?string $pageTitle, string $commonName, ?string $imageUrl): ?array
    {
        $fileName = $this->commonsFileName($imageUrl);

        if ($fileName === null) {
            return null;
        }

        return $this->candidateFromFile(
            $fileName,
            'Photo of a '.$commonName,
            ['kind' => 'scan', 'qid' => $qid],
            $pageTitle,
        );
    }

    /**
     * Validate a candidate token created for a scanned species.
     *
     * @return array<string, mixed>
     */
    public function decodeScanCandidate(string $token, string $qid): array
    {
        $candidate = $this->decryptCandidate($token);

        if (($candidate['kind'] ?? null) !== 'scan'
            || strtoupper((string) ($candidate['qid'] ?? '')) !== strtoupper($qid)
            || (int) ($candidate['expires_at'] ?? 0) < now()->timestamp
            || blank($candidate['download_url'] ?? null)) {
            throw new RuntimeException('That image suggestion has expired. Please look up the species again.');
        }

        $this->assertCandidateHosts($candidate);

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeCandidate(string $token, Animal $animal): array
    {
        $candidate = $this->decryptCandidate($token);

        if ((int) ($candidate['animal_id'] ?? 0) !== $animal->id
            || (int) ($candidate['expires_at'] ?? 0) < now()->timestamp
            || blank($candidate['download_url'] ?? null)) {
            throw new RuntimeException('That image suggestion has expired. Please search again.');
        }

        $this->assertCandidateHosts($candidate);

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptCandidate(string $token): array
    {
        try {
            $candidate = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw new RuntimeException('That image suggestion is no longer valid. Please search again.');
        }

        if (! is_array($candidate)) {
            throw new RuntimeException('That image suggestion is no longer valid. Please search again.');
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>|null
     */
    private function candidateFromFile(string $fileName, string $alt, array $claims, ?string $pageTitle): ?array
    {
        $candidate = $this->imageInfo($fileName);

        if ($candidate === null) {
            return null;
        }

        $expiresAt = now()->addMinutes((int) config('ecosight.images.candidate_ttl_minutes', 15))->timestamp;

        $token = Crypt::encryptString((string) json_encode(array_merge($claims, [
            'download_url' => $candidate['download_url'],
            'source_url' => $candidate['source_url'],
            'alt' => $alt,
            'credit' => $candidate['credit'],
            'license' => $candidate['license'],
            'expires_at' => $expiresAt,
        ])));

        return [
            'token' => $token,
            'preview_url' => $candidate['download_url'],
            'download_url' => $candidate['download_url'],
            'source_url' => $candidate['source_url'],
            'alt' => $alt,
            'credit' => $candidate['credit'],
            'license' => $candidate['license'],
            'page_title' => $pageTitle,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function assertCandidateHosts(array $candidate): void
    {
        $this->assertImageHost((string) $candidate['download_url']);

        if (filled($candidate['source_url'] ?? null)) {
            $this->assertPageHost((string) $candidate['source_url']);
        }
    }

    public function download(array $candidate): string
    {
        $url = (string) ($candidate['download_url'] ?? '');
        $this->assertImageHost($url);

        $response = $this->client()
            ->timeout((int) config('ecosight.images.download_timeout_seconds', 15))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('The image could not be downloaded from Wikimedia Commons.');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! Str::startsWith($contentType, 'image/')) {
            throw new RuntimeException('Wikimedia returned a file that is not an image.');
        }

        if (strlen($response->body()) > (int) config('ecosight.images.max_bytes', AnimalImageService::MAX_BYTES)) {
            throw new RuntimeException('The downloaded image is too large.');
        }

        return $response->body();
    }

    /**
     * @return array{pageimage: string, title: string}|null
     */
    private function findPage(Animal $animal): ?array
    {
        $query = trim($animal->common_name.' '.$animal->scientific_name);

        $response = $this->lookup(config('ecosight.images.wikipedia_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'generator' => 'search',
            'gsrsearch' => $query,
            'gsrnamespace' => 0,
            'gsrlimit' => 5,
            'redirects' => 1,
            'prop' => 'pageimages',
            'piprop' => 'name|original|thumbnail',
            'pithumbsize' => 1200,
        ]);

        $pages = collect($response->json('query.pages', []))
            ->filter(fn (mixed $page): bool => is_array($page) && filled($page['pageimage'] ?? null))
            ->sortBy('index');

        foreach ($pages as $page) {
            if (! $this->isRejectedFile((string) $page['pageimage'])) {
                return [
                    'pageimage' => (string) $page['pageimage'],
                    'title' => (string) ($page['title'] ?? $animal->common_name),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{download_url: string, source_url: string, credit: ?string, license: string}|null
     */
    private function imageInfo(string $fileName): ?array
    {
        $response = $this->lookup(config('ecosight.images.commons_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'titles' => 'File:'.$fileName,
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime|extmetadata',
            'iiurlwidth' => 1200,
        ]);

        $page = collect($response->json('query.pages', []))->first();
        $info = is_array($page) ? ($page['imageinfo'][0] ?? null) : null;

        if (! is_array($info)) {
            return null;
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        if ((int) ($info['width'] ?? 0) < 600 || (int) ($info['height'] ?? 0) < 600) {
            return null;
        }

        $downloadUrl = (string) ($info['thumburl'] ?? $info['url'] ?? '');
        $sourceUrl = (string) ($info['descriptionurl'] ?? '');
        $metadata = $info['extmetadata'] ?? [];
        $license = $this->metadataValue($metadata, 'LicenseShortName')
            ?: $this->metadataValue($metadata, 'UsageTerms');
        $description = $this->metadataValue($metadata, 'ImageDescription');
        $credit = $this->metadataValue($metadata, 'Artist')
            ?: $this->metadataValue($metadata, 'Credit')
            ?: 'Wikimedia Commons';

        if ($downloadUrl === '' || $license === '' || $this->isRejectedLicense($license) || $this->isRejectedFile($fileName.' '.$description)) {
            return null;
        }

        $this->assertImageHost($downloadUrl);
        $this->assertPageHost($sourceUrl);

        return [
            'download_url' => $downloadUrl,
            'source_url' => $sourceUrl,
            'credit' => Str::limit($credit, 250, ''),
            'license' => Str::limit($license, 90, ''),
        ];
    }

    private function lookup(string $url, array $query): Response
    {
        $response = $this->client()
            ->timeout((int) config('ecosight.images.lookup_timeout_seconds', 5))
            ->get($url, $query);

        if (! $response->successful()) {
            throw new RuntimeException('Wikimedia did not respond. Please try again later.');
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config('ecosight.wikimedia.user_agent'),
            'Accept' => 'application/json',
        ]);
    }

    private function isRejectedLicense(string $license): bool
    {
        $license = Str::lower($license);

        if (Str::contains($license, [
            'noncommercial', 'non-commercial', 'no derivative', 'no derivatives',
            'fair use', 'all rights reserved', 'copyrighted', 'gfdl',
        ]) || preg_match('/\b(nc|nd)\b/', $license) === 1) {
            return true;
        }

        return ! Str::contains($license, ['cc0', 'public domain', ' pd', 'cc by']);
    }

    private function isRejectedFile(string $text): bool
    {
        $text = Str::lower($text);

        foreach ((array) config('ecosight.images.reject_terms', []) as $term) {
            $term = trim(Str::lower((string) $term));

            if ($term !== '' && preg_match('/\b'.preg_quote($term, '/').'(?:s|es)?\b/u', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn a Wikimedia Commons image URL into the file name used by the
     * Commons imageinfo API. Local Wikipedia uploads are rejected.
     */
    private function commonsFileName(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'upload.wikimedia.org'
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');

        if (! Str::startsWith($path, '/wikipedia/commons/')) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn (string $segment): bool => $segment !== '',
        ));

        $fileName = ($segments[2] ?? null) === 'thumb'
            ? ($segments[count($segments) - 2] ?? null)
            : end($segments);

        if (! is_string($fileName) || $fileName === '') {
            return null;
        }

        $fileName = rawurldecode($fileName);

        if ($fileName === '' || Str::contains($fileName, ['/', '\\'])) {
            return null;
        }

        return $fileName;
    }

    private function metadataValue(mixed $metadata, string $key): ?string
    {
        $value = is_array($metadata) ? data_get($metadata, $key.'.value') : null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value) ?: null;
    }

    private function assertImageHost(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'upload.wikimedia.org'
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new RuntimeException('The image must come from Wikimedia Commons.');
        }
    }

    private function assertPageHost(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! in_array($parts['host'] ?? null, ['commons.wikimedia.org', 'en.wikipedia.org'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new RuntimeException('The image source page is not an approved Wikimedia site.');
        }
    }
}
