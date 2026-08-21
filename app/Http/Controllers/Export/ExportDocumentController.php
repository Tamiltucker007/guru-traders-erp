<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\UpdateExportDocumentRequest;
use App\Models\Buyer;
use App\Models\CompanyProfile;
use App\Models\ExportDocument;
use App\Models\Incoterm;
use App\Models\OrderConfirmation;
use App\Models\Port;
use App\Models\ShipmentMethod;
use App\Services\Export\ExportDocumentService;
use App\Services\Export\OcrFieldVerifier;
use App\Services\Export\OcrOrderContextBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ExportDocumentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ExportDocumentService $documents,
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
            new Middleware('permission:export-document.view', only: ['index', 'show']),
            new Middleware('permission:export-document.create', only: ['raiseFromOrderConfirmation']),
            new Middleware('permission:export-document.edit', only: ['edit', 'update']),
            new Middleware('permission:export-document.delete', only: ['destroy']),
            new Middleware('permission:export-document.generate', only: ['deliveryChallanPdf', 'eInvoicePdf', 'packingListPdf', 'billOfLadingDraftPdf', 'exportInvoicePdf', 'itemSummaryPdf', 'purchaseBillsPdf', 'vgmPdf', 'bankDocsPdf', 'buyerDocsPdf']),
        ];
    }

    public function index(Request $request): View
    {
        $documents = ExportDocument::query()
            ->with(['buyer:id,company_name,display_code', 'orderConfirmation:id,oc_num', 'checklist.type'])
            ->when(
                $request->filled('buyer_id'),
                fn ($q) => $q->where('buyer_id', $request->integer('buyer_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        $ocrDashboards = [];
        foreach ($documents as $document) {
            $ocrDashboards[$document->id] = $this->verifier->documentDashboard($document);
        }

        return view('export.documents.index', [
            'documents'     => $documents,
            'ocrDashboards' => $ocrDashboards,
            'buyers'        => Buyer::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'      => ExportDocument::STATUSES,
            'filters'       => $request->only('search', 'status', 'buyer_id', 'sort', 'direction'),
        ]);
    }

    public function show(ExportDocument $document): View
    {
        $document->load([
            'orderConfirmation', 'buyer', 'currency', 'incoterm',
            'portOfLoading', 'portOfDischarge', 'shipmentMethod',
            'items' => fn ($q) => $q->with(['product', 'colours.sizes']),
            'checklist' => fn ($q) => $q->with([
                'type',
                'matchedBuyer:id,display_code,company_name',
                'matchedSupplier:id,display_code,company_name',
                'matchedOrderConfirmation:id,oc_num,buyer_ref',
            ])->orderBy('id'),
            'creator', 'updater',
        ]);

        return view('export.documents.show', [
            'document' => $document,
            'orderContext' => $this->orderContext->build($document),
        ]);
    }

    public function edit(ExportDocument $document): View
    {
        return view('export.documents.edit', [
            'document'        => $document,
            'incoterms'       => Incoterm::active()->orderBy('code')->pluck('code', 'id'),
            'ports'           => Port::active()->orderBy('name')->pluck('name', 'id'),
            'shipmentMethods' => ShipmentMethod::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(UpdateExportDocumentRequest $request, ExportDocument $document): RedirectResponse
    {
        $data = $request->validated();

        $document->update(collect($data)->except('cartons')->all());
        $this->documents->syncCartons($document, $data['cartons'] ?? []);

        return redirect()
            ->route('export.documents.show', $document)
            ->with('success', "Export Document \"{$document->doc_num}\" updated successfully.");
    }

    /**
     * Delivery Challan — sent to the customs department when goods go to the
     * docks/airport. One fixed layout, no variants.
     */
    public function deliveryChallanPdf(ExportDocument $document): Response
    {
        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();

        $pdf = Pdf::loadView('export.documents.delivery-challan', compact('document', 'company'));
        $filename = $this->documentFilename($document, 'delivery-challan');

        $this->documents->attachGeneratedFile(
            $document,
            'delivery_challan',
            null,
            $this->storeGeneratedPdf($pdf, $document, 'delivery-challan'),
            $filename
        );

        return $pdf->download($filename);
    }

    /**
     * E-Invoice — the export invoice's content, ready to submit to the
     * government e-invoice portal. The IRN/Ack No./QR code the portal
     * returns are outside this system — recorded on the checklist row's
     * reference number once the portal issues them, same as any other
     * uploaded document.
     */
    public function eInvoicePdf(ExportDocument $document): Response
    {
        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();

        $pdf = Pdf::loadView('export.documents.e-invoice', compact('document', 'company'));
        $filename = $this->documentFilename($document, 'e-invoice');

        $this->documents->attachGeneratedFile(
            $document,
            'e_invoice',
            null,
            $this->storeGeneratedPdf($pdf, $document, 'e-invoice'),
            $filename
        );

        return $pdf->download($filename);
    }

    /**
     * Packing List — three variants of the same underlying shipment data
     * (see DocumentChecklistTypeSeeder's packing_list row), one route
     * covering all three rather than three near-identical actions:
     *
     *   for-our-record-with-supplier   Format A — flat item list + supplier breakdown
     *   without-supplier-for-carton    Format B — one packing slip per carton
     *   for-export-documentation       Format C — multi-sheet customs format
     */
    public function packingListPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'for-our-record-with-supplier' => 'export.documents.packing-list-with-supplier',
            'without-supplier-for-carton'  => 'export.documents.packing-list-carton',
            'for-export-documentation'     => 'export.documents.packing-list-customs',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();

        $pdf = Pdf::loadView($views[$variant], compact('document', 'company'));
        $filename = $this->documentFilename($document, "packing-list-{$variant}");

        $this->documents->attachGeneratedFile(
            $document,
            'packing_list',
            $variant,
            $this->storeGeneratedPdf($pdf, $document, "packing-list-{$variant}"),
            $filename
        );

        return $pdf->download($filename);
    }

    /**
     * Bill of Lading (Draft) — the draft sent to the shipping line to
     * confirm before they cut the real B/L. One fixed layout, no variants;
     * includes the invoice number/date as the checklist row's own
     * description requires.
     */
    public function billOfLadingDraftPdf(ExportDocument $document): Response
    {
        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();

        $pdf = Pdf::loadView('export.documents.bill-of-lading-draft', compact('document', 'company'));
        $filename = $this->documentFilename($document, 'bl-draft');

        $this->documents->attachGeneratedFile(
            $document,
            'bl_draft',
            null,
            $this->storeGeneratedPdf($pdf, $document, 'bl-draft'),
            $filename
        );

        return $pdf->download($filename);
    }

    public function exportInvoicePdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'for-customs'      => 'For Customs',
            'for-buyer'        => 'For Buyer',
            'for-bank'         => 'For Bank',
            'for-e-way-bill'   => 'For E-way Bill',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.export-invoice', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "export-invoice-{$variant}");

        $this->documents->attachGeneratedFile($document, 'export_invoice', $variant, $this->storeGeneratedPdf($pdf, $document, "export-invoice-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    public function itemSummaryPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'raw-data-all-details'                         => 'Raw Data (All Details)',
            'split-by-description-price-band'              => 'Split by Description & Price Band',
            'split-by-description-price-band-aa-ab'        => 'Split by Description & Price Band',
            'split-by-description-price-band-aaab'         => 'Split by Description & Price Band',
            'supplier-wise-split'                          => 'Supplier-wise Split',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.item-summary', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "item-summary-{$variant}");

        $this->documents->attachGeneratedFile($document, 'item_summary', $variant, $this->storeGeneratedPdf($pdf, $document, "item-summary-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    public function purchaseBillsPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'by-carton-no-export-invoicebalance-shipment'         => 'By Carton No. & Export Invoice/Balance Shipment',
            'by-carton-no-export-invoice-balance-shipment'        => 'By Carton No. & Export Invoice/Balance Shipment',
            'by-carton-no-item-description-supplier'              => 'By Carton No., Item Description & Supplier',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.purchase-bills', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "purchase-bills-{$variant}");

        $this->documents->attachGeneratedFile($document, 'purchase_bills', $variant, $this->storeGeneratedPdf($pdf, $document, "purchase-bills-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    public function vgmPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'lcl-shipment' => 'LCL Shipment',
            'fcl-shipment' => 'FCL Shipment',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.vgm', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "vgm-{$variant}");

        $this->documents->attachGeneratedFile($document, 'vgm', $variant, $this->storeGeneratedPdf($pdf, $document, "vgm-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    public function bankDocsPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'gr-waiver'        => 'GR Waiver',
            'bill-of-exchange' => 'Bill of Exchange',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.bank-docs', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "bank-docs-{$variant}");

        $this->documents->attachGeneratedFile($document, 'bank_docs', $variant, $this->storeGeneratedPdf($pdf, $document, "bank-docs-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    public function buyerDocsPdf(ExportDocument $document, string $variant): Response
    {
        $views = [
            'gr-release-email'             => 'GR Release Email',
            'bill-of-exchange-intimation'  => 'Bill of Exchange Intimation',
        ];

        abort_unless(isset($views[$variant]), 404);

        $document = $this->loadForInvoiceDocument($document);
        $company = CompanyProfile::current();
        $variantTitle = $views[$variant];

        $pdf = Pdf::loadView('export.documents.buyer-docs', compact('document', 'company', 'variant', 'variantTitle'));
        $filename = $this->documentFilename($document, "buyer-docs-{$variant}");

        $this->documents->attachGeneratedFile($document, 'buyer_docs', $variant, $this->storeGeneratedPdf($pdf, $document, "buyer-docs-{$variant}"), $filename);

        return $pdf->download($filename);
    }

    private function loadForInvoiceDocument(ExportDocument $document): ExportDocument
    {
        return $document->load([
            'orderConfirmation', 'buyer.country', 'currency', 'incoterm',
            'portOfLoading', 'portOfDischarge', 'shipmentMethod',
            'items' => fn ($q) => $q->with(['product.category', 'product.gstRate', 'colours.sizes', 'sourceItem.supplier']),
            'cartons.lines',
        ]);
    }

    /** Same reasoning as InquiryController::documentFilename() — doc_num contains "/", invalid in a download filename. */
    private function documentFilename(ExportDocument $document, string $slug): string
    {
        return str_replace('/', '-', $document->doc_num)."-{$slug}.pdf";
    }

    private function storeGeneratedPdf(PdfDocument $pdf, ExportDocument $document, string $slug): string
    {
        $path = "export-documents/{$document->id}/{$slug}-".now()->timestamp.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    /**
     * "Raise Export Document for Selected" — reached from the OC show page,
     * same shape as OrderConfirmationController::raisePurchaseOrders().
     */
    public function raiseFromOrderConfirmation(Request $request, OrderConfirmation $orderConfirmation): RedirectResponse
    {
        if ($orderConfirmation->status !== 'confirmed') {
            return back()->with('warning', 'The buyer has not confirmed this OC yet — mark it Confirmed before raising an Export Document.');
        }

        $itemIds = array_map('intval', (array) $request->input('item_ids', []));

        if (empty($itemIds)) {
            return back()->with('warning', 'Select at least one item to raise an Export Document.');
        }

        try {
            $document = $this->documents->raiseFromOrderConfirmation($orderConfirmation, $itemIds);
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('export.documents.show', $document)
            ->with('success', "Export Document \"{$document->doc_num}\" raised with the 26-point checklist ready to track.");
    }

    public function destroy(ExportDocument $document): RedirectResponse
    {
        $document->delete();

        return redirect()
            ->route('export.documents.index')
            ->with('success', "Export Document \"{$document->doc_num}\" deleted successfully.");
    }
}
