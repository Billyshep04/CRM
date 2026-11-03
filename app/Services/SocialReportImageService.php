<?php

namespace App\Services;

use App\Models\SocialReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SocialReportImageService
{
    /**
     * The thumbnail panel on the PDF is about 58mm × 20mm. DomPDF can't crop with
     * object-fit, so images are centre-cropped to that shape when they're uploaded.
     */
    private const WIDTH = 900;

    private const HEIGHT = 315;

    /**
     * @return string The path on the local disk, e.g. social-report-images/abc….jpg
     */
    public function store(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = self::WIDTH / self::HEIGHT;

        if ($sourceWidth / $sourceHeight > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }
        $cropX = (int) floor(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) floor(($sourceHeight - $cropHeight) / 2);

        $target = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        // Transparent PNGs would otherwise turn black as JPEGs.
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        ob_start();
        imagejpeg($target, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $path = 'social-report-images/'.Str::random(32).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }

    public function dataUri(?string $path): ?string
    {
        if (! is_string($path) || preg_match(SocialReport::IMAGE_PATH_PATTERN, $path) !== 1 || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
