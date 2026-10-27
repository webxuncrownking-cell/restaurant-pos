/**
 * Restaurant POS - Isolated Client-Side IndexedDB & Offline Engine
 * Database: RestaurantPOS_DB_{tenantId}_{deviceId}
 */

(function(window) {
  'use strict';

  const PosDB = {
    db: null,
    tenantId: 1,
    deviceId: 1,
    dbName: 'RestaurantPOS_DB_1_1',
    dbVersion: 1,

    // Initialize Database for specific tenant and device
    async init(tenantId, deviceId) {
      this.tenantId = parseInt(tenantId, 10) || 1;
      this.deviceId = parseInt(deviceId, 10) || 1;
      this.dbName = `RestaurantPOS_DB_${this.tenantId}_${this.deviceId}`;

      return new Promise((resolve, reject) => {
        const req = indexedDB.open(this.dbName, this.dbVersion);

        req.onupgradeneeded = (e) => {
          const db = e.target.result;

          // Catalog Store
          if (!db.objectStoreNames.contains('catalog')) {
            db.createObjectStore('catalog', { keyPath: 'id' });
          }

          // Tables Store
          if (!db.objectStoreNames.contains('tables')) {
            db.createObjectStore('tables', { keyPath: 'id' });
          }

          // Offline Orders Queue Store
          if (!db.objectStoreNames.contains('orders_queue')) {
            const orderStore = db.createObjectStore('orders_queue', { keyPath: 'client_uuid' });
            orderStore.createIndex('sequence_number', 'sequence_number', { unique: true });
            orderStore.createIndex('status', 'sync_status', { unique: false });
          }

          // Staff Offline Credentials Store
          if (!db.objectStoreNames.contains('staff')) {
            db.createObjectStore('staff', { keyPath: 'id' });
          }

          // Metadata & Leases Store
          if (!db.objectStoreNames.contains('meta')) {
            db.createObjectStore('meta', { keyPath: 'key' });
          }
        };

        req.onsuccess = (e) => {
          this.db = e.target.result;
          console.log(`[PosDB] Opened IndexedDB store: ${this.dbName}`);
          resolve(this.db);
        };

        req.onerror = (e) => {
          console.error('[PosDB] Failed to open IndexedDB:', e.target.error);
          reject(e.target.error);
        };
      });
    },

    // Transaction helper
    async tx(storeName, mode, callback) {
      if (!this.db) await this.init(this.tenantId, this.deviceId);
      return new Promise((resolve, reject) => {
        const transaction = this.db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);
        let result = null;

        try {
          result = callback(store);
        } catch (err) {
          reject(err);
          return;
        }

        transaction.oncomplete = () => resolve(result);
        transaction.onerror = (e) => reject(e.target.error);
      });
    },

    // Save full catalog snapshot atomically in the local offline database.
    async saveCatalog(products, categories, versionInfo) {
      if (!this.db) await this.init(this.tenantId, this.deviceId);

      return new Promise((resolve, reject) => {
        let transaction;
        try {
          transaction = this.db.transaction(['catalog', 'meta'], 'readwrite');
          const catalogStore = transaction.objectStore('catalog');
          const metaStore = transaction.objectStore('meta');

          catalogStore.clear();
          (Array.isArray(products) ? products : []).forEach(product => catalogStore.put(product));
          metaStore.put({ key: 'catalog_version', value: versionInfo });
          metaStore.put({ key: 'categories', value: Array.isArray(categories) ? categories : [] });

          transaction.oncomplete = () => resolve(true);
          transaction.onerror = () => reject(transaction.error || new Error('Unable to save POS catalog.'));
          transaction.onabort = () => reject(transaction.error || new Error('POS catalog save was aborted.'));
        } catch (error) {
          reject(error);
        }
      });
    },

    // Retrieve full catalog from IndexedDB
    async getCatalog() {
      return new Promise((resolve, reject) => {
        const transaction = this.db.transaction(['catalog', 'meta'], 'readonly');
        const catStore = transaction.objectStore('catalog');
        const metaStore = transaction.objectStore('meta');

        const productsReq = catStore.getAll();
        const categoriesReq = metaStore.get('categories');
        const versionReq = metaStore.get('catalog_version');

        transaction.oncomplete = () => {
          resolve({
            products: productsReq.result || [],
            categories: categoriesReq.result ? categoriesReq.result.value : [],
            version: versionReq.result ? versionReq.result.value : null
          });
        };
        transaction.onerror = (e) => reject(e.target.error);
      });
    },

    // Save Dining Tables
    async saveTables(tables) {
      return this.tx('tables', 'readwrite', (store) => {
        store.clear();
        tables.forEach(t => store.put(t));
      });
    },

    // Get Dining Tables
    async getTables() {
      return new Promise((resolve, reject) => {
        const store = this.db.transaction('tables', 'readonly').objectStore('tables');
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = (e) => reject(e.target.error);
      });
    },

    // Save Authorized Staff
    async saveStaff(staffList) {
      return this.tx('staff', 'readwrite', (store) => {
        store.clear();
        staffList.forEach(s => store.put(s));
      });
    },

    // Get Staff Member
    async getStaff(userId) {
      return new Promise((resolve, reject) => {
        const store = this.db.transaction('staff', 'readonly').objectStore('staff');
        const req = store.get(parseInt(userId, 10));
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = (e) => reject(e.target.error);
      });
    },

    // Save or clear the in-progress ticket draft in IndexedDB so an
    // accidental offline page reload does not lose the current cart.
    async saveDraftTicket(draft) {
      return this.tx('meta', 'readwrite', (store) => {
        if (!draft || !Array.isArray(draft.ticket) || draft.ticket.length === 0) {
          store.delete('draft_ticket');
          return;
        }
        store.put({ key: 'draft_ticket', value: draft });
      });
    },

    // Load the last unfinished ticket draft for this tenant/device.
    async getDraftTicket() {
      if (!this.db) await this.init(this.tenantId, this.deviceId);
      return new Promise((resolve, reject) => {
        const store = this.db.transaction('meta', 'readonly').objectStore('meta');
        const req = store.get('draft_ticket');
        req.onsuccess = () => resolve(req.result ? req.result.value : null);
        req.onerror = () => reject(req.error || new Error('Unable to restore saved POS ticket.'));
      });
    },

    // Save Active Authorization Lease
    async saveLease(leaseData) {
      return this.tx('meta', 'readwrite', (store) => {
        store.put({ key: 'active_lease', value: leaseData });
      });
    },

    // Get Active Authorization Lease
    async getLease() {
      return new Promise((resolve, reject) => {
        const store = this.db.transaction('meta', 'readonly').objectStore('meta');
        const req = store.get('active_lease');
        req.onsuccess = () => resolve(req.result ? req.result.value : null);
        req.onerror = (e) => reject(e.target.error);
      });
    },

    // Monotonic Sequence Number Generator. Resolve after the counter is durable.
    async getNextSequence() {
      return new Promise((resolve, reject) => {
        if (!this.db) {
          reject(new Error('POS local database is not initialized.'));
          return;
        }

        let nextSequence = null;
        let settled = false;
        const transaction = this.db.transaction('meta', 'readwrite');
        const store = transaction.objectStore('meta');
        const req = store.get('last_sequence');

        req.onsuccess = () => {
          const current = Number(req.result && req.result.value) || 0;
          nextSequence = current + 1;
          store.put({ key: 'last_sequence', value: nextSequence });
        };
        req.onerror = () => {
          if (!settled) {
            settled = true;
            reject(req.error || new Error('Unable to read the local order sequence.'));
          }
        };
        transaction.oncomplete = () => {
          if (!settled) {
            settled = true;
            resolve(nextSequence);
          }
        };
        transaction.onerror = () => {
          if (!settled) {
            settled = true;
            reject(transaction.error || new Error('Unable to commit the local order sequence.'));
          }
        };
        transaction.onabort = () => {
          if (!settled) {
            settled = true;
            reject(transaction.error || new Error('The local order sequence transaction was aborted.'));
          }
        };
      });
    },

    // Enqueue an Offline Order
    async queueOrder(orderData) {
      const seq = await this.getNextSequence();
      orderData.sequence_number = seq;
      orderData.sync_status = 'pending';
      orderData.queued_at = new Date().toISOString();

      return new Promise((resolve, reject) => {
        // Commit the sale to the offline queue and discard the live draft in
        // the same transaction. A reload after checkout must not resurrect an
        // already-enqueued ticket and tempt the cashier to submit it twice.
        const transaction = this.db.transaction(['orders_queue', 'meta'], 'readwrite');
        const store = transaction.objectStore('orders_queue');
        const req = store.put(orderData);
        transaction.objectStore('meta').delete('draft_ticket');

        transaction.oncomplete = () => {
          console.log(`[PosDB] Enqueued order #${orderData.order_number} (Seq #${seq})`);
          resolve(orderData);
        };
        transaction.onerror = () => reject(transaction.error || new Error('Unable to save order to the offline queue.'));
        transaction.onabort = () => reject(transaction.error || new Error('Offline order transaction was aborted.'));
        req.onerror = () => {
          try { transaction.abort(); } catch (_) {}
          reject(req.error || new Error('Unable to queue order.'));
        };
      });
    },

    // Get All Pending Unsynced Orders
    async getPendingOrders() {
      return new Promise((resolve, reject) => {
        const store = this.db.transaction('orders_queue', 'readonly').objectStore('orders_queue');
        const req = store.getAll();
        req.onsuccess = () => {
          const all = req.result || [];
          const pending = all.filter(o => o.sync_status === 'pending');
          // Sort by monotonic sequence number ascending
          pending.sort((a, b) => a.sequence_number - b.sequence_number);
          resolve(pending);
        };
        req.onerror = (e) => reject(e.target.error);
      });
    },

    // Mark Order as Synced
    async markOrderSynced(clientUuid, serverOrderId) {
      return new Promise((resolve, reject) => {
        const transaction = this.db.transaction('orders_queue', 'readwrite');
        const store = transaction.objectStore('orders_queue');
        const req = store.get(clientUuid);

        req.onsuccess = () => {
          if (req.result) {
            const item = req.result;
            item.sync_status = 'synced';
            item.server_order_id = serverOrderId;
            item.synced_at = new Date().toISOString();
            store.put(item);
          }
          resolve(true);
        };
        req.onerror = (e) => reject(e.target.error);
      });
    },

    // Emergency JSON Export of Unsynced Orders
    async exportUnsyncedOrdersJSON() {
      const pending = await this.getPendingOrders();
      if (pending.length === 0) {
        alert('No unsynced orders found in offline queue.');
        return;
      }

      const backupPayload = {
        exported_at: new Date().toISOString(),
        tenant_id: this.tenantId,
        device_id: this.deviceId,
        orders_count: pending.length,
        orders: pending
      };

      const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(backupPayload, null, 2));
      const downloadAnchor = document.createElement('a');
      downloadAnchor.setAttribute("href", dataStr);
      downloadAnchor.setAttribute("download", `restaurant_pos_emergency_backup_${this.tenantId}_${this.deviceId}_${Date.now()}.json`);
      document.body.appendChild(downloadAnchor);
      downloadAnchor.click();
      downloadAnchor.remove();
    },

    // PBKDF2-HMAC-SHA256 Client Offline PIN Verification
    async verifyStaffPin(inputPin, saltHex, expectedHashHex) {
      try {
        const enc = new TextEncoder();
        const pinBytes = enc.encode(inputPin);
        
        // Convert hex salt to Uint8Array
        const saltBytes = new Uint8Array(saltHex.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));

        const baseKey = await crypto.subtle.importKey(
          'raw',
          pinBytes,
          { name: 'PBKDF2' },
          false,
          ['deriveBits', 'deriveKey']
        );

        const derivedKeyBits = await crypto.subtle.deriveBits(
          {
            name: 'PBKDF2',
            salt: saltBytes,
            iterations: 10000,
            hash: 'SHA-256'
          },
          baseKey,
          256
        );

        const derivedHex = Array.from(new Uint8Array(derivedKeyBits))
          .map(b => b.toString(16).padStart(2, '0'))
          .join('');

        return derivedHex.toLowerCase() === expectedHashHex.toLowerCase();
      } catch (err) {
        console.error('[PosDB] PBKDF2 verification error:', err);
        return false;
      }
    }
  };

  window.PosDB = PosDB;
})(window);
