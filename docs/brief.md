# GO Tracker Plugin – Brief Tecnico

## 🎯 Obiettivo del sistema
Il plugin **GO Tracker** serve a **capire quale pre-landing page (PLP)** porta più visitatori a un e-commerce esterno.  
Esempio pratico: le PLP stanno su **camillabarone.space**, mentre l’e-commerce è su **milano-bags.com**.  

L’obiettivo è misurare, in modo **preciso e server-side**, quanti utenti ogni PLP manda all’e-commerce, conservando anche i parametri UTM delle campagne pubblicitarie.

---

## 🚩 Problema da risolvere
- Il traffico arriva da Meta Ads (e altre fonti di pubblicità) con parametri UTM.  
- Gli utenti passano da pre-landing page (PLP) diverse.  
- Cliccano un bottone che li porta all’e-commerce.  
- Senza un sistema dedicato **non si può sapere quale PLP manda più utenti allo shop**, quindi non si riesce a capire quali pagine sono più efficaci.

---

## 🛠️ Come lo risolve
Il plugin introduce una **pagina di redirect intermedia** su WordPress, chiamata `/go`.  
Questa pagina funziona come un **tornello intelligente**:

1. **L’utente clicca il bottone** su una PLP.  
   - Il link non porta direttamente allo shop (`milano-bags.com`).  
   - Porta invece a `/go?dest=...` sul dominio delle PLP (`camillabarone.space`).  

2. **Il plugin intercetta la richiesta** su `/go`.  
   - **Valida** che il dominio di destinazione sia tra quelli consentiti (whitelist).  
   - **Propaga** i parametri UTM (utm_source, utm_campaign, ecc.) e aggiunge il parametro `plp` con lo slug della pre-landing.  
   - **Registra** nel database un log con:  
     - timestamp  
     - plp (pagina pre-landing)  
     - destinazione (shop)  
     - UTM  
     - referrer  
     - user agent  
     - IP (salvato in formato binario, non in chiaro)  

3. Dopo il log, il plugin esegue un **redirect 302** verso l’e-commerce con tutti i parametri.  
   - L’utente non nota nulla, arriva normalmente sullo shop.  

4. In WordPress Admin → **GO Tracker**, c’è una dashboard che mostra:  
   - Click totali per PLP  
   - Breakdown per campagna (utm_campaign)  
   - Filtro temporale (es. ultimi 7 / 30 giorni)  

---

## 📊 Cosa permette di fare
- Sapere con certezza quale PLP porta più traffico allo shop.  
- Incrociare le informazioni con le campagne pubblicitarie (grazie agli UTM).  
- Ottenere dati più puliti rispetto al tracking client-side (Google Analytics), perché la registrazione è **server-side**.  
- Ridurre gli errori causati da adblocker o chiusure della finestra prima che lo shop si carichi.  

---

## ✅ Vantaggi chiave
- **Semplicità**: basta cambiare i link dei bottoni nelle PLP da diretto → `/go`.  
- **Affidabilità**: il log è lato server, quindi meno falsi positivi.  
- **Sicurezza**: redirect solo verso domini whitelisted (no open redirect).  
- **Portabilità**: il plugin funziona su qualsiasi sito WordPress che usi pre-landing, non solo su camillabarone.space.  

---

## 🔗 Esempio pratico
### Senza plugin (attuale)
```

CTA → [https://milano-bags.com/?utm\_source=fb\&utm\_campaign=test](https://milano-bags.com/?utm_source=fb&utm_campaign=test)

```

### Con plugin
```

CTA → [https://camillabarone.space/go?dest=https://milano-bags.com](https://camillabarone.space/go?dest=https://milano-bags.com)

```

Il plugin trasforma e invia così:
```

Redirect → [https://milano-bags.com/?utm\_source=fb\&utm\_campaign=test\&plp=migliori-borse](https://milano-bags.com/?utm_source=fb&utm_campaign=test&plp=migliori-borse)

```

Nel database viene registrato un click con:  
- plp = "migliori-borse"  
- utm_campaign = "test"  
- referrer = "https://camillabarone.space/migliori-borse"  
- ecc.  

---

## 📌 In sintesi
Il plugin **GO Tracker** è un sistema di misurazione server-side che permette di sapere **da quale pre-landing arrivano gli utenti che entrano nell’e-commerce**.  
Si basa su un **redirect intermedio controllato**, che registra e conserva i dati chiave prima di mandare l’utente sul negozio.  
```

---

Vuoi che ti aggiunga anche lo **schema visuale (diagramma a blocchi)** in mermaid dentro al `brief.md`, così puoi incollarlo direttamente in GitHub/Notion e avere il flusso illustrato?
