/**
 * GuinchaFácil — Fila offline de pontos Proof-of-Road (Pacote L1.4/L2.1).
 *
 * Buffer real em IndexedDB para pontos de GPS que falharam ao enviar
 * (rede indisponível/instável durante o atendimento). Fecha a lacuna
 * documentada em doc/PLANO_IMPLEMENTACAO_100_GUINCHAFACIL.md §4.5:
 * "buffer em IndexedDB quando offline" — antes disso, um ponto que
 * falhava ao enviar era simplesmente perdido (silenciado no .catch()).
 *
 * IndexedDB é por origem (não por aba), então tanto guincho/atendimento.php
 * (que grava pontos) quanto guincho/dashboard.php (que só lê a contagem
 * pendente, ver "Pontos em fila" no card Proof-of-Road) enxergam o mesmo
 * armazenamento — sem precisar de nenhum canal de comunicação entre as
 * páginas.
 */
(function (global) {
    'use strict';

    const DB_NAME = 'guinchafacil_por_offline_queue';
    const DB_VERSION = 1;
    const STORE_NAME = 'points';

    let dbPromise = null;

    function abrirBanco() {
        if (dbPromise) return dbPromise;
        if (!('indexedDB' in global)) {
            dbPromise = Promise.reject(new Error('IndexedDB indisponível neste navegador.'));
            return dbPromise;
        }

        dbPromise = new Promise((resolve, reject) => {
            const req = global.indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'client_point_id' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error || new Error('Falha ao abrir IndexedDB.'));
        });
        return dbPromise;
    }

    function comStore(modo, executar) {
        return abrirBanco().then((db) => new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, modo);
            const store = tx.objectStore(STORE_NAME);
            const resultado = executar(store);
            tx.oncomplete = () => resolve(resultado && resultado.result !== undefined ? resultado.result : resultado);
            tx.onerror = () => reject(tx.error);
        }));
    }

    /** Enfileira um ponto que falhou ao enviar (mantém todos os campos originais do payload). */
    function add(ponto) {
        if (!ponto || !ponto.client_point_id) return Promise.reject(new Error('Ponto sem client_point_id.'));
        return comStore('readwrite', (store) => store.put(Object.assign({ enfileirado_em: Date.now() }, ponto)));
    }

    /** Remove um ponto da fila (usado após reenvio bem-sucedido). */
    function remove(clientPointId) {
        return comStore('readwrite', (store) => store.delete(clientPointId));
    }

    /** Quantidade de pontos pendentes de sincronização — é isso que o card "Proof-of-Road" exibe em "Pontos em fila". */
    function count() {
        if (!('indexedDB' in global)) return Promise.resolve(0);
        return comStore('readonly', (store) => store.count()).catch(() => 0);
    }

    /** Lista todos os pontos pendentes, mais antigos primeiro. */
    function listAll() {
        return abrirBanco().then((db) => new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            const req = store.getAll();
            req.onsuccess = () => resolve((req.result || []).sort((a, b) => (a.enfileirado_em || 0) - (b.enfileirado_em || 0)));
            req.onerror = () => reject(req.error);
        })).catch(() => []);
    }

    /**
     * Tenta reenviar todos os pontos pendentes, um de cada vez, na ordem em
     * que foram enfileirados (preserva a sequência do trajeto). Para no
     * primeiro reenvio que falhar, para não furar a ordem dos pontos.
     * `enviarFn(ponto)` deve devolver uma Promise que resolve em caso de
     * sucesso (o ponto foi aceito pelo servidor) e rejeita em caso de falha.
     */
    async function flush(enviarFn) {
        const pendentes = await listAll();
        let enviados = 0;
        for (const ponto of pendentes) {
            try {
                await enviarFn(ponto);
                await remove(ponto.client_point_id);
                enviados++;
            } catch (e) {
                break; // ainda sem rede/servidor — mantém o restante na fila e tenta de novo depois
            }
        }
        return { enviados, restantes: pendentes.length - enviados };
    }

    global.PorOfflineQueue = { add, remove, count, listAll, flush };
})(window);
