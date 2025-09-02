# GoWPTracker – Testing Guide

This document describes how to test the plugin during development and after deployment.

---

## ⚠️ Rule
**Never delete test cases from this file.**  
You can only:
- Flag tests as executed ✅  
- Add notes or new test cases  

---

## 🧪 Step 1 – Core Redirect
- [ ] Test `/go?dest=https://milano-bags.com` → should redirect (302) correctly.  
- [ ] Test `/go?dest=https://notallowed.com` → should block with error.  
- Notes:  

---

## 🧪 Step 2 – Logging
- [ ] Click a CTA on a PLP with UTM params.  
- [ ] Check DB table `wp_go_clicks` for new row.  
- [ ] Confirm fields: timestamp, plp, utm_campaign, referrer, ua, ip.  
- Notes:  

---

## 🧪 Step 3 – UTM + PLP Propagation
- [ ] Visit PLP with `?utm_source=fb&utm_campaign=test`.  
- [ ] Click CTA → redirected URL should include UTM + `plp=slug-of-plp`.  
- [ ] Notes:  

---

## 🧪 Step 4 – Admin Dashboard
- [ ] Open WP Admin → GO Tracker.  
- [ ] Confirm click counts match DB entries.  
- [ ] Test filter: last 7 days vs 30 days.  
- Notes:  

---

## 🧪 Step 5 – JavaScript Helper
- [ ] Add JS helper to CTA links.  
- [ ] Reload PLP with UTM params.  
- [ ] Confirm CTAs automatically include UTM + PLP in the `/go` URL.  
- Notes:  

---

## 🧪 Step 6 – Security
- [ ] Try injecting invalid query params → must be sanitized.  
- [ ] Test IP logging → must not be plain text.  
- [ ] Simulate bot (HEAD request, UA empty) → must not be logged.  
- Notes:  

---

## 🧪 Step 7 – Reporting Enhancements (future)
- [ ] Test CSV export contains correct rows.  
- [ ] Test charts match aggregated DB data.  
- Notes:  
