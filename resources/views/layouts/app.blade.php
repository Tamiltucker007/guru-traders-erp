<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Guru Traders ERP') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')

        <style>
            /* ============================ SIDEBAR — LIGHT ============================ */
            .app-sidebar {
                background: #ffffff;
                border-right: 1px solid #e9edf2;
            }

            /* Brand */
            .app-sidebar .sidebar-brand {
                background: #ffffff;
                border-bottom: 1px solid #eef1f5;
                padding: .9rem 1rem;
                display: flex; align-items: center; gap: .5rem;
            }
            .app-sidebar .brand-link {
                display: flex; align-items: center; gap: .65rem;
                text-decoration: none; padding: 0;
                flex: 1 1 auto; min-width: 0;
            }

            /* Collapse control. Quiet until pointed at — it sits next to the
               brand all day and should not compete with it. */
            .app-sidebar .sidebar-toggle {
                flex: 0 0 auto;
                width: 28px; height: 28px;
                display: grid; place-items: center;
                border: 0; border-radius: 7px;
                background: transparent; color: #9aa4b2;
                font-size: .8rem; line-height: 1;
                cursor: pointer;
                transition: background-color .12s ease, color .12s ease;
            }
            .app-sidebar .sidebar-toggle:hover { background: #f3f6fa; color: #2563eb; }
            .app-sidebar .sidebar-toggle:focus-visible {
                outline: 2px solid #2563eb; outline-offset: 2px;
            }
            .app-sidebar .brand-mark {
                width: 38px; height: 38px; flex-shrink: 0;
                display: grid; place-items: center;
                border-radius: 10px;
                box-shadow: 0 2px 6px rgba(37, 99, 235, .28);
            }
            .app-sidebar .brand-text {
                display: flex; flex-direction: column; line-height: 1.15;
                font-weight: 600; font-size: .98rem; color: #111827;
            }
            .app-sidebar .brand-text small {
                font-size: .68rem; font-weight: 500; color: #9aa4b2;
                text-transform: uppercase; letter-spacing: .06em;
            }

            /* Section headers */
            .app-sidebar .nav-header {
                color: #9aa4b2;
                font-size: .68rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .08em;
                padding: 1.1rem 1.15rem .4rem;
                background: transparent;
            }

            /* Links */
            .app-sidebar .sidebar-menu .nav-link {
                color: #4b5563;
                font-size: .885rem;
                font-weight: 500;
                border-radius: 8px;
                margin: 1px .6rem;
                padding: .5rem .7rem;
                display: flex; align-items: center;
                transition: background-color .12s ease, color .12s ease;
            }
            .app-sidebar .sidebar-menu .nav-link .nav-icon {
                font-size: 1rem;
                width: 1.5rem;
                color: #8b95a5;
                transition: color .12s ease;
            }
            .app-sidebar .sidebar-menu .nav-link:hover {
                background: #f3f6fa;
                color: #111827;
            }
            .app-sidebar .sidebar-menu .nav-link:hover .nav-icon { color: #2563eb; }

            .app-sidebar .sidebar-menu .nav-link.active {
                background: #eff4ff;
                color: #1d4ed8;
                font-weight: 600;
                box-shadow: inset 3px 0 0 #2563eb;
            }
            .app-sidebar .sidebar-menu .nav-link.active .nav-icon { color: #2563eb; }

            /* Treeview children */
            .app-sidebar .nav-treeview .nav-link {
                font-size: .845rem;
                padding-left: 1.9rem;
                margin-left: 1rem;
            }
            .app-sidebar .nav-treeview .nav-link .nav-icon {
                font-size: 1.15rem;
                width: 1rem;
                opacity: .5;
            }

            /* Modules whose screens are not built yet — visible but clearly inert. */
            .app-sidebar .nav-link.soon { color: #a3acb9; cursor: default; }
            .app-sidebar .nav-link.soon .nav-icon { color: #c2c9d4; }
            .app-sidebar .nav-link.soon:hover { background: #f7f9fc; color: #6b7280; }
            .app-sidebar .nav-link.soon > p::after {
                content: "";
                display: inline-block;
                width: 5px; height: 5px;
                border-radius: 50%;
                background: #d6dbe3;
                margin-left: .45rem;
                vertical-align: middle;
            }

            /* ==================== SIDEBAR — COLLAPSED (icon rail) ====================
               AdminLTE narrows the aside to 4.6rem and hides every <p> for us,
               and re-expands it on hover. Three things it cannot know about
               this theme are corrected below. All of them are scoped with
               :not(:hover) so the hover-expanded rail stays identical to the
               normal sidebar. */

            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) {
                overflow-x: hidden;
            }

            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-wrapper {
                overflow-x: hidden;
            }

            /* 1. Our links carry a .6rem side margin, which pushes AdminLTE's
                  3.6rem link past the 4.6rem rail and knocks the icons off
                  centre. Centre them on the rail instead. */
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-link {
                margin-left: auto; margin-right: auto;
                padding-left: 0; padding-right: 0;
                justify-content: center;
                white-space: nowrap;
            }
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-link p,
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-link .nav-arrow {
                display: none !important;
            }
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-treeview {
                display: none !important;
            }
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-icon {
                width: auto;
                margin-right: 0 !important;
            }
            /* The active marker is an inset left edge — invisible once the link
               is centred, so the collapsed state shows it as a filled pill. */
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-link.active {
                box-shadow: none; background: #e0eaff;
            }

            /* 2. Section headers are display:none when collapsed, so seven
                  groups run together as one undifferentiated strip of icons.
                  A hairline keeps the grouping readable without the words. */
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-header {
                display: block;
                height: 0; overflow: hidden;
                padding: .45rem 0 0; margin: .35rem .9rem 0;
                border-top: 1px solid #eef1f5;
            }

            /* 3. No room for the collapse button beside a hidden brand.
                  Hovering the rail expands it and brings the button back, and
                  the header hamburger works either way. */
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-toggle { display: none; }
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .brand-text { display: none !important; }
            .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-brand {
                justify-content: center; padding-left: .5rem; padding-right: .5rem;
            }
            /* Points the way it will move. */
            .sidebar-mini.sidebar-collapse .sidebar-toggle i { transform: rotate(180deg); }

            /* Smooth transition when rail expands on hover */
            .sidebar-mini.sidebar-collapse .app-sidebar:hover {
                box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
                z-index: 1039;
            }

            /* Below the expand breakpoint the sidebar is an off-canvas drawer,
               not a rail: mini mode there would leave a 4.6rem strip of icons
               permanently covering the content. Restore the full-width hide.
               Same specificity as AdminLTE's rule, declared later, so it wins. */
            @media (max-width: 991.98px) {
                .sidebar-mini.sidebar-collapse .app-sidebar {
                    min-width: var(--lte-sidebar-width);
                    max-width: var(--lte-sidebar-width);
                    margin-left: calc(var(--lte-sidebar-width) * -1);
                }
            }

            /* Scrollbar */
            .app-sidebar .sidebar-wrapper::-webkit-scrollbar { width: 6px; }
            .app-sidebar .sidebar-wrapper::-webkit-scrollbar-thumb {
                background: #dfe4ea; border-radius: 3px;
            }
            .app-sidebar .sidebar-wrapper::-webkit-scrollbar-thumb:hover { background: #c8cfd8; }

            /* ============================ CONTENT ============================ */
            body { background: #f5f7fa; }
            .app-header { border-bottom: 1px solid #e9edf2; }
            .card { border: 1px solid #e9edf2; }
            .app-content-header h3 { font-weight: 600; color: #111827; }

            /* Permission matrix */
            .matrix-table th { font-weight: 600; font-size: .8125rem; white-space: nowrap; }
            .matrix-table td { padding-top: .4rem; padding-bottom: .4rem; }
            .matrix-table .form-check-input { cursor: pointer; }

            /* ============================ FORMS ============================ */
            /* Long master forms are grouped into labelled sections — see the
               ui.form-section component. A flat 20-field grid is unreadable.
               Note: never write a component tag inside this stylesheet, even in
               a comment — Blade parses it and the layout stops compiling. */
            .form-section { margin-bottom: 1.75rem; }
            .form-section-head {
                display: flex; align-items: flex-start; gap: .65rem;
                padding-bottom: .6rem; margin-bottom: 1.1rem;
                border-bottom: 1px solid #e9edf2;
            }
            .form-section-icon {
                width: 30px; height: 30px; flex-shrink: 0;
                display: grid; place-items: center;
                border-radius: 8px;
                background: #eff4ff; color: #2563eb; font-size: .95rem;
            }
            .form-section-title {
                margin: 0; font-size: .95rem; font-weight: 600; color: #111827;
                line-height: 1.7;
            }
            .form-section-subtitle {
                margin: 0; font-size: .8rem; color: #8b95a5;
            }

            /* Read-only fields the system fills in (auto codes, calculated
               values) look different from fields the user types into. */
            .form-control[readonly] {
                background-color: #f5f7fa;
                color: #6b7280;
                border-style: dashed;
            }

            /* Incentive grid — mirrors the sheet's L..U column layout, so one
               header row instead of the same four labels repeated per scheme. */
            .grid-table { --bs-table-bg: transparent; }
            .grid-table th {
                font-size: .75rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: .04em; color: #8b95a5; white-space: nowrap;
                border-bottom-width: 1px;
            }
            .grid-table td { vertical-align: top; padding: .45rem .4rem; }
            .grid-table td:first-child, .grid-table th:first-child { padding-left: 0; }
            .grid-table .scheme-name { font-weight: 600; color: #374151; padding-top: .5rem; }
            .grid-table .cell-error { font-size: .78rem; color: var(--bs-danger); margin-top: .2rem; }
            .grid-table .na { color: #c2c9d4; padding-top: .5rem; }

            /* Order Format column builder — draggable rows, reordered by drag
               handle same as the client's prototype ("Drag rows to reorder"). */
            .column-row { cursor: default; }
            .column-row.is-dragging { opacity: .4; }
            .column-row.drag-over-top td { box-shadow: inset 0 2px 0 var(--bs-primary); }
            .column-row.drag-over-bottom td { box-shadow: inset 0 -2px 0 var(--bs-primary); }
            .column-drag-handle {
                cursor: grab; color: #b7bfcb; padding-top: .55rem !important;
                touch-action: none;
            }
            .column-drag-handle:hover { color: #6b7280; }
            .subcol-chip { font-size: .72rem; padding: .2rem .45rem .2rem .55rem; }

            /* A colour row's size grid, once the Order Format gives Size
               fixed sub-column tags (S/M/L/XL…) — the tag itself becomes
               read-only, so only the qty box next to it takes input. */
            .inquiry-size.is-grid .js-size-label {
                background: #f4f6fd; border-color: #d8def0; color: #4b5563;
                font-weight: 600; text-align: center; pointer-events: none;
            }

            /* One field per line, label on the left. Capped width — a text
               input stretched across a 1900px monitor is harder to read, not
               easier. */
            .form-stack { max-width: 860px; }
            .form-stack .form-line { margin-bottom: 1rem; }
            .form-stack .form-line:last-child { margin-bottom: 0; }
            .form-stack .col-form-label { color: #374151; }
            @media (max-width: 575.98px) {
                .form-stack .col-form-label { padding-bottom: .15rem; }
            }

            /* Save bar stays reachable at the bottom of a long form. */
            .form-actions {
                position: sticky; bottom: 0;
                display: flex; gap: .5rem; align-items: center;
                padding: .85rem 0 .35rem;
                margin-top: .5rem;
                background: linear-gradient(to top, #fff 70%, rgba(255,255,255,0));
                border-top: 1px solid #e9edf2;
            }

            /* Required marker */
            .form-label .req { color: var(--bs-danger); font-weight: 400; }
            .col-form-label .req { color: var(--bs-danger); font-weight: 400; }

            /* Carton marking details — Buyer sheet col X. A stack of labelled
               lines with a live preview beside it, mirroring the widget the
               client mocked up on the sheet. */
            .carton-line { margin-bottom: .7rem; }
            .carton-line-head {
                display: flex; align-items: center; gap: .5rem;
                margin-bottom: .2rem;
            }
            /* The label is editable but reads as a caption, not a second field —
               the value below it is what the user is filling in. */
            .carton-label-input {
                flex: 1 1 auto; min-width: 0;
                border: 0; border-bottom: 1px dashed transparent;
                background: transparent; padding: 0;
                font-size: .7rem; font-weight: 600; letter-spacing: .06em;
                text-transform: uppercase; color: #8b95a5;
            }
            .carton-label-input:hover { border-bottom-color: #d4dae2; }
            .carton-label-input:focus {
                outline: none; color: #374151; border-bottom-color: var(--bs-primary);
            }
            .carton-preview {
                border-radius: 10px; overflow: hidden;
                background: #1f2430; border: 1px solid #2b3140;
                position: sticky; top: 1rem;
            }
            .carton-preview-head {
                padding: .5rem .85rem;
                font-size: .68rem; font-weight: 600;
                letter-spacing: .08em; text-transform: uppercase;
                color: #8b95a5; border-bottom: 1px solid #2b3140;
            }
            .carton-preview-body {
                padding: .85rem; margin: 0; min-height: 8rem;
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: .82rem; line-height: 1.7; color: #e5e7eb;
                white-space: pre-wrap; word-break: break-word;
            }
            .carton-preview-body.is-empty { color: #6b7280; font-style: italic; }

            /* Unit chips on the Order Format master. Editable in place, as the
               client's prototype draws them — a repeating text row would have
               been four times the height for six short words. */
            .unit-chips { display: flex; flex-wrap: wrap; gap: .4rem; min-height: 2rem; }
            .unit-chip {
                display: inline-flex; align-items: center; gap: .35rem;
                padding: .25rem .5rem .25rem .6rem;
                border: 1px solid #d8def0; border-radius: 999px;
                background: #f4f6fd; color: #4b5563;
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: .78rem; font-weight: 600; letter-spacing: .03em;
            }
            .unit-chip-remove {
                border: 0; background: transparent; color: #9aa4b2;
                font-size: 1rem; line-height: 1; padding: 0 .1rem; cursor: pointer;
            }
            .unit-chip-remove:hover { color: var(--bs-danger); }

            /* Reference images — thumbnails with a keep/remove tick, so a save
               that drops one is a deliberate act rather than a side effect. */
            .reference-image {
                display: block; width: 8rem;
                border: 1px solid #e9edf2; border-radius: 8px;
                overflow: hidden; background: #fff;
            }
            .reference-image img {
                display: block; width: 100%; height: 6rem; object-fit: cover;
            }
            .reference-image-keep {
                display: flex; align-items: center; gap: .35rem;
                padding: .3rem .5rem;
                border-top: 1px solid #eef1f5;
                font-size: .75rem; color: #6b7280;
            }

            /* The three formula lines on the Markup master, printed under the
               percentage fields as the prototype prints them. */
            .formula-strip {
                display: flex; flex-wrap: wrap; align-items: stretch; gap: 0;
                border: 1px solid #e9edf2; border-radius: 10px;
                background: #fafbfd; overflow: hidden;
            }
            .formula-cell {
                flex: 1 1 12rem; min-width: 12rem;
                padding: .7rem .9rem;
                border-right: 1px solid #eef1f5;
            }
            .formula-cell:last-of-type { border-right: 0; }
            .formula-head {
                font-size: .65rem; font-weight: 700; letter-spacing: .08em;
                text-transform: uppercase; color: #9aa4b2;
            }
            .formula-body {
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: .8rem; color: #4b5563; margin-top: .15rem;
            }
            .formula-value { font-size: 1rem; font-weight: 600; color: #111827; margin-top: .3rem; }
            .formula-note {
                flex: 1 1 100%;
                display: flex; align-items: center; gap: .5rem;
                padding: .55rem .9rem;
                border-top: 1px solid #eef1f5;
                font-size: .78rem; color: #8b95a5;
            }

            /* Role picker cards on the user form */
            .role-option { cursor: pointer; transition: border-color .15s, background-color .15s; }
            .role-option:hover { border-color: var(--bs-primary) !important; }
            .role-option:has(input:checked) {
                border-color: var(--bs-primary) !important;
                background-color: var(--bs-primary-bg-subtle);
            }
        </style>
    </head>
    {{-- sidebar-mini turns the collapse into a 4.6rem icon rail instead of
         hiding the sidebar outright, so the menu is still one click away. --}}
    <body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
        <script>
            /**
             * Apply the remembered sidebar state before the first paint.
             *
             * AdminLTE restores it too, but only on DOMContentLoaded — by then
             * the expanded sidebar has been painted and the collapse reads as a
             * flicker on every navigation. This sets the exact class AdminLTE
             * sets, from the exact key it writes, so the two cannot disagree:
             * AdminLTE's own restore then finds the work already done.
             *
             * Below the expand breakpoint the sidebar is a drawer, not a rail,
             * and starting it collapsed there is the default anyway.
             *
             * Inside <body> rather than <head> because document.body must
             * exist. Guarded: localStorage throws in Safari private mode.
             */
            try {
                if (localStorage.getItem('lte.sidebar.state') === 'sidebar-collapse'
                    && window.innerWidth > 991.98) {
                    document.body.classList.add('sidebar-collapse');
                }
            } catch (e) { /* no persistence available — start expanded */ }
        </script>

        <div class="app-wrapper">
            <!-- Header -->
            @include('layouts.header')

            <!-- Sidebar -->
            @include('layouts.sidebar')

            <!-- App Main -->
            <main class="app-main">
                <!-- App Content Header -->
                @isset($header)
                    <div class="app-content-header">
                        <div class="container-fluid">
                            <div class="row align-items-center">
                                <div class="col-sm-6">
                                    <h3 class="mb-0">{{ $header }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                @endisset

                <!-- App Content -->
                <div class="app-content">
                    <div class="container-fluid">
                        @foreach (['success' => 'check-circle', 'error' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
                            @if(session($type))
                                <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} alert-dismissible fade show d-flex align-items-center" role="alert">
                                    <i class="bi bi-{{ $icon }} me-2"></i>
                                    <div>{{ session($type) }}</div>
                                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                        @endforeach

                        {{-- Field-level errors render next to their input. This summary is
                             only useful when several fields failed at once. --}}
                        @if($errors->any() && $errors->count() > 1)
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <div class="fw-semibold mb-1">
                                    <i class="bi bi-exclamation-octagon me-1"></i>Please fix {{ $errors->count() }} problem(s):
                                </div>
                                <ul class="mb-0 ps-4 small">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        {{ $slot ?? '' }}
                        @yield('content')
                    </div>
                </div>
            </main>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Bootstrap tooltips — used by every list screen's action buttons.
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            // One confirm handler for every destructive form, instead of an
            // inline onsubmit="return confirm(...)" repeated on each one.
            document.querySelectorAll('form.js-confirm').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (! window.confirm(form.dataset.confirm || 'Are you sure?')) {
                        e.preventDefault();
                    }
                });
            });

            // Modules that have no screen yet.
            document.querySelectorAll('.nav-link.soon').forEach(function (link) {
                link.addEventListener('click', function (e) { e.preventDefault(); });
            });

            // The sidebar's collapse button says what it will do next. It stays
            // reachable while collapsed (hovering the rail expands it), so a
            // fixed "Collapse sidebar" would be wrong half the time. AdminLTE
            // fires these on the sidebar element after it has changed state.
            var sidebar = document.querySelector('.app-sidebar');
            var toggle = sidebar?.querySelector('.sidebar-toggle');

            if (sidebar && toggle) {
                var describe = function () {
                    var collapsed = document.body.classList.contains('sidebar-collapse');
                    var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';

                    toggle.title = label;
                    toggle.setAttribute('aria-label', label);
                    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                };

                sidebar.addEventListener('opened.lte.push-menu', describe);
                sidebar.addEventListener('collapsed.lte.push-menu', describe);
                describe();
            }
        });
        </script>

        @stack('scripts')
    </body>
</html>
