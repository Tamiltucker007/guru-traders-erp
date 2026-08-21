<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

/**
 * Pre-fills the singleton company profile row from the letterhead. GSTIN,
 * IEC and bank details are deliberately left blank — the user fills those in
 * via Administration → Company Profile once they have them to hand, rather
 * than this seeder guessing at compliance-sensitive numbers.
 *
 * Only creates the row if the table is empty — re-running the seeder must
 * never overwrite GSTIN/IEC/bank details someone has since filled in.
 */
class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanyProfile::query()->exists()) {
            $this->command?->info('Company profile already exists — leaving it as is.');

            return;
        }

        $logoPath = null;
        $bundledLogo = public_path('images/gt-logo.png');
        if (is_file($bundledLogo)) {
            $relative = 'company-profile/gt-logo.png';
            $dest = storage_path('app/public/'.$relative);
            if (! is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            copy($bundledLogo, $dest);
            $logoPath = $relative;
        }

        CompanyProfile::create([
            'company_name' => 'Guru Traders',
            'tagline'      => 'An Indian Govt. Recognised Export House — Exporters of Readymade Garments, Textiles & Sundry Items',
            'address'      => "Shree Hanuman Industrial Estate, Unit No. 210/211, 2nd Floor,\nG. D. Ambekar Marg, Wadala,\nMumbai 400 031, India.",
            'phone'        => '24131047 / 24149628 / 24156076',
            'email'        => 'chetan@gurutradersindia.com',
            'logo_path'    => $logoPath,
        ]);

        $this->command?->info('Company profile seeded from letterhead.');
    }
}
