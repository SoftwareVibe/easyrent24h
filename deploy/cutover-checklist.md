# Checklist di cutover – easyRent24h (WordPress → Laravel + Vue)

> Gate 8 del piano. Spuntare ogni voce; il rollback è sempre possibile fino allo switch DNS.

## T-7 giorni — preparazione
- [ ] Server di produzione pronto: PHP 8.2+ (ext: intl, zip, gd, pdo_mysql), MySQL 8 / MariaDB, nginx + certbot, Node solo per build.
- [ ] Deploy backend: `composer install --no-dev`, `.env` di produzione (APP_ENV=production, DB, MAIL SMTP reale, STRIPE_*, PAYPAL_* **live**, OFFLINE_PAYMENTS=false, FRONTEND_URL=https://www.easyrent24h.com).
- [ ] `php artisan migrate --force` + seed catalogo (`CatalogImportSeeder` con export aggiornato).
- [ ] Build frontend (`npm run build`) servita da nginx con gzip/brotli e cache asset.
- [ ] Webhook Stripe configurato su `https://.../api/webhooks/stripe` (`STRIPE_WEBHOOK_SECRET`).
- [ ] Esportare le sitemap Yoast del sito vivo e completare `deploy/nginx-redirects.conf` con TUTTE le URL indicizzate (Search Console → pagine indicizzate).
- [ ] Prova generale su staging con dati reali: 5 casi d'oro (`scripts/live-compare.ps1` puntato allo staging).

## T-1 giorno — freeze e delta
- [ ] Avvisare lo staff: freeze prenotazioni manuali su WordPress.
- [ ] Export delta: nuovo dump SQL → rigenerare `data/catalog-export.json` (stesso procedimento) → re-import bookings recenti (ordini WooCommerce → tabella `bookings`).
- [ ] Backup completo del sito WordPress (già presente in `WordPressSitesBackups`, aggiornarlo).
- [ ] Abbassare il TTL DNS a 300s.

## Giorno X — switch
- [ ] Mettere WordPress in maintenance (i redirect del nuovo server prenderanno il suo posto).
- [ ] Ultimo delta bookings (ordini arrivati nelle ultime ore).
- [ ] Switch DNS verso il nuovo server.
- [ ] Smoke test in produzione: home, catalogo per 3 località, preventivo (totale atteso), prenotazione test con Stripe live a 1€? No: usare coupon 100% di test o ordine offline, poi annullare.
- [ ] Verificare redirect 301 sulle 20 URL principali (curl -I).
- [ ] Inviare sitemap nuova in Search Console; monitorare Crawl errors.

## Post go-live (T+7)
- [ ] Monitorare log Laravel (`storage/logs`) e nginx 404 → aggiungere redirect mancanti.
- [ ] Confrontare prenotazioni/giorno con la media storica.
- [ ] Spegnere i rinnovi dei plugin WordPress non più necessari.

## Rollback
1. Ripuntare il DNS al vecchio server (TTL 300s → propagazione rapida).
2. Togliere il maintenance da WordPress.
3. Re-importare a mano sul vecchio sito le prenotazioni arrivate sul nuovo (tabella `orders`/`bookings`, sono poche ore).
