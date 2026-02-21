<?php

namespace Database\Factories;

use App\Models\PortalCapture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalCapture>
 */
class PortalCaptureFactory extends Factory
{
    protected $model = PortalCapture::class;

    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'presentation_id'       => null,
            'source_site'           => 'www.property24.com',
            'page_type'             => 'search',
            'source_url'            => 'https://www.property24.com/for-sale/uvongo/kwazulu-natal/6359',
            'final_url'             => 'https://www.property24.com/for-sale/uvongo/kwazulu-natal/6359',
            'page_title'            => 'Property for Sale in Uvongo',
            'captured_at'           => now(),
            'extractor_version'     => 'portal_ext_v1',
            'dom_hash_sha256'       => hash('sha256', 'test'),
            'html_bytes'            => 1024,
            'raw_html_path'         => '',
            'screenshot_path'       => null,
            'parse_status'          => 'parsed',
            'extracted_fields_json' => null,
            'jsonld_json'           => null,
            'found_image_urls_json' => null,
        ];
    }
}
