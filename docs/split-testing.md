# GoWPTracker – Split Testing for PLPs (Design)

## 🎯 Obiettivo
Permettere di creare uno o più “split test” con un URL unico per ciascun test (es. `/split/summer-sale`), e far ruotare automaticamente il traffico verso un insieme di pagine WordPress (PLP) selezionate dall’admin, con pesi configurabili. L’assegnazione deve essere sticky per utente (cookie), in modo che, a parità di test, lo stesso utente veda sempre la stessa variante.

---

## ✅ Risultato Atteso
- Un URL unico per test: `/split/{slug}`.
- Rotazione automatica tra N pagine WordPress scelte dall’admin.
- Rotazione pesata (es. Variante A 70%, B 30%).
- Assegnazione “sticky” per utente via cookie (es. 30 giorni).
- Propagazione dei parametri UTM e query string verso la PLP.
- Logging degli hit del test (test, variante, timestamp, IP binario, UA, referrer).
- Report in admin: click per test/variante, esportazione CSV e grafico.
- Recent Hits Log: visualizzazione degli ultimi 10 click per il test selezionato.

---

## 📐 Architettura ad Alto Livello
1. Endpoint pubblico: `^split/{slug}` → seleziona una variante e redirige alla relativa PLP.
2. Admin UI: gestione test (crea/modifica/attiva), selezione pagine WP come varianti e pesi.
3. DB: tabelle per test, varianti e log hit split.
4. Security: blocco HEAD/bot, sanificazione input, capability checks, nonce nelle form.
5. Privacy: IP in binario come già fatto, nessun dato personale in chiaro.

---

## 🗂️ Modello Dati (DB)
Useremo prefissi WP (`$wpdb->prefix`). Campi e tipi indicativi:

- Tabella: `{prefix}go_split_tests`
  - `id BIGINT UNSIGNED PK AUTO_INCREMENT`
  - `slug VARCHAR(191) UNIQUE NOT NULL` (es. "summer-sale")
  - `name VARCHAR(191) NOT NULL`
  - `status TINYINT(1) NOT NULL DEFAULT 1` (1=attivo, 0=disattivo)
  - `created_at DATETIME NOT NULL`
  - `updated_at DATETIME NOT NULL`

- Tabella: `{prefix}go_split_variants`
  - `id BIGINT UNSIGNED PK AUTO_INCREMENT`
  - `test_id BIGINT UNSIGNED NOT NULL` (FK su tests)
  - `post_id BIGINT UNSIGNED NOT NULL` (ID pagina/post WordPress)
  - `weight INT UNSIGNED NOT NULL DEFAULT 1` (peso per rotazione)
  - `created_at DATETIME NOT NULL`
  - `updated_at DATETIME NOT NULL`
  - Indici: `KEY idx_test (test_id)`, `KEY idx_post (post_id)`

- Tabella: `{prefix}go_split_hits`
  - `id BIGINT UNSIGNED PK AUTO_INCREMENT`
  - `ts DATETIME NOT NULL`
  - `test_slug VARCHAR(191) NOT NULL`
  - `variant_id BIGINT UNSIGNED NOT NULL`
  - `client_id VARCHAR(191) NULL` (hash cookie; opzionale)
  - `ip VARBINARY(16) NOT NULL`
  - `ua TEXT NULL`
  - `referrer TEXT NULL`
  - Indici: `KEY idx_ts (ts)`, `KEY idx_test (test_slug)`, `KEY idx_variant (variant_id)`

Note:
- `client_id` può essere un valore pseudo-random salvato nel cookie per analisi anonime.
- Gli `upgrade` DB possono essere gestiti in `activate_plugin()` o con routine di migrazione condizionali.

---

## 🔀 Endpoint e Routing
- Rewrite rule: `^split/([^/]+)/?$` → `index.php?gowptracker_split=$matches[1]`
- Query var: `%gowptracker_split%`
- Hook: `template_redirect` con handler `handle_split_redirect()` (priorità 9, come `/go`).

Flow dell’handler:
1. Blocca HEAD/bot (403) come per `/go`.
2. Recupera `slug` da `get_query_var('gowptracker_split')`.
3. Carica test e varianti attive da DB; se non trovati → 404.
4. Se presente cookie sticky `GoWPTrackerSplit_{slug}`, assegna la variante salvata (se ancora valida); altrimenti seleziona variante via rotazione pesata e salva cookie.
5. Costruisce URL destinazione come permalink WP della `post_id` selezionata.
6. Propaga i parametri UTM e altre query dall’URL `/split` al permalink della PLP (merge non distruttivo).
7. Logga l’hit su `{prefix}go_split_hits` (timestamp, slug, variant_id, ip, ua, referrer, client_id) e redirige 302.

Cookie sticky:
- Nome: `GoWPTrackerSplit_{slug}`
- Valore: `variant_id` o hash `variant_id:client_id`
- Scadenza: 30 giorni
- Path: `/`

---

## ⚖️ Algoritmo di Selezione (Rotazione Pesata)
- Input: array varianti con `weight`.
- Calcolo: somma pesi `W`; estrae `r` in `[1..W]` e scorre cumulativamente finché `r` ≤ somma parziale.
- Se cookie presente e variante ancora attiva, bypass della selezione.

