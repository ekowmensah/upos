const CACHE_NAME = 'upos-v1';
const OFFLINE_URL = '/offline.html';

const CRITICAL_ASSETS = [
    '/',
    '/offline.html',
    '/css/app.css',
    '/js/app.js',
    '/js/offline-db.js',
    '/manifest.json'
];

self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Caching critical assets');
            return cache.addAll(CRITICAL_ASSETS).catch((error) => {
                console.error('[Service Worker] Failed to cache:', error);
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    if (event.request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                    
                    return new Response('Offline', {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({
                            'Content-Type': 'text/plain'
                        })
                    });
                });
            })
    );
});

self.addEventListener('sync', (event) => {
    console.log('[Service Worker] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-transactions') {
        event.waitUntil(syncTransactions());
    }
});

async function syncTransactions() {
    try {
        const db = await openDatabase();
        const transactions = await getPendingTransactions(db);
        
        if (transactions.length === 0) {
            console.log('[Service Worker] No pending transactions to sync');
            return;
        }
        
        console.log(`[Service Worker] Syncing ${transactions.length} transactions`);
        
        for (const transaction of transactions) {
            try {
                const response = await fetch('/api/sync/transaction', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(transaction)
                });
                
                if (response.ok) {
                    await markTransactionSynced(db, transaction.id);
                    console.log('[Service Worker] Transaction synced:', transaction.id);
                }
            } catch (error) {
                console.error('[Service Worker] Failed to sync transaction:', error);
            }
        }
    } catch (error) {
        console.error('[Service Worker] Sync failed:', error);
        throw error;
    }
}

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('upos_offline', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getPendingTransactions(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending-transactions'], 'readonly');
        const store = transaction.objectStore('pending-transactions');
        const index = store.index('synced');
        const request = index.getAll(0);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function markTransactionSynced(db, transactionId) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending-transactions'], 'readwrite');
        const store = transaction.objectStore('pending-transactions');
        const request = store.get(transactionId);
        
        request.onsuccess = () => {
            const data = request.result;
            if (data) {
                data.synced = 1;
                data.syncedAt = Date.now();
                store.put(data);
            }
            resolve();
        };
        request.onerror = () => reject(request.error);
    });
}

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
