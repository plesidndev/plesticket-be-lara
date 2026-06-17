# Plesticket Feature Progress

Legend: ✅ Done · ⚠️ Partial · ❌ Not yet · 📱 Separate project

---

## Auth & UX

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 1 | Auth blocker (redirect setelah login) | ✅ Done | `login.vue` baca `?redirect=` query param, user balik ke halaman asal setelah login |
| 2 | Pick location — sub-wilayah (kota by provinsi) | ✅ Done | `GET /cities?province_code=XX` · EventFormBody load kota saat provinsi dipilih |

---

## Ticket Purchasing Flow

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 3 | 1 booking bisa lebih dari 1 tipe tiket | ✅ Done | Backend `items[]` array · Frontend per-type quantity stepper |
| 4 | Dashboard pemilihan tiket | ✅ Done | Stepper `−/+` per tipe tiket langsung di event detail page |
| 5a | Flow buy — Pilih tiket | ✅ Done | Multi-type stepper, auto-select jika belum pilih |
| 5b | Flow buy — Summary | ✅ Done | Sheet step 1: per-item rows + grand total |
| 5c | Flow buy — Metode pembayaran | ⚠️ Partial | Hardcoded "Bank Transfer" · belum ada gateway |
| 5d | Flow buy — Waiting payment | ❌ Not yet | Perlu integrasi payment gateway + webhook handler |
| 5e | Flow buy — Detail tiket paid | ✅ Done | QR code per tiket ditampilkan setelah bayar, link ke `/orders` |
| 6 | Tiket kirim ke email | ❌ Not yet | Perlu Mailable class + queue + trigger di `OrderService::pay()` |
| 7 | Batasan max 10 tiket per email per event | ⚠️ Partial | Max 10 per item sudah ada di `CreateOrderRequest` · cross-order limit belum dicek |

---

## Talent

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 8 | Data talent di event (lineup) | ✅ Done | Section "Lineup" di event detail EO · add/remove dari masterdata atau free-name |
| 9 | Master data talent | ✅ Done | CRUD lengkap · EO submit → admin verify · tabel `talents` |
| 10 | Talent bisa group atau personal | ✅ Done | Field `type: personal\|group` di tabel talents |
| 11 | Isi talent free jika belum ada di masterdata | ✅ Done | Field `free_name` di `event_talents` · bisa pakai tanpa pilih dari masterdata |

---

## Agen / Ticket Box

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 12 | Ticket box = agen | ❌ Not yet | `OrganizerRole::MitraTicketBox` sudah ada · tapi belum ada logika penjualan agen |
| 13 | Agen dapat potongan (commission %) yang di-set EO | ❌ Not yet | Perlu kolom `commission_rate` di `organizer_members` + tracking order via agen |
| 14 | Akun agen dibuat di dashboard EO | ⚠️ Partial | `OrganizerMemberController` sudah bisa buat member dengan role apa pun · belum ada UI khusus agen + set komisi |

---

## Scanner Tiket

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 15 | Scanner tiket (web) | ⚠️ Partial | Backend `POST /tickets/{code}/scan` auth:organizer selesai · Frontend page ada tapi belum ada kamera QR scanner |
| 16 | Grab data offline | ❌ Not yet | Perlu service worker + local cache tickets di browser |
| 17 | APK simple scanner + grab data offline | 📱 Separate | React Native / Flutter · di luar scope web project ini |
| 18 | APK bisa scan online dan offline | 📱 Separate | Sync logic di mobile app · di luar scope web project ini |

---

## Payment Gateway

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 19 | Payment QRIS via Xendit / Midtrans | ❌ Not yet | Perlu SDK + create invoice/payment → return URL atau QRIS · webhook handler untuk mark order paid + generate tiket |

---

## Summary

| Status | Count |
|--------|-------|
| ✅ Done | 9 |
| ⚠️ Partial | 4 |
| ❌ Not yet | 5 |
| 📱 Separate project | 2 |
| **Total** | **20** |

---

## Next Priorities

1. ❌ Cross-order max 10 tiket per email per event (`OrderService::create`)
2. ❌ Tiket kirim ke email (Mailable + queue)
3. ❌ Payment gateway Xendit/Midtrans + waiting payment state + webhook
4. ❌ Agen commission system (`commission_rate` + order tracking)
5. ⚠️ QR camera scanner di browser scanner page