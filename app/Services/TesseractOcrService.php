<?php

namespace App\Services;

class TesseractOcrService
{
    /**
     * Run Tesseract OCR on a local image file.
     *
     * @param string $filePath
     * @return string
     */
    public function extractText(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $executable = $this->findExecutable();

        if (! $executable || ! function_exists('imagecreatefromstring')) return '';

        $source = @imagecreatefromstring((string) file_get_contents($filePath));
        if (! $source) return '';
        $bestText = '';
        $bestScore = 0;
        $results = [];
        $bestRotation = 0;

        try {
            foreach ([0, 90, 180, 270] as $rotation) {
                $image = $rotation === 0 ? $source : imagerotate($source, $rotation, 255);
                if (! $image) continue;
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                imagefilter($image, IMG_FILTER_CONTRAST, -25);
                $width = imagesx($image);
                $height = imagesy($image);
                if (max($width, $height) < 1800) {
                    $scale = min(3, 1800 / max($width, $height));
                    $scaled = imagescale($image, (int) ($width * $scale), (int) ($height * $scale), IMG_BICUBIC);
                    if ($scaled) {
                        if ($image !== $source) imagedestroy($image);
                        $image = $scaled;
                    }
                }

                $temporaryDirectory = storage_path('app/ocr-temp');
                if (! is_dir($temporaryDirectory)) @mkdir($temporaryDirectory, 0775, true);
                $temporary = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid('visitor-ocr-', true).'.png';
                imagepng($image, $temporary, 3);
                if ($image !== $source) imagedestroy($image);
                foreach ([6, 11] as $pageMode) {
                    $command = sprintf('%s %s stdout -l eng --oem 1 --psm %d 2>&1', escapeshellarg($executable), escapeshellarg($temporary), $pageMode);
                    $text = trim((string) @shell_exec($command));
                    $score = $this->documentTextScore($text);
                    $results[] = ['rotation' => $rotation, 'text' => $text, 'score' => $score];
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestText = $text;
                        $bestRotation = $rotation;
                    }
                }
                @unlink($temporary);
            }
        } catch (\Throwable $e) {
            logger()->warning('Tesseract execution failed: '.$e->getMessage());
        } finally {
            imagedestroy($source);
        }

        if ($bestScore < 8) return '';

        $usefulTexts = array_column(array_filter($results, fn ($result) => $result['rotation'] === $bestRotation && $result['score'] >= 8), 'text');
        array_unshift($usefulTexts, $bestText);
        return implode("\n", array_values(array_unique(array_filter($usefulTexts))));
    }

    private function documentTextScore(string $text): int
    {
        if ($text === '' || str_contains(strtolower($text), 'error opening data file')) return 0;
        $score = min(20, intdiv(strlen(preg_replace('/[^A-Za-z]/', '', $text)), 20));
        if (preg_match('/\b(?:19|20)\d{10}\b|\b\d{9}[VX]\b/i', $text)) $score += 30;
        foreach (['name', 'address', 'identity', 'national', 'date of birth', 'sri lanka'] as $keyword) {
            if (stripos($text, $keyword) !== false) $score += 8;
        }
        return $score;
    }

    /**
     * Find the Tesseract executable path prioritizing F drive and custom .env settings.
     *
     * @return string|null
     */
    public function findExecutable(): ?string
    {
        $envPath = env('TESSERACT_PATH');
        if (filled($envPath) && file_exists($envPath)) {
            return $envPath;
        }

        $candidates = [
            'F:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'F:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'F:\\Tesseract-OCR\\tesseract.exe',
            'F:\\tesseract\\tesseract.exe',
            'F:\\Tesseract\\tesseract.exe',
            'F:\\tesseract.exe',
            'F:\\php-8.4\\tesseract.exe',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            getenv('LOCALAPPDATA').'\\Programs\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract',
            'tesseract',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== 'tesseract' && file_exists($candidate)) {
                return $candidate;
            }
            if ($candidate === 'tesseract') {
                $test = @shell_exec('tesseract --version 2>&1');
                if ($test && preg_match('/tesseract\s+v?\d/i', $test) && ! str_contains(strtolower($test), 'not recognized')) {
                    return 'tesseract';
                }
            }
        }

        return null;
    }
}
