<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class AppReleaseService
{
    private const ANDROID_KEY = 'app_release_android';
    private const IOS_KEY = 'app_release_ios';

    public function all(?Request $request = null, bool $signedUrls = true): array
    {
        return [
            'android' => $this->android($request, $signedUrls),
            'ios' => $this->ios($request, $signedUrls),
        ];
    }

    public function android(?Request $request = null, bool $signedUrls = true): array
    {
        $stored = AppSetting::get(self::ANDROID_KEY, []);
        $path = $stored['storage_path'] ?? 'app-releases/android/SCAK-Admin.apk';
        $exists = Storage::disk('local')->exists($path);

        return [
            'platform' => 'android',
            'title' => 'SCAK Admin Android',
            'version_name' => (string) ($stored['version_name'] ?? '1.0.0'),
            'version_code' => (int) ($stored['version_code'] ?? 1),
            'notes' => (string) ($stored['notes'] ?? 'Internal Android build hosted on SCAK.'),
            'storage_path' => $path,
            'available' => $exists,
            'download_url' => $exists && $signedUrls ? $this->signedDownloadUrl('android') : null,
            'external_url' => null,
            'install_method' => 'apk',
        ];
    }

    public function ios(?Request $request = null, bool $signedUrls = true): array
    {
        $stored = AppSetting::get(self::IOS_KEY, []);
        $path = $stored['storage_path'] ?? 'app-releases/ios/SCAK-Admin.ipa';
        $exists = Storage::disk('local')->exists($path);
        $externalUrl = filled($stored['external_url'] ?? null) ? (string) $stored['external_url'] : null;

        return [
            'platform' => 'ios',
            'title' => 'SCAK Admin iPhone',
            'version_name' => filled($stored['version_name'] ?? null) ? (string) $stored['version_name'] : null,
            'version_code' => null,
            'build_number' => filled($stored['build_number'] ?? null) ? (string) $stored['build_number'] : null,
            'notes' => (string) ($stored['notes'] ?? 'iPhone build in progress. For internal iOS distribution we will use TestFlight or ad hoc provisioning.'),
            'storage_path' => $path,
            'available' => $exists || filled($externalUrl),
            'download_url' => $exists && $signedUrls ? $this->signedDownloadUrl('ios') : null,
            'external_url' => $externalUrl,
            'install_method' => filled($externalUrl) ? 'external' : ($exists ? 'ipa' : 'coming_soon'),
        ];
    }

    public function update(array $payload): array
    {
        AppSetting::put(self::ANDROID_KEY, [
            'version_name' => $payload['android']['version_name'],
            'version_code' => (int) $payload['android']['version_code'],
            'notes' => $payload['android']['notes'] ?? null,
            'storage_path' => $payload['android']['storage_path'] ?? 'app-releases/android/SCAK-Admin.apk',
        ]);

        AppSetting::put(self::IOS_KEY, [
            'version_name' => filled($payload['ios']['version_name'] ?? null) ? $payload['ios']['version_name'] : null,
            'build_number' => filled($payload['ios']['build_number'] ?? null) ? (string) $payload['ios']['build_number'] : null,
            'notes' => $payload['ios']['notes'] ?? null,
            'external_url' => filled($payload['ios']['external_url'] ?? null) ? $payload['ios']['external_url'] : null,
            'storage_path' => $payload['ios']['storage_path'] ?? 'app-releases/ios/SCAK-Admin.ipa',
        ]);

        return $this->all();
    }

    public function fileForPlatform(string $platform): ?array
    {
        return match ($platform) {
            'android' => $this->fileDescriptor('android'),
            'ios' => $this->fileDescriptor('ios'),
            default => null,
        };
    }

    private function fileDescriptor(string $platform): ?array
    {
        $release = $platform === 'android' ? $this->android(null, false) : $this->ios(null, false);
        $path = $release['storage_path'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return [
            'disk' => 'local',
            'path' => $path,
            'name' => basename((string) $path),
            'mime' => $platform === 'android'
                ? 'application/vnd.android.package-archive'
                : 'application/octet-stream',
        ];
    }

    private function signedDownloadUrl(string $platform): string
    {
        return URL::temporarySignedRoute(
            'apps.download',
            now()->addMinutes(30),
            ['platform' => $platform]
        );
    }
}

