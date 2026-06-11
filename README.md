# easyRent24h – Clone Laravel + Vue

Replica pulita e performante di [easyrent24h.com](https://www.easyrent24h.com) (noleggio scooter/Vespa/auto in Costiera Amalfitana), in sviluppo secondo il piano in [`vault/piano-implementazione.md`](vault/piano-implementazione.md).

## Struttura

| Cartella | Contenuto |
|---|---|
| `backend/` | API Laravel 12: catalogo, motore prezzi (condizioni stagionali/località/durata), motore disponibilità a fasce di 30 minuti, prenotazioni |
| `frontend/` | SPA Vue 3 (Vite, Pinia, Vue Router, vue-i18n EN/IT/ES): home con ricerca, catalogo, popup prenotazione, carrello |
| `data/` | `catalog-export.json` — dati reali estratti dal dump SQL del sito WordPress (input del seeder ETL) |
| `vault/` | Documentazione: analisi del sito attuale (con la logica esatta del motore RenRoll) e piano a step/gate |

## Avvio in locale

Backend (richiede PHP ≥ 8.2 + Composer):

```bash
cd backend
composer install
php artisan migrate:fresh --seed   # importa i dati reali da data/catalog-export.json
php artisan serve                  # API su http://localhost:8000
```

Frontend (richiede Node ≥ 20):

```bash
cd frontend
npm install
npm run dev                        # http://localhost:5173 (API su localhost:8000)
```

Test backend (34 test sui "casi d'oro" del motore):

```bash
cd backend
php artisan test
```

## Logica di dominio replicata (vedi `vault/analisi-easyrent24h.md` §10)

- **Prezzi**: tariffa per giorno risolta giorno-per-giorno dalle condizioni (stagione, durata con tie-break, weekday, località ritiro/consegna, tipo veicolo, forfait).
- **Regola del "giorno in meno"**: riconsegna entro le 09:30 su noleggio multi-giorno → ultimo giorno non fatturato.
- **Disponibilità a fasce di 30'** (08:00–20:00) contro lo stock: lo stesso mezzo è rinoleggiabile nel giorno di riconsegna dagli slot successivi.
- **Hub logistici** (Agerola, Positano): un solo ritiro/consegna per slot per hub.
- **Finestre per località**: Amalfi & co. solo 08:00/20:00; Positano/Praiano/Sorrento dalle 09:00.
- **Extra con prezzo per località**: es. Home Delivery €25 di listino ma €0–20 effettivi.
- Tutto ciò che nel sito originale era hardcoded è **configurazione** (tabelle `settings`, `locations`, `hubs`).
