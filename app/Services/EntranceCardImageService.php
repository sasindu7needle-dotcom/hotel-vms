<?php

namespace App\Services;

use App\Models\VerifiedVisitor;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use GdImage;
use RuntimeException;

class EntranceCardImageService
{
    private const WIDTH = 680;

    private const HEIGHT = 1190;

    // 472 / 11,800 m = 4 cm and 826 / 11,800 m = 7 cm.
    // The pHYs PNG metadata written below preserves that exact print size.
    private const OUTPUT_WIDTH = 472;

    private const OUTPUT_HEIGHT = 826;

    private const PIXELS_PER_METRE = 11800;

    public function __construct(private VisitorCategoryCardService $categoryCards)
    {
    }

    /** Render the downloadable visitor card as a real, portable PNG image. */
    public function render(
        VerifiedVisitor $visitor,
        string $eventName,
        string $qrPayload,
        string $cardStatus,
        ?string $photoDataUri = null,
    ): string {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if (! $image) {
            throw new RuntimeException('The entrance card image could not be created.');
        }

        imageantialias($image, true);
        $category = $this->categoryCards->categoryFor($visitor);
        $white = $this->color($image, '#ffffff');
        $ink = $this->color($image, '#18202b');
        $black = $this->color($image, '#171a18');
        $lime = $this->color($image, '#c8e063');
        $muted = $this->color($image, '#718064');
        $label = $this->color($image, '#829049');
        $soft = $this->color($image, '#f1f6da');
        $photoBackground = $this->color($image, '#edf1e8');
        $line = $this->color($image, '#d8ded0');

        $categoryArtwork = $this->imageFromBytes($this->categoryCards->bytes($category));
        if ($categoryArtwork) {
            $this->drawCoverImage($image, $categoryArtwork, 0, 0, self::WIDTH, self::HEIGHT);
            imagedestroy($categoryArtwork);

            $this->drawCategoryCardDetails($image, $visitor, $qrPayload, $photoDataUri);

            return $this->encodeOutput($image);
        }

        imagefill($image, 0, 0, $white);
        imagefilledellipse($image, 670, 1180, 420, 420, $this->color($image, '#f4f8df'));
        imagefilledrectangle($image, 0, 0, self::WIDTH, 92, $black);
        $passLabel = mb_strtoupper(($category?->name ?: $visitor->category ?: 'Entrance').' PASS');
        $this->fittedText($image, $passLabel, 40, 58, 20, $white, true, 340);

        $statusWidth = $cardStatus === 'VERIFIED' ? 122 : 206;
        $statusX = self::WIDTH - $statusWidth - 38;
        $this->roundedRectangle($image, $statusX, 23, self::WIDTH - 38, 69, 23, $lime);
        $this->fittedText($image, $cardStatus, $statusX + ($statusWidth / 2), 54, 16, $ink, true, $statusWidth - 24, 'center');

        $this->drawContainedImage($image, public_path('img/logo.png'), 104, 108, 472, 116);

        if ($visitor->eventRegistrationDay) {
            $eventLine = $visitor->eventRegistrationDay->label.' · '.$visitor->eventRegistrationDay->event_date->format('d M Y');
            $this->fittedText($image, $eventLine, self::WIDTH / 2, 264, 16, $muted, true, 590, 'center');
        } else {
            $this->fittedText($image, $eventName, self::WIDTH / 2, 264, 16, $muted, true, 590, 'center');
        }

        $this->roundedRectangle($image, 216, 276, 464, 552, 34, $white);
        $this->roundedRectangle($image, 226, 286, 454, 542, 28, $photoBackground);
        $photo = $this->imageFromDataUri($photoDataUri);
        if ($photo) {
            $this->drawCoverImage($image, $photo, 226, 286, 228, 256);
            imagedestroy($photo);
        } else {
            imageellipse($image, 340, 382, 96, 96, $muted);
            imagearc($image, 340, 510, 174, 150, 190, 350, $muted);
        }

        $this->text($image, 'VISITOR NAME', self::WIDTH / 2, 606, 16, $label, true, 'center');
        $this->wrappedText($image, $visitor->full_name ?: 'Verified Visitor', 48, 624, 584, 30, 2, $ink, true, 'center');

        $this->roundedRectangle($image, 36, 716, 644, 798, 20, $soft);
        imagefilledrectangle($image, 339, 716, 341, 798, $white);
        $this->text($image, 'OCCUPATION', 188, 744, 13, $label, true, 'center');
        $this->text($image, 'COMPANY', 492, 744, 13, $label, true, 'center');
        $this->wrappedText($image, $visitor->occupation ?: 'Not provided', 50, 758, 276, 18, 2, $ink, true, 'center');
        $this->wrappedText(
            $image,
            $visitor->company ?: $visitor->exhibitorProfile?->company_name ?: $visitor->exhibitorProfile?->name_board ?: 'Not provided',
            354,
            758,
            276,
            18,
            2,
            $ink,
            true,
            'center'
        );

        for ($x = 0; $x < self::WIDTH; $x += 16) {
            imageline($image, $x, 872, min($x + 8, self::WIDTH), 872, $line);
        }

        $qrPng = (new Writer(new GDLibRenderer(184, 2, 'png', 9)))
            ->writeString($qrPayload, ecLevel: ErrorCorrectionLevel::H());
        $qrImage = imagecreatefromstring($qrPng);
        if (! $qrImage) {
            throw new RuntimeException('The entrance card QR code could not be created.');
        }
        imagecopy($image, $qrImage, 248, 892, 0, 0, 184, 184);
        imagedestroy($qrImage);

        $this->text($image, 'PARTICIPANT REFERENCE NUMBER', self::WIDTH / 2, 1110, 13, $label, true, 'center');
        $this->fittedText($image, $qrPayload, self::WIDTH / 2, 1134, 13, $ink, true, 600, 'center');

        return $this->encodeOutput($image);
    }

