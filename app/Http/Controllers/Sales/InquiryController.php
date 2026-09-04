<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInquiryRequest;
use App\Http\Requests\Sales\UpdateInquiryRequest;
use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\FobValue;
use App\Models\Inquiry;
use App\Models\InquirySource;
use App\Models\Product;
use App\Models\Supplier;
use App\Exports\InquiryExport;
use App\Services\NumberSeriesService;
use App\Services\Sales\InquiryService;
use App\Services\Export\OcrOrderContextBuilder;
use App\Support\FinancialYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class InquiryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly InquiryService $inquiries,
        private readonly NumberSeriesService $numbers,
        private readonly OcrOrderContextBuilder $orderContext,
    ) {
    }

    /**
     * Per-action permissions live here rather than on the route, so a new
     * action cannot be added without also deciding what guards it.
     *
     * products() / suppliers() are deliberately absent — cascade endpoints
     * for a dropdown, guarded by `auth` alone at the route-group level, same
     * call the codebase already makes for GeoController and
     * SupplierController::agents().
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inquiry.view', only: ['index', 'show', 'pdf', 'xlsx']),
            new Middleware('permission:inquiry.create', only: ['create', 'store']),
            new Middleware('permission:inquiry.edit', only: ['edit', 'update']),
            new Middleware('permission:inquiry.delete', only: ['destroy']),
            // Reachable from either the create or the edit form.
            new Middleware('permission:inquiry.create|inquiry.edit', only: ['storeSource']),
        ];
    }

    public function index(Request $request): View
    {
        $inquiries = Inquiry::query()
            ->with(['buyer:id,company_name,display_code', 'category:id,name', 'format:id,name', 'items'])
            ->when(
                $request->filled('buyer_id'),
                fn ($q) => $q->where('buyer_id', $request->integer('buyer_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        // Counted across every inquiry, not just the current filtered page —
        // the strip answers "how is the pipeline doing overall", the table
        // below it answers "show me these ones".
        $byStatus = Inquiry::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $stats = [
            'total'         => (int) $byStatus->sum(),
            'draft'         => (int) ($byStatus['draft'] ?? 0),
            'price_working' => (int) ($byStatus['price_working'] ?? 0),
            'quote_sent'    => (int) ($byStatus['quote_sent'] ?? 0),
            // A confirmed inquiry that has since converted to an OC is still
            // "confirmed" from the pipeline's point of view — converting is
            // what confirmed inquiries are for, not a different outcome.
            'confirmed'     => (int) (($byStatus['confirmed'] ?? 0) + ($byStatus['converted_to_oc'] ?? 0)),
        ];

        return view('sales.inquiries.index', [
            'inquiries' => $inquiries,
            'buyers'    => Buyer::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'  => Inquiry::STATUSES,
            'filters'   => $request->only('search', 'status', 'buyer_id', 'sort', 'direction'),
            'stats'     => $stats,
        ]);
    }

    public function create(): View
    {
        return view('sales.inquiries.create', $this->formData());
    }

    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $inquiry = $this->inquiries->create($request->validated());

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" created successfully.");
    }

    public function show(Inquiry $inquiry): View
    {
        $inquiry->load([
            'buyer', 'category', 'format', 'agent', 'currency', 'source',
            'items' => fn ($q) => $q->with(['product', 'supplier', 'fobValue', 'colours.sizes', 'bomLines']),
            'followUps.creator',
            'creator', 'updater',
        ]);

        return view('sales.inquiries.show', [
            'inquiry' => $inquiry,
            'orderContext' => $this->orderContext->buildFromInquiry($inquiry),
        ]);
    }

    public function edit(Inquiry $inquiry): View
    {
        $inquiry->load([
            'items' => fn ($q) => $q->with(['colours.sizes', 'bomLines']),
            'followUps',
        ]);

        return view('sales.inquiries.edit', $this->formData() + ['inquiry' => $inquiry]);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->inquiries->update($inquiry, $request->validated());

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" updated successfully.");
    }

    public function pdf(Inquiry $inquiry): Response
    {
        $pdf = Pdf::loadView('sales.inquiries.pdf', ['inquiry' => $this->loadForDocument($inquiry)]);

        return $pdf->download($this->documentFilename($inquiry, 'pdf'));
    }

    public function xlsx(Inquiry $inquiry)
    {
        return Excel::download(
            new InquiryExport($this->loadForDocument($inquiry)),
            $this->documentFilename($inquiry, 'xlsx')
        );
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" deleted successfully.");
    }

    /**
     * Quick-add for the Source field. Same shape as
     * BuyerController::storeDesignation() — typing a name not already in the
     * list adds it, rather than sending the user off to a separate screen.
     */
    public function storeSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $source = InquirySource::firstOrCreate(
            ['name' => trim($data['name'])],
            ['status' => 'active']
        );

        return response()->json(['id' => $source->id, 'name' => $source->name]);
    }

    /**
     * Product options for an item row, narrowed to the inquiry's category —
     * same cascade shape as GeoController::cities().
     */
    public function products(Request $request): JsonResponse
    {
        // unit_export / unit_po travel with each option so the Inquiry (and OC)
        // item row can default Unit from Product Master — Unit Master was
        // cancelled, so Product is the only authoritative source beyond the
        // Order Format's own unit chips.
        $products = Product::active()
            ->with(['bomItems', 'incentives'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'item_group_code', 'unit_po', 'unit_export'])
            ->map(fn (Product $product) => [
                'id'          => $product->id,
                'text'        => $product->item_group_code ? "{$product->name} ({$product->item_group_code})" : $product->name,
                'unit_po'     => $product->unit_po,
                'unit_export' => $product->unit_export,
                'bom'         => $product->bomItems->map(fn ($line) => [
                    'component_name' => $line->component_name,
                    'qty'            => (float) $line->qty,
                    'unit'           => $line->unit,
                    'is_custom'      => (bool) $line->is_custom,
                    'remarks'        => $line->remarks,
                ])->values(),
                // Rate % × FOB vs Cap × PCS → lower (same as ProductIncentive::claimAmount).
                'incentives'  => $product->incentives->map(fn ($row) => [
                    'scheme'      => $row->scheme,
                    'label'       => $row->schemeLabel(),
                    'percent_1'   => (float) ($row->percent_1 ?? 0),
                    'percent_2'   => (float) ($row->percent_2 ?? 0),
                    'cap_value'   => $row->cap_value !== null ? (float) $row->cap_value : null,
                    'cap_value_2' => $row->cap_value_2 !== null ? (float) $row->cap_value_2 : null,
                ])->values(),
            ]);

        return response()->json($products);
    }

    /**
     * Supplier options for an item row, narrowed the same way as products —
     * Category::suppliers() is the same pivot the Supplier master itself uses.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::active()
            ->ofParty('supplier')
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($request->integer('category_id')))
            )
            ->orderBy('company_name')
            ->get()
            ->map(fn (Supplier $supplier) => ['id' => $supplier->id, 'text' => $supplier->label]);

        return response()->json($suppliers);
    }

    /**
     * Everything the PDF and Excel documents read: the format's own column
     * list (for which columns to draw and the Size sub-column tags), and each
     * item's colour/size breakdown.
     */
    private function loadForDocument(Inquiry $inquiry): Inquiry
    {
        return $inquiry->load([
            'buyer', 'category', 'format.columns', 'format.images', 'currency', 'source',
            'items' => fn ($q) => $q->with(['product', 'supplier', 'colours.sizes']),
        ]);
    }

    /**
     * inquiry_no reads like "INQ/2026-27/001" — readable in the UI, unsafe as
     * a filename since a slash there means "directory", not "dash".
     */
    private function documentFilename(Inquiry $inquiry, string $extension): string
    {
        return str_replace('/', '-', $inquiry->inquiry_no).'.'.$extension;
    }

    /**
     * Dropdown sources shared by the create and edit forms, plus everything
     * the JS cascades need pre-embedded as JSON: which categories belong to
     * which buyer, which formats belong to which category, and each buyer's
     * own agent/commission/currency to snapshot in when picked.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $financialYear = FinancialYear::current();

        return [
            'buyers' => Buyer::active()->orderBy('company_name')->get([
                'id', 'company_name', 'display_code', 'agent_id',
                'agent_commission_type', 'agent_commission_value', 'currency_id',
            ])->load('categories:id'),

            'categories' => Category::active()->orderBy('name')->pluck('name', 'id'),

            // Buyer-side agents, same filter BuyerController itself applies —
            // the select narrows the list, StoreInquiryRequest does not
            // re-enforce the side, same call already made there.
            'agents' => Agent::active()->ofType('buyer')->orderBy('name')->get()->pluck('label', 'id'),

            'formats' => DocumentFormat::active()->with(['units', 'columns', 'categories:id', 'images'])
                ->orderBy('name')->get(),

            'fobValues'  => FobValue::active()->orderBy('name')->pluck('name', 'id'),
            'currencies' => Currency::active()->orderBy('iso_code')->get()->pluck('label', 'id'),

            'statuses' => Inquiry::STATUSES,
            // Change request #8 — quick-add lookup, replacing the fixed list.
            'sources'  => InquirySource::active()->orderBy('name')->pluck('name', 'id'),

            'financialYear'  => $financialYear,
            'numberPreview'  => $this->numbers->preview('inquiry', $financialYear) ?? "INQ/{$financialYear}/001",
        ];
    }
}
