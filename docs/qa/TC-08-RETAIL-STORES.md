# TC-08 — Retail Stores & Visit Planner

**Priority**: P1–P3  
**URL**: /admin/retail-stores | /admin/retail-visit-planner

---

## 1. Retail Store Management

### 1.1 Create Retail Store

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-01 | Create store — required fields | Admin/Manager logged in | 1. Retail Stores → New 2. Enter store name "Dharti Kirana", contact "9876543210", city "Ahmedabad" 3. Save | Store created. Appears in list | P1 |
| TC-08-02 | Create store — all fields | — | 1. Fill name, contact person, phone, address, area, landmark, city, state, pincode, GPS co-ords, Google Maps URL, visit interval, notes 2. Save | All fields saved correctly | P2 |
| TC-08-03 | Create store — blank name | — | 1. Leave name blank 2. Save | Validation error: name required | P1 |
| TC-08-04 | Create store — with GPS coordinates | — | 1. Enter latitude/longitude 2. Save | Coordinates saved. Used in visit planner distance calc | P2 |
| TC-08-05 | Create store — with Google Maps URL | — | 1. Enter Google Maps link 2. Save | Link saved. Clickable on store detail | P2 |
| TC-08-06 | Create store — invalid coordinates | — | 1. Enter lat > 90 or lng > 180 2. Save | Validation error: invalid coordinates | P2 |

### 1.2 Edit Retail Store

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-07 | Edit contact number | Store exists | 1. Edit → change contact 2. Save | Contact updated | P2 |
| TC-08-08 | Edit visit interval | Store exists | 1. Edit → change "Visit every X days" 2. Save | Interval updated. Affects visit planner scoring | P2 |
| TC-08-09 | Change store status | Store active | 1. Toggle status to inactive 2. Save | Inactive stores excluded from visit planner | P2 |

### 1.3 Store List & Search

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-10 | Search by store name | 346 stores exist | 1. Type "dharti" | Matching stores shown | P2 |
| TC-08-11 | Filter by city | — | 1. Filter by city | Only stores in that city shown | P2 |
| TC-08-12 | Filter by area | — | 1. Filter by area | Area-scoped results | P2 |
| TC-08-13 | Filter by status | — | 1. Filter active/inactive | Correct subset shown | P2 |

---

## 2. Store Visits

### 2.1 Record Visit (Via VisitsRelationManager)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-20 | Record visit to store | Store exists | 1. Open retail store 2. Visits tab → New Visit 3. Enter visit date, notes, outcome 4. Save | Visit recorded. "Last Visited" date updates | P1 |
| TC-08-21 | Record visit — with order | Store visit | 1. Record visit 2. Add order amount ₹2500 | Order amount saved. Affects scoring | P2 |
| TC-08-22 | Record multiple visits | Store exists | 1. Record 3 visits over time | All 3 visits listed. Latest visit shown on store | P2 |
| TC-08-23 | View visit history | Store with visits | 1. Open store → Visits tab | All visits listed with dates, notes, amounts | P2 |

---

## 3. Retail Visit Planner

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-30 | Access visit planner | Manager/Admin logged in | 1. Field Sales → Visit Planner | Page loads without error | P1 |
| TC-08-31 | Store recommendations shown | Stores with visit history | 1. View recommendations | Scored store list displayed (overdue stores at top) | P1 |
| TC-08-32 | Overdue stores ranked higher | Mix of visited/unvisited stores | 1. View recommendations | Stores not visited recently ranked highest | P1 |
| TC-08-33 | High-value stores ranked higher | Stores with different avg order values | 1. View recommendations | Higher-revenue stores ranked higher (all else equal) | P2 |
| TC-08-34 | Filter recommendations by date | Planner open | 1. Set date filter | Recommendations updated for that day | P2 |
| TC-08-35 | Limit stores to visit | Planner open | 1. Set "Max stores to visit" = 5 2. Generate plan | Only top 5 recommended stores shown | P2 |
| TC-08-36 | Route optimization | Multiple stores with GPS | 1. Click "Build Route" | Nearest-neighbour ordering applied. Google Maps URL generated | P1 |
| TC-08-37 | Open route in Google Maps | Route built | 1. Click "Open in Google Maps" link | Google Maps opens in new tab with waypoints | P2 |
| TC-08-38 | Stores without GPS excluded from route | Some stores lack coordinates | 1. Build route | Only GPS-enabled stores included in route | P2 |
| TC-08-39 | Visit planner with no stores | No retail stores | 1. Open planner | Empty state message shown. No errors | P3 |
| TC-08-40 | Summary cards accurate | Planner open | 1. Check summary cards (stores due, overdue, high-value) | Numbers match actual data | P2 |

---

## 4. Bulk Order Inquiries

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-50 | Create bulk order inquiry | Retail store exists | 1. Retail Stores → select store → Bulk Orders → New 2. Enter product, qty, expected date 3. Save | Inquiry created | P2 |
| TC-08-51 | View bulk inquiries for store | Inquiries exist | 1. Open store → Bulk Order Inquiries tab | All inquiries listed | P2 |
| TC-08-52 | Update inquiry status | Inquiry exists | 1. Edit inquiry → mark as "Fulfilled" | Status updated | P2 |

---

## 5. Feedback

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-08-60 | View feedback submissions | Feedback exists | 1. Go to Feedback section | All feedback listed | P2 |
| TC-08-61 | Filter feedback by store | — | 1. Filter by retail store | Store-scoped feedback shown | P2 |
| TC-08-62 | Feedback navigation badge | Unread feedback exists | 1. View sidebar | Badge count shown on Feedback menu item | P2 |
| TC-08-63 | Feedback navigation badge — no error | No feedback in DB | 1. Check sidebar | Badge shows 0 or is hidden. No 500 error | P1 |