    /**
     * Category artwork is the complete card template. Only the visitor identity
     * and scannable QR data are added; none of the legacy card chrome is drawn.
     */
    private function drawCategoryCardDetails(
        GdImage $image,
        VerifiedVisitor $visitor,
        string $qrPayload,
        ?string $photoDataUri,
    ): void {
        $white = $this->color($image, '#ffffff');
        $ink = $this->color($image, '#18202b');
        $muted = $this->color($image, '#667085');
        $photoBackground = $this->color($image, '#edf1e8');

        // Place the visitor name above the photo and QR row.
        $identityPanel = imagecolorallocatealpha($image, 255, 255, 255, 18);
        $this->roundedRectangle($image, 45, 325, 635, 450, 24, $identityPanel);
        $this->text($image, 'VISITOR NAME', self::WIDTH / 2, 365, 14, $muted, true, 'center');
        $this->wrappedText(
            $image,
            $visitor->full_name ?: 'Verified Visitor',
            69,
            380,
            542,
            27,
            2,
            $ink,
            true,
            'center',
        );

        // Keep the two scannable identity elements in one balanced row below it.
        $this->roundedRectangle($image, 55, 475, 313, 761, 36, $white);
        $this->roundedRectangle($image, 70, 490, 298, 746, 28, $photoBackground);
        $photo = $this->imageFromDataUri($photoDataUri);
        if ($photo) {
            $this->drawCoverImage($image, $photo, 70, 490, 228, 256);
            imagedestroy($photo);
        } else {
            imageellipse($image, 184, 586, 96, 96, $muted);
            imagearc($image, 184, 714, 174, 150, 190, 350, $muted);
        }

        $this->roundedRectangle($image, 367, 475, 625, 761, 28, $white);
        $qrPng = (new Writer(new GDLibRenderer(184, 2, 'png', 9)))
            ->writeString($qrPayload, ecLevel: ErrorCorrectionLevel::H());
        $qrImage = imagecreatefromstring($qrPng);
        if (! $qrImage) {
            throw new RuntimeException('The entrance card QR code could not be created.');
        }
        imagecopy($image, $qrImage, 404, 501, 0, 0, 184, 184);
        imagedestroy($qrImage);

        $this->text($image, 'REFERENCE', 496, 715, 12, $muted, true, 'center');
        $this->fittedText($image, $qrPayload, 496, 741, 11, $ink, true, 220, 'center');

    }

    private function encodeOutput(GdImage $image): string
    {
        $output = imagecreatetruecolor(self::OUTPUT_WIDTH, self::OUTPUT_HEIGHT);
        if (! $output) {
            imagedestroy($image);

            throw new RuntimeException('The entrance card output image could not be created.');
        }
        imagealphablending($output, true);
        imagecopyresampled(
            $output,
            $image,
            0,
            0,
            0,
            0,
            self::OUTPUT_WIDTH,
            self::OUTPUT_HEIGHT,
            self::WIDTH,
            self::HEIGHT,
        );
        imagedestroy($image);

        ob_start();
        imagepng($output, null, 8);
        $png = ob_get_clean();
        imagedestroy($output);

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('The entrance card PNG could not be encoded.');
        }

        return $this->withPhysicalDimensions($png);
    }

    /** Add a PNG pHYs chunk so print software reads the card as exactly 4 cm x 7 cm. */
    private function withPhysicalDimensions(string $png): string
    {
        $data = pack('NNC', self::PIXELS_PER_METRE, self::PIXELS_PER_METRE, 1);
        $typeAndData = 'pHYs'.$data;
        $chunk = pack('N', strlen($data)).$typeAndData.pack('N', crc32($typeAndData));

        // PNG signature (8 bytes) + IHDR chunk (25 bytes).
        return substr($png, 0, 33).$chunk.substr($png, 33);
    }

    private function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private function roundedRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function drawContainedImage(GdImage $canvas, string $path, int $x, int $y, int $width, int $height): void
    {
        $source = is_file($path) ? @imagecreatefrompng($path) : false;
        if (! $source) {
            return;
        }

        $ratio = min($width / imagesx($source), $height / imagesy($source));
        $drawWidth = (int) round(imagesx($source) * $ratio);
        $drawHeight = (int) round(imagesy($source) * $ratio);
        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $source,
            $x + (int) (($width - $drawWidth) / 2),
            $y + (int) (($height - $drawHeight) / 2),
            0,
            0,
            $drawWidth,
            $drawHeight,
            imagesx($source),
            imagesy($source),
        );
        imagedestroy($source);
    }

    private function drawCoverImage(GdImage $canvas, GdImage $source, int $x, int $y, int $width, int $height): void
    {
        $sourceRatio = imagesx($source) / imagesy($source);
        $targetRatio = $width / $height;
        if ($sourceRatio > $targetRatio) {
            $cropHeight = imagesy($source);
            $cropWidth = (int) round($cropHeight * $targetRatio);
            $sourceX = (int) ((imagesx($source) - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = imagesx($source);
            $cropHeight = (int) round($cropWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) ((imagesy($source) - $cropHeight) / 2);
        }

        imagecopyresampled($canvas, $source, $x, $y, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
    }

    private function imageFromDataUri(?string $dataUri): GdImage|false
    {
        if (! $dataUri || ! str_contains($dataUri, ',')) {
            return false;
        }

        $bytes = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);

        return is_string($bytes) ? @imagecreatefromstring($bytes) : false;
    }

    private function imageFromBytes(?string $bytes): GdImage|false
    {
        return filled($bytes) ? @imagecreatefromstring($bytes) : false;
    }

    private function text(
        GdImage $image,
        string $text,
        float $x,
        int $baseline,
        int $size,
        int $color,
        bool $bold = false,
        string $align = 'left',
    ): void {
        $font = $this->font($bold);
        if ($font) {
            $bounds = imagettfbbox($size, 0, $font, $text);
            $width = $bounds ? $bounds[2] - $bounds[0] : 0;
            $drawX = $align === 'center' ? $x - ($width / 2) : ($align === 'right' ? $x - $width : $x);
            imagettftext($image, $size, 0, (int) round($drawX), $baseline, $color, $font, $text);

            return;
        }

        $fontId = 5;
        $width = imagefontwidth($fontId) * strlen($text);
        $drawX = $align === 'center' ? $x - ($width / 2) : ($align === 'right' ? $x - $width : $x);
        imagestring($image, $fontId, (int) round($drawX), $baseline - imagefontheight($fontId), $text, $color);
    }

    private function fittedText(
        GdImage $image,
        string $text,
        float $x,
        int $baseline,
        int $size,
        int $color,
        bool $bold,
        int $maxWidth,
        string $align = 'left',
    ): void {
        while ($size > 10 && $this->textWidth($text, $size, $bold) > $maxWidth) {
            $size--;
        }

        $this->text($image, $text, $x, $baseline, $size, $color, $bold, $align);
    }

    private function wrappedText(
        GdImage $image,
        string $text,
        int $x,
        int $top,
        int $maxWidth,
        int $size,
        int $maxLines,
        int $color,
        bool $bold,
        string $align,
    ): void {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        foreach ($words as $word) {
            $lineIndex = max(count($lines) - 1, 0);
            $candidate = isset($lines[$lineIndex]) ? $lines[$lineIndex].' '.$word : $word;
            if (isset($lines[$lineIndex]) && $this->textWidth($candidate, $size, $bold) > $maxWidth) {
                $lines[] = $word;
            } else {
                $lines[$lineIndex] = $candidate;
            }
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '.').'…';
        }

        $lineHeight = (int) round($size * 1.45);
        foreach ($lines as $index => $line) {
            $anchor = $align === 'center' ? $x + ($maxWidth / 2) : $x;
            $this->fittedText($image, $line, $anchor, $top + $size + ($index * $lineHeight), $size, $color, $bold, $maxWidth, $align);
        }
    }

    private function textWidth(string $text, int $size, bool $bold): int
    {
        $font = $this->font($bold);
        if (! $font) {
            return imagefontwidth(5) * strlen($text);
        }

        $bounds = imagettfbbox($size, 0, $font, $text);

        return $bounds ? $bounds[2] - $bounds[0] : 0;
    }

    private function font(bool $bold): ?string
    {
        $projectFont = resource_path('fonts/'.($bold ? 'Inter-Bold.ttf' : 'Inter-Regular.ttf'));
        $candidates = [
            $projectFont,
            $bold ? 'C:\\Windows\\Fonts\\arialbd.ttf' : 'C:\\Windows\\Fonts\\arial.ttf',
            $bold ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            $bold ? '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf' : '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
