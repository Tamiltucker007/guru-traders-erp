@php
    /**
     * Sidebar.
     *
     * ## Structure
     *
     * Sections are named for the desk that works them, and they are the same
     * names, in the same order, as the groups in config/permissions.php — an
     * admin ticking boxes in the permission matrix sees exactly the sections
     * the user will get. Renaming a section here means renaming the group
     * there too.
     *
     *   Masters         the data everything below is built from
     *   Sales           the buyer asks, we confirm
     *   Procurement     we order, the goods arrive
     *   Export          we pack, we ship the paperwork
     *   Finance         bills in, money out, money back
     *   Reports         read-only
     *   Administration  who may do what
     *
     * Masters sits first, directly under Dashboard: nothing downstream can be
     * raised until the buyer, the product and the format behind it exist, so
     * that is where a new install starts and where anyone chasing a wrong
     * price, unit or format ends up. Sales through Reports then run in the
     * order a job actually moves, and Administration is last because it is
     * opened twice a year.
     *
     * Within each section the order is the order the work happens in, never
     * alphabetical.
     *
     * ## Rules
     *
     * Every entry is permission-gated, and a section header is only rendered
     * when the user can see at least one of its children — so a role with no
     * Finance permissions never sees an empty "FINANCE" heading. Each $canAny
     * list must therefore name exactly the permissions of the items rendered
     * below it, no more: one extra permission in the list is an empty header
     * waiting for the role that happens to hold only that one.
     *
     * Modules whose screens are not built yet point at "#" and carry a small
     * dot marker, so nobody reports a dead link as a bug.
     */
    $can = fn (string $permission) => auth()->user()?->can($permission);
    $canAny = fn (array $permissions) => collect($permissions)->contains($can);
@endphp

{{-- data-enable-persistence: AdminLTE defaults to off, which means the
     sidebar springs back open on every page load and the toggle is useless on
     a multi-screen app. On, it remembers the choice in localStorage. --}}
