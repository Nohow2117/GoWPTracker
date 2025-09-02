# GoWPTracker – Development Roadmap

## ⚠️ Rule
**No deletion of data is allowed in this file.**  
Steps in the roadmap can only be:
- **Flagged** as completed (`[x]`) or pending (`[ ]`)  
- Annotated with additional notes, fixes, or test results  

---

## 🛠️ Step 1 – Project Setup
- [x] Create repository on GitHub  
- [x] Add initial documentation (`README.md`, `architecture.md`, `brief.md`, `roadmap.md`)  
- [x] Configure license (GPLv3)  
- [x] Test: Verify repo can be cloned and plugin folder structure is correct  

**Notes:**
- La struttura base del plugin è presente nel branch main.


**Notes:**  

---

## 🛠️ Step 2 – Core Redirect Endpoint
- [x] Fix crash WordPress spostando register_activation_hook fuori dalla classe GoWPTracker (COMPLETATO)
- [x] Creazione tabella custom wp_go_clicks per il logging dei click (COMPLETATO)
- [x] Implementazione whitelist domini consentiti nell'endpoint /go (hardcoded) (COMPLETATO)
- [x] Implementazione redirect 302 verso la destinazione se valida, errore se non valida (COMPLETATO)
- [x] Logging click su redirect /go nella tabella wp_go_clicks (COMPLETATO)
- [ ] Test: Verify redirect works with a valid `dest` and blocks invalid domains  

**Notes:**  

---

## 🛠️ Step 3 – Logging System
- [x] Create custom DB table `go_clicks`
- [x] Store timestamp, PLP slug, destination, UTM params, referrer, UA, IP (COMPLETATO)
- [x] Test: Manually click CTA and check that logs are inserted in DB (COMPLETATO)

**Notes:**
- Tutti i dati richiesti vengono loggati e test manuale superato.

---

## 🛠️ Step 4 – PLP + UTM Propagation
- [x] Capture UTM parameters from query string (COMPLETATO)
- [x] Append UTM + `plp` to destination URL (COMPLETATO)
- [x] Test: Check that redirected URL contains original UTM + PLP slug (COMPLETATO)

**Notes:**
- Propagazione UTM/PLP verificata manualmente: la destinazione riceve tutti i parametri.

**Notes:**  

---

## 🛠️ Step 5 – Admin Dashboard
- [ ] Create WP Admin page under "GO Tracker"  
- [ ] Show clicks grouped by PLP and campaign  
- [ ] Add time filter (7 / 30 days)  
- [ ] Test: Confirm data aggregates match raw DB entries  

**Notes:**  

---

## 🛠️ Step 6 – JavaScript Helper
- [ ] Implement JS script to automatically append UTM + PLP slug to CTA links  
- [ ] Ensure compatibility with multiple CTAs on same page  
- [ ] Test: Load PLP with UTM → click CTA → confirm redirected URL includes params  

**Notes:**  

---

## 🛠️ Step 7 – Security & Privacy
- [ ] Sanitize all inputs (dest, UTM, PLP)  
- [ ] Store IP in binary format (not plain text)  
- [ ] Filter out bots/HEAD requests  
- [ ] Test: Attempt malicious redirects and confirm they are blocked  

**Notes:**  

---

## 🛠️ Step 8 – Reporting Enhancements
- [ ] Add CSV export  
- [ ] Add chart visualization (clicks per PLP over time)  
- [ ] Test: Export and chart match DB values  

**Notes:**  

---

## 🛠️ Step 9 – Deployment & Maintenance
- [ ] Configure auto-deploy from GitHub to WordPress server  
- [ ] Test staging environment before production updates  
- [ ] Document upgrade process  

**Notes:**  

---

## 🛠️ Step 10 – Future Extensions
- [ ] Webhooks for real-time event streaming  
- [ ] Integration with analytics tools (GA4, BigQuery)  
- [ ] Conversion tracking (click → purchase)  

**Notes:**  
