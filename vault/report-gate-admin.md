# Report Gate "Admin senza errori" — 11/06/2026

> Gate richiesto dopo la segnalazione di errori durante la navigazione dell'admin.
> Metodo: matrice di test completa → esecuzione e fix iterativo fino a verde → navigazione reale con Playwright → report.

## 1. Matrice di test creata

**`tests/Feature/AdminFullSmokeTest.php`** (4 test) — su **dati reali importati dal sito**:
- [x] Tutte le 12 risorse: pagina **lista** (vehicles, locations, hubs, price-conditions, extras, coupons, bookings, orders, vendors, vendor-payments, contact-messages, settings)
- [x] Tutte le 12 risorse: pagina **crea**
- [x] Tutte le 12 risorse: pagina **modifica del primo record reale** (è qui che emergono i bug di cast json/relazioni/valori legacy)
- [x] Dashboard `/vendor` con il vendor reale importato

**`tests/Feature/AdminCrudTest.php`** (8 test) — i **salvataggi** (Livewire):
- [x] Round-trip di modifica+salvataggio sul primo record reale di tutte le 12 risorse
- [x] Creazione veicolo con località di ritiro collegate
- [x] Creazione **blocco manuale** dal pannello → verifica che chiuda davvero il calendario (AvailabilityEngine)
- [x] Creazione condizione di prezzo
- [x] Creazione coupon, località, pagamento vendor
- [x] Relation manager **Listino**: aggiunta tariffa condizionale a un veicolo
- [x] Azione **"Annulla e rimborsa"** su ordine → bookings cancellati
- [x] Modifica setting JSON → valore riletto correttamente dalla cache

**Navigazione reale (Playwright)**: login, dashboard, tutte le 12 liste, edit veicolo con tab Listino (scroll → caricamento lazy ok), creazione ordine reale via API → tabella ordini → click "Annulla e rimborsa" → conferma modal → stato `refunded` e **date di nuovo disponibili** (verificato via API quote). Console browser: 0 errori dopo il fix.

## 2. Problemi riscontrati e risolti

### P1 — 🔴 `The "intl" PHP extension is required to use the [format] method` (l'errore segnalato)
- **Sintomo**: errore 500 su ogni pagina admin con tabelle che formattano numeri/valute (Vehicles, Orders, …).
- **Causa radice**: sul sistema giravano **9 processi PHP orfani** accumulati dalle sessioni di lavoro; quello che rispondeva sulla porta 8000 era stato avviato **prima** dell'abilitazione di `intl` in `C:\xampp\php\php.ini` (fatta durante l'installazione di Filament). Il dev-server PHP ricarica i file PHP a ogni richiesta ma **non ricarica il php.ini**, quindi continuava a girare senza intl. I "riavvii" non funzionavano perché il vecchio processo manteneva il socket e quello nuovo moriva subito.
- **Fix**:
  1. terminati tutti i processi PHP orfani e verificata porta libera prima del riavvio;
  2. verifica nel processo server: `intl=true`;
  3. **hardening**: aggiunti `ext-intl` e `ext-zip` ai require di `composer.json` (l'installazione fallisce subito su ambienti senza le estensioni invece di esplodere a runtime);
  4. nota di troubleshooting nel README (come riconoscere e uccidere i server orfani).
- **Verificato**: tutte le pagine ricontrollate nel browser, 0 errori console, log pulito.

### P2 — 🟡 Test del relation manager scritto con l'API sbagliata
- Il primo test del Listino usava `callAction('create')` invece di `callTableAction('create')` (in Filament v5 la CreateAction dell'header è un'azione di tabella). Corretto il test; il componente era già funzionante.

### Non-problemi verificati (per completezza)
- **Riquadro vuoto sotto "Save changes"** nell'edit veicolo: è il relation manager **Listino in lazy loading** — si popola quando entra nel viewport (scroll). Comportamento standard Filament, confermato funzionante (tariffa base €49 + bottone "New vehicle price").
- **403 su `/vendor` da utente admin**: voluto — gli accessi ai due pannelli sono separati per ruolo (`canAccessPanel`).
- **404 su `/admin/orders/1/edit`** subito dopo un riseed: non c'erano ordini nel DB di sviluppo, non è un errore.

## 3. Esito

- **Suite completa: 61 test, 229 assertion, tutti verdi** (49 preesistenti + 12 nuovi della matrice admin).
- Browser: navigate tutte le sezioni + 2 flussi interattivi (Listino, Annulla e rimborsa) senza errori.

## 4. Come evitare il problema in futuro
- Un solo comando per il riavvio pulito del dev server:
  `Get-Process php | Stop-Process -Force; php artisan serve`
- In produzione il problema non esiste (php-fpm/nginx ricaricano l'ini al deploy e composer blocca l'install senza estensioni).
