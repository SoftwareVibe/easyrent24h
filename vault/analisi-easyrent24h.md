# Analisi funzionale e tecnica – easyRent24h.com

> Documento di analisi propedeutico alla **replica in PHP Laravel (backend/API) + Vue.js (frontend)**.
> Sito analizzato: `https://www.easyrent24h.com` — noleggio scooter/Vespa e auto sulla Costiera Amalfitana.
> Analisi condotta via Playwright (front-end pubblico) + accesso `wp-admin` (back-end).
> Data analisi: 2026-06-10.

---

## 1. Cos'è l'applicativo (in una frase)

Piattaforma di **noleggio veicoli (scooter, Vespa, auto)** con **prenotazione a calendario** (date + fasce orarie), **ritiro/riconsegna in località diverse** sulla Costiera Amalfitana, **opzioni extra** (consegna a domicilio, action cam, gadget), **carrello e checkout e-commerce** con pagamento online (Stripe/PayPal), **acconti/pagamenti parziali**, **multilingua** (EN/IT/ES) e **programma affiliati B2B**.

A livello tecnico è un **WordPress** costruito sul tema commerciale **RenRoll** (motore di noleggio `Renroll Core`) integrato con **WooCommerce**.

---

## 2. Stack tecnologico attuale (WordPress)

| Area | Tecnologia |
|---|---|
| CMS | WordPress |
| Tema / motore noleggio | **RenRoll** (`wp-theme-renroll`) + plugin **Renroll Core 7.9** (autore *templines*) |
| E-commerce | **WooCommerce 10.6** |
| Acconti/pagamenti parziali | **Deposits & Partial Payments for WooCommerce** (`awcdp`) |
| Pagamenti | **Stripe**, **PayPal Payments (ppcp)**, **WooPayments** |
| Coupon/affiliati | **WooCommerce URL Coupons** |
| Multilingua/multivaluta | **WPML** (CMS + String + Media) + **WPML Multicurrency for WooCommerce** (EN/IT/ES, EUR) |
| Campi custom | **Meta Box** (`rwmb`) |
| Page builder | **Elementor + Elementor Pro** |
| Form | **Contact Form 7** (+ multilingue), WPForms Lite |
| Email marketing | **MailPoet** |
| Email transazionali | **WP Mail SMTP** |
| Verifica email cliente | **Customer Email Verification for WooCommerce** |
| Logica PHP custom | **Code Snippets** (snippet "Dashboard Flotta", "acquisti ads") |
| SEO | **Yoast SEO** (sitemap, meta) |
| Recensioni | **Rich Showcase for Google Reviews** + badge custom "ER24H Badge" (5.0 / 341 recensioni) |
| Chat | **Chaty** (widget WhatsApp) |
| Privacy/cookie | **iubenda** (GDPR/CCPA) |
| Anti-bot | **Jetpack Protect** (captcha matematico al login) |
| Performance | SiteGround **Speed Optimizer**, Jetpack Boost |
| Analytics | Google **Site Kit**, Analytics for WooCommerce, TikTok, Pinterest |
| Slider | Slider Revolution |

> 57 plugin installati, 39 attivi. Hosting: SiteGround.

---

## 3. Mappa del sito (pagine pubbliche)

