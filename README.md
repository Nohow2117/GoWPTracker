# GoWPTracker

**GoWPTracker** is a WordPress plugin that tracks outbound clicks from pre-landing pages (PLPs) to an external e-commerce site.  
It uses a server-side redirect (`/go`) to log essential data (PLP, UTM parameters, referrer, user agent, timestamp) before sending the user to the destination.

---

## 🚀 Purpose
When running ads (e.g., Facebook Ads) you may split traffic across multiple pre-landing pages before sending users to your shop.  
Without tracking, you cannot know which PLP generates the most clicks to your store.  

**GoWPTracker solves this** by logging every click server-side, giving you a clear report of which PLPs drive the most traffic and which campaigns perform best.

---

## ✨ Features
- Server-side tracking of outbound clicks via `/go` redirect
- Logs:
  - Pre-landing page (PLP)
  - Destination host
  - UTM parameters (`utm_source`, `utm_campaign`, etc.)
  - Referrer
  - User agent
  - Timestamp
- Admin dashboard in WordPress to view:
  - Clicks per PLP
  - Breakdown per campaign
  - Filter by time range
- Domain whitelist to prevent open redirects
- Privacy-friendly: IP addresses stored in binary format, not plain text

### Split Testing (PLP rotator)
- Public endpoint: `/split/{slug}` with sticky assignment via cookie (30 days)
- Weighted rotation across WordPress pages (variants)
- Admin UI to create/edit tests:
  - Up to 10 variants with dynamic add/remove
  - Percent-based weights with "Equalize" button (sets each to floor(100/N))
  - Slug immutable when editing existing tests
  - Validation, nonce and capability checks
- Reporting: clicks per test/variant with CSV export

Read more in `docs/split-testing.md`.

---

## 🔧 Installation
1. Download or clone this repository:
   ```bash
   git clone https://github.com/Nohow2117/GoWPTracker.git
