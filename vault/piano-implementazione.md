# Piano di implementazione a step e gate – Clone easyRent24h in Laravel + Vue

> Obiettivo: **clone più performante e pulito** del sito easyrent24h.com (noleggio scooter/auto Costiera Amalfitana) con backend **Laravel (PHP)** e frontend **Vue.js**.
> Ogni step termina con un **GATE**: criteri verificabili che devono essere soddisfatti prima di passare allo step successivo.
> Basato su: `analisi-easyrent24h.md` (in particolare §10 – logica esatta del motore estratta dai sorgenti FTP).
> Ultimo aggiornamento: 2026-06-11.

---

## Principi guida (dalle lezioni del sito attuale)

1. **Tutto ciò che oggi è hardcoded diventa configurazione**: hub logistici, fasce orarie per località, veicoli no-same-day, regola "giorno in meno" (09:30), coupon-eccezione, buffer 30', orari globali 08:00–20:00.
2. **Niente tabelle contatore denormalizzate** (`renroll_stock`): la disponibilità si calcola a runtime da `bookings` con query indicizzate (+ cache breve). Più semplice, niente bug di ricalcolo.
3. **Un solo modello Booking** al posto del doppio binario ordine WooCommerce + `renroll_order`.
4. **API stateless documentate** (OpenAPI) consumate da Vue: il motore (prezzo/disponibilità/fasce) è testabile in isolamento.
5. **Parità funzionale prima, migliorie dopo**: il gate finale di ogni modulo è il confronto col comportamento del sito attuale sugli stessi casi.

---

## STEP 0 – Setup progetto e fondamenta
**Attività**
- Repo Git (monorepo: `backend/` Laravel 11+, `frontend/` Vue 3 + Vite + Pinia + Vue Router + vue-i18n).
- Ambienti: locale (Docker/Laragon), staging. CI minima (lint + test).
- Scelta SSR/SEO: Nuxt 3 oppure Vue SPA + prerendering per pagine statiche (decisione al gate).

**GATE 0** ✅ quando:
- [ ] Repo + ambienti avviabili con un comando documentato.
- [ ] Decisione presa e verbalizzata su: Nuxt vs SPA, MySQL vs MariaDB, hosting target.
---

## STEP 1 – Modello dati e migrazione contenuti
**Attività**
- Migrazioni Laravel per le entità (da analisi §8.2 + §10):
  - `vehicles` (ex `catalog`: titolo, sottotitolo, tipo, stock, price_on_request, custom_price_text, sale, gallery, video, descrizione, tradotte EN/IT/ES),
  - `locations` (+ flag `activate_shipping`, `hub_id`, finestra oraria pickup/dropoff per replicare le "fasce per luoghi specifici"),
  - `hubs` (ex liste Agerola/Positano),
  - `features`, `extra_options` (price, type day/total, max_qty, always_included, price_cond),
  - `price_conditions` (ex tassonomia `condition`: days_from/to, days_first, fixed_price, weekdays, days, months, years, locations, locations_dropoff, vehicle_types, from_date, to_date),
  - `vehicle_prices` (vehicle_id, condition_id nullable, price) — ex `renroll_price`,
  - `vehicle_min_max` (o colonne su vehicles) — ex `renroll_min_max`,
  - `bookings` (vehicle_id, order_id nullable, date_start, date_end, ora_inizio, ora_fine, pickup_location_id, dropoff_location_id, quantity, status) — ex `renroll_order` + meta item WooCommerce; i blocchi manuali = booking `type=block`,
  - `vehicle_no_same_day` (o flag su vehicles),
  - `settings` (orario apertura/chiusura, step minuti, soglia giorno-in-meno, buffer minuti, cleaning_days, pickup_dropoff_days, holidays, minimum/maximum_days globali).
- Script **ETL dal dump SQL** `dbjkglnikefpaj.sql`: import veicoli, località, condizioni, listini, extra, traduzioni, storico prenotazioni (per non perdere il calendario).