### Pagine istituzionali / contenuto
- `/` — **Home**: hero slider (3 slide), motore di ricerca prenotazione, flotta in evidenza, recensioni, CTA WhatsApp.
- `/about-us/` — **Chi siamo**: storia, 2 sedi fisiche (Positano – Viale Pasitea 1, Piazza dei Mulini; Agerola – Piazza Unità d'Italia 12, Pianillo), USP (no deposito, consegna gratuita, mezzi nuovi).
- `/contact/` — **Contatti**: form CF7 (nome, email, telefono, oggetto, messaggio) + tel/email/WhatsApp.
- `/tour/` — **Vespa Tour guidato**: landing con itinerario (Amalfi, Atrani, Positano, Praiano, Fiordo di Furore, Grotta dello Smeraldo), "Request availability", incluso/non incluso.
- `/affiliate/` — **Programma affiliati B2B**: registrazione azienda (ragione sociale, CF/P.IVA, telefono, indirizzo) + login + coupon.
- `/news/` — **Blog**: guide SEO per destinazione (*Amalfi/Praiano/Positano/Agerola/Furore by Scooter*).
- `/gadget/` — Merchandising/upsell (es. portachiavi Vespa) da aggiungere al noleggio.
- `/terms-of-use/`, `/terms-and-conditions/`, privacy/cookie (iubenda).

### Pagine funzionali (e-commerce / noleggio)
- `/catalog/` — **Catalogo/ricerca prenotazione** (cuore dell'app). Redirect automatico con `?pickup=<id>`.
- `/catalog/<slug-veicolo>/` — Dettaglio veicolo (accessibile dal flusso con contesto pickup/date).
- `/cart-2/` — **Carrello** WooCommerce (+ coupon).
- `/checkout-2/` — **Checkout** (redirect a carrello se vuoto).
- `/my-account-2/` — **Area cliente**: login + registrazione con campi fiscali italiani.
- `/shop-2/` — Shop WooCommerce.

### Multilingua (WPML)
Ogni pagina esiste in **EN** (default, root), **IT** (`/it/...`) e **ES** (`/es/...`). Es. `/my-account-2/`, `/it/my-account/`, `/es/mi-cuenta/`.

---

## 4. Modello dati (entità principali)

### 4.1 Veicolo (`catalog` – custom post type, ~20 record)
Ogni veicolo è un post `catalog` **collegato a un prodotto WooCommerce** (`data-product-id`), che gestisce prezzo a carrello e checkout.

Campi (Meta Box) rilevati nell'editor veicolo:

| Campo | Tipo | Note |
|---|---|---|
| `title` | testo | Nome veicolo (es. "Smart Fortwo", "Vespa Primavera") |
| `subheader` | testo | Sottotitolo |
| `vehicle_type` | tassonomia (singola) | **Scooter** / **Cars** (auto) |
| `location[]` | tassonomia (multi) | Località di **ritiro** ammesse |
| `location_drop[]` | tassonomia (multi) | Località di **riconsegna** (= ritiro se vuoto) |
| `price` | decimale (€) | Tariffa base **/ giorno** |
| `price_cond[]` | regole | **Prezzo condizionale** (per durata/stagione – "Manage conditions") |
| `price_on_request` | bool | "Prezzo su richiesta" |
| `custom_price_text` | testo | Testo alternativo al prezzo nella griglia |
| `sale` | testo | Badge sconto |
| `stock` | intero | **Quantità a magazzino** (es. 1) per la disponibilità |
| `feature[]` | tassonomia | Caratteristiche (vedi 4.4) |
| `extra_option[]` | tassonomia | Extra disponibili (vedi 4.3) |
| `image_gallery` | galleria | Immagini |
| `video_url` | testo | YouTube |
| booking/availability | calendario | `templines-renroll-booking` – disponibilità per date |

### 4.2 Località (`location` – tassonomia, 14 termini)
Agerola, Amalfi, Atrani, C/mare di Stabia, Conca dei Marini, Furore, Gragnano, Maiori, Minori, Pimonte, Pompei, Positano, Praiano, Sorrento.
Ogni località ha un **indirizzo** associato (mostrato nel form: `js-location-address`). Usata sia per ritiro che riconsegna; combinazioni diverse → possibile sovrapprezzo via extra.

### 4.3 Extra options (`extra_option` – tassonomia con campi custom)
Campi per termine: **Tipo** (`total` = forfait sull'intero noleggio), **Prezzo**, **Max quantità**.

| Extra | Slug | Tipo | Prezzo | Max |
|---|---|---|---|---|
| Insta360 X3 (action cam) | gopro-en | total | €59 | 1 |
| Home Delivery (consegna) | price-pick-up-en | total | €25 | 1 |
| Home Pickup (ritiro a domicilio) | price-drop-off | total | €25 | 1 |
| Gadget | gadget-en | total | €15 | 2 |

> Selezionando **Home Pickup/Delivery**, al checkout viene richiesto l'indirizzo; altrimenti viene mostrato il punto di ritiro più vicino.

### 4.4 Features (`feature` – tassonomia, con icona)
Caratteristiche con icona mostrate sulla scheda veicolo. Esempi rilevati:
- Patente richiesta (A/B, A1 o B, B)
- Alimentazione (Benzina, Elettrico, Ibrido)
- Cilindrata (125cc)
- Cambio (Automatico / Manuale)
- Posti (2 / 4 / 5)
- Bagagli (1 valigia + 1 bagaglio a mano / 2 valigie)
- Autonomia (~75 km), Ricarica (presa domestica)
- Aria condizionata, Caschi + top box inclusi, supporto telefono/GPS, USB
- Deposito cauzionale (es. €300), Limite km/giorno (es. 100 km)

### 4.5 Altre entità
- **Prodotto WooCommerce** (`product`): controparte commerciale del veicolo (prezzo, cart, ordine).
- **Ordine WooCommerce**: prenotazione confermata (con date, località, extra, eventuale acconto).
- **Cliente / Utente**: con campi extra (Cognome, Agency/`aziendaInsegna`, **Codice Fiscale/P.IVA**, telefono, indirizzo IT: via/cap/comune/provincia/stato, flag `isAffiliato`).
- **Affiliato**: cliente B2B con coupon dedicato (commissioni via URL Coupons).
- **HTML Block** (`html_block`): blocchi riusabili (footer, seo-text, product-bottom).
- **Post/News** + categorie (events, non-categorizzato).

---

## 5. Flusso di prenotazione (core UX)

1. **Ricerca** (home o `/catalog/`): l'utente sceglie **Tipo** (All/Cars/Scooters), **Pickup location**, **range di date** (formato `GG.MM.AAAA`), filtro **prezzo** (slider €0–€120).
2. **Lista flotta**: card veicolo (`.c-vehicle`) con immagine, features con icone, "**Rent from € X / day**" e bottone **Continue** (`js-get-booking-form`, `data-id` = product id).
3. **Form di prenotazione** (popup, caricato via AJAX):
   - Range date (`start`/`end`) + **fasce orarie** ritiro/riconsegna (`fasciaOraStart`/`fasciaOraEnd`).
   - **Start Location** (`pick_up`) e **End Location** (`drop_off`).
   - **Quantità**.
   - **Extra options** (checkbox: Home Delivery, Home Pickup, Insta360, Gadget) con prezzo e max.
   - **Controllo disponibilità**: messaggio "Dates are available / unavailable" (verifica stock per date).
4. **Calcolo prezzo** via AJAX (`action=templines_calc_total`):
   `Totale = (tariffa_giorno × num_giorni × quantità) + Σ extra`
   con riepilogo: *Rental price (€/day × giorni) + Extra options + Total*.
5. **Continue** → aggiunge al **carrello WooCommerce**.
6. **Carrello** (`/cart-2/`): riepilogo, coupon.
7. **Checkout** (`/checkout-2/`): dati cliente/fatturazione, eventuale **acconto/pagamento parziale**, indirizzo se consegna a domicilio.
8. **Pagamento**: **Stripe** / **PayPal** / **WooPayments** (valuta EUR; multivaluta WPML).
9. **Ordine** creato + email (verifica email cliente, WP Mail SMTP) → gestione lato admin.

> Esiste anche un percorso "soft" via **Contact Form 7** (campo nascosto `order-details`, `your-name`, `your-email`, `order-notes`) per richieste/preventivi, oltre al canale **WhatsApp** (Chaty) molto spinto nel marketing.

---

## 6. Funzionalità per area (riepilogo per la replica)

**Front-office cliente**
- Ricerca veicoli per tipo, località, date, prezzo.
- Catalogo/flotta con schede ricche (features con icone, gallery, video, badge sconto).
- Booking widget con disponibilità a calendario, fasce orarie, ritiro/riconsegna multi-località.
- Extra a pagamento (forfait, con max quantità).
- Prezzi dinamici (per giorno + regole condizionali per durata/stagione).
- Carrello, coupon, checkout, acconti/pagamenti parziali.
- Pagamenti Stripe/PayPal.
- Area cliente (ordini, dati, indirizzi, dati fiscali IT).
- Multilingua EN/IT/ES, multivaluta.
- Tour guidato (prodotto/esperienza separata con richiesta disponibilità).
- Merchandising/gadget come upsell.
- Blog/guide SEO per destinazione.
- Recensioni Google, chat WhatsApp, newsletter (MailPoet).

**Back-office / gestione**
- CRUD veicoli (catalog) con prezzo, stock, località, features, extra, gallery.
- **Calendario disponibilità/flotta** (Renroll `templines_calendar`, snippet "Dashboard Flotta").
- Gestione tassonomie: località (con indirizzi), features (con icone), extra (tipo/prezzo/max), tipi veicolo.
- Gestione ordini/prenotazioni WooCommerce.
- Gestione acconti/depositi.
- Gestione coupon e **affiliati B2B**.
- Gestione clienti (con dati fiscali, ruolo affiliato).
- Traduzioni (WPML) e contenuti (Elementor, HTML blocks).
- SEO (Yoast), email, marketing.

---

## 7. Integrazioni esterne
- **Stripe**, **PayPal**, **WooPayments** (pagamenti).
- **Google**: Site Kit, Analytics, Google Reviews, Google for WooCommerce (Merchant).
- **TikTok**, **Pinterest** (catalogo prodotti/ads).
- **MailPoet** (newsletter), **WP Mail SMTP** (invio email).
- **iubenda** (consenso cookie/privacy).
- **WhatsApp** (Chaty, link `wa.me`).
- Contatti: tel `+39 377 0904439`, email `easyrent24h@gmail.com`.

---

## 8. Blueprint per la replica in **Laravel + Vue**

### 8.1 Architettura proposta
- **Backend**: Laravel (API REST/JSON, o Inertia se si preferisce monolite). Auth con Sanctum (clienti + admin). Pannello admin con **Filament** o Nova (sostituisce wp-admin/Renroll).
- **Frontend**: Vue 3 (Vite, Pinia, Vue Router, i18n). SSR opzionale con Nuxt per la SEO (importante: il sito vive di SEO locale).
- **Pagamenti**: Laravel Cashier (Stripe) + SDK PayPal.
- **i18n/multivaluta**: `vue-i18n` + tabelle traduzioni; valuta singola EUR (multivaluta opzionale).
- **Media**: spatie/media-library per gallery veicoli.

### 8.2 Modello dati (schema Laravel sintetico)

```
vehicles
  id, name, slug, subheader, vehicle_type_id, price_per_day:decimal,
  price_on_request:bool, custom_price_text, sale_badge, stock:int,
  description, video_url, is_active, sort_order, timestamps

vehicle_types            (id, name, slug)              // scooter | car
locations                (id, name, slug, address, lat, lng, sort_order)
features                 (id, name, slug, icon)        // icona/immagine
extra_options            (id, name, slug, type, price:decimal, max_qty:int)

vehicle_feature          (vehicle_id, feature_id)              // pivot
vehicle_extra_option     (vehicle_id, extra_option_id)         // pivot
vehicle_pickup_location  (vehicle_id, location_id)             // ritiro
vehicle_dropoff_location (vehicle_id, location_id)             // riconsegna

vehicle_media            (id, vehicle_id, path, type, position)

price_conditions         (id, vehicle_id, min_days, max_days,
                          date_from, date_to, price)            // prezzo condizionale

vehicle_availability     (id, vehicle_id, date, units_booked)   // o blocchi start/end
  // disponibilità = stock - prenotazioni che si sovrappongono al range

bookings
  id, code, customer_id, vehicle_id, start_at, end_at,
  pickup_location_id, dropoff_location_id, pickup_time_slot,
  dropoff_time_slot, quantity, delivery_address,
  rental_subtotal, extras_total, total, deposit_amount, status,
  payment_status, payment_provider, coupon_id, language, currency, timestamps

booking_extra            (booking_id, extra_option_id, qty, price)

customers / users
  id, first_name, last_name, email(verified), phone, company_name,
  tax_code (CF/P.IVA), address(via,cap,comune,provincia,stato),
  is_affiliate:bool, role

coupons                  (id, code, type, value, affiliate_id, ...)
affiliates               (id, user_id, company, vat, commission, ...)
tours                    (id, title, itinerary, included, not_included, ...)
products (gadget)        (id, name, price, ...)                 // upsell
posts (blog/news), pages (cms), html_blocks, translations
```

### 8.3 Endpoint API principali (esempio)
```
GET   /api/vehicles?type=&pickup=&start=&end=&min_price=&max_price=
GET   /api/vehicles/{slug}
POST  /api/booking/quote          // = templines_calc_total: calcola totale + disponibilità
POST  /api/booking                // crea prenotazione → carrello
GET   /api/locations
GET   /api/extra-options
POST  /api/cart / GET /api/cart
POST  /api/checkout               // crea ordine + intent pagamento (Stripe/PayPal)
POST  /api/coupon/apply
POST  /api/auth/register|login    // Sanctum, con campi fiscali
GET   /api/account/bookings
POST  /api/contact                // form contatti
POST  /api/tour/request           // richiesta disponibilità tour
```

### 8.4 Componenti Vue principali
- `SearchBar` (tipo, località pickup, datepicker range, slider prezzo).
- `VehicleCard` / `VehicleGrid` (features con icone, prezzo "from €X/day").
- `BookingModal` (date+fasce orarie, pickup/dropoff, quantità, extra, calcolo live del totale via `/booking/quote`, badge disponibilità).
- `CartView`, `CheckoutView` (dati cliente, indirizzo consegna, acconto, pagamento).
- `AccountDashboard` (prenotazioni, profilo, dati fiscali).
- `TourLanding`, `BlogList`/`BlogPost`, `AffiliateForm`, `ContactForm`.
- `LanguageSwitcher`, `WhatsAppButton`, `ReviewsBadge`.

### 8.5 Logiche di business da replicare (attenzione particolare)
1. **Disponibilità**: per ogni veicolo, `stock` (unità) meno le prenotazioni che si sovrappongono al range richiesto → disponibile/non disponibile per quelle date.
2. **Calcolo prezzo**: `tariffa_giorno × giorni × quantità + Σ extra`, con **regole di prezzo condizionali** (per durata/stagione) che sovrascrivono la tariffa base.
3. **Extra di tipo `total`**: forfait una tantum sull'intero noleggio (non per giorno), con `max_qty`.
4. **Ritiro ≠ riconsegna**: località diverse ammesse per veicolo; "Home Delivery/Pickup" come extra che attiva richiesta indirizzo al checkout.
5. **Acconti/pagamenti parziali** (modello deposit): pagare una quota ora, saldo dopo.
6. **Coupon/affiliati**: coupon legati ad affiliati (tracciamento commissioni, anche via URL).
7. **Multilingua/SEO**: slug e contenuti tradotti EN/IT/ES, sitemap per CPT, hreflang.
8. **Verifica email** cliente + email transazionali (conferma prenotazione).

---

## 9. Note e limiti dell'analisi
- L'analisi del front-end è completa sulle pagine pubbliche; il dettaglio del singolo veicolo è accessibile solo dentro il flusso (redirect al catalogo senza contesto pickup/date).
- I custom field del CPT `catalog` non sono esposti via REST pubblica: il modello dati è stato ricavato dall'editor `wp-admin` (Meta Box) e dal form di booking.
- ~~Il calcolo prezzo esatto e le regole condizionali (`price_cond`) sono gestiti dal plugin **Renroll Core** (codice non ispezionato a livello PHP)~~ → **superato**: la logica esatta è stata estratta dai sorgenti FTP, vedi §10.
- Importi e listini (€49–€120/giorno, extra €15–€59) sono quelli rilevati alla data dell'analisi.
- Le credenziali admin usate sono quelle fornite dal committente; non sono state apportate modifiche al sito (sola lettura/navigazione).

---

## 10. Logica precisa del motore (estratta dai sorgenti FTP)

> Fonte: backup FTP `C:\Users\Davide\source\WordpressSites\WordPressSitesBackups\easyRent\easyrent24h.com` (file `public_html` + dump SQL `dbjkglnikefpaj.sql`, DB prefix **`wp39689_`**).
> Il motore vive quasi interamente in **`wp-content/plugins/templines-renroll/templines-renroll.php`** (~7.400 righe, Renroll Core 7.9 **pesantemente personalizzato** — le funzioni custom hanno nomi italiani). I numeri di riga sotto si riferiscono a questo file. Esiste la copia vanilla in `plugins/original_templines-renroll/` utile per diff.

### 10.1 Tabelle custom del motore

| Tabella | Colonne chiave | Ruolo |
|---|---|---|
| `wp39689_renroll_order` | `vehicle_id, date_start, date_end, order_id, oraInizio, oraFine, pickupLocation` | Una riga per prenotazione confermata (o blocco manuale se `order_id IS NULL`). Le colonne `oraInizio/oraFine/pickupLocation` sono **aggiunte custom**. |
| `wp39689_renroll_stock` | `vehicle_id, day, cnt, oraInizio, oraFine` | Contatore di occupazione per veicolo/giorno/fascia, **ricalcolato** da `templines_recalculate_stock()` a ogni cambio stato ordine. `oraInizio/oraFine` custom: `''` = da inizio/fine giornata. |
| `wp39689_renroll_price` | `vehicle_id, condition_id, price` | Listino: prezzo/giorno per veicolo per ciascuna *condizione*; `condition_id=0` = tariffa base. |
| `wp39689_renroll_min_max` | `vehicle_id, min_days, max_days` | Min/max giorni di noleggio per veicolo (override del valore globale). |

Le prenotazioni nascono come ordini WooCommerce su **un unico prodotto "default"**: i dati veri (veicolo, date, orari, località, extra) viaggiano come meta dell'item (`templines_renroll`: `vehicle_id, start, end, fasciaOraStart, fasciaOraEnd, days, pick_up_id, drop_off_id, price, total, extra, extra_total`). Hook `woocommerce_order_status_changed` → `templines_add_booking()` (riga 5436) inserisce in `renroll_order` e ricalcola `renroll_stock`; stati `failed/refunded/cancelled` (o cestino) → `templines_remove_booking()`.

### 10.2 Costanti custom hardcoded (testa del file, righe 11–84)

- `$globalRangeOrario = 30` (step slot), `$globalStartOrario = '08:00'`, `$globalEndOrario = '20:00'`.
- `$globalBeforeMorningGiornoInMeno = '09:30'` → regola del **"giorno in meno"** (§10.4).
- `$globalAgerolaList` / `$globalPositanoList`: i term_id delle località raggruppati in **2 hub logistici** (ogni località ha 2 id: IT + EN per WPML):
  - **Hub Agerola**: Agerola, Amalfi, Atrani, C/mare di Stabia, Conca dei Marini, Furore, Gragnano, Pimonte, Pompei (+ altri id).
  - **Hub Positano**: Positano, Praiano, Sorrento (+ altri id).
- `$globalIdVeicoliNoDisponibiliGiornoStesso`: lista di post_id veicoli (IT+EN) **non noleggiabili in giornata** (se `start == oggi` → "Dates are unavailable", riga 3052 `checkStessoGiornoVeicoliEccezione`).
- `$globalCouponsEccezioni`: coppie coupon→luogo (es. `bartoloparcheggio`→Agerola, `easyrentcoupon17`→Agerola) usate per **nascondere il catalogo** (§10.6).

### 10.3 Calcolo prezzo (esatto)

Endpoint AJAX `templines_calc_total` → `templines_ajax_calc_total()` (riga 2256). Catena:

1. **Giorni fatturabili**: `diff = (end − start in giorni) + 1` (booking_type "day"). MA se scatta la regola del giorno in meno (§10.4) → `diff − 1` e `end − 1 giorno` ai fini del prezzo.
2. **Prezzo base**: `templines_get_price()` (riga 5730) esegue una **query SQL generata** da `templines_get_range_conditions()` (riga 5988) sulla tabella `renroll_price`:
   - Esiste una tassonomia **`condition`** (le "condizioni di prezzo"). Ogni termine ha term-meta: `days_from`, `days_to`, `days_first`, `fixed_price`, `weekdays[]`, `days[]`, `months[]`, `years[]`, `location` (csv), `location_dropoff` (csv), `type` (vehicle_type csv), `from_date`, `to_date`.
   - `templines_get_active_conditions()` (riga 5744): per **ogni giorno del range** verifica quali condizioni sono soddisfatte (durata ≥ days_from e ≤ days_to, giorno della settimana, giorno/mese/anno, range date stagionale, località pickup/dropoff, tipo veicolo, primi N giorni con `days_first`). Risultato: `condition_id → [indici giorni coperti]`. Tra condizioni "duplicate" (stessi criteri, days_from diversi) vince quella con `days_from` più alto.
   - La SQL finale partiziona i giorni: ogni giorno è prezzato dalla **prima combinazione di condizioni** che lo copre (bitmask dalla più ricca alla più povera), i giorni residui a tariffa base `condition_id=0`. `Totale base = Σ (prezzo_condizione × giorni_coperti)`; condizioni `fixed_price` valgono come forfait (prezzo non moltiplicato per i giorni).
3. **Extra**: `templines_get_extra_total()` (riga 3993). Tipo `day` → `prezzo × qty × giorni`; tipo `total` → `prezzo × qty` una tantum. Anche gli extra supportano `price_cond` per condizione (giorni coperti per tipo day, prima condizione match per tipo total).
4. **Totale**: `(prezzo_base + Σ extra) × quantity`. `percentualeSulTotale()` (riga 4751) è oggi un **no-op** (sconto 13% hub Agerola commentato).
5. **Vincoli**: `pickup_dropoff_days` (giorni della settimana ammessi per ritiro/consegna, theme mod), `holidays` (date escluse), min/max giorni (`renroll_min_max` per veicolo, poi minimo condizionale da theme mod legato alle `condition`, poi default).

### 10.4 Regola del "giorno in meno" (custom, importantissima)

`IsOrarioInferioreAllaConsegna()` (riga 4766): se il noleggio è **multi-giorno** e la riconsegna (`fasciaOraEnd`) è **≤ 09:30** (o l'orario non è ancora stato scelto), l'ultimo giorno **non si paga**: il prezzo è calcolato su `end − 1` e `days − 1`. È il motivo per cui il sito mostra prezzi diversi a parità di date al variare dell'orario di riconsegna.

### 10.5 Disponibilità e fasce orarie (esatto)

Il sito lavora in modalità **unlimited_booking** con `stock` per veicolo (meta). Due livelli:

1. **Check date** `templines_check_dates()` (riga 2201): la coppia (start,end) è KO se esiste in `renroll_order` un blocco manuale sovrapposto (`order_id IS NULL`), oppure se in `renroll_stock` esiste un giorno interno al range con `cnt ≥ stock` (range day-based: `day>start AND day<end` — i giorni estremi NON bloccano, perché si gestiscono a ore). `cleaning_days` (theme mod) estende `end` come buffer pulizia.
2. **Check orario** `templines_check_cart_availability()` (riga 3149): costruisce la matrice `hours_cnt[veicolo][giorno][slot 30']` sommando:
   - le prenotazioni nel **carrello** + quella richiesta: giorno di inizio occupa gli slot da `fasciaOraStart`→20:00, giorno di fine 08:00→`fasciaOraEnd`, giorni intermedi tutto (08:00→20:00), noleggio in giornata solo start→end (`getHoursCnt`, riga 3931);
   - le righe `renroll_stock` esistenti (`getHoursCntByStock`, riga 3892, stessa espansione).
   Se uno slot supera `stock` → errore "non disponibile" (`checkHoursCnt`, riga 3854). Altrimenti `getFasciaOrariaCorretta()` (riga 3818) restituisce, per il giorno di inizio e di fine, gli **slot con `cnt < stock`** → sono le opzioni di `fasciaOraStart/fasciaOraEnd` mostrate nel popup.
3. **Filtri successivi sulle fasce** (dentro `templines_ajax_calc_total`, righe 2449–2582), in quest'ordine:
   - `getFasceDaEscludere()` (riga 2968): esclude gli slot in cui **nello stesso hub logistico** (lista Agerola o Positano) esiste già un ritiro/consegna su quella data (query su `renroll_order.pickupLocation` raggruppata) — vincolo "lo staff non può essere in due posti";
   - se `start == oggi` (o `end == oggi`): solo slot **dopo l'ora attuale + 30'** (`getDopoOrarioAttuale`, timezone Europe/Rome);
   - stesso giorno: fine ≥ inizio + 30' (`checkFasciaInizioIfFasciaFineNotEmpty` / `checkFasciaFineByStartTime`);
   - multi-giorno: le fasce di inizio valide sono solo la "coda" contigua fino alle 20:00 e quelle di fine la "testa" contigua dalle 08:00 (`checkFasciaInizioByMoreDays`/`checkFasciaFineByMoreDays`) rispetto alle esclusioni;
   - **fasce per luoghi specifici** (`fasciaInizioPerLuoghiSpecifici`/`fasciaFinePerLuoghiSpecifici`, righe 2614/2664, **hardcoded per term_id**): Amalfi, Atrani, C/mare, Conca dei Marini, Furore, Gragnano, Pimonte, Pompei → **solo 2 opzioni: 08:00 o 20:00**; Positano, Praiano, Sorrento → slot ogni 30' **dalle 09:00**; tutte le altre (Agerola, Maiori, Minori, …) → slot ogni 30' 08:00–20:00. L'intersezione finale di tutti questi insiemi popola le select; se vuota → "Dates are unavailable".

### 10.6 Quali veicoli vengono mostrati (esatto)

`templines_filter()` su `pre_get_posts` (riga 6343) per archivio `catalog` / tax `vehicle_type`:
- filtro `pickup` → tax_query su `location` (term_id); `features[]` → tax_query AND su `feature`; prezzo min/max → su prezzo calcolato per le date; sort per data/prezzo/menu_order (cookie `sort`, `limit` 12/60).
- **La disponibilità per date NON filtra l'elenco**: il `posts_where` che escludeva i veicoli occupati è stato **commentato di proposito** ("Non escludere i veicoli occupati", riga 6594). Tutti i veicoli della località restano visibili; l'indisponibilità emerge solo nel popup (calc_total).
- Il **drop_off non filtra il catalogo** (è validato solo al booking contro il meta `location_drop` del veicolo).
- **Eccezione coupon**: in `archive-catalog.php` (tema, righe 124/148) l'intera lista è azzerata se il coupon attivo nel carrello è in `$globalCouponsEccezioni` e il gruppo-località della pagina corrisponde (es. coupon `bartoloparcheggio` → nasconde catalogo hub Agerola) — `checkIsTrueLocationByCoupon()` (riga 6448).
- Il dettaglio veicolo singolo è **disabilitato**: `templines_force_404()` (riga 6627) redirige alla ricerca.
- **Redirect d'ingresso**: `/catalog/` senza parametro `pickup` viene rediretto a `/catalog/?pickup=218` (Agerola EN) via hook `template_redirect` custom — il catalogo "completo" non esiste mai.
- Ordinamento custom (prima scooter, poi auto, 2 prodotti sempre in fondo) **definito ma disattivato** (`templines_catalog_custom_order_clauses`, add_filter commentato — "Punto 4 preventivo").

### 10.7 Flusso add-to-cart / ordine (esatto)

`templines_ajax_book()` (riga 4552): rivalida date+prezzo server-side, valida `pick_up` ∈ termini `location` del veicolo e `drop_off` ∈ meta `location_drop`, valida orari (same-day: inizio < fine), riapplica il "giorno in meno", calcola extra, attiva `activate_shipping` se la località ha il term-meta (per Home Delivery), e aggiunge al carrello. Con `multiple_booking = true` (configurazione attuale, vedi §10.10) il carrello può contenere **più prenotazioni insieme**; con `disable_redirect_after_booking = true` l'utente resta sulla pagina con messaggio "View cart" (nessun redirect automatico al checkout).

A cart/checkout la ri-verifica usa la variante **`templines_check_cart_availability_only_checkout()`** (riga 3484): stessa logica oraria ma ritorna solo veri errori, più `checkOrarioInizioStessaData` (KO se data+ora di ritiro sono già passate rispetto a now+30', Europe/Rome).

Lato admin: il **Booking Calendar** mostra i giorni di ritiro/riconsegna come liberi (le date degli ordini vengono spostate +1/−1 in `templines_ajax_date_class` perché quei giorni sono prenotabili a fasce); le prenotazioni manuali da calendario ("reserve") scrivono anch'esse `oraInizio/oraFine` in `renroll_order`.

### 10.8 Sistema affiliati custom (`/backend_rapp`, fuori da WordPress)

Applicazione PHP custom standalone (stesso DB) per la gestione **rappresentanti/affiliati**:
- login a sessione con ruoli admin/vendor (password MD5 ⚠️, query con concatenazione ⚠️ SQL injection);
- gestione vendor: coupon assegnato (WooCommerce URL Coupons), percentuale commissione, ordini generati, registrazione/storico pagamenti, reset coupon, cambio stato (tabelle custom `payments`, `azionistato`, `menus`);
- generazione **QR code** dei coupon (lib Endroid; repository immagini in `/qrcode` + `ListaCoupon.xlsx`); invio email via PHPMailer.
- Nella replica: modulo "Affiliati" in Laravel (ruoli, commissioni, coupon, QR, report) — da riscrivere completamente, sanando le vulnerabilità.

### 10.9 Configurazione reale e dati dal dump SQL

Valori effettivi in produzione (opzione `theme_mods_renroll` + tabelle, DB prefix `wp39689_`):

| Impostazione | Valore reale |
|---|---|
| `unlimited_booking` | **true** (modalità stock per veicolo — è il ramo orario descritto in §10.5) |
| `multiple_booking` | **true** (più prenotazioni nello stesso carrello) |
| `disable_redirect_after_booking` | true (resta in pagina, messaggio "View cart") |
| `pickup_dropoff_days` | `7,1,2,3,4,5,6` = tutti i giorni ammessi |
| `holidays` | vuoto |
| `minimum_days` / `maximum_days` | 1 / nessun massimo; tabella `renroll_min_max` **vuota**; condizioni min-days non usate |
| `pickup_dropoff_time` | false (il selettore orario "vanilla" è spento: contano solo le fasce custom) |
| `cleaning_days` | non valorizzato (nessun buffer pulizia) |
| `price_on_request_shortcode` | CF7 id 2384 "Price request" |
| `catalog_order` | newest, low_price, high_price attivi |

Dati reali:
- **43 post `catalog`** (con traduzioni WPML; ~14 veicoli unici per lingua). Esempi: Piaggio Liberty `price=49, stock=1`, drop ammessi = tutto l'hub Agerola; Vespa Primavera `price=99, stock=2`, drop = Positano/Praiano/Sorrento; Vespa Primavera Ed. Limitata `price=119, stock=1`.
- La località **pickup** è una term_relationship sulla tassonomia `location`; il **drop_off** è il postmeta `location_drop` (CSV di term_id). Unico termmeta località: `activate_shipping`.
- `renroll_order`: **3.116 righe** storiche (alcune con `order_id NULL` e orari corrotti = carrelli abbandonati/blocchi).
- `renroll_price`: quasi solo tariffe base (`condition_id=0`); unica riga condizionale reale: veicolo 3167 con condition 231 "Agosto Aumento prezzo" (`months=08, years=2025`) a 50€.
- **Extra "Price Delivery" (id 268) e "Price Collection" (id 234)** (= Home Delivery/Pickup, type `total`, base €25): hanno `price_cond` per **condition di località** ("Pick up X"/"dropp off X", legate alla località via termmeta `location`/`location_dropoff`) → prezzo effettivo per località: Positano/Praiano/Minori/Maiori 0€, Agerola/Furore 5€, Conca/Pimonte/C.mare/Gragnano/Pompei 10€, Amalfi/Atrani 15€, Sorrento 20€. Quindi il "supplemento consegna/ritiro a domicilio" varia per località tramite il motore condizioni.
- **Acconti**: plugin AWCDP salva su ogni item `awcdp_deposit_meta` (es. deposit 25–30% del totale, remaining a saldo).
- **Snippet Code Snippets attivi**: solo 2 — tag Google Ads (AW-17170167751) e "Dashboard Flotta" che è **solo uno stub** (shortcode che stampa un placeholder per admin: nessuna logica reale di flotta in questo backup).
- Nel DB convivono **7 tabelle senza prefisso** (`vendors`, `payments`, `azionistato`, `menus`, `users`, `vendite`, `test`) usate dal backend affiliati custom (§10.8), charset latin1, password MD5.

### 10.10 Implicazioni per la replica Laravel

1. Il "calendario di disponibilità" da replicare è una **matrice slot 30' per veicolo/giorno** confrontata con `stock`; conviene modellarla con una tabella `bookings` (range + orari) e calcolare gli slot a runtime con la stessa espansione (start-day: da ora X a fine giornata; end-day: da inizio giornata a ora Y) invece di mantenere una tabella contatore denormalizzata.
2. Il listino diventa: `vehicle_prices(vehicle_id, condition_id nullable, price)` + `price_conditions` (campi: days_from/to, days_first, fixed, weekdays, days, months, years, locations, locations_dropoff, vehicle_types, from_date, to_date) con un **PriceResolver** day-by-day identico a §10.3.
3. Regole da portare a **configurazione** (oggi hardcoded): hub logistici, fasce per località, veicoli no-same-day, regola 09:30 "giorno in meno", coupon-eccezione, buffer 30' sull'ora attuale.
4. `renroll_order`/`renroll_stock` → un'unica tabella `bookings` con stato; i blocchi manuali (`order_id NULL`) → `blocks` o booking di tipo "block".

---

*Fine analisi.*
