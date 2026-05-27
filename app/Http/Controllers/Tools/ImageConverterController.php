<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImageConverterController extends Controller
{
    private const MAX_KB = 51200;

    private const OUTPUT_FORMATS = ['png', 'jpg', 'webp'];

    public function index()
    {
        return view('tools.image-converter.index');
    }

    public function run(Request $request)
    {
        $request->validate([
            'images'   => 'required|array|min:1|max:50',
            'images.*' => 'file|mimes:jpg,jpeg,png,heic,heif,webp,bmp,tiff,gif|max:' . self::MAX_KB,
            'format'   => 'required|in:' . implode(',', self::OUTPUT_FORMATS),
        ]);

        if (! extension_loaded('imagick')) {
            return back()->withErrors(['images' => 'Imagick PHP extension is required for image conversion.']);
        }

        $format  = $request->input('format');
        $files   = $request->file('images');
        $outDir  = $this->outDir();
        $converted = [];

        try {
            foreach ($files as $file) {
                $img = new \Imagick($file->getRealPath());
                if (method_exists($img, 'autoOrient')) { $img->autoOrient(); }
                $img->setImageFormat($format);

                if ($format === 'jpg') {
                    $img->setImageCompressionQuality(92);
                    $img->setImageBackgroundColor('white');
                    $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                    $img->setImageFormat('jpg');
                } elseif ($format === 'webp') {
                    $img->setImageCompressionQuality(90);
                }

                $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'image';
                $base = Str::slug($base) ?: 'image';
                $outPath = $outDir . DIRECTORY_SEPARATOR . $base . '_' . Str::random(6) . '.' . $format;
                $img->writeImage($outPath);
                $img->clear();

                $converted[] = $outPath;
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['images' => 'Conversion failed: ' . $e->getMessage()]);
        }

        if (count($converted) === 1) {
            return response()->download($converted[0], basename($converted[0]))->deleteFileAfterSend(true);
        }

        $zipPath = $outDir . DIRECTORY_SEPARATOR . 'converted_' . Str::uuid() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return back()->withErrors(['images' => 'Failed to create archive.']);
        }
        foreach ($converted as $f) { $zip->addFile($f, basename($f)); }
        $zip->close();

        foreach ($converted as $f) { @unlink($f); }

        return response()->download($zipPath, 'converted-images.zip')->deleteFileAfterSend(true);
    }

    private function outDir(): string
    {
        $dir = storage_path('app/private/image-converter/' . (auth()->id() ?? 'anon') . '/' . Str::uuid());
        if (! is_dir($dir)) { @mkdir($dir, 0775, true); }
        return $dir;
    }
}