**GATE 1** ✅ quando:
- [ ] Migrazioni + seeder ETL girano senza errori sul dump reale.
- [ ] Conteggi tornano: ~20 veicoli, 14 località, extra/feature/condizioni/listini completi, storico bookings importato.
- [ ] Spot-check manuale di 3 veicoli (prezzi per condizione identici al wp-admin).
---

## STEP 2 – Motore di pricing (PriceResolver)
**Attività**
- Servizio `PriceResolver`: replica esatta di §10.3 — per ogni giorno del range determina la condizione applicabile (durata, weekday, giorno/mese/anno, stagione from/to, località pickup/dropoff, tipo veicolo, days_first, fixed_price; tie-break sul days_from più alto) e somma i prezzi da `vehicle_prices`, fallback tariffa base.
- Regola **"giorno in meno"**: riconsegna ≤ 09:30 su noleggio multi-giorno → ultimo giorno non fatturato (soglia da `settings`).
- Calcolo extra (type day × giorni / type total una tantum, price_cond, max_qty) e quantità.
- Vincoli: min/max giorni (per veicolo → condizionale → globale), pickup_dropoff_days, holidays.

**GATE 2** ✅ quando:
- [ ] Suite di test unitari con **casi d'oro presi dal sito vivo** (stesse date/veicoli → stesso totale, incluso il caso riconsegna 09:00 vs 10:00).
- [ ] Test su condizioni stagionali e fixed_price.
- [ ] Confronto di 5 preventivi side-by-side col sito attuale.

---

## STEP 3 – Motore di disponibilità e fasce orarie (AvailabilityEngine)
**Attività**
- Servizio che replica §10.5 da `bookings` a runtime:
  - matrice slot 30' per veicolo/giorno (start-day: da ora_inizio a chiusura; end-day: da apertura a ora_fine; giorni pieni in mezzo); slot disponibile se occupazione < stock;
  - check range (blocchi manuali, giorni interni saturi, cleaning_days);
  - fasce proponibili per start/end day + filtri: esclusioni hub (un solo ritiro/consegna per slot per hub), now+buffer per oggi, coerenza inizio<fine same-day, contiguità multi-giorno, finestre per località (Amalfi & co. solo 08:00/20:00, Positano/Praiano/Sorrento dalle 09:00) **da configurazione**, veicoli no-same-day.
