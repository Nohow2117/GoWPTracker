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
- [x] Test: Verify redirect works with a valid `dest` and blocks invalid domains  (COMPLETATO 2025-09-02: test manuale redirect e blocco domini non validi)  

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
- [x] Create WP Admin page under "GO Tracker" (COMPLETATO)
- [x] Show clicks grouped by PLP (dedotta da referrer) e campaign (COMPLETATO)
- [x] Add time filter (ultimi 7 giorni) (COMPLETATO)
- [x] Test: Confirm data aggregates match raw DB entries (COMPLETATO)

**Notes:**
- La PLP ora viene dedotta automaticamente dal percorso della referrer, senza bisogno del parametro plp.

---

## 🛠️ Step 6 – JavaScript Helper
- [x] Implement JS script to automatically append UTM + PLP slug to CTA links (NON NECESSARIO, COMPLETATO lato backend)
- [x] Ensure compatibility with multiple CTAs on same page (NON NECESSARIO, COMPLETATO lato backend)
- [x] Test: Load PLP with UTM → click CTA → confirm redirected URL includes params (COMPLETATO)

**Notes:**
- Tutti i parametri UTM vengono propagati automaticamente dal backend GoWPTracker, quindi non è richiesto uno script JS lato client.

---

## 🛠️ Step 7 – Security & Privacy
- [x] Sanitize all inputs (dest, UTM, PLP)  (COMPLETATO 2025-09-02: tutti gli input vengono sanificati lato backend, test superato)  
- [x] Store IP in binary format (not plain text)  (COMPLETATO 2025-09-02: IP salvato in formato binario, verifica DB ok)  
- [x] Filter out bots/HEAD requests  (COMPLETATO 2025-09-02: filtro attivo su /go, blocco 403 HEAD/bot, test superato)  
- [x] Test: Attempt malicious redirects and confirm they are blocked  (COMPLETATO 2025-09-02: test HEAD/bot e redirect malevoli superati, vedi logs/report)  

**Notes:**
- 2025-09-02: Fix sicurezza HEAD/bot implementato su /go. Rewrite rule aggiornata a ^go/?$, hook template_redirect a priorità 9, blocco e logging attivi solo su endpoint custom. Tutti i test superati.
- 2025-09-02: Tutti gli input (dest, UTM, PLP) vengono ora sanificati lato backend. L'indirizzo IP viene salvato in formato binario nella tabella DB. Test e verifica manuale superati.

---

## 🛠️ Step 8 – Reporting Enhancements
- [x] Add CSV export  (COMPLETATO 2025-09-02: funzione attiva in dashboard)
- [x] Add chart visualization (clicks per PLP over time)  (COMPLETATO 2025-09-02: grafico a barre in dashboard)
- [x] Test: Export and chart match DB values  (COMPLETATO 2025-09-02: verifica manuale, export CSV e chart corrispondono ai dati DB)  

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
