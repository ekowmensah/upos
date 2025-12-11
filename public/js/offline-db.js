class OfflineDatabase {
    constructor() {
        this.dbName = 'upos_offline';
        this.version = 1;
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = () => {
                console.error('Failed to open IndexedDB:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('IndexedDB opened successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('products')) {
                    const productStore = db.createObjectStore('products', { keyPath: 'id' });
                    productStore.createIndex('name', 'name', { unique: false });
                    productStore.createIndex('sku', 'sku', { unique: false });
                    productStore.createIndex('cached_at', 'cached_at', { unique: false });
                }

                if (!db.objectStoreNames.contains('customers')) {
                    const customerStore = db.createObjectStore('customers', { keyPath: 'id' });
                    customerStore.createIndex('name', 'name', { unique: false });
                    customerStore.createIndex('mobile', 'mobile', { unique: false });
                    customerStore.createIndex('cached_at', 'cached_at', { unique: false });
                }

                if (!db.objectStoreNames.contains('pending-transactions')) {
                    const txStore = db.createObjectStore('pending-transactions', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    txStore.createIndex('created_at', 'created_at', { unique: false });
                    txStore.createIndex('synced', 'synced', { unique: false });
                    txStore.createIndex('business_id', 'business_id', { unique: false });
                }

                if (!db.objectStoreNames.contains('sync-history')) {
                    const syncStore = db.createObjectStore('sync-history', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                    syncStore.createIndex('status', 'status', { unique: false });
                }

                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }

                console.log('IndexedDB schema created/upgraded');
            };
        });
    }

    async cacheProducts(products) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['products'], 'readwrite');
        const store = transaction.objectStore('products');
        const timestamp = Date.now();

        for (const product of products) {
            product.cached_at = timestamp;
            await this.promisifyRequest(store.put(product));
        }

        await this.setSetting('products_cached_at', timestamp);
        console.log(`Cached ${products.length} products`);
        return timestamp;
    }

    async cacheCustomers(customers) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['customers'], 'readwrite');
        const store = transaction.objectStore('customers');
        const timestamp = Date.now();

        for (const customer of customers) {
            customer.cached_at = timestamp;
            await this.promisifyRequest(store.put(customer));
        }

        await this.setSetting('customers_cached_at', timestamp);
        console.log(`Cached ${customers.length} customers`);
        return timestamp;
    }

    async getProducts(searchTerm = '') {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['products'], 'readonly');
        const store = transaction.objectStore('products');
        const products = await this.promisifyRequest(store.getAll());

        if (!searchTerm) {
            return products;
        }

        const search = searchTerm.toLowerCase();
        return products.filter(product => 
            product.name.toLowerCase().includes(search) ||
            (product.sku && product.sku.toLowerCase().includes(search))
        );
    }

    async getCustomers(searchTerm = '') {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['customers'], 'readonly');
        const store = transaction.objectStore('customers');
        const customers = await this.promisifyRequest(store.getAll());

        if (!searchTerm) {
            return customers;
        }

        const search = searchTerm.toLowerCase();
        return customers.filter(customer => 
            customer.name.toLowerCase().includes(search) ||
            (customer.mobile && customer.mobile.includes(search))
        );
    }

    async savePendingTransaction(transactionData) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['pending-transactions'], 'readwrite');
        const store = transaction.objectStore('pending-transactions');

        const data = {
            ...transactionData,
            created_at: Date.now(),
            synced: 0,
            syncedAt: null
        };

        const id = await this.promisifyRequest(store.add(data));
        console.log('Saved pending transaction:', id);
        return id;
    }

    async getPendingTransactions() {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['pending-transactions'], 'readonly');
        const store = transaction.objectStore('pending-transactions');
        const index = store.index('synced');
        
        return await this.promisifyRequest(index.getAll(0));
    }

    async markTransactionSynced(transactionId, serverResponse = {}) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['pending-transactions'], 'readwrite');
        const store = transaction.objectStore('pending-transactions');
        
        const data = await this.promisifyRequest(store.get(transactionId));
        if (data) {
            data.synced = 1;
            data.syncedAt = Date.now();
            data.serverResponse = serverResponse;
            await this.promisifyRequest(store.put(data));
            
            await this.addSyncHistory({
                transaction_id: transactionId,
                status: 'success',
                timestamp: Date.now(),
                response: serverResponse
            });
        }
    }

    async deletePendingTransaction(transactionId) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['pending-transactions'], 'readwrite');
        const store = transaction.objectStore('pending-transactions');
        await this.promisifyRequest(store.delete(transactionId));
    }

    async addSyncHistory(historyData) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['sync-history'], 'readwrite');
        const store = transaction.objectStore('sync-history');
        
        return await this.promisifyRequest(store.add(historyData));
    }

    async getSyncHistory(limit = 10) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['sync-history'], 'readonly');
        const store = transaction.objectStore('sync-history');
        const index = store.index('timestamp');
        
        const history = await this.promisifyRequest(index.getAll());
        return history.reverse().slice(0, limit);
    }

    async setSetting(key, value) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['settings'], 'readwrite');
        const store = transaction.objectStore('settings');
        
        await this.promisifyRequest(store.put({ key, value }));
    }

    async getSetting(key, defaultValue = null) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction(['settings'], 'readonly');
        const store = transaction.objectStore('settings');
        
        const result = await this.promisifyRequest(store.get(key));
        return result ? result.value : defaultValue;
    }

    async getStats() {
        if (!this.db) await this.init();

        const productsCount = await this.getCount('products');
        const customersCount = await this.getCount('customers');
        const pendingCount = await this.getCount('pending-transactions');
        const syncHistoryCount = await this.getCount('sync-history');

        return {
            products: productsCount,
            customers: customersCount,
            pendingTransactions: pendingCount,
            syncHistory: syncHistoryCount,
            lastProductsCache: await this.getSetting('products_cached_at'),
            lastCustomersCache: await this.getSetting('customers_cached_at')
        };
    }

    async getCount(storeName) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        
        return await this.promisifyRequest(store.count());
    }

    async clearStore(storeName) {
        if (!this.db) await this.init();

        const transaction = this.db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        
        await this.promisifyRequest(store.clear());
        console.log(`Cleared ${storeName} store`);
    }

    async clearAllData() {
        if (!this.db) await this.init();

        const storeNames = ['products', 'customers', 'pending-transactions', 'sync-history', 'settings'];
        
        for (const storeName of storeNames) {
            await this.clearStore(storeName);
        }
        
        console.log('All offline data cleared');
    }

    promisifyRequest(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async close() {
        if (this.db) {
            this.db.close();
            this.db = null;
        }
    }
}

const offlineDB = new OfflineDatabase();
