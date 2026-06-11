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
- [x] **Piano a step e gate** per la realizzazione del clone: `piano-implementazione.md` (solo piano, implementazione NON iniziata).

---

## Prossimi passi

### 1. ~~Analisi del codice sorgente via FTP~~ ✅ COMPLETATA (2026-06-11)
Analizzato il backup in `C:\Users\Davide\source\WordpressSites\WordPressSitesBackups\easyRent\easyrent24h.com` (`public_html` + `dbjkglnikefpaj.sql`, prefix `wp39689_`).
Tutto documentato in **`analisi-easyrent24h.md` §10**: disponibilità a fasce orarie 30' vs stock, regole hardcoded per hub/località, "giorno in meno" (riconsegna ≤ 09:30), motore condizioni di prezzo (`condition` + `renroll_price`), extra con prezzi per località, configurazione reale (`unlimited_booking`, `multiple_booking`, ecc.), backend affiliati `backend_rapp`, snippet ("Dashboard Flotta" = solo stub).
L'analisi "black box" (vecchio punto 2) **non serve più**.

### 2. Validazione del piano con Davide ⬅️ PROSSIMO PUNTO
Rivedere insieme **`piano-implementazione.md`** (step e gate): confermare scelte del Gate 0 (Nuxt vs SPA, DB, hosting) e priorità dei moduli.

### 3. Esecuzione del piano (solo dopo OK)
Seguire `piano-implementazione.md`: Step 0 (setup) → Step 1 (modello dati + ETL dal dump) → Step 2–3 (motori prezzo/disponibilità con test "casi d'oro") → Step 4–8. **Nessuno step parte senza il gate approvato.**

---

## Promemoria per riapertura sessione
- Il **login a WordPress non persiste**: alla riapertura va rifatto su `https://www.easyrent24h.com/wp-admin` (user `wp_15703794`), probabile captcha matematico Jetpack.
- Per riprendere la conversazione: `claude --continue` o `claude --resume` da questa cartella.
- I risultati sono comunque su disco in `analisi-easyrent24h.md` e in questo file.
