<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class LocalFaceVerificationService
{
    private static ?string $resolvedPython = null;
    private static ?array $pythonEnvironment = null;

    public function inspectDocument(string $imagePath): array
    {
        return $this->run([
            'inspect',
            '--image',
            $imagePath,
        ]);
    }

    public function compare(string $documentPath, string $livePath): array
    {
        return $this->run([
            'compare',
            '--document',
            $documentPath,
            '--live',
            $livePath,
            '--threshold',
            (string) config('services.local_face.cosine_threshold', 0.363),
        ]);
    }

    private function run(array $arguments): array
    {
        $python = $this->resolvePython();
        $script = (string) config('services.local_face.script_path');
        $detector = (string) config('services.local_face.detector_model');
        $recognizer = (string) config('services.local_face.recognizer_model');

        foreach ([$script, $detector, $recognizer] as $requiredPath) {
            if (! is_file($requiredPath)) {
                throw new RuntimeException('A required local face-verification file is missing.');
            }
        }

        $process = new Process(array_merge([
            $python,
            $script,
            '--detector',
            $detector,
            '--recognizer',
            $recognizer,
        ], $arguments), null, self::$pythonEnvironment);
        $process->setTimeout((float) config('services.local_face.timeout', 45));
        $process->run();

        $result = json_decode(trim($process->getOutput()), true);
        if (! is_array($result)) {
            logger()->error('Local face verifier returned invalid output.', [
                'exit_code' => $process->getExitCode(),
                'stderr' => trim($process->getErrorOutput()),
            ]);

            throw new RuntimeException('The local face-verification process failed.');
        }

        if (! $process->isSuccessful() && data_get($result, 'code') === 'face_verification_failed') {
            logger()->error('Local face verifier failed.', [
                'exit_code' => $process->getExitCode(),
                'stderr' => trim($process->getErrorOutput()),
            ]);
        }

        return $result;
    }

    private function resolvePython(): string
    {
        if (self::$resolvedPython !== null) {
            return self::$resolvedPython;
        }

        $configured = (string) config('services.local_face.python_path', 'python');
        $candidates = [];

        // Web-server PATH values often differ from an interactive terminal on
        // Windows. Prefer installed Python executables before a PATH alias when
        // the application has not been configured with an explicit path.
        if (PHP_OS_FAMILY === 'Windows' && in_array(strtolower($configured), ['python', 'python.exe', 'py'], true)) {
            $installed = glob('C:\\Python*\\python.exe') ?: [];
            rsort($installed, SORT_NATURAL);
            $candidates = array_merge($candidates, $installed);
        }

        $candidates[] = $configured;
        if (PHP_OS_FAMILY !== 'Windows') {
            $candidates[] = 'python3';
        }

        $sitePackages = array_filter([
            (string) config('services.local_face.site_packages', ''),
        ]);
        if (PHP_OS_FAMILY === 'Windows') {
            $sitePackages = array_merge(
                $sitePackages,
                glob('C:\\Users\\*\\AppData\\Roaming\\Python\\Python*\\site-packages') ?: []
            );
        }

        $environments = [null];
        foreach (array_unique($sitePackages) as $sitePackage) {
            if (is_dir($sitePackage)) {
                $environments[] = ['LOCAL_FACE_SITE_PACKAGES' => $sitePackage];
            }
        }

        $failures = [];
        foreach (array_unique($candidates) as $candidate) {
            foreach ($environments as $environment) {
                try {
                    $probe = new Process([
                        $candidate,
                        '-c',
                        'import os,sys; p=os.getenv("LOCAL_FACE_SITE_PACKAGES"); p and sys.path.append(p); import cv2,numpy',
                    ], null, $environment);
                    $probe->setTimeout(10);
                    $probe->run();

                    if ($probe->isSuccessful()) {
                        self::$pythonEnvironment = $environment;

                        return self::$resolvedPython = $candidate;
                    }

                    $failures[$candidate] = trim($probe->getErrorOutput());
                } catch (\Throwable $exception) {
                    $failures[$candidate] = $exception->getMessage();
                    // Try the next interpreter/environment combination.
                }
            }
        }

        logger()->error('No Python interpreter with OpenCV and NumPy was found.', [
            'candidates' => $failures,
        ]);

        throw new RuntimeException(
            'Python with OpenCV and NumPy is unavailable. Set FACE_PYTHON_PATH to the correct executable.'
        );
    }
}
