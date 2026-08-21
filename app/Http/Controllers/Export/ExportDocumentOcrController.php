<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExtractDocumentRequest;
use App\Models\DocumentChecklistType;
use App\Models\ExportDocument;
use App\Models\ExportDocumentChecklist;
use App\Services\Export\ExportDocumentService;
use App\Services\Export\GeminiDocumentExtractor;
use App\Services\Export\OcrFieldVerifier;
use App\Services\Export\OcrOrderContextBuilder;
use App\Services\Export\OcrPartyMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Standalone Document OCR desk — sits under Export, after Export Documents.
 * Covers Uploaded checklist types (Gemini extract → save to Export Document).
 */
class ExportDocumentOcrController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly GeminiDocumentExtractor $ocr,
        private readonly ExportDocumentService $documents,
        private readonly OcrPartyMatcher $parties,
        private readonly OcrOrderContextBuilder $orderContext,
        private readonly OcrFieldVerifier $verifier,
    ) {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:export-document.view', only: ['index']),
            new Middleware('permission:export-document.edit', only: ['extract', 'store']),
        ];
    }

    public function index(Request $request): View
    {
        $selectedDocId = $request->integer('export_document_id') ?: null;
        $typeCode = $request->string('type_code')->toString() ?: 'cha_checklist';

        if (! in_array($typeCode, GeminiDocumentExtractor::PHASE1_TYPES, true)) {
            $typeCode = 'cha_checklist';
        }

        $documents = ExportDocument::query()
            ->with('buyer:id,company_name,display_code')
            ->orderByDesc('id')
            ->get();

        $selected = $selectedDocId
            ? $documents->firstWhere('id', $selectedDocId)
            : $documents->first();

        $checklist = null;
        if ($selected) {
            $type = DocumentChecklistType::query()->where('code', $typeCode)->first();
            if ($type) {
                $checklist = ExportDocumentChecklist::query()
                    ->with([
                        'matchedBuyer:id,display_code,company_name',
                        'matchedSupplier:id,display_code,company_name',
                        'matchedOrderConfirmation:id,oc_num,buyer_ref',
                    ])
                    ->where('export_document_id', $selected->id)
                    ->where('document_checklist_type_id', $type->id)
                    ->first();
            }
        }

        return view('export.ocr.index', [
            'documents'      => $documents,
            'selected'       => $selected,
            'typeCode'       => $typeCode,
            'typeLabels'     => GeminiDocumentExtractor::phase1Labels(),
            'checklist'      => $checklist,
            'ocrConfigured'  => $this->ocr->isConfigured(),
            'upcomingTypes'  => [],
            'orderContext'   => $this->orderContext->build($selected),
        ]);
    }

    public function extract(ExtractDocumentRequest $request): JsonResponse
    {
        $typeCode = $request->string('type_code')->toString();

        if (! in_array($typeCode, GeminiDocumentExtractor::PHASE1_TYPES, true)) {
            return response()->json([
                'message' => 'Only uploaded checklist types are enabled for OCR on this desk.',
            ], 422);
        }

        if (! $this->ocr->isConfigured()) {
            return response()->json([
                'message' => 'Gemini is not configured. Add GEMINI_API_KEY to your .env file.',
            ], 503);
        }

        try {
            $result = $this->ocr->extract($request->file('file'), $typeCode);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'OCR failed unexpectedly. Try again or enter fields manually.'], 500);
        }

        $selected = null;
        if ($request->filled('export_document_id')) {
            $selected = ExportDocument::query()->find($request->integer('export_document_id'));
        }

        $parties = $this->parties->resolve(
            $result['buyer_name'] ?? null,
            $result['supplier_name'] ?? null,
            $selected,
            is_scalar($result['fields']['invoice_no'] ?? null)
                ? trim((string) $result['fields']['invoice_no'])
                : null,
        );

        // Prefer the suggested shipment when OCR matched a different ED for this order.
        $contextDoc = $selected;
        $suggestedId = $parties['suggested_export_documents'][0]['id'] ?? null;
        if ($suggestedId && (! $selected || (int) $selected->id !== (int) $suggestedId)) {
            $contextDoc = ExportDocument::query()->find($suggestedId) ?? $selected;
        }

        return response()->json($result + [
            'parties'        => $parties,
            'order_context'  => $this->orderContext->build($contextDoc, $parties),
            'verification'   => $this->verifier->compare($contextDoc, $result),
        ]);
    }

    /**
     * Save the uploaded scan + OCR fields onto the matching checklist row.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'export_document_id'   => ['required', 'integer', 'exists:export_documents,id'],
            'type_code'            => ['required', 'string', 'in:'.implode(',', GeminiDocumentExtractor::PHASE1_TYPES)],
            'file'                 => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'reference_no'         => ['nullable', 'string', 'max:120'],
            'remarks'              => ['nullable', 'string', 'max:1000'],
            'matched_buyer_id'     => ['nullable', 'integer', 'exists:buyers,id'],
            'matched_supplier_id'  => ['nullable', 'integer', 'exists:suppliers,id'],
            'matched_order_confirmation_id' => ['nullable', 'integer', 'exists:order_confirmations,id'],
            'ocr_verification_json' => ['nullable', 'string'],
            'insurance_action'     => ['nullable', 'in:upload_certificate'],
            'bl_number'            => ['nullable', 'string', 'max:120'],
            'bl_date'              => ['nullable', 'date'],
        ]);

        if ($data['type_code'] === 'insurance') {
            $request->validate([
                'bl_number' => ['required', 'string', 'max:120'],
                'bl_date'   => ['required', 'date'],
            ]);
            $data['bl_number'] = $request->string('bl_number')->toString();
            $data['bl_date'] = $request->string('bl_date')->toString();
            $data['insurance_action'] = 'upload_certificate';
        }

        $document = ExportDocument::query()->findOrFail($data['export_document_id']);
        $type = DocumentChecklistType::query()->where('code', $data['type_code'])->firstOrFail();

        $checklist = ExportDocumentChecklist::query()
            ->where('export_document_id', $document->id)
            ->where('document_checklist_type_id', $type->id)
            ->first();

        if (! $checklist) {
            $this->documents->ensureChecklist($document);
            $checklist = ExportDocumentChecklist::query()
                ->where('export_document_id', $document->id)
                ->where('document_checklist_type_id', $type->id)
                ->firstOrFail();
        }

        try {
            $verification = null;
            if (! empty($data['ocr_verification_json'])) {
                $decoded = json_decode($data['ocr_verification_json'], true);
                if (is_array($decoded)) {
                    $verification = $decoded;
                }
            }

            $this->documents->recordChecklist($checklist, [
                'file'                 => $request->file('file'),
                'mark_done'            => true,
                'reference_no'         => $data['reference_no'] ?? null,
                'remarks'              => $data['remarks'] ?? null,
                'matched_buyer_id'     => $data['matched_buyer_id'] ?? null,
                'matched_supplier_id'  => $data['matched_supplier_id'] ?? null,
                'matched_order_confirmation_id' => $data['matched_order_confirmation_id'] ?? null,
                'ocr_verification'     => $verification,
                'insurance_action'     => $data['insurance_action'] ?? null,
                'bl_number'            => $data['bl_number'] ?? null,
                'bl_date'              => $data['bl_date'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('export.ocr.index', [
                'export_document_id' => $document->id,
                'type_code'          => $data['type_code'],
            ])
            ->with('success', "\"{$type->name}\" saved from OCR for {$document->doc_num}.");
    }
}
