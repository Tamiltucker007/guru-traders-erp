<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExtractDocumentRequest;
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
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One checklist row's upload / mark-done / reset / OCR — reached from the
 * Export Document show page's checklist grid. Gated under export-document.edit:
 * recording a checklist row is an edit of the Export Document it belongs to,
 * not a module of its own.
 */
class ExportDocumentChecklistController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ExportDocumentService $documents,
        private readonly GeminiDocumentExtractor $ocr,
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
            new Middleware('permission:export-document.edit', only: ['update', 'reset', 'extract']),
            new Middleware('permission:export-document.view|purchase-bill.view|packing.view', only: ['file']),
        ];
    }

    public function update(Request $request, ExportDocument $document, ExportDocumentChecklist $checklist): RedirectResponse
    {
        abort_unless($checklist->export_document_id === $document->id, 404);

        $checklist->loadMissing('type');
        $isInsurance = $checklist->type?->code === 'insurance';

        $rules = [
            'file'                 => ['nullable', 'file', 'max:10240'],
            'mark_done'            => ['nullable', 'boolean'],
            'reference_no'         => ['nullable', 'string', 'max:120'],
            'amount'               => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'remarks'              => ['nullable', 'string', 'max:1000'],
            'matched_buyer_id'     => ['nullable', 'integer', 'exists:buyers,id'],
            'matched_supplier_id'  => ['nullable', 'integer', 'exists:suppliers,id'],
            'matched_order_confirmation_id' => ['nullable', 'integer', 'exists:order_confirmations,id'],
            'insurance_action'     => ['nullable', 'in:cancel_draft,upload_certificate'],
            'bl_number'            => ['nullable', 'string', 'max:120'],
            'bl_date'              => ['nullable', 'date'],
        ];

        if ($isInsurance && $request->string('insurance_action')->toString() === 'upload_certificate') {
            $rules['file'] = ['required', 'file', 'max:10240'];
            $rules['bl_number'] = ['required', 'string', 'max:120'];
            $rules['bl_date'] = ['required', 'date'];
        }

        if ($isInsurance && $request->string('insurance_action')->toString() === 'cancel_draft') {
            $rules['file'] = ['nullable'];
        }

        $data = $request->validate($rules);

        try {
            $this->documents->recordChecklist($checklist, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $isInsurance && ($data['insurance_action'] ?? null) === 'cancel_draft'
            ? '"Insurance Certificate" — draft cancelled.'
            : "\"{$checklist->type->name}\" updated.";

        return back()->with('success', $message);
    }

    public function reset(ExportDocument $document, ExportDocumentChecklist $checklist): RedirectResponse
    {
        abort_unless($checklist->export_document_id === $document->id, 404);

        $this->documents->resetChecklist($checklist);

        return back()->with('success', "\"{$checklist->type->name}\" reset to pending.");
    }

    /** Stream a generated/uploaded checklist file so View works without the public storage symlink. */
    public function file(ExportDocument $document, ExportDocumentChecklist $checklist): StreamedResponse
    {
        abort_unless($checklist->export_document_id === $document->id, 404);
        abort_unless($checklist->hasFile() && Storage::disk('public')->exists($checklist->file_path), 404);

        return Storage::disk('public')->response(
            $checklist->file_path,
            $checklist->original_name ?: basename($checklist->file_path)
        );
    }

    /**
     * Run Gemini OCR on an uploaded scan and return suggested checklist fields.
     * Does not save — the user reviews then clicks Save on the checklist form.
     */
    public function extract(
        ExtractDocumentRequest $request,
        ExportDocument $document,
        ExportDocumentChecklist $checklist,
    ): JsonResponse {
        abort_unless($checklist->export_document_id === $document->id, 404);

        $checklist->loadMissing('type');
        $typeCode = $checklist->type?->code;

        if ($typeCode !== $request->string('type_code')->toString()) {
            return response()->json(['message' => 'Checklist type does not match the request.'], 422);
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

        $parties = $this->parties->resolve(
            $result['buyer_name'] ?? null,
            $result['supplier_name'] ?? null,
            $document,
            is_scalar($result['fields']['invoice_no'] ?? null)
                ? trim((string) $result['fields']['invoice_no'])
                : null,
        );

        return response()->json([
            'reference_no'  => $result['reference_no'],
            'remarks'       => $result['remarks'],
            'buyer_name'    => $result['buyer_name'] ?? null,
            'supplier_name' => $result['supplier_name'] ?? null,
            'fields'        => $result['fields'],
            'parties'       => $parties,
            'order_context' => $this->orderContext->build($document, $parties),
            'verification'  => $this->verifier->compare($document, $result),
        ]);
    }
}
