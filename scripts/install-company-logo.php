<?php

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\File;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sourceCandidates = [
    ($_SERVER['USERPROFILE'] ?? getenv('USERPROFILE') ?: '').DIRECTORY_SEPARATOR.'Downloads'.DIRECTORY_SEPARATOR.'GT Logo png.png',
    ($_SERVER['USERPROFILE'] ?? getenv('USERPROFILE') ?: '').DIRECTORY_SEPARATOR.'Downloads'.DIRECTORY_SEPARATOR.'gt-logo-from-drive.png',
    base_path('public/images/gt-logo.png'),
];

$source = null;
foreach ($sourceCandidates as $candidate) {
    if ($candidate && is_file($candidate) && filesize($candidate) > 1000) {
        $bytes = file_get_contents($candidate, false, null, 0, 8);
        // Skip HTML error pages masquerading as images.
        if ($bytes !== false && str_starts_with($bytes, "\x89PNG")) {
            $source = $candidate;
            break;
        }
        if ($bytes !== false && (str_starts_with($bytes, "\xFF\xD8\xFF") || str_starts_with($bytes, 'GIF'))) {
            $source = $candidate;
            break;
        }
    }
}

if (! $source) {
    fwrite(STDERR, "No valid logo file found.\n");
    exit(1);
}

$relative = 'company-profile/gt-logo.png';
$dest = storage_path('app/public/'.$relative);
File::ensureDirectoryExists(dirname($dest));
File::copy($source, $dest);
File::ensureDirectoryExists(public_path('images'));
File::copy($source, public_path('images/gt-logo.png'));

$profile = CompanyProfile::current();
$profile->forceFill(['logo_path' => $relative])->save();

echo "Installed logo from: {$source}\n";
echo "Stored at: {$dest} (".filesize($dest)." bytes)\n";
echo "CompanyProfile logo_path={$profile->logo_path}\n";
