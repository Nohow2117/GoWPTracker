# GoWPTracker – Changelog

All notable changes to this project will be documented here.  
The format is based on [Keep a Changelog](https://keepachangelog.com/)  
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]
### Added
- Initial roadmap and documentation files (`README.md`, `architecture.md`, `brief.md`, `roadmap.md`).

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
