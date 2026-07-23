<?php

namespace App\Services\LeadDiscovery;

use App\Contracts\LeadDiscoveryProvider;
use App\Exceptions\LeadDiscoveryConfigurationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GooglePlacesLeadDiscoveryProvider implements LeadDiscoveryProvider
{
    public function search(string $query, string $location, int $pageSize = 20, ?string $pageToken = null): array
    {
        $key = (string) config('lead-discovery.google_places.api_key');
        if ($key === '') {
            throw new LeadDiscoveryConfigurationException('Google Places is not configured. Add GOOGLE_PLACES_API_KEY to the application environment.');
        }

        $payload = ['textQuery' => trim($query.' in '.$location), 'pageSize' => min(20, max(1, $pageSize)), 'languageCode' => 'en'];
        if ($pageToken) {
            $payload['pageToken'] = $pageToken;
        }

        $response = $this->client($key)->post('/places:searchText', $payload)->throw()->json();

        return ['places' => $response['places'] ?? [], 'next_page_token' => $response['nextPageToken'] ?? null];
    }

    private function client(string $key): PendingRequest
    {
        return Http::baseUrl((string) config('lead-discovery.google_places.base_url'))
            ->acceptJson()->asJson()->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.websiteUri,places.googleMapsUri,places.rating,places.userRatingCount,places.primaryType,places.location,nextPageToken',
            ])->timeout((int) config('lead-discovery.google_places.timeout', 20))->retry(2, 500);
    }
}