---

## 🛠️ Admin UI
Sotto il menu "GO Tracker":

- Voce: "Split Tests"
  - Listing: tabella test (slug, nome, stato, varianti, link `/split/{slug}`, azioni edit/disable/delete).
  - Add/Edit Test:
    - Campi: `name`, `slug` (unico, immutabile in modifica), `status` (checkbox attivo), `variants` (lista dinamica).
    - Varianti (fino a 10):
      - Selettore pagina WordPress (`post_id`) con `wp_dropdown_pages()`.
      - `weight` numerico espresso in percentuale.
      - Pulsanti: "+ Aggiungi variante", "Rimuovi" per singola riga, "Equalizza pesi".
    - Validazioni: slug unico (in creazione), almeno 1 variante, pesi > 0, capability `manage_options`, nonce.
  - UX: mostra URL copiabile `/split/{slug}` e un pulsante "Copia".

### Equalizzazione pesi (percentuale)
- Il pulsante "Equalizza pesi" imposta ogni peso a `floor(100 / N)` dove `N` è il numero di varianti visibili.
- Esempi: 2 varianti → 50/50; 3 varianti → 33/33/33 (somma 99). Un eventuale perfezionamento per distribuire il resto sino a 100 (es. 34/33/33) può essere aggiunto.
- Limite varianti: massimo 10 per test, gestito sia a livello UI sia lato backend.

Permessi:
- Solo `manage_options`.

---

## 📊 Reporting & Export
- Dashboard Split:
  - Filtro data (ultimi 7/30 giorni).
  - Tabella: `test_slug`, `variant_id` (o titolo pagina), `clicks`.
  - Grafico (Chart.js) per test selezionato: clicks per variante.
  - Esporta CSV (stesso dataset della tabella corrente).

Query esempio (concettuale):
- `SELECT test_slug, variant_id, COUNT(*) as clicks FROM {prefix}go_split_hits WHERE ts >= ? GROUP BY test_slug, variant_id;`

---

## 🔒 Sicurezza & Privacy
- `/go`: continuare a bloccare HEAD e bot/spider noti (403) per evitare spam/log sporchi.
- `/split`: NON bloccare HEAD né bot/crawler (Meta, Google, ecc.) così che possano ispezionare le landing e non rifiutare inserzioni.
- Sanificare `slug` e ogni input admin (nonce, capability check, `sanitize_text_field`, `absint`).
- Redirigere solo verso permalink di pagine/post esistenti (no open redirect).
- IP in binario, nessun dato personale in chiaro.

---

## 🔁 Integrazione con il flusso attuale
- `/split/{slug}` redirige a una PLP interna WP con UTM propagati.
- Sulla PLP, i CTA già passano tramite `/go` (GoWPTracker) che logga verso e-commerce e propaga UTM.
- Quindi avremo tracciamento a 2 livelli:
  1) Entrata nella PLP via split test (tabella `go_split_hits`).
  2) Uscita dalla PLP verso shop via `/go` (tabella `go_clicks`).

---

## 🧪 Test & QA
- 404 se test non esiste o non ha varianti attive.
- Sticky: ritorno al test → stessa variante.
- Rotazione: con pesi (70/30) su volume adeguato, proporzioni rispettate.
- Propagazione UTM: `?utm_source=fb` su `/split/slug` appare nella PLP.
- Sicurezza: HEAD/bot → 403, slug non valido → 404, varianti con post non pubblicati → esclusi.
- CSV export: corrispondenza con DB.
- Grafici: corrispondenza con tabella.

---

## 🧩 Piano di Implementazione (Baby Steps™)
1) DB – creare tabelle `go_split_tests`, `go_split_variants`, `go_split_hits` con migrazione sicura.
2) Rewrite & Query Var – aggiungere `%gowptracker_split%` e regola `^split/{slug}`.
3) Handler – `handle_split_redirect()` con blocco HEAD/bot, selezione variante, cookie sticky, redirect, logging.
4) Admin – pagina "Split Tests": listing + create/edit con selezione pagine e pesi.
5) Reporting – tabella aggregata + CSV + grafico per test.
6) QA – test end-to-end (sticky, pesi, UTM, sicurezza).
7) Docs – aggiornare README/roadmap/changelog e esempi d’uso.

Ogni step sarà completato, validato e documentato prima del successivo.

---

## ❓ Open Questions
- Serve supporto a tipi di contenuto custom (CPT) oltre alle Pagine? (Default: sì, qualsiasi post_type pubblico.)
- Durata cookie diversa da 30 giorni? Parametrizzabile?
- Necessario forzare equalizzazione varianti se una viene disattivata? (Fallback a selezione su attive.)
- Necessità di targeting (es. device/geo)? Fuori scope per MVP.

---

## 📎 Esempi d’Uso
- Creo test `summer-sale` con 2 varianti:
  - Variante A → Pagina ID 123 (peso 70)
  - Variante B → Pagina ID 456 (peso 30)
- Condivido l’URL: `https://example.com/split/summer-sale?utm_source=fb&utm_campaign=summer`
- Il sistema indirizza gli utenti a 123 o 456 (sticky), propagando gli UTM.
