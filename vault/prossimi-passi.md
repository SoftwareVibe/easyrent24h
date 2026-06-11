# Prossimi passi – Progetto replica easyRent24h

> Roadmap delle attività da completare per arrivare alla replica in **Laravel + Vue**.
> Documento di analisi principale: [`analisi-easyrent24h.md`](./analisi-easyrent24h.md)
> Ultimo aggiornamento: 2026-06-10.

---

## Stato attuale
- [x] Navigazione completa front-end pubblico (Playwright).
- [x] Accesso e analisi back-end WordPress (`wp-admin`).
- [x] Documento di analisi funzionale/tecnica + blueprint Laravel/Vue (`analisi-easyrent24h.md`).
- [x] **Analisi sorgenti FTP + dump SQL completata (2026-06-11)** → logica esatta del motore documentata in `analisi-easyrent24h.md` **§10** (prezzi, disponibilità a fasce 30', regole per località/hub, coupon, affiliati, configurazione reale dal DB).
- [x] **Piano a step e gate** per la realizzazione del clone: `piano-implementazione.md`.
- [x] **Implementazione Step 0–4 (11/06/2026)**: monorepo Laravel 12 + Vue 3 su GitHub (`SoftwareVibe/easyrent24h`), ETL dai dati reali, motori prezzo/disponibilità con 34 test verdi, API + SPA con flusso completo.

---

## Prossimi passi

### 1. ~~Analisi del codice sorgente via FTP~~ ✅ COMPLETATA (2026-06-11)
Analizzato il backup in `C:\Users\Davide\source\WordpressSites\WordPressSitesBackups\easyRent\easyrent24h.com` (`public_html` + `dbjkglnikefpaj.sql`, prefix `wp39689_`).
Tutto documentato in **`analisi-easyrent24h.md` §10**: disponibilità a fasce orarie 30' vs stock, regole hardcoded per hub/località, "giorno in meno" (riconsegna ≤ 09:30), motore condizioni di prezzo (`condition` + `renroll_price`), extra con prezzi per località, configurazione reale (`unlimited_booking`, `multiple_booking`, ecc.), backend affiliati `backend_rapp`, snippet ("Dashboard Flotta" = solo stub).
L'analisi "black box" (vecchio punto 2) **non serve più**.

### 2. ~~Esecuzione Step 0–4~~ ✅ FATTO (11/06/2026)
Monorepo `backend/` (Laravel 12) + `frontend/` (Vue 3) su GitHub `SoftwareVibe/easyrent24h`.
Modello dati + seeder ETL da `data/catalog-export.json` (14 località, 31 condizioni, 5 extra, 19 veicoli), `PriceResolver`/`AvailabilityEngine`/`QuoteService`/`BookingService` (34 test verdi sui casi d'oro), API REST e SPA con flusso ricerca → catalogo → preventivo live → prenotazione. Stato dettagliato nell'intestazione di `piano-implementazione.md`.

### 3. ~~Completamento Gate 3/4~~ ✅ FATTO (11/06/2026) — vedi `gate-3-4-report.md`
- Confronto live: **5/5 identici** (totali E liste fasce) — script riproducibile in `scripts/live-compare.ps1`.
- Lighthouse catalogo: **performance 99**, SEO 100, best-practices 100, a11y 95; robots/sitemap/meta OG aggiunti.
- Concorrenza su MySQL: 2 prenotazioni simultanee su stock 1 → una 201, una 422. Lock ok.
- Immagini veicoli recuperate dall'FTP, ottimizzate (webp 640px, 6,6 MB → ~0,5 MB) e in card.
- Residuo rimandato allo Step 8: hreflang/URL localizzati (richiede SSR/prerender).

### 4. ~~Step 5 — Checkout e pagamenti~~ ✅ IMPLEMENTATO (11/06/2026)
- Acconto 25% configurabile (ex AWCDP); flusso `pending → deposit_paid → paid`; annullo/rimborso libera le date (testato).
- Driver: **Stripe** (Payment Intents + webhook `payment_intent.succeeded`), **PayPal** (Orders v2, create→approve→capture), **offline** (test/paga al ritiro).
- Coupon con sconto % e **eccezioni coupon-hub** (bartoloparcheggio bloccato su hub Agerola — testato); endpoint `POST /api/coupons/validate`.
- Email conferma EN/IT/ES (markdown mailable, testate tutte e tre + e2e nel log).
- SPA: pagina `/checkout/:numero` con scelta acconto/totale, Stripe Payment Element, redirect PayPal, riepilogo ordine; campo coupon nel popup.
- **Residuo per chiudere il Gate 5**: inserire le chiavi sandbox in `backend/.env` (`STRIPE_SECRET`, `STRIPE_KEY`, `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`) e fare un giro di prova con carta test `4242…` e conto sandbox PayPal. ⚠️ Servono le chiavi di Davide.

### 5. ~~Step 6–8~~ ✅ IMPLEMENTATI (11/06/2026)
- **Admin** `/admin` (Filament v5, utente `davidesaiano1@gmail.com` / `EasyRent24h!` — **cambiare password**): veicoli+listino, località con finestre orarie, blocchi manuali, ordini con annulla/rimborsa, coupon, settings.
- **Affiliati** `/vendor`: pannello rappresentanti con ordini/commissioni propri, QR coupon (`/qr/coupon/CODICE.svg`), 65 coupon reali importati.
- **Cutover preparato**: `deploy/nginx-redirects.conf` + `deploy/cutover-checklist.md`, pagine statiche, WhatsApp, hreflang.

### 6. Cose che servono da Davide per chiudere tutto ⬅️ PROSSIMO PUNTO
1. **Chiavi sandbox/live Stripe e PayPal** in `backend/.env` → test Gate 5 e produzione.
2. **Server di produzione** (PHP 8.2 + MySQL + nginx) → eseguire la checklist di cutover (Gate 8).
3. Anagrafiche reali dei **vendor** (nome, email, coupon, %) → creare gli account `/vendor`.
4. Cambiare la password admin e decidere l'indirizzo email mittente (SMTP).

---

## Promemoria per riapertura sessione
- Il **login a WordPress non persiste**: alla riapertura va rifatto su `https://www.easyrent24h.com/wp-admin` (user `wp_15703794`), probabile captcha matematico Jetpack.
- Per riprendere la conversazione: `claude --continue` o `claude --resume` da questa cartella.
- I risultati sono comunque su disco in `analisi-easyrent24h.md` e in questo file.
