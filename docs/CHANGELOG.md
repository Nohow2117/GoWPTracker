# GoWPTracker – Changelog

All notable changes to this project will be documented here.  
The format is based on [Keep a Changelog](https://keepachangelog.com/)  
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]
### Added
- Initial roadmap and documentation files (`README.md`, `architecture.md`, `brief.md`, `roadmap.md`).

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

## [0.3.0] – YYYY-MM-DD
### Planned
- CSV export of clicks.
- Chart visualization of click performance.
- Webhooks for real-time analytics integrations.
