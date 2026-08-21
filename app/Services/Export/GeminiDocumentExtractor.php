<?php

namespace App\Services\Export;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Gemini OCR for Export Document uploads.
 *
 * Only "Uploaded" sheet rows (not Generated packing/invoice formats).
 * Enabled types are listed in PHASE1_TYPES / shown on the Document OCR desk.
 */
class GeminiDocumentExtractor
{
    /**
     * Uploaded checklist types we will enable over time.
     * The Document OCR UI only exposes PHASE1_TYPES.
     *
     * @var list<string>
     */
    public const UPLOADED_TYPES = [
        'cha_checklist',      // #4 Checklist (CHA)
        'e_sanchit_docs',     // #5
        'assessed_copy',      // #9
        'leo_copy',           // #10
        'measurement_copy',
        'clp',                // #13
        'bl_final',           // #15
        'insurance',          // #16
        'payment_received',   // #20
        'eefc_upload',        // #21
        'firc',               // #22
        'bank_certificate',   // #23
        'ebrc',
    ];

    /**
     * Currently live in the Document OCR screen (all uploaded sheet rows).
     *
     * @var list<string>
     */
    public const PHASE1_TYPES = self::UPLOADED_TYPES;

    /**
     * @deprecated Use PHASE1_TYPES / UPLOADED_TYPES — kept for older checklist OCR route.
     *
     * @var list<string>
     */
    public const SUPPORTED_TYPES = self::UPLOADED_TYPES;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function supports(string $typeCode): bool
    {
        return in_array($typeCode, self::UPLOADED_TYPES, true);
    }

    public function isPhase1(string $typeCode): bool
    {
        return in_array($typeCode, self::PHASE1_TYPES, true);
    }

    /**
     * Human labels for the OCR document-type picker.
     *
     * @return array<string, string>
     */
    public static function phase1Labels(): array
    {
        return [
            'cha_checklist'     => 'Checklist (from CHA)',
            'e_sanchit_docs'    => 'E-Sanchit Documents',
            'assessed_copy'     => 'Assessed Copy',
            'leo_copy'          => 'LEO Copy',
            'measurement_copy'  => 'Measurement Copy',
            'clp'               => 'CLP',
            'bl_final'          => 'Bill of Lading (Final)',
            'insurance'         => 'Insurance Certificate',
            'payment_received'  => 'Payment Received (Swift)',
            'eefc_upload'       => 'Payment Proof to Bank (EEFC)',
            'firc'              => 'FIRC',
            'bank_certificate'  => 'Bank Certificate',
            'ebrc'              => 'eBRC',
        ];
    }

