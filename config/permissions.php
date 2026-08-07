<?php

/*
|--------------------------------------------------------------------------
| Permission Registry
|--------------------------------------------------------------------------
|
| Single source of truth for every permission in the application.
|
| Permissions are NOT created by hand through the UI. They are declared here
| and pushed into the database by PermissionsSeeder (or `php artisan
| permission:sync`). A permission that is not in this file does not exist.
|
| Why: `@can('product.view')` in a Blade file and a hand-typed
| `prodcut.view` row in the database fail silently — the gate just returns
| false and the menu item disappears with no error. Declaring them here means
| the string in the code and the string in the database come from one place.
|
| To add a module: add it under the right group below, then run
| `php artisan permission:sync`.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Super Admin account
    |--------------------------------------------------------------------------
    | Override in .env for anything other than local development.
    */
    'super_admin' => [
        'email'    => env('SUPER_ADMIN_EMAIL', 'admin@gurutraders.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'Guru@123'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default actions
    |--------------------------------------------------------------------------
    | Used when a module does not declare its own "actions" key.
    */
    'default_actions' => ['view', 'create', 'edit', 'delete'],

    /*
    |--------------------------------------------------------------------------
    | Action labels (for the role permission matrix UI)
    |--------------------------------------------------------------------------
    */
    'action_labels' => [
        'view'     => 'View',
        'create'   => 'Create',
        'edit'     => 'Edit',
        'delete'   => 'Delete',
        'approve'  => 'Approve',
        'export'   => 'Export',
        'generate' => 'Generate',
        'sync'     => 'Sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules, grouped for the UI
    |--------------------------------------------------------------------------
    | key    => permission prefix, e.g. "product" => product.view, product.create
    | label  => shown in the permission matrix and the sidebar
    | actions=> optional override of default_actions
    | built  => false means the screen does not exist yet; it still gets a
    |           permission so roles can be configured ahead of the build,
    |           and the matrix shows it greyed out.
    */
    /*
     * Groups are named for the desk that works them, and they are the same
     * names, in the same order, as the sidebar sections — so an admin ticking
     * boxes in the permission matrix sees exactly the sections the user will
     * get. Changing a group name here means changing the sidebar heading too.
     *
     * ## What was removed, and why
     *
     * The client's own prototype sidebar runs Inquiries -> Order Confirmations
     * -> Purchase Orders -> Goods Inward -> Packing -> Export Docs, and nothing
     * else. Five modules we had declared do not appear anywhere in it:
     *
     *   quotation        A quotation is what a costed inquiry prints. Keeping a
     *                    separate module meant re-entering the same buyer,
     *                    items and prices on a second screen. Becomes an
     *                    inquiry.export action.
     *   quality-check    Accepted and rejected quantity are recorded as the
     *                    goods are received. A separate screen meant opening
     *                    the same PO twice. Becomes inward-entry.approve.
     *   shipment         Booking, container and BL details belong on the
     *                    document set they print onto. Folded into
     *                    export-document, which gains create/edit/delete.
     *   sample-approval  Not in the client's flow. A sample is a status on the
     *                    PO, not a module — reconfirm on the call.
     *   material-issue   Only exists if we issue our own fabric and trims to
     *                    the jobber. Open question on the call.
     *
     *   contract         Removed separately: it never had a screen. A contract
     *                    number is a field on the order for direct-order
     *                    buyers (see buyers.order_mode).
     *
     * None of this is lost work — no screen existed for any of them. Any that
     * the client asks back for is one entry here plus one sidebar block.
     */
    'groups' => [

        /*
         * The seven masters. Nothing else belongs here — ports, currencies,
         * HSN codes, sizes, colours, payment terms and incoterms are dropdown
         * sources, not business masters. They have no screen and no permission
         * of their own; LookupSeeder populates them, and a Lookups module can
         * be declared here the day someone needs to edit them in the UI.
         */
        'Masters' => [
            'category'  => ['label' => 'Categories'],
            'po-format' => ['label' => 'Order Formats'],
            'product'   => ['label' => 'Products'],
            'buyer'     => ['label' => 'Buyers'],
            'supplier'  => ['label' => 'Suppliers'],
            'jobber'    => ['label' => 'Jobbers'],
            'agent'     => ['label' => 'Agents'],
            'fob-value' => ['label' => 'FOB Values'],
            'markup'    => ['label' => 'Markup'],
        ],

        'Sales' => [
            'inquiry'            => ['label' => 'Inquiries',           'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export'], 'built' => true],
            'order-confirmation' => ['label' => 'Order Confirmations', 'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export'], 'built' => true],
        ],

        'Procurement' => [
            'purchase-order' => ['label' => 'Purchase Orders', 'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export'], 'built' => true],
            // "approve" is the QC pass: the receiver records what arrived, the
            // checker approves what is good. Two people, one screen, one row.
            'inward-entry'   => ['label' => 'Goods Inward',    'actions' => ['view', 'create', 'edit', 'delete', 'approve'], 'built' => true],
        ],

        'Export' => [
            'packing'         => ['label' => 'Packing',           'built' => false],
            'export-document' => ['label' => 'Export Documents',  'actions' => ['view', 'create', 'edit', 'delete', 'generate', 'export'], 'built' => false],
        ],

        'Finance' => [
            'purchase-bill'    => ['label' => 'Purchase Bills',    'built' => false],
            'debit-note'       => ['label' => 'Debit Notes',       'actions' => ['view', 'create', 'edit', 'delete', 'approve'], 'built' => false],
            'payment'          => ['label' => 'Supplier Payments', 'actions' => ['view', 'create', 'edit', 'delete', 'approve'], 'built' => false],
            // Money coming IN from the buyer. "Foreign Payments" read as money
            // going out; the key stays, the label says which direction.
            'foreign-payment'  => ['label' => 'Buyer Receipts',    'actions' => ['view', 'create', 'edit', 'delete', 'approve'], 'built' => false],
            'agent-commission' => ['label' => 'Agent Commission',  'built' => false],
        ],

        /*
         * Outstanding lives here, not under Finance: it holds view and export
         * only — nothing is ever created or edited on it. That is a report.
         */
        'Reports' => [
            'outstanding' => ['label' => 'Outstanding', 'actions' => ['view', 'export'], 'built' => false],
            'report'      => ['label' => 'Reports',     'actions' => ['view', 'export'], 'built' => false],
        ],

        /*
         * Who may do what. Nothing else — the four system-settings modules
         * that used to sit alongside these (Company Profile, Lookups, Number
         * Series, Activity Logs) were removed: none had a screen, and carrying
         * permissions for screens nobody has built means every role has to be
         * re-decided when they finally are.
         *
         * Company Profile is the one that will certainly come back. Export
         * documents print the exporter's name, address, IEC and GST from
         * somewhere, and that somewhere is this module. Re-adding it is one
         * entry here, one sidebar block and a `permission:sync`.
         *
         * The heading is "Administration" again rather than "Setup" now that
         * the section holds only user, role and permission management. That
         * was the accurate name all along; it was only widened to "Setup"
         * because operational roles held lookup.view and would otherwise have
         * seen the word ADMINISTRATION above their menu. No operational role
         * has anything in here now.
         */
        'Administration' => [
            'user'       => ['label' => 'Users'],
            'role'       => ['label' => 'Roles'],
            'permission' => ['label' => 'Permissions', 'actions' => ['view', 'sync']],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | System roles
    |--------------------------------------------------------------------------
    | These roles are seeded and protected: they cannot be renamed or deleted
    | through the UI, because route middleware and Gate checks reference them
    | by name. Roles created through the UI are freely editable.
    |
    | permissions:
    |   '*'                      => every permission (Super Admin)
    |   ['module.*', 'x.view']   => wildcard per module, or exact permission
    */
    'roles' => [

        'Super Admin' => [
            'description' => 'Full system access including permission management.',
            'permissions' => '*',
        ],

        'Admin' => [
            'description' => 'Full operational access. Cannot manage permissions.',
            'permissions' => [
                'user.*', 'role.view',
                'category.*', 'po-format.*', 'product.*', 'buyer.*', 'supplier.*', 'jobber.*',
                'agent.*', 'fob-value.*', 'markup.*',
                'inquiry.*', 'order-confirmation.*',
                'purchase-order.*', 'inward-entry.*',
                'packing.*', 'export-document.*',
                'purchase-bill.*', 'debit-note.*', 'payment.*', 'foreign-payment.*',
                'agent-commission.*',
                'outstanding.*', 'report.*',
            ],
        ],

        'Merchandising & Manufacturing' => [
            'description' => 'Handles inquiry to purchase order, suppliers and products.',
            'permissions' => [
                'category.view', 'po-format.view', 'product.*', 'buyer.view',
                'supplier.*', 'agent.view',
                'inquiry.*', 'order-confirmation.*',
                'purchase-order.*', 'inward-entry.view',
                'export-document.view',
                'outstanding.view', 'report.view', 'report.export',
            ],
        ],

        'Accounts' => [
            'description' => 'Bills, payments, commission and outstanding.',
            'permissions' => [
                'product.view', 'buyer.view', 'supplier.view', 'agent.view', 'markup.view',
                'purchase-order.view', 'inward-entry.view', 'export-document.view',
                'purchase-bill.*', 'debit-note.*', 'payment.*', 'foreign-payment.*',
                'agent-commission.*',
                'outstanding.*', 'report.view', 'report.export',
            ],
        ],

        /*
         * Shipment was folded into export-document, so this desk's whole job is
         * now that one module plus the money coming back in against it.
         */
        'Export Documentation & Foreign Payment' => [
            'description' => 'Shipment, export documents and buyer payment realisation.',
            'permissions' => [
                'category.view', 'product.view', 'buyer.view',
                'order-confirmation.view', 'packing.view',
                'export-document.*',
                'foreign-payment.*',
                'outstanding.view', 'report.view', 'report.export',
            ],
        ],

        'Packing' => [
            'description' => 'Packing lists, cartons and markings.',
            'permissions' => [
                'product.view', 'buyer.view',
                'order-confirmation.view', 'purchase-order.view', 'inward-entry.view',
                'packing.*',
                'report.view',
            ],
        ],

        /*
         * The checker now works inside Goods Inward rather than on a screen of
         * their own: edit records the inspection, approve is the pass.
         */
        'Quality Checker' => [
            'description' => 'Inspects goods inward and records pass/reject quantity.',
            'permissions' => [
                'product.view', 'supplier.view',
                'purchase-order.view',
                'inward-entry.view', 'inward-entry.edit', 'inward-entry.approve',
                'debit-note.view', 'debit-note.create',
                'report.view',
            ],
        ],

        'Jobworker' => [
            'description' => 'External jobber. Sees only their own purchase orders and deliveries.',
            'permissions' => [
                'purchase-order.view',
                'inward-entry.view', 'inward-entry.create',
            ],
        ],

    ],

];
