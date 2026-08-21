<?php

use App\Http\Controllers\Masters\BuyerController;
use App\Http\Controllers\Masters\AgentController;
use App\Http\Controllers\Masters\CategoryController;
use App\Http\Controllers\Masters\DocumentFormatController;
use App\Http\Controllers\Masters\FobValueController;
use App\Http\Controllers\Masters\GeoController;
use App\Http\Controllers\Masters\JobberController;
use App\Http\Controllers\Masters\MarkupController;
use App\Http\Controllers\Masters\ProductController;
use App\Http\Controllers\Masters\SupplierController;
use App\Http\Controllers\Administration\CompanyProfileController;
use App\Http\Controllers\Export\ExportDocumentChecklistController;
use App\Http\Controllers\Export\ExportDocumentController;
use App\Http\Controllers\Export\ExportDocumentOcrController;
use App\Http\Controllers\Export\PackingController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Procurement\InwardEntryController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Sales\InquiryController;
use App\Http\Controllers\Sales\OrderConfirmationController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\UserManagement\RoleController;
use App\Http\Controllers\UserManagement\UserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Masters
    |--------------------------------------------------------------------------
    | Built in dependency order — Category has no parent, Product needs it.
    | Agent, Buyer, Supplier, Jobber, PO Format and Markup follow.
    |
    | As with User Management, per-action permissions are declared inside each
    | controller's middleware() method, not here.
    */
    Route::prefix('masters')->name('masters.')->group(function () {

        /*
         * Cascading Country -> State -> City dropdowns. Shared reference data,
         * so these are guarded by `auth` alone rather than a module permission
         * — see GeoController.
         */
        Route::get('geo/states', [GeoController::class, 'states'])->name('geo.states');
        Route::get('geo/cities', [GeoController::class, 'cities'])->name('geo.cities');

        Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])
            ->name('categories.toggle-status');
        Route::resource('categories', CategoryController::class);

        // Declared before the resource so "check-code" is not swallowed by
        // products/{product}.
        Route::get('products/check-code', [ProductController::class, 'checkCode'])
            ->name('products.check-code');
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
            ->name('products.toggle-status');
        // Quick-add from the GST % field on the Product form itself — sheet
        // col K: "different rates with option to add in the future".
        Route::post('products/gst-rates', [ProductController::class, 'storeGstRate'])
            ->name('products.gst-rates.store');
        Route::resource('products', ProductController::class);

        // Same ordering rule as products — before the resource, or
        // buyers/{buyer} matches these fixed segments first.
        Route::patch('buyers/{buyer}/toggle-status', [BuyerController::class, 'toggleStatus'])
            ->name('buyers.toggle-status');
        // Quick-add from the Payment Terms field on the Buyer form itself —
        // "add more in the future" on the sheet, without a separate screen.
        Route::post('buyers/payment-terms', [BuyerController::class, 'storePaymentTerm'])
            ->name('buyers.payment-terms.store');
        // Same quick-add, for the contact's Designation field.
        Route::post('buyers/designations', [BuyerController::class, 'storeDesignation'])
            ->name('buyers.designations.store');
        Route::resource('buyers', BuyerController::class);

        /*
         * Same ordering rule again. "agents" here is the col X dropdown source
         * filtered by party type — it is a cascade endpoint on the Supplier
         * form, not the Agent master, which is masters.agents.* below.
         */
        Route::get('suppliers/check-code', [SupplierController::class, 'checkCode'])
            ->name('suppliers.check-code');
        Route::get('suppliers/agents', [SupplierController::class, 'agents'])
            ->name('suppliers.agents');
        // Quick-add for the Supplier/Jobber Type field — same shape as the
        // Buyer form's storePaymentTerm()/storeDesignation(). Shared by both
        // the Supplier and Jobber screens, which read the same SupplierType
        // lookup, so there is one route rather than two identical ones.
        Route::post('suppliers/supplier-types', [SupplierController::class, 'storeSupplierType'])
            ->name('suppliers.supplier-types.store');
        Route::patch('suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])
            ->name('suppliers.toggle-status');
        Route::resource('suppliers', SupplierController::class);

        Route::get('jobbers/check-code', [JobberController::class, 'checkCode'])
            ->name('jobbers.check-code');
        Route::get('jobbers/agents', [JobberController::class, 'agents'])
            ->name('jobbers.agents');
        Route::patch('jobbers/{jobber}/toggle-status', [JobberController::class, 'toggleStatus'])
            ->name('jobbers.toggle-status');
        Route::resource('jobbers', JobberController::class)->parameters(['jobbers' => 'jobber']);

        Route::get('agents/check-code', [AgentController::class, 'checkCode'])
            ->name('agents.check-code');
        Route::patch('agents/{agent}/toggle-status', [AgentController::class, 'toggleStatus'])
            ->name('agents.toggle-status');
        Route::resource('agents', AgentController::class);

        Route::patch('fob-values/{fobValue}/toggle-status', [FobValueController::class, 'toggleStatus'])
            ->name('fob-values.toggle-status');
        Route::resource('fob-values', FobValueController::class);

        /*
         * Order Formats. Bound as {format} rather than {documentFormat} — the
         * client calls these order formats, and the URL is the one place that
         * name is visible to them.
         */
        Route::patch('formats/{format}/toggle-status', [DocumentFormatController::class, 'toggleStatus'])
            ->name('formats.toggle-status');
        Route::resource('formats', DocumentFormatController::class)
            ->parameters(['formats' => 'format']);

        // Before the resource, or markups/{markup} matches these fixed segments.
        Route::get('markups/supplier-discount', [MarkupController::class, 'supplierDiscount'])
            ->name('markups.supplier-discount');
        Route::get('markups/supplier-agent-commission', [MarkupController::class, 'supplierAgentCommission'])
            ->name('markups.supplier-agent-commission');
        Route::get('markups/buyer-agent-commission', [MarkupController::class, 'buyerAgentCommission'])
            ->name('markups.buyer-agent-commission');
        Route::patch('markups/{markup}/toggle-status', [MarkupController::class, 'toggleStatus'])
            ->name('markups.toggle-status');
        Route::resource('markups', MarkupController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    | The buyer side: an inquiry is costed against an Order Format (Masters),
    | confirmed lines convert to an Order Confirmation, and confirmed OC
    | lines are raised onto a Purchase Order (Procurement, below) — one PO
    | per supplier.
    */
    Route::prefix('sales')->name('sales.')->group(function () {

        // Cascade endpoints for the item-row dropdowns, narrowed by category.
        // Guarded by auth alone, same call already made for GeoController and
        // SupplierController::agents() — shared reference data, not a module
        // permission of its own. Shared by the Inquiry, OC and PO forms —
        // one source, so a form doesn't have to guess which module's route
        // to call.
        Route::get('inquiries/products', [InquiryController::class, 'products'])
            ->name('inquiries.products');
        Route::get('inquiries/suppliers', [InquiryController::class, 'suppliers'])
            ->name('inquiries.suppliers');
        // Quick-add for the Source field — same shape as the Buyer form's
        // designations/payment-terms quick-add routes.
        Route::post('inquiries/sources', [InquiryController::class, 'storeSource'])
            ->name('inquiries.sources.store');

        // Points at OrderConfirmationController, not InquiryController — the
        // route name stays sales.inquiries.convert-to-oc because it's reached
        // from the Inquiry screen, but converting an inquiry now creates a
        // real OC rather than only flipping item status.
        Route::post('inquiries/{inquiry}/convert-to-oc', [OrderConfirmationController::class, 'convertFromInquiry'])
            ->name('inquiries.convert-to-oc');

        Route::get('inquiries/{inquiry}/pdf', [InquiryController::class, 'pdf'])
            ->name('inquiries.pdf');
        Route::get('inquiries/{inquiry}/xlsx', [InquiryController::class, 'xlsx'])
            ->name('inquiries.xlsx');

        Route::resource('inquiries', InquiryController::class);

        Route::post('order-confirmations/{orderConfirmation}/raise-purchase-orders', [OrderConfirmationController::class, 'raisePurchaseOrders'])
            ->name('order-confirmations.raise-purchase-orders');
        Route::post('order-confirmations/{orderConfirmation}/raise-export-document', [ExportDocumentController::class, 'raiseFromOrderConfirmation'])
            ->name('order-confirmations.raise-export-document');
        Route::resource('order-confirmations', OrderConfirmationController::class)
            ->parameters(['order-confirmations' => 'orderConfirmation']);
    });

    /*
    |--------------------------------------------------------------------------
    | Procurement
    |--------------------------------------------------------------------------
    | The supplier side: a Purchase Order is raised against a confirmed OC's
    | items, one PO per supplier.
    */
    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchaseOrder']);

        Route::get('inward-entries/po-details/{purchaseOrder}', [InwardEntryController::class, 'poDetails'])
            ->name('inward-entries.po-details');
        Route::post('inward-entries/{inwardEntry}/approve', [InwardEntryController::class, 'approve'])
            ->name('inward-entries.approve');
        Route::resource('inward-entries', InwardEntryController::class)
            ->parameters(['inward-entries' => 'inwardEntry']);
    });

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    | "We pack, we ship the paperwork" — the checklist tracker for one
    | Export Document, raised against a confirmed OC from the Sales group
    | above (see order-confirmations.raise-export-document).
    */
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('packing', [PackingController::class, 'index'])
            ->middleware('permission:packing.view')
            ->name('packing.index');
        Route::get('packing/{document}', [PackingController::class, 'show'])
            ->middleware('permission:packing.view')
            ->name('packing.show');

        Route::get('ocr', [ExportDocumentOcrController::class, 'index'])->name('ocr.index');
        Route::post('ocr/extract', [ExportDocumentOcrController::class, 'extract'])->name('ocr.extract');
        Route::post('ocr', [ExportDocumentOcrController::class, 'store'])->name('ocr.store');

        Route::post('documents/{document}/checklist/{checklist}', [ExportDocumentChecklistController::class, 'update'])
            ->name('documents.checklist.update');
        Route::post('documents/{document}/checklist/{checklist}/ocr', [ExportDocumentChecklistController::class, 'extract'])
            ->name('documents.checklist.ocr');
        Route::delete('documents/{document}/checklist/{checklist}', [ExportDocumentChecklistController::class, 'reset'])
            ->name('documents.checklist.reset');
        Route::get('documents/{document}/checklist/{checklist}/file', [ExportDocumentChecklistController::class, 'file'])
            ->name('documents.checklist.file');

        Route::get('documents/{document}/delivery-challan', [ExportDocumentController::class, 'deliveryChallanPdf'])
            ->name('documents.delivery-challan');
        Route::get('documents/{document}/e-invoice', [ExportDocumentController::class, 'eInvoicePdf'])
            ->name('documents.e-invoice');
        // One route for all three Packing List formats — {variant} is the
        // checklist row's own variant_code (see DocumentChecklistTypeSeeder).
        Route::get('documents/{document}/packing-list/{variant}', [ExportDocumentController::class, 'packingListPdf'])
            ->name('documents.packing-list');
        Route::get('documents/{document}/bill-of-lading-draft', [ExportDocumentController::class, 'billOfLadingDraftPdf'])
            ->name('documents.bl-draft');
        Route::get('documents/{document}/export-invoice/{variant}', [ExportDocumentController::class, 'exportInvoicePdf'])
            ->name('documents.export-invoice');
        Route::get('documents/{document}/item-summary/{variant}', [ExportDocumentController::class, 'itemSummaryPdf'])
            ->name('documents.item-summary');
        Route::get('documents/{document}/purchase-bills/{variant}', [ExportDocumentController::class, 'purchaseBillsPdf'])
            ->name('documents.purchase-bills');
        Route::get('documents/{document}/vgm/{variant}', [ExportDocumentController::class, 'vgmPdf'])
            ->name('documents.vgm');
        Route::get('documents/{document}/bank-docs/{variant}', [ExportDocumentController::class, 'bankDocsPdf'])
            ->name('documents.bank-docs');
        Route::get('documents/{document}/buyer-docs/{variant}', [ExportDocumentController::class, 'buyerDocsPdf'])
            ->name('documents.buyer-docs');

        Route::resource('documents', ExportDocumentController::class)
            ->parameters(['documents' => 'document'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);
    });

    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('purchase-bills', [FinanceController::class, 'purchaseBills'])
            ->middleware('permission:purchase-bill.view')
            ->name('purchase-bills.index');
        Route::get('debit-notes', [FinanceController::class, 'debitNotes'])
            ->middleware('permission:debit-note.view')
            ->name('debit-notes.index');
        Route::get('supplier-payments', [FinanceController::class, 'supplierPayments'])
            ->middleware('permission:payment.view')
            ->name('supplier-payments.index');
        Route::get('buyer-receipts', [FinanceController::class, 'buyerReceipts'])
            ->middleware('permission:foreign-payment.view')
            ->name('buyer-receipts.index');
        Route::get('agent-commission', [FinanceController::class, 'agentCommission'])
            ->middleware('permission:agent-commission.view')
            ->name('agent-commission.index');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('outstanding', [ReportsController::class, 'outstanding'])
            ->middleware('permission:outstanding.view')
            ->name('outstanding.index');
        Route::get('/', [ReportsController::class, 'index'])
            ->middleware('permission:report.view')
            ->name('index');
    });

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    | Per-action permissions are declared inside each controller's
    | middleware() method rather than here, so a new action cannot be added
    | without also deciding its permission.
    */
    Route::prefix('user-management')->name('user-management.')->group(function () {

        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
        Route::resource('users', UserController::class);

        Route::resource('roles', RoleController::class);

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions/sync', [PermissionController::class, 'sync'])->name('permissions.sync');

        Route::get('company-profile', [CompanyProfileController::class, 'edit'])->name('company-profile.edit');
        Route::put('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');
    });

});

require __DIR__.'/auth.php';