    /**
     * @return array{
     *     reference_no: ?string,
     *     remarks: ?string,
     *     buyer_name: ?string,
     *     supplier_name: ?string,
     *     fields: array<string, mixed>
     * }
     */
    public function extract(UploadedFile $file, string $typeCode): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gemini is not configured. Set GEMINI_API_KEY in .env.');
        }

        if (! $this->supports($typeCode)) {
            throw new RuntimeException("OCR is not available for checklist type \"{$typeCode}\".");
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Upload a PDF or image (JPEG, PNG, WebP, GIF).');
        }

        $schema = $this->schemaFor($typeCode);
        $prompt = $this->promptFor($typeCode, $schema);

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $key = config('services.gemini.key');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        try {
            $http = Http::timeout(60)->withQueryParameters(['key' => $key]);

            if (config('services.gemini.verify_ssl') === false) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mime,
                                'data'      => base64_encode($file->get()),
                            ],
                        ],
                    ],
                ]],
                'generationConfig' => [
                    'temperature'      => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Gemini OCR failed: '.$this->safeErrorMessage($e->getMessage()), 0, $e);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();

            throw new RuntimeException('Gemini OCR failed: '.$this->safeErrorMessage((string) $message));
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $fields = $this->parseJsonObject($text);

        return $this->mapToChecklistFields($typeCode, $fields);
    }

    /**
     * @return array<string, string>
     */
    private function schemaFor(string $typeCode): array
    {
        return match ($typeCode) {
            // Sheet #4 — CHA checklist after docs are filed on the customs site.
            'cha_checklist' => [
                'checklist_no'              => 'CHA checklist / job / reference number',
                'checklist_date'            => 'Checklist or filing date as YYYY-MM-DD',
                'shipping_bill_no'          => 'Shipping bill number if printed',
                'invoice_no'                => 'Export invoice number if printed',
                'cha_name'                  => 'Clearing house agent / CHA name if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee / notify party name if printed',
                'buyer_address'             => 'Buyer / consignee address if printed',
                'supplier_or_shipper_name'  => 'Supplier / manufacturer / shipper name if printed (not CHA, not the Indian exporter)',
                'currency'                  => 'Currency code if printed (e.g. USD, AED, INR)',
                'total_amount'              => 'Invoice / shipment total amount as a number if printed',
                'quantity'                  => 'Total quantity / pieces if printed',
                'unit_price'                => 'Unit price if printed',
                'hs_code'                   => 'HS / HSN code if printed',
                'status_or_remarks'         => 'Any status line or short note on the checklist',
            ],
            // Sheet #5 — signed letterhead pack filed on ICEGATE e-Sanchit.
            'e_sanchit_docs' => [
                'ack_or_ref_no'             => 'E-Sanchit acknowledgement / IRN / upload reference if printed',
                'document_date'             => 'Document or upload date as YYYY-MM-DD',
                'invoice_no'                => 'Export invoice number if printed',
                'packing_list_ref'          => 'Packing list number or reference if printed',
                'shipping_bill_no'          => 'Shipping bill number if printed',
                'exporter_name'             => 'Exporter / shipper name if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee / notify party name if printed',
                'buyer_address'             => 'Buyer / consignee address if printed',
                'supplier_or_shipper_name'  => 'Supplier / manufacturer name if printed (not the Indian exporter)',
                'currency'                  => 'Currency code if printed (e.g. USD, AED, INR)',
                'total_amount'              => 'Invoice total amount as a number if printed',
                'quantity'                  => 'Total quantity / pieces if printed',
                'unit_price'                => 'Unit price if printed',
                'hs_code'                   => 'HS / HSN code if printed',
                'status_or_remarks'         => 'Any status line or short note on the document',
            ],
            'leo_copy' => [
                'leo_number'                => 'LEO / Let Export Order number (e.g. 15/274)',
                'leo_date'                  => 'LEO date as YYYY-MM-DD',
                'shipping_bill_no'          => 'Shipping bill number (SB No) if printed',
                'invoice_no'                => 'Export invoice number if printed',
                'port_of_loading'           => 'Port of loading if printed (e.g. Nhava Sheva / INNSA1)',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee name if printed',
                'supplier_or_shipper_name'  => 'Supplier / manufacturer name if printed',
                'status_or_remarks'         => 'Any status such as LET EXPORT / LEO granted / cleared for export',
            ],
            'assessed_copy' => [
                'assessed_ref_no'           => 'Assessed / ICEGATE tracking id if printed (e.g. SB2108…), else shipping bill number',
                'assessed_date'             => 'Assessment or shipping bill date as YYYY-MM-DD',
                'shipping_bill_no'          => 'Shipping bill number (SB No) if printed',
                'invoice_no'                => 'Export invoice number if printed (e.g. EXP…)',
                'examiner_or_office'        => 'Customs office / port name if printed (e.g. JNCH Nhava Sheva)',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee name if printed',
                'buyer_address'             => 'Buyer / consignee address if printed',
                'supplier_or_shipper_name'  => 'Supplier / manufacturer name if printed',
                'currency'                  => 'Currency code if printed (e.g. USD, AED, INR)',
                'total_amount'              => 'FOB / invoice / assessable value as a number if printed',
                'quantity'                  => 'Total quantity / pieces if printed',
                'unit_price'                => 'Unit price if printed',
                'hs_code'                   => 'HS / HSN / CTH code if printed',
                'status_or_remarks'         => 'Any status such as Assessed Copy / Passed for stuffing / Examined',
            ],
            'bl_final' => [
                'bl_number'                 => 'Bill of Lading number',
                'bl_date'                   => 'B/L date as YYYY-MM-DD',
                'vessel_or_voyage'          => 'Vessel / voyage if printed',
                'container_no'              => 'Container number if printed',
                'port_of_loading'           => 'Port of loading if printed',
                'port_of_discharge'         => 'Port of discharge if printed',
                'buyer_or_consignee_name'   => 'Consignee / notify party / overseas buyer name if printed',
                'supplier_or_shipper_name'  => 'Shipper / supplier name if printed (when distinct from the Indian exporter)',
                'status_or_remarks'         => 'Any status line or short note on the B/L',
            ],
            'clp' => [
                'clp_ref_no'                => 'CLP / container load plan reference if printed',
                'document_date'             => 'Document date as YYYY-MM-DD',
                'container_no'              => 'Container number if printed',
                'seal_no'                   => 'Seal number if printed',
                'shipping_bill_no'          => 'Shipping bill number if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee name if printed',
                'supplier_or_shipper_name'  => 'Supplier / shipper name if printed',
                'status_or_remarks'         => 'Any status line or short note on the CLP',
            ],
            'measurement_copy' => [
                'measurement_ref'           => 'Certificate of Measurement number (e.g. 21649)',
                'document_date'             => 'Dock / measurement date as YYYY-MM-DD',
                'shipping_bill_no'          => 'Ship bill / shipping bill number if printed',
                'packages'                  => 'Number of packages / cartons if printed',
                'cbm_or_volume'             => 'Total CBM / M3 volume if printed (e.g. 3.536)',
                'dimensions'                => 'Carton dimensions LxWxH in cm if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / consignee name if printed',
                'supplier_or_shipper_name'  => 'Supplier / shipper name if printed',
                'status_or_remarks'         => 'Shipper / measurer / any short note',
            ],
            'insurance' => [
                'policy_or_cert_no'         => 'Insurance certificate / policy number (e.g. C003114995)',
                'document_date'             => 'Certificate issue date as YYYY-MM-DD',
                'bl_number'                 => 'Bill of lading / BL number if printed',
                'bl_date'                   => 'Bill of lading date as YYYY-MM-DD if printed; if only one date appears on the certificate, use that same date here',
                'insured_amount'            => 'Amount insured with currency if printed',
                'insurer_name'              => 'Insurance company name if printed (e.g. Tata AIG)',
                'buyer_or_consignee_name'   => 'Assured / overseas buyer / consignee name if printed',
                'supplier_or_shipper_name'  => 'Supplier / shipper name if printed',
                'status_or_remarks'         => 'Invoice no / conveyance / any short note',
            ],
            'payment_received' => [
                'swift_or_ref_no'           => 'Swift / UTR / payment reference number',
                'payment_date'              => 'Payment date as YYYY-MM-DD',
                'amount'                    => 'Payment amount with currency if printed',
                'payer_or_bank'             => 'Remitting bank name if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / remitter / ordering customer name if printed',
                'supplier_or_shipper_name'  => 'Supplier name if printed (usually null on Swift)',
                'invoice_no'                => 'Related invoice number if printed',
                'status_or_remarks'         => 'Any status line or short note',
            ],
            'eefc_upload' => [
                'eefc_ref_no'               => 'EEFC / bank payment proof reference',
                'document_date'             => 'Document date as YYYY-MM-DD',
                'amount'                    => 'Amount credited if printed',
                'bank_name'                 => 'Bank name if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer name if printed',
                'supplier_or_shipper_name'  => 'Supplier name if printed',
                'status_or_remarks'         => 'Any status line or short note',
            ],
            'firc' => [
                'firc_no'                   => 'FIRC number',
                'document_date'             => 'FIRC date as YYYY-MM-DD',
                'amount'                    => 'FIRC amount with currency if printed',
                'bank_name'                 => 'Issuing bank if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer / remitter name if printed',
                'supplier_or_shipper_name'  => 'Supplier name if printed',
                'status_or_remarks'         => 'Any status line or short note',
            ],
            'bank_certificate' => [
                'certificate_no'            => 'Bank certificate number',
                'document_date'             => 'Certificate date as YYYY-MM-DD',
                'amount'                    => 'Amount if printed',
                'bank_name'                 => 'Issuing bank if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer name if printed',
                'supplier_or_shipper_name'  => 'Supplier name if printed',
                'status_or_remarks'         => 'Any status line or short note',
            ],
            'ebrc' => [
                'ebrc_no'                   => 'eBRC number',
                'document_date'             => 'eBRC date as YYYY-MM-DD',
                'shipping_bill_no'          => 'Shipping bill number if printed',
                'invoice_no'                => 'Invoice number if printed',
                'amount'                    => 'Realised amount if printed',
                'buyer_or_consignee_name'   => 'Overseas buyer name if printed',
                'supplier_or_shipper_name'  => 'Supplier name if printed',
                'status_or_remarks'         => 'Any status line or short note',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $schema
     */
    private function promptFor(string $typeCode, array $schema): string
    {
        $keys = collect($schema)
            ->map(fn (string $desc, string $key) => "- {$key}: {$desc}")
            ->implode("\n");

        $context = match ($typeCode) {
            'cha_checklist' => 'This is an Indian export CHA checklist / customs filing checklist received from the Clearing House Agent after documents were uploaded to the customs website.',
            'e_sanchit_docs' => 'This is an Indian ICEGATE e-Sanchit document pack (export invoice / packing list / declaration) on the exporter letterhead, signed and stamped for customs upload.',
            'assessed_copy' => 'This is an Indian Customs EDI / ICEGATE Assessed Copy (Shipping Bill Summary), watermarked ASSESSED COPY, after goods are assessed / passed for stuffing.',
            'leo_copy' => 'This is an Indian Customs EDI / ICEGATE LET EXPORT (LEO) copy — the final Let Export Order, showing LEO number and date.',
            'clp' => 'This is a Container Load Plan (CLP) received from the CHA for an export shipment.',
            'measurement_copy' => 'This is a Certificate of Measurement / measurement copy (volume CBM of cartons) for an export shipment, often from a measurement company like NMMC.',
            'bl_final' => 'This is a final ocean Bill of Lading (B/L) for an export shipment.',
            'insurance' => 'This is a marine cargo insurance certificate / policy for an export shipment.',
            'payment_received' => 'This is a Swift / payment advice showing foreign inward remittance from the buyer.',
            'eefc_upload' => 'This is bank proof of payment release / credit into an EEFC account.',
            'firc' => 'This is a Foreign Inward Remittance Certificate (FIRC) issued by a bank.',
            'bank_certificate' => 'This is a bank certificate related to export proceeds realisation.',
            'ebrc' => 'This is an electronic Bank Realisation Certificate (eBRC) from the RBI / DGFT ecosystem.',
            default => 'This is an export-shipping document scan for a garment exporter.',
        };

        return <<<PROMPT
{$context}
Checklist type code: {$typeCode}.

Extract these fields. Return ONLY a JSON object with exactly these keys.
Use null when a value is not clearly present on the document.
Do not invent values.

{$keys}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonObject(string $text): array
    {
        $text = trim($text);

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $text = $matches[0];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned a response that was not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{
     *     reference_no: ?string,
     *     remarks: ?string,
     *     buyer_name: ?string,
     *     supplier_name: ?string,
     *     fields: array<string, mixed>
     * }
     */
    private function mapToChecklistFields(string $typeCode, array $fields): array
    {
        $nullIfBlank = static function (mixed $value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            return is_scalar($value) ? trim((string) $value) : null;
        };

        $reference = null;
        $remarksParts = [];
        $buyerName = $nullIfBlank($fields['buyer_or_consignee_name'] ?? null);
        $supplierName = $nullIfBlank($fields['supplier_or_shipper_name'] ?? null);

        switch ($typeCode) {
            case 'cha_checklist':
                $reference = $nullIfBlank($fields['checklist_no'] ?? null)
                    ?? $nullIfBlank($fields['shipping_bill_no'] ?? null)
                    ?? $nullIfBlank($fields['invoice_no'] ?? null);

                if ($date = $nullIfBlank($fields['checklist_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($cha = $nullIfBlank($fields['cha_name'] ?? null)) {
                    $remarksParts[] = 'CHA: '.$cha;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'e_sanchit_docs':
                $reference = $nullIfBlank($fields['ack_or_ref_no'] ?? null)
                    ?? $nullIfBlank($fields['invoice_no'] ?? null)
                    ?? $nullIfBlank($fields['packing_list_ref'] ?? null);

                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($pl = $nullIfBlank($fields['packing_list_ref'] ?? null)) {
                    $remarksParts[] = 'PL: '.$pl;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($exporter = $nullIfBlank($fields['exporter_name'] ?? null)) {
                    $remarksParts[] = 'Exporter: '.$exporter;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'bl_final':
                $reference = $nullIfBlank($fields['bl_number'] ?? null);
                if ($date = $nullIfBlank($fields['bl_date'] ?? null)) {
                    $remarksParts[] = 'B/L date: '.$date;
                }
                if ($vessel = $nullIfBlank($fields['vessel_or_voyage'] ?? null)) {
                    $remarksParts[] = 'Vessel: '.$vessel;
                }
                if ($container = $nullIfBlank($fields['container_no'] ?? null)) {
                    $remarksParts[] = 'Container: '.$container;
                }
                if ($pol = $nullIfBlank($fields['port_of_loading'] ?? null)) {
                    $remarksParts[] = 'POL: '.$pol;
                }
                if ($pod = $nullIfBlank($fields['port_of_discharge'] ?? null)) {
                    $remarksParts[] = 'POD: '.$pod;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'clp':
                $reference = $nullIfBlank($fields['clp_ref_no'] ?? null)
                    ?? $nullIfBlank($fields['container_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($container = $nullIfBlank($fields['container_no'] ?? null)) {
                    $remarksParts[] = 'Container: '.$container;
                }
                if ($seal = $nullIfBlank($fields['seal_no'] ?? null)) {
                    $remarksParts[] = 'Seal: '.$seal;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'measurement_copy':
                $reference = $nullIfBlank($fields['measurement_ref'] ?? null)
                    ?? $nullIfBlank($fields['shipping_bill_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($pkgs = $nullIfBlank($fields['packages'] ?? null)) {
                    $remarksParts[] = 'Pkgs: '.$pkgs;
                }
                if ($cbm = $nullIfBlank($fields['cbm_or_volume'] ?? null)) {
                    $remarksParts[] = 'CBM: '.$cbm;
                }
                if ($dims = $nullIfBlank($fields['dimensions'] ?? null)) {
                    $remarksParts[] = 'Dims: '.$dims;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'insurance':
                $reference = $nullIfBlank($fields['policy_or_cert_no'] ?? null)
                    ?? $nullIfBlank($fields['bl_number'] ?? null);
                // Certificates often print one date only — use it as B/L date when
                // a separate B/L date line is missing (fills the #16 form field).
                if (! $nullIfBlank($fields['bl_date'] ?? null) && ($docDate = $nullIfBlank($fields['document_date'] ?? null))) {
                    $fields['bl_date'] = $docDate;
                }
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($bl = $nullIfBlank($fields['bl_number'] ?? null)) {
                    $remarksParts[] = 'B/L: '.$bl;
                }
                if ($blDate = $nullIfBlank($fields['bl_date'] ?? null)) {
                    $remarksParts[] = 'B/L date: '.$blDate;
                }
                if ($amt = $nullIfBlank($fields['insured_amount'] ?? null)) {
                    $remarksParts[] = 'Insured: '.$amt;
                }
                if ($insurer = $nullIfBlank($fields['insurer_name'] ?? null)) {
                    $remarksParts[] = 'Insurer: '.$insurer;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'payment_received':
                $reference = $nullIfBlank($fields['swift_or_ref_no'] ?? null);
                if ($date = $nullIfBlank($fields['payment_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($amt = $nullIfBlank($fields['amount'] ?? null)) {
                    $remarksParts[] = 'Amount: '.$amt;
                }
                if ($payer = $nullIfBlank($fields['payer_or_bank'] ?? null)) {
                    $remarksParts[] = 'From: '.$payer;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'eefc_upload':
                $reference = $nullIfBlank($fields['eefc_ref_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($amt = $nullIfBlank($fields['amount'] ?? null)) {
                    $remarksParts[] = 'Amount: '.$amt;
                }
                if ($bank = $nullIfBlank($fields['bank_name'] ?? null)) {
                    $remarksParts[] = 'Bank: '.$bank;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'firc':
                $reference = $nullIfBlank($fields['firc_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($amt = $nullIfBlank($fields['amount'] ?? null)) {
                    $remarksParts[] = 'Amount: '.$amt;
                }
                if ($bank = $nullIfBlank($fields['bank_name'] ?? null)) {
                    $remarksParts[] = 'Bank: '.$bank;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'bank_certificate':
                $reference = $nullIfBlank($fields['certificate_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($amt = $nullIfBlank($fields['amount'] ?? null)) {
                    $remarksParts[] = 'Amount: '.$amt;
                }
                if ($bank = $nullIfBlank($fields['bank_name'] ?? null)) {
                    $remarksParts[] = 'Bank: '.$bank;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'ebrc':
                $reference = $nullIfBlank($fields['ebrc_no'] ?? null)
                    ?? $nullIfBlank($fields['shipping_bill_no'] ?? null);
                if ($date = $nullIfBlank($fields['document_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($amt = $nullIfBlank($fields['amount'] ?? null)) {
                    $remarksParts[] = 'Amount: '.$amt;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'leo_copy':
                $reference = $nullIfBlank($fields['leo_number'] ?? null)
                    ?? $nullIfBlank($fields['shipping_bill_no'] ?? null);

                if ($date = $nullIfBlank($fields['leo_date'] ?? null)) {
                    $remarksParts[] = 'LEO date: '.$date;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($port = $nullIfBlank($fields['port_of_loading'] ?? null)) {
                    $remarksParts[] = 'Port: '.$port;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            case 'assessed_copy':
                $reference = $nullIfBlank($fields['assessed_ref_no'] ?? null)
                    ?? $nullIfBlank($fields['shipping_bill_no'] ?? null)
                    ?? $nullIfBlank($fields['invoice_no'] ?? null);

                if ($date = $nullIfBlank($fields['assessed_date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                if ($sb = $nullIfBlank($fields['shipping_bill_no'] ?? null)) {
                    $remarksParts[] = 'SB: '.$sb;
                }
                if ($inv = $nullIfBlank($fields['invoice_no'] ?? null)) {
                    $remarksParts[] = 'Invoice: '.$inv;
                }
                if ($office = $nullIfBlank($fields['examiner_or_office'] ?? null)) {
                    $remarksParts[] = 'Office: '.$office;
                }
                if ($note = $nullIfBlank($fields['status_or_remarks'] ?? null)) {
                    $remarksParts[] = $note;
                }
                break;

            default:
                $reference = $nullIfBlank($fields['reference_no'] ?? null);
                if ($date = $nullIfBlank($fields['date'] ?? null)) {
                    $remarksParts[] = 'Date: '.$date;
                }
                break;
        }

        if ($buyerName) {
            $remarksParts[] = 'Buyer: '.$buyerName;
        }
        if ($supplierName) {
            $remarksParts[] = 'Supplier: '.$supplierName;
        }

        return [
            'reference_no'   => $reference,
            'remarks'        => $remarksParts === [] ? null : implode(' · ', $remarksParts),
            'buyer_name'     => $buyerName,
            'supplier_name'  => $supplierName,
            'fields'         => $fields,
        ];
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/([?&]key=)[^&\s]+/i', '$1***', $message) ?? $message;
        $message = preg_replace('/AIza[0-9A-Za-z_-]+/', '***', $message) ?? $message;
        $message = preg_replace('/AQ\.[0-9A-Za-z_-]+/', '***', $message) ?? $message;

        return $message;
    }
}
