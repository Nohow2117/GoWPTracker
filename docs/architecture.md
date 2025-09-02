# GoWPTracker – Architecture Overview

## 🎯 Goal
The purpose of **GoWPTracker** is to provide **server-side tracking** of outbound clicks from pre-landing pages (PLPs) to an external e-commerce site.  
It allows marketers to identify **which PLPs generate the most traffic** toward the shop and to analyze performance across campaigns (via UTM parameters).

---

## 🧩 High-Level Architecture
The system introduces a controlled **redirect endpoint** (`/go`) inside WordPress.  
This endpoint acts as a **gatekeeper**: it logs click metadata before redirecting the user to the target e-commerce URL.

### Flow
1. **Traffic Entry**
   - Users arrive on a PLP (e.g., `camillabarone.space/migliori-borse`) with UTM parameters from ads.
2. **CTA Click**
   - CTA buttons point to `/go?dest=https://milano-bags.com/...`.
   - A JavaScript helper ensures UTM parameters and PLP slug are appended automatically.
3. **Redirect Handling**
   - The `/go` route validates the destination domain (whitelist).  
   - Logs are written into a dedicated database table.  
   - User is redirected (HTTP 302) to the e-commerce site with all UTM and PLP parameters preserved.
4. **Reporting**
   - WordPress admin panel shows aggregated metrics (clicks per PLP, breakdown by campaign, time filters).

---

## 🗄️ Data Model
Custom DB table: `wp_go_clicks`

| Column       | Type             | Notes                                  |
|--------------|------------------|----------------------------------------|
| id           | BIGINT AUTO_INC  | Primary key                            |
| ts           | DATETIME         | Timestamp of the click                  |
| ip           | VARBINARY(16)    | Visitor IP in binary (IPv4/IPv6)        |
| ua           | TEXT             | User agent                             |
| referrer     | TEXT             | Page referrer                          |
| dest         | TEXT             | Destination URL                        |
| dest_host    | VARCHAR(191)     | Destination host (for validation)       |
| plp          | VARCHAR(191)     | Pre-landing page slug                   |
| utm_source   | VARCHAR(191)     | UTM param                              |
| utm_medium   | VARCHAR(191)     | UTM param                              |
| utm_campaign | VARCHAR(191)     | UTM param                              |
| utm_content  | VARCHAR(191)     | UTM param                              |
| utm_term     | VARCHAR(191)     | UTM param                              |
| fbclid       | VARCHAR(191)     | Facebook click ID                      |
| gclid        | VARCHAR(191)     | Google Ads click ID                    |

Indexes:
- `idx_ts` (query by time range)
- `idx_plp` (query by PLP slug)
- `idx_dest_host` (filter by domain)

---

## 🔒 Security Considerations
- **Domain whitelist**: prevents open redirects. Only configured shop domains are allowed.  
- **Input sanitization**: all query parameters sanitized before logging or propagation.  
- **IP handling**: stored in binary (not plain text) for privacy compliance.  
- **Redirect type**: 302 (temporary) ensures no SEO impact.  

---

## 📊 Reporting Layer
The plugin includes an admin page:
- **Aggregations**: clicks grouped by PLP and campaign.  
- **Filters**: time range selector (e.g., last 7 days, last 30 days).  
- **Output**: table view, optional CSV export (future extension).  

This allows non-technical users to quickly identify the best-performing PLPs.

---

## ⚙️ Extensibility
The system is designed to be modular:
- **Destination validation**: customizable via plugin settings.  
- **Logging**: can be extended to push data into external BI tools (BigQuery, Redshift, etc.).  
- **Webhooks**: future support for sending events to analytics or CRM in real-time.  
- **Conversion tracking**: can be paired with shop-side events to build funnels (click → session → purchase).

---

## 🔧 Deployment & Maintenance
- Install plugin in `wp-content/plugins/go-tracker/`.  
- Activate from WP Admin.  
- Configure allowed domains.  
- Update CTA links in PLPs to point to `/go`.  
- Optional: add JavaScript helper to automatically append UTM and PLP slug.  

---

## 🖼️ Sequence Diagram
```mermaid
sequenceDiagram
    participant User
    participant PLP as Pre-Landing Page (camillabarone.space)
    participant Go as /go Redirect (Plugin)
    participant DB as WP DB (go_clicks)
    participant Shop as E-commerce (milano-bags.com)

    User->>PLP: Visits PLP with UTM params
    User->>PLP: Clicks CTA
    PLP->>Go: /go?dest=milano-bags.com
    Go->>DB: Log click (PLP, UTM, referrer, ts, ua, ip)
    Go->>Shop: Redirect 302 with UTM + plp
    Shop->>User: Shop homepage loads
