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

### 3. Completamento Gate 3/4 ⬅️ PROSSIMO PUNTO
- Confronto live col sito attuale: 5 combinazioni veicolo/date/località (fasce e totali identici).
- Lighthouse ≥ 90 sul catalogo; meta/hreflang/sitemap.
- Test di concorrenza su MySQL (lock già implementato in `BookingService`).
- Immagini/gallery veicoli (gli ID `_thumbnail_id` sono nell'export; i file stanno in `public_html/wp-content/uploads`).

### 4. Step 5–8 del piano
Checkout/pagamenti (Stripe + PayPal, acconto 25–30% ex AWCDP), email transazionali, admin (Filament: calendario, blocchi, listini), modulo affiliati (riscrittura sicura di `backend_rapp` con QR coupon), contenuti/SEO/redirect 301/cutover.

---

## Promemoria per riapertura sessione
- Il **login a WordPress non persiste**: alla riapertura va rifatto su `https://www.easyrent24h.com/wp-admin` (user `wp_15703794`), probabile captcha matematico Jetpack.
- Per riprendere la conversazione: `claude --continue` o `claude --resume` da questa cartella.
- I risultati sono comunque su disco in `analisi-easyrent24h.md` e in questo file.
