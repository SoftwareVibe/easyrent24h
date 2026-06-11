# Report Gate 3/4 – Verifiche di parità e performance

> Data: 2026-06-11. Tutti i criteri residui dei Gate 3 e 4 verificati.
> Script di confronto: `scripts/live-compare.ps1` (riproducibile).

## 1. Confronto live: sito attuale vs clone — ✅ 5/5 identici

Stesse combinazioni inviate a `templines_calc_total` (easyrent24h.com) e a `POST /api/quote` (clone):

| # | Caso | Live | Clone | Esito |
|---|---|---|---|---|
| 1 | Liberty, Agerola, 21–23/09, senza orari (regola giorno in meno) | days=2, €98, 25 slot 08:00–20:00 | days=2, €98, 25 slot 08:00–20:00 | ✅ |
| 2 | Liberty, Agerola, 21–23/09, riconsegna 10:00 | days=3, €147 | days=3, €147 | ✅ |
| 3 | Vespa Tiffany, Positano, 22/09 in giornata | days=1, €99, start 09:00–19:30 (22 slot), end 09:00–20:00 (23 slot) | identico, anche il numero di slot | ✅ |
| 4 | Liberty, Amalfi, 21–23/09 (località endpoints-only) | days=2, €98, fasce **solo [08:00, 20:00]** | identico | ✅ |
| 5 | Smart Fortwo, Agerola, 21–24/09, riconsegna 18:00 | days=4, €316 | days=4, €316 | ✅ |

Coincidono non solo i totali ma anche le **liste complete di fasce orarie**, incluse le regole sottili (same-day: end parte 30' dopo; finestra Positano dalle 09:00; Amalfi solo apertura/chiusura).

## 2. Concorrenza su MySQL — ✅

Server con 4 worker PHP su MySQL (XAMPP), due `POST /api/bookings` **simultanee** sullo stesso veicolo stock=1, stesse date:
- Richiesta A → `201 Created` (ordine `ER-20260611-…`, totale €147, acconto €36,75)
- Richiesta B → `422 Dates are unavailable`

Il lock pessimistico per veicolo in `BookingService` serializza i checkout: l'overbooking concorrente (possibile sul sito attuale) è eliminato.

## 3. Lighthouse (desktop, build servita con compressione) — ✅

Pagina `/catalog` con dati reali:

| Categoria | Score |
|---|---|
| **Performance** | **99** (target ≥ 90) — FCP 0.4s, LCP ~1s, TBT 0ms |
| Accessibility | 95 |
| Best practices | 100 |
| SEO | 100 |

Interventi fatti per arrivarci: skeleton anti-CLS sul catalogo, prime 3 immagini `eager`+`fetchpriority=high`, immagini convertite in webp 640px (da 6,6 MB a ~0,5 MB totali), label/contrasto/ordine heading sistemati, meta description/OG/canonical, `robots.txt` + `sitemap.xml`.

## 4. Immagini veicoli — ✅

Recuperate dal backup FTP (`wp-content/uploads`) via `_thumbnail_id` → `_wp_attached_file` dal dump SQL: 19 veicoli mappati (16 file, alcuni condivisi), ottimizzate e collegate al seeder (`data/vehicle-images.json` → colonna `gallery`) e alla card del catalogo.

## Note residue (non bloccanti)

- **hreflang/URL localizzati**: la SPA gestisce la lingua client-side; per le URL `/it/`, `/es/` con hreflang servirà il passaggio SSR/prerender previsto allo Step 8 (o Nuxt).
- La live-compare può dare differenze sulle **fasce** se sul sito arrivano prenotazioni reali nelle date di test (i totali restano confrontabili); usare date lontane.
- Lighthouse va eseguito su build servita con gzip (`npx serve -s dist`): `vite preview` non comprime e penalizza ~4 punti.