- Lock/transazione alla conferma per evitare overbooking concorrente (miglioria rispetto all'attuale).

**GATE 3** ✅ quando:
- [ ] Test unitari sugli scenari: stesso giorno, multi-giorno, riconsegna+rinoleggio stesso giorno, saturazione stock, conflitto hub, eccezione no-same-day.
- [ ] Confronto con il sito vivo: per 5 combinazioni veicolo/date/località le fasce proposte coincidono.
- [ ] Test di concorrenza (2 checkout simultanei sullo stesso ultimo mezzo → uno fallisce).
---

## STEP 4 – API pubbliche + Catalogo/Booking front-end
**Attività**
- API REST (da §8.3 raffinato): `GET /vehicles` (filtri pickup/tipo/feature/prezzo/sort — ricordare: la disponibilità NON filtra la lista, e default pickup = Agerola), `POST /quote` (= calc_total: totale + fasce), `POST /bookings` (= book), `GET /locations`, `GET /extras`.
- Vue: pagine Home/ricerca, catalogo con card veicolo, popup di prenotazione (date range picker, select località pickup/dropoff dal veicolo, select fasce popolate dalla quote API, extra, quantità), i18n EN/IT/ES, valuta EUR.

**GATE 4** ✅ quando:
- [ ] Flusso completo ricerca → catalogo → popup → preventivo identico al sito attuale (stessi 5 casi d'oro del Gate 2/3).
- [ ] Lighthouse: performance ≥ 90 su catalogo (oggi il sito è pesante: questo è il "più performante").
- [ ] SEO di base: meta, hreflang, sitemap.
- [ ] Demo navigabile end-to-end.

---

## STEP 5 – Checkout, pagamenti, ordini
**Attività**
- Checkout custom (niente WooCommerce): dati cliente, riepilogo, **acconto/saldo** (ex plugin deposits — Stripe payment intent parziale o logica deposito propria), Stripe + PayPal, conferma via email (Mailable; ex MailPoet/SMTP).
- Stati ordine/booking allineati: pagato/parziale/cancellato/rimborsato → libera o blocca il calendario (ex `templines_add_booking/remove_booking`).
- Coupon: codici, coupon via URL (ex WooCommerce URL Coupons), validazioni, eccezioni coupon-località (da configurazione).

**GATE 5** ✅ quando:
- [ ] Ordine end-to-end in sandbox Stripe e PayPal (anche con acconto).
- [ ] Cancellazione/rimborso libera le date.
- [ ] Email di conferma corretta nelle 3 lingue.
---

## STEP 6 – Admin (gestionale)
**Attività**
- Pannello (Filament consigliato): CRUD veicoli/listini/condizioni/extra/località/hub/settings, **calendario prenotazioni** con blocchi manuali e prenotazione manuale con orari (parità col Booking Calendar attuale), gestione ordini/rimborsi, dashboard flotta (replica/miglioria dello snippet "Dashboard Flotta" — dettagli dallo studio del dump SQL).

**GATE 6** ✅ quando:
- [ ] Dall'admin si riesce a: aggiungere veicolo con listino stagionale, bloccare date, inserire prenotazione manuale, cambiare le finestre orarie di una località **senza toccare codice**.
---

## STEP 7 – Modulo affiliati/rappresentanti
**Attività**
- Riscrittura di `backend_rapp` dentro Laravel: ruoli admin/vendor, coupon assegnati, percentuali commissione, report ordini per vendor, registrazione/storico pagamenti, generazione QR code coupon, email. Sanare le falle attuali (MD5, SQL injection, no CSRF) con auth Laravel standard.

**GATE 7** ✅ quando:
- [ ] Un vendor di prova vede solo i propri ordini/commissioni; QR generato e funzionante su un coupon reale.
- [ ] Import storico pagamenti/coupon dal vecchio DB.
---

## STEP 8 – Contenuti, SEO e cutover
**Attività**
- Pagine statiche (chi siamo, FAQ, contatti, policy iubenda, WhatsApp widget), redirect 301 dalla struttura URL WordPress, sitemap/hreflang definitive, Analytics.
- Migrazione finale dati (delta bookings), freeze del vecchio sito, switch DNS, monitoraggio.
- Piano di rollback documentato.

**GATE 8 (go-live)** ✅ quando:
- [ ] Checklist redirect 301 verificata sulle URL indicizzate principali.
- [ ] Prova generale su staging con dati reali aggiornati.
- [ ] Backup + rollback testato.
---

## Rischi principali e mitigazioni

| Rischio | Mitigazione |
|---|---|
| Differenze sottili di prezzo/fasce rispetto al sito attuale | "Casi d'oro" registrati dal sito vivo come test di regressione (Gate 2/3/4) |
| Perdita prenotazioni durante il cutover | Import delta + finestra di freeze breve (Gate 8) |
| Logica nascosta negli snippet DB / theme mods | ✅ Già estratta (analisi §10.9): snippet attivi solo Google Ads + stub "Dashboard Flotta"; theme mods censiti |
| Overbooking concorrente (oggi non gestito) | Lock transazionale al Gate 3 |
| SEO: perdita ranking | Redirect 301 + parità meta/hreflang + sitemap (Gate 8) |

---

## Ordine di esecuzione e dipendenze

```
STEP 0 → STEP 1 → STEP 2 ─┐
                 └→ STEP 3 ─┴→ STEP 4 → STEP 5 → STEP 6 → STEP 8
                                          STEP 7 (parallelo a 6, dopo 5)
```

> **Nota**: i gate sono auto-verificabili (test e checklist); il documento si aggiorna a ogni gate superato.
