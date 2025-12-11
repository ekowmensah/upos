@inject('request', 'Illuminate\Http\Request')

@if (
    $request->segment(1) == 'pos' &&
        ($request->segment(2) == 'create' || $request->segment(3) == 'edit' || $request->segment(2) == 'payment'))
    @php
        $pos_layout = true;
    @endphp
@else
    @php
        $pos_layout = false;
    @endphp
@endif

@php
    $whitelist = ['127.0.0.1', '::1'];
@endphp

<!DOCTYPE html>
<html class="tw-bg-white tw-scroll-smooth" lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <!-- Tell the browser to be responsive to screen width -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"
        name="viewport">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ Session::get('business.name') }}</title>

    @include('layouts.partials.css')
    

    @include('layouts.partials.extracss')

    @yield('css')

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('/manifest.json') }}">
    <meta name="theme-color" content="#3c8dbc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

</head>
<body
    class="tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100 @if ($pos_layout) hold-transition lockscreen @else hold-transition skin-@if (!empty(session('business.theme_color'))){{ session('business.theme_color') }}@else{{ 'blue-light' }} @endif sidebar-mini @endif" >
    <div class="tw-flex">
        <script type="text/javascript">
            if (localStorage.getItem("upos_sidebar_collapse") == 'true') {
                var body = document.getElementsByTagName("body")[0];
                body.className += " sidebar-collapse";
            }
        </script>
        @if (!$pos_layout)
            @include('layouts.partials.sidebar')
        @endif

        @if (in_array($_SERVER['REMOTE_ADDR'], $whitelist))
            <input type="hidden" id="__is_localhost" value="true">
        @endif

        <!-- Add currency related field-->
        <input type="hidden" id="__code" value="{{ session('currency')['code'] }}">
        <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] }}">
        <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] }}">
        <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] }}">
        <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') }}">
        <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
        <input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">
        <!-- End of currency related field-->
        @can('view_export_buttons')
            <input type="hidden" id="view_export_buttons">
        @endcan
        @if (isMobile())
            <input type="hidden" id="__is_mobile">
        @endif
        @if (session('status'))
            <input type="hidden" id="status_span" data-status="{{ session('status.success') }}"
                data-msg="{{ session('status.msg') }}">
        @endif
        <main class="tw-flex tw-flex-col tw-flex-1 tw-h-full tw-min-w-0 tw-bg-gray-100">

            @if (!$pos_layout)
                @include('layouts.partials.header')
            @else
                @include('layouts.partials.header-pos')
            @endif
            <!-- empty div for vuejs -->
            <div id="app">
                @yield('vue')
            </div>
            <div class="tw-flex-1 tw-overflow-y-auto tw-h-screen" id="scrollable-container">
                @yield('content')
                @if (!$pos_layout)
                
                    @include('layouts.partials.footer')
                @else
                    @include('layouts.partials.footer_pos')
                @endif
            </div>
            <div class='scrolltop no-print'>
                <div class='scroll icon'><i class="fas fa-angle-up"></i></div>
            </div>

            @if (config('constants.iraqi_selling_price_adjustment'))
                <input type="hidden" id="iraqi_selling_price_adjustment">
            @endif

            <!-- This will be printed -->
            <section class="invoice print_section" id="receipt_section">
            </section>
        </main>

        @include('home.todays_profit_modal')
        <!-- /.content-wrapper -->



        <audio id="success-audio">
            <source src="{{ asset('/audio/success.ogg?v=' . $asset_v) }}" type="audio/ogg">
            <source src="{{ asset('/audio/success.mp3?v=' . $asset_v) }}" type="audio/mpeg">
        </audio>
        <audio id="error-audio">
            <source src="{{ asset('/audio/error.ogg?v=' . $asset_v) }}" type="audio/ogg">
            <source src="{{ asset('/audio/error.mp3?v=' . $asset_v) }}" type="audio/mpeg">
        </audio>
        <audio id="warning-audio">
            <source src="{{ asset('/audio/warning.ogg?v=' . $asset_v) }}" type="audio/ogg">
            <source src="{{ asset('/audio/warning.mp3?v=' . $asset_v) }}" type="audio/mpeg">
        </audio>

        @if (!empty($__additional_html))
            {!! $__additional_html !!}
        @endif

        @include('layouts.partials.javascripts')

        <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

        @if (!empty($__additional_views) && is_array($__additional_views))
            @foreach ($__additional_views as $additional_view)
                @includeIf($additional_view)
            @endforeach
        @endif
        <div>

            <div class="overlay tw-hidden"></div>

        <!-- Offline Indicator -->
        <div id="offline-indicator" style="display: none; position: fixed; top: 0; left: 0; right: 0; background: #f44336; color: white; padding: 10px; text-align: center; z-index: 9999; font-weight: bold;">
            <span id="offline-text">📡 You are offline. Transactions will be saved locally and synced when connection is restored.</span>
        </div>

        <!-- Offline DB Script -->
        <script src="{{ asset('/js/offline-db.js') }}"></script>

        <!-- Service Worker Registration & Offline Logic -->
        <script>
            let isOnline = navigator.onLine;
            let syncInProgress = false;

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js')
                        .then(registration => {
                            console.log('Service Worker registered successfully:', registration.scope);
                            
                            registration.addEventListener('updatefound', () => {
                                const newWorker = registration.installing;
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        console.log('New service worker available');
                                        if (confirm('A new version is available. Reload to update?')) {
                                            newWorker.postMessage({ type: 'SKIP_WAITING' });
                                            window.location.reload();
                                        }
                                    }
                                });
                            });
                        })
                        .catch(error => {
                            console.error('Service Worker registration failed:', error);
                        });
                });
            }

            function updateOnlineStatus() {
                const indicator = document.getElementById('offline-indicator');
                const indicatorText = document.getElementById('offline-text');
                
                if (navigator.onLine) {
                    isOnline = true;
                    indicator.style.background = '#4caf50';
                    indicatorText.textContent = '✅ Back online! Syncing pending transactions...';
                    indicator.style.display = 'block';
                    
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 3000);
                    
                    syncPendingTransactions();
                } else {
                    isOnline = false;
                    indicator.style.background = '#f44336';
                    indicatorText.textContent = '📡 You are offline. Transactions will be saved locally and synced when connection is restored.';
                    indicator.style.display = 'block';
                }
            }

            async function syncPendingTransactions() {
                if (syncInProgress) {
                    console.log('Sync already in progress');
                    return;
                }

                try {
                    syncInProgress = true;
                    await offlineDB.init();
                    
                    const pendingTransactions = await offlineDB.getPendingTransactions();
                    
                    if (pendingTransactions.length === 0) {
                        console.log('No pending transactions to sync');
                        syncInProgress = false;
                        return;
                    }

                    console.log(`Syncing ${pendingTransactions.length} pending transactions`);

                    for (const transaction of pendingTransactions) {
                        try {
                            const response = await fetch('/api/sync/transaction', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(transaction)
                            });

                            const result = await response.json();

                            if (result.success) {
                                await offlineDB.markTransactionSynced(transaction.id, result.data);
                                console.log('Transaction synced successfully:', transaction.id);
                                
                                toastr.success('Transaction synced: Invoice #' + result.data.invoice_no);
                            } else {
                                console.error('Failed to sync transaction:', result.message);
                                toastr.error('Failed to sync transaction: ' + result.message);
                            }
                        } catch (error) {
                            console.error('Error syncing transaction:', error);
                        }
                    }

                    const remainingPending = await offlineDB.getPendingTransactions();
                    if (remainingPending.length === 0) {
                        toastr.success('All transactions synced successfully!');
                    }

                } catch (error) {
                    console.error('Sync error:', error);
                } finally {
                    syncInProgress = false;
                }
            }

            async function cacheEssentialData() {
                try {
                    await offlineDB.init();
                    
                    const lastProductsCache = await offlineDB.getSetting('products_cached_at');
                    const lastCustomersCache = await offlineDB.getSetting('customers_cached_at');
                    const now = Date.now();
                    const oneHour = 60 * 60 * 1000;

                    if (!lastProductsCache || (now - lastProductsCache) > oneHour) {
                        console.log('Caching products...');
                        const productsResponse = await fetch('/api/offline/products', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (productsResponse.ok) {
                            const productsData = await productsResponse.json();
                            if (productsData.success) {
                                await offlineDB.cacheProducts(productsData.data);
                                console.log('Products cached successfully');
                            }
                        }
                    }

                    if (!lastCustomersCache || (now - lastCustomersCache) > oneHour) {
                        console.log('Caching customers...');
                        const customersResponse = await fetch('/api/offline/customers', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (customersResponse.ok) {
                            const customersData = await customersResponse.json();
                            if (customersData.success) {
                                await offlineDB.cacheCustomers(customersData.data);
                                console.log('Customers cached successfully');
                            }
                        }
                    }

                    const stats = await offlineDB.getStats();
                    console.log('Offline DB Stats:', stats);

                } catch (error) {
                    console.error('Error caching data:', error);
                }
            }

            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);

            window.addEventListener('load', () => {
                updateOnlineStatus();
                
                if (isOnline) {
                    cacheEssentialData();
                    syncPendingTransactions();
                }

                setInterval(() => {
                    if (isOnline) {
                        syncPendingTransactions();
                    }
                }, 30000);
            });

            if (typeof window.offlineDB === 'undefined') {
                window.offlineDB = offlineDB;
            }
        </script>

</body>
<style>
    @media print {
  #scrollable-container {
    overflow: visible !important;
    height: auto !important;
  }
}
</style>
<style>
    .small-view-side-active {
        display: grid !important;
        z-index: 1000;
        position: absolute;
    }
    .overlay {
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.8);
        position: fixed;
        top: 0;
        left: 0;
        display: none;
        z-index: 20;
    }

    .tw-dw-btn.tw-dw-btn-xs.tw-dw-btn-outline {
        width: max-content;
        margin: 2px;
    }

    #scrollable-container{
        position:relative;
    }
    



</style>

</html>
