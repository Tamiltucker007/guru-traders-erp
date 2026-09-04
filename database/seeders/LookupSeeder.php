<?php

namespace Database\Seeders;

use App\Models\CalculationBasis;
use App\Models\Country;
use App\Models\Currency;
use App\Models\GstRate;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Port;
use App\Models\PriceBand;
use App\Models\ShipmentMethod;
use Illuminate\Database\Seeder;

/**
 * Starting values for the Category, Product and Buyer dropdowns.
 *
 * Idempotent — every row goes through firstOrCreate on its natural key, so
 * re-running this on a deploy never duplicates and never overwrites a value the
 * client has since edited.
 *
 * No units here: the client cancelled the Unit Master, so the unit is typed on
 * the Product master instead. See the products migration.
 *
 * These lists are a starting point, not the client's final data. Every one of
 * these tables is annotated "with an option to add more in the future" on the
 * sheets, and gets a Lookups screen under Settings in a later phase.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->productLookups();
        $this->buyerLookups();

        $this->command?->info('Lookups seeded.');
    }

    private function productLookups(): void
    {
        // Product sheet col J: "AA and AB with an option to add in the future".
        // N/A — client request: some products have no price band.
        foreach ([['AA', 'Band AA'], ['AB', 'Band AB'], ['NA', 'N/A']] as [$code, $name]) {
            PriceBand::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        // Product sheet col K. Standard Indian GST slabs.
        foreach ([0, 5, 12, 18, 28] as $rate) {
            GstRate::firstOrCreate(['rate' => $rate]);
        }

        /*
         * Product sheet cols N, R, U: what an incentive percentage applies to.
         *
         * "Net Value" is not on the Product sheet — it comes from the Agent
         * master, where the client's prototype offers Net Value for a
         * supplier-side commission and FOB Value for a buyer-side one. Both
         * screens read the same table, so the list is the union.
         */
        foreach (['FOB Value', 'Net Value', 'Gross Value', 'Quantity', 'Net Weight', 'Square Metre'] as $basis) {
            CalculationBasis::firstOrCreate(['name' => $basis]);
        }
    }

    /**
     * Buyer sheet cols L, N, Q, R, S, T.
     */
    private function buyerLookups(): void
    {
        // Col L — full ISO country list (client: "please add all countries").
        $countries = require __DIR__.'/data/iso_countries.php';

        foreach ($countries as [$iso, $name, $dial]) {
            Country::firstOrCreate(
                ['iso_code' => $iso],
                ['name' => $name, 'dial_code' => $dial, 'status' => 'active']
            );
        }

        // Col T. INR first — it is the PO currency; the rest are buyer-side.
        $currencies = [
            ['INR', 'Indian Rupee', '₹'],
            ['USD', 'US Dollar',    '$'],
            ['EUR', 'Euro',         '€'],
            ['GBP', 'Pound Sterling', '£'],
            ['AED', 'UAE Dirham',   'د.إ'],
            ['AUD', 'Australian Dollar', 'A$'],
            ['CAD', 'Canadian Dollar',   'C$'],
            ['JPY', 'Japanese Yen', '¥'],
        ];

        foreach ($currencies as [$iso, $name, $symbol]) {
            Currency::firstOrCreate(['iso_code' => $iso], ['name' => $name, 'symbol' => $symbol]);
        }

        // Col N. Indian load ports and the discharge ports on the example rows.
        $ports = [
            ['IN', 'INMAA', 'Chennai Port',      'sea'],
            ['IN', 'INNSA', 'Nhava Sheva',       'sea'],
            ['IN', 'INTUT', 'Tuticorin Port',    'sea'],
            ['IN', 'INCOK', 'Cochin Port',       'sea'],
            ['IN', 'INMAA4', 'Chennai Air Cargo', 'air'],
            ['GB', 'GBLON', 'London Port',       'sea'],
            ['GB', 'GBFXT', 'Felixstowe',        'sea'],
            ['US', 'USNYC', 'New York Port',     'sea'],
            ['US', 'USLAX', 'Los Angeles Port',  'sea'],
            ['AE', 'AEJEA', 'Jebel Ali',         'sea'],
            ['DE', 'DEHAM', 'Hamburg',           'sea'],
            ['NL', 'NLRTM', 'Rotterdam',         'sea'],
        ];

        foreach ($ports as [$iso, $code, $name, $type]) {
            Port::firstOrCreate(
                ['code' => $code],
                [
                    'name'       => $name,
                    'type'       => $type,
                    'country_id' => Country::where('iso_code', $iso)->value('id'),
                ]
            );
        }

        // Col R. The incoterms an apparel exporter actually quotes on.
        $incoterms = [
            ['EXW', 'Ex Works'],
            ['FOB', 'Free On Board'],
            ['CFR', 'Cost and Freight'],
            ['CIF', 'Cost, Insurance and Freight'],
            ['DAP', 'Delivered At Place'],
            ['DDP', 'Delivered Duty Paid'],
            ['FCA', 'Free Carrier'],
        ];

        foreach ($incoterms as [$code, $name]) {
            Incoterm::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        /*
         * Col Q. `days` is filled where the term is a plain net period, so due
         * dates can be computed later without parsing the name. "Advance" and
         * "Against L/C" have no net period — null, not 0, because 0 would mean
         * "due immediately" and these are simply not date-driven.
         */
        /*
         * `has_split` says whether the term divides the payment into an advance
         * and a balance, which is what opens the two percentage boxes on the
         * Buyer form. Stated here rather than read out of the name — "Advance"
         * and "50% Advance, 50% on Delivery" both contain the word and only the
         * second one splits. Same reasoning as `days` above.
         */
        $paymentTerms = [
            // name, days, has_split, applies_to
            ['Advance',                      null, false, 'both'],
            ['30 Days',                      30,   false, 'both'],
            ['45 Days',                      45,   false, 'both'],
            ['60 Days',                      60,   false, 'both'],
            ['90 Days',                      90,   false, 'buyer'],
            ['Against L/C',                  null, false, 'buyer'],
            ['50% Advance, 50% on Delivery', null, true,  'both'],
            ['Part Advance & Part at Sight', null, true,  'buyer'],
        ];

        foreach ($paymentTerms as [$name, $days, $hasSplit, $appliesTo]) {
            PaymentTerm::firstOrCreate(
                ['name' => $name],
                ['days' => $days, 'has_split' => $hasSplit, 'applies_to' => $appliesTo],
            );
        }

        // Col S — dropdown on Buyer master (client: no free-text typing).
        foreach (['Sea', 'Air', 'Air + Sea', 'Courier', 'Land', 'Road', 'Rail'] as $method) {
            ShipmentMethod::firstOrCreate(['name' => $method]);
        }
    }
}
