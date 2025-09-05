# GoWPTracker – Changelog

All notable changes to this project will be documented here.  
The format is based on [Keep a Changelog](https://keepachangelog.com/)  
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

## [0.9.0] – 2025-09-04
### Added
- **Go Tracker Reset**: Added a button to the Go Tracker admin UI to reset all click statistics, with a confirmation prompt.

---

## [0.8.0] – 2025-09-04
### Added
- **PLP Parameter Injection**: Automatically adds the source page slug as a `plp` URL parameter to all `/go` redirects to track the click origin.
- Initial roadmap and documentation files (`README.md`, `architecture.md`, `brief.md`, `roadmap.md`).

---

## [0.7.0] – 2025-09-03
### Added
- **Reset Statistics**: Added a button to the split test report UI to reset the click statistics for a specific test.
- **Recent Hits Log**: Added a "Recent Hits" log to the Split Test admin page, showing the last 10 clicks in real-time.
- **GeoIP Lookup**: Implemented GeoIP lookup and device type detection for split test hits.
- **Database Schema Update**: The database schema for `go_split_hits` was updated to store geo-location and device data.

### Fixed
- **Split Test Caching**: Resolved a browser caching issue on 302 redirects that prevented correct variant randomization on subsequent visits by adding cache-busting headers.

---

## [0.6.0] – 2025-09-03
### Added
- **Split Test Deletion**: Implemented the ability to delete split tests and their variants directly from the admin UI, including nonce protection.
- **Go Tracker UI Refactor**: The admin report table for `/go` clicks now groups results by PLP and shows associated campaigns in a collapsible accordion view for improved readability.

### Changed
- **Split Test Logic**: Removed the "sticky" cookie assignment for variants. Each visit to a `/split` URL now performs a random weighted selection, ensuring traffic is always distributed according to weights.

### Security
- Added a JavaScript confirmation prompt before deleting a split test to prevent accidental clicks.

---

## [0.5.1] – 2025-09-02
### Added
- Split Tests Admin UI: varianti dinamiche fino a 10 con pulsanti Aggiungi/Rimuovi.
- Pulsante "Equalizza pesi" in percentuale: ogni variante viene impostata a `floor(100/N)`.
- Possibilità di modificare test esistenti: nome, stato e varianti/pesi (slug immutabile).

### Changed
- Input peso ora è inteso come percentuale. UI aggiornata e documentazione rivista.

### Fixed
- Parse error PHP in blocco JS inline: riscritto con heredoc per evitare problemi di escaping.

---

## [0.5.0] – 2025-09-02
### Added
- Split Testing Admin UI: submenu "Split Tests" con lista test e form di creazione (slug, nome, stato, varianti con pesi).
- Endpoint `/split/{slug}` completo: selezione pesata delle varianti e assegnazione sticky via cookie (30 giorni).
- Logging degli hit in `{prefix}go_split_hits` con `client_id`, UA, IP binario, referrer.
- Propagazione automatica dei parametri di query/UTM verso la variante.
- Report base: selettore test + periodo (7/30 giorni), tabella clicks per variante, esportazione CSV.

### Changed
- Policy bot/crawler: su `/split` non blocchiamo HEAD/bot (compatibilità con anteprime e review delle inserzioni). Su `/go` i bot restano bloccati.

### Notes
- Prossimo passo: Step 7 – test E2E e di sicurezza per `/split` (slug sconosciuto, varianti non pubblicate, ecc.).

---

## [0.2.0] – 2025-09-02
### Security & Fix
- Blocco richieste HEAD e bot su endpoint `/go` (403 Forbidden, logging).
- Rewrite rule aggiornata a `^go/?$` per compatibilità `/go` e `/go/`.
- Hook `template_redirect` ora a priorità 9 (prima di redirect_canonical).
- Tutti gli input (`dest`, UTM, PLP) ora vengono sanificati lato backend.
- L'indirizzo IP viene salvato in formato binario nella tabella DB.
- Test di sicurezza e regressione superati (vedi roadmap e logs).

---

## [0.1.0] – YYYY-MM-DD
### Added
- Basic `/go` redirect endpoint.
- Domain whitelist validation.
- Initial database table `wp_go_clicks`.
- Logging of timestamp, PLP slug, destination, UTM params, referrer, UA, IP.
- Admin page to display click counts grouped by PLP and campaign.

### Security
- Sanitization of input parameters.
- Whitelist to prevent open redirects.
- Binary storage of IP addresses.

---

## [0.2.0] – YYYY-MM-DD
### Added
- JavaScript helper to append UTM + PLP to CTA links.
- Time filter in Admin dashboard (7 / 30 days).

---

## [0.4.0] – 2025-09-02
### Added
- Database tables for Split Testing: `go_split_tests`, `go_split_variants`, `go_split_hits` (created via dbDelta on activation).
- Design document `docs/split-testing.md` describing architecture, DB schema, endpoint, admin UI, logging, and security.

### Notes
- This release introduces schema and documentation. Endpoints, admin UI, logging and reporting will follow in next steps.

---

## [0.3.0] – 2025-09-02
### Added
- CSV export of clicks (dashboard, Step 8 roadmap).
- Chart visualization of click performance (dashboard, Step 8 roadmap).
- Test: Export and chart match DB values (manual verification, Step 8 roadmap).

### Planned
- Webhooks for real-time analytics integrations.