<aside class="app-sidebar shadow-sm" data-bs-theme="light" data-enable-persistence="true">

    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-mark"><x-brand-logo :size="38" /></span>
            <span class="brand-text">
                Guru Traders
                <small>Export ERP</small>
            </span>
        </a>

        {{-- AdminLTE listens for [data-lte-toggle="sidebar"] anywhere on the
             page, so this needs no wiring of its own. It is hidden once the
             rail is collapsed — there is no room beside a hidden brand — but
             hovering the rail expands it and brings the button back, and the
             header hamburger works regardless. --}}
        <button type="button" class="sidebar-toggle" data-lte-toggle="sidebar"
                aria-label="Collapse sidebar" title="Collapse sidebar">
            <i class="bi bi-chevron-double-left"></i>
        </button>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-grid-1x2"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                {{-- ============================= MASTERS =============================
                     First, directly under Dashboard: an inquiry, an OC or a PO
                     cannot be raised until the buyer, the product and the
                     format behind it exist.

                     Setup order within the section, so a fresh install fills it
                     top to bottom and each row only needs what is above it:

                       Categories     nothing points at it; the next five do
                       Order Formats  linked per category
                       Products       needs a category
                       Buyers         need categories
                       Suppliers      need categories
                       Agents         attached to a buyer or a supplier
                       Markup         priced against all of the above

                     Contract is absent: it never had a screen. A contract
                     number is a field on the order for direct-order buyers.
                --}}
                @if($canAny(['category.view', 'po-format.view', 'product.view', 'buyer.view', 'supplier.view', 'jobber.view', 'agent.view', 'fob-value.view', 'markup.view']))
                    <li class="nav-header">Masters</li>

                    @can('category.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.categories.index') }}"
                               class="nav-link {{ request()->routeIs('masters.categories.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-tags"></i><p>Categories</p>
                            </a>
                        </li>
                    @endcan
                    @can('po-format.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.formats.index') }}"
                               class="nav-link {{ request()->routeIs('masters.formats.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-file-earmark-ruled"></i><p>Order Formats</p>
                            </a>
                        </li>
                    @endcan
                    @can('product.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.products.index') }}"
                               class="nav-link {{ request()->routeIs('masters.products.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-box-seam"></i><p>Products</p>
                            </a>
                        </li>
                    @endcan
                    @can('buyer.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.buyers.index') }}"
                               class="nav-link {{ request()->routeIs('masters.buyers.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-globe-asia-australia"></i><p>Buyers</p>
                            </a>
                        </li>
                    @endcan
                    @can('supplier.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.suppliers.index') }}"
                               class="nav-link {{ request()->routeIs('masters.suppliers.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-truck"></i><p>Suppliers</p>
                            </a>
                        </li>
                    @endcan
                    @if($can('jobber.view') || $can('supplier.view'))
                        <li class="nav-item">
                            <a href="{{ route('masters.jobbers.index') }}"
                               class="nav-link {{ request()->routeIs('masters.jobbers.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-tools"></i><p>Jobbers</p>
                            </a>
                        </li>
                    @endif
                    @can('agent.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.agents.index') }}"
                               class="nav-link {{ request()->routeIs('masters.agents.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-person-badge"></i><p>Agents</p>
                            </a>
                        </li>
                    @endcan
                    @can('fob-value.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.fob-values.index') }}"
                               class="nav-link {{ request()->routeIs('masters.fob-values.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-currency-dollar"></i><p>FOB Values</p>
                            </a>
                        </li>
                    @endcan
                    @can('markup.view')
                        <li class="nav-item">
                            <a href="{{ route('masters.markups.index') }}"
                               class="nav-link {{ request()->routeIs('masters.markups.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-percent"></i><p>Markup</p>
                            </a>
                        </li>
                    @endcan
                @endif


                {{-- ============================== SALES ==============================
                     The buyer side. Quotation is not a menu item: it is what a
                     costed inquiry prints, so it lives on the inquiry as an
                     export action rather than as a second screen asking for the
                     same buyer, the same items and the same prices again.
                --}}
                @if($canAny(['inquiry.view', 'order-confirmation.view']))
                    <li class="nav-header">Sales</li>

                    @can('inquiry.view')
                        <li class="nav-item">
                            <a href="{{ route('sales.inquiries.index') }}"
                               class="nav-link {{ request()->routeIs('sales.inquiries.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-chat-square-text"></i><p>Inquiries</p>
                            </a>
                        </li>
                    @endcan
                    @can('order-confirmation.view')
                        <li class="nav-item">
                            <a href="{{ route('sales.order-confirmations.index') }}"
                               class="nav-link {{ request()->routeIs('sales.order-confirmations.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-check2-square"></i><p>Order Confirmations</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- =========================== PROCUREMENT ===========================
                     The supplier side. Quality Check is not a menu item: what
                     arrived and what passed are two columns on the same receipt
                     line, so the checker works inside Goods Inward with
                     inward-entry.approve rather than reopening the same PO on a
                     screen of their own.
                --}}
                @if($canAny(['purchase-order.view', 'inward-entry.view']))
                    <li class="nav-header">Procurement</li>

                    @can('purchase-order.view')
                        <li class="nav-item">
                            <a href="{{ route('procurement.purchase-orders.index') }}"
                               class="nav-link {{ request()->routeIs('procurement.purchase-orders.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-cart-check"></i><p>Purchase Orders</p>
                            </a>
                        </li>
                    @endcan
                    @can('inward-entry.view')
                        <li class="nav-item">
                            <a href="{{ route('procurement.inward-entries.index') }}"
                               class="nav-link {{ request()->routeIs('procurement.inward-entries.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-box-arrow-in-down"></i><p>Goods Inward</p>
                            </a>
                        </li>
                    @endcan
                @endif

                {{-- ============================== EXPORT =============================
                     Shipment is not a menu item: the booking, container and BL
                     details are what the invoice, packing list and COO print
                     from, so they are captured on the document set itself.
                --}}
                @if($canAny(['packing.view', 'export-document.view']))
                    <li class="nav-header">Export</li>

                    @can('packing.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-boxes"></i><p>Packing</p></a></li>
                    @endcan
                    @can('export-document.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-files"></i><p>Export Documents</p></a></li>
                    @endcan
                @endif

                {{-- ============================= FINANCE =============================
                     Money order: the supplier bills us, a debit note adjusts
                     that bill, we pay what is left, the buyer pays us, the agent
                     takes their cut.
                --}}
                @if($canAny(['purchase-bill.view', 'debit-note.view', 'payment.view', 'foreign-payment.view', 'agent-commission.view']))
                    <li class="nav-header">Finance</li>

                    @can('purchase-bill.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-receipt"></i><p>Purchase Bills</p></a></li>
                    @endcan
                    @can('debit-note.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-file-earmark-minus"></i><p>Debit Notes</p></a></li>
                    @endcan
                    @can('payment.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-cash-coin"></i><p>Supplier Payments</p></a></li>
                    @endcan
                    @can('foreign-payment.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-currency-exchange"></i><p>Buyer Receipts</p></a></li>
                    @endcan
                    @can('agent-commission.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-cash-stack"></i><p>Agent Commission</p></a></li>
                    @endcan
                @endif

                {{-- ============================= REPORTS =============================
                     Outstanding sits here rather than under Finance: it holds
                     view and export only. Nothing is ever created on it.
                --}}
                @if($canAny(['outstanding.view', 'report.view']))
                    <li class="nav-header">Reports</li>

                    @can('outstanding.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-hourglass-split"></i><p>Outstanding</p></a></li>
                    @endcan
                    @can('report.view')
                        <li class="nav-item"><a href="#" class="nav-link soon"><i class="nav-icon bi bi-bar-chart-line"></i><p>Reports</p></a></li>
                    @endcan
                @endif

                {{-- ========================== ADMINISTRATION =========================
                     Who may do what, and nothing else. Company Profile,
                     Lookups, Number Series and Activity Logs used to sit here;
                     none had a screen, and a menu full of links that go
                     nowhere is worse than a short menu.

                     User Management is a treeview because its three screens are
                     one subject and are opened together.

                     No "My Profile" entry — the header's user dropdown already
                     carries Profile and Logout, top right on every page.
                --}}
                @if($canAny(['user.view', 'role.view', 'permission.view']))
                    <li class="nav-header">Administration</li>

                    <li class="nav-item {{ request()->routeIs('user-management.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('user-management.*') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-people"></i>
                            <p>User Management<i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('user.view')
                                <li class="nav-item">
                                    <a href="{{ route('user-management.users.index') }}"
                                       class="nav-link {{ request()->routeIs('user-management.users.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-dot"></i><p>Users</p>
                                    </a>
                                </li>
                            @endcan
                            @can('role.view')
                                <li class="nav-item">
                                    <a href="{{ route('user-management.roles.index') }}"
                                       class="nav-link {{ request()->routeIs('user-management.roles.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-dot"></i><p>Roles</p>
                                    </a>
                                </li>
                            @endcan
                            @can('permission.view')
                                <li class="nav-item">
                                    <a href="{{ route('user-management.permissions.index') }}"
                                       class="nav-link {{ request()->routeIs('user-management.permissions.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-dot"></i><p>Permissions</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif

            </ul>
        </nav>
    </div>
</aside>
