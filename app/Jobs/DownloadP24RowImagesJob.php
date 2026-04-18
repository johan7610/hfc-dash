<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\Importer\P24ImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadP24RowImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $propertyId, public array $urls) {}

    public function handle(P24ImageDownloader $downloader): void
    {
        $property = Property::withoutGlobalScopes()->find($this->propertyId);
        if (!$property || empty($this->urls)) return;

        $stored = [];
        foreach ($this->urls as $idx => $url) {
            $ordinal = $idx + 1;
            $dest = "properties/{$property->id}/{$ordinal}.jpg";
            $path = $downloader->download($url, $dest);
            if ($path) $stored[] = $path;
        }

        if (!empty($stored)) {
            $property->refresh();
            $property->images_json = $stored;
            $property->saveQuietly();
        } else {
            Log::warning('DownloadP24RowImagesJob: no images stored', [
                'property_id' => $property->id,
                'url_count'   => count($this->urls),
            ]);
        }
    }
}
