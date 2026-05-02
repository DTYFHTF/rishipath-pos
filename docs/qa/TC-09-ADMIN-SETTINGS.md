# TC-09 — Admin & Settings

**Priority**: P1–P3  
**URL**: /admin/users | /admin/roles | /admin/stores | /admin/organizations | /admin/notifications | /admin/report-schedules

---

## 1. User Management

### 1.1 Create User

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-01 | Create user — required fields | Super Admin logged in | 1. Settings → Users → New 2. Enter name, email, password, role 3. Save | User created. Can log in with credentials | P1 |
| TC-09-02 | Create user — duplicate email | User with email exists | 1. Create user with same email | Validation error: email already taken | P1 |
| TC-09-03 | Create user — blank password | — | 1. Leave password empty 2. Save | Validation error: password required | P1 |
| TC-09-04 | Create user — short password | — | 1. Enter 3-char password | Validation error: min 8 characters | P1 |
| TC-09-05 | Create user — assign role | Roles configured | 1. Select "Cashier" role during user creation | User gets cashier permissions after login | P1 |
| TC-09-06 | Create user — assign store | Stores configured | 1. Assign user to specific store | User's POS scoped to that store | P2 |
| TC-09-07 | Manager creates user | Store Manager role | 1. Create user as Manager | Succeeds (Manager has create_users permission) | P2 |
| TC-09-08 | Cashier cannot create user | Cashier role | 1. Navigate to /admin/users/create | Redirected or 403 | P1 |

### 1.2 Edit User

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-09 | Change user role | User exists | 1. Edit user → change role 2. Save | Role updated. Permissions change on next login | P1 |
| TC-09-10 | Reset user password | User exists | 1. Edit user → new password 2. Save | User can log in with new password | P2 |
| TC-09-11 | Deactivate user | Active user | 1. Toggle active/inactive 2. Save | Inactive user cannot log in | P2 |

### 1.3 User List

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-12 | View all users | Users exist | 1. Go to Users list | All org users listed with role, status | P1 |
| TC-09-13 | Search user by name | — | 1. Type name | Matching users shown | P2 |

---

## 2. Role Management

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-20 | View all roles | Super Admin | 1. Settings → Roles | System roles listed (Super Admin, Manager, Cashier, Clerk, Accountant) | P1 |
| TC-09-21 | Create custom role | Super Admin | 1. Roles → New 2. Name "Pharmacist", select permissions 3. Save | Custom role created | P2 |
| TC-09-22 | Edit role permissions | Super Admin | 1. Edit role → add/remove permissions 2. Save | Permissions updated. Affects all users with this role | P2 |
| TC-09-23 | Cannot edit system roles | — | 1. Try to edit "Super Administrator" role | Edit blocked or permissions locked | P2 |
| TC-09-24 | Delete custom role | Custom role with no users | 1. Delete role | Role deleted | P3 |
| TC-09-25 | Delete role with users | Role assigned to users | 1. Try to delete | Error: cannot delete role with active users | P2 |

---

## 3. Store Management

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-30 | Create store | Super Admin | 1. Settings → Stores → New 2. Enter store name, address, phone 3. Save | Store created. Available in store switcher | P2 |
| TC-09-31 | Edit store details | Store exists | 1. Edit store name 2. Save | Updated everywhere | P2 |
| TC-09-32 | Deactivate store | Active store | 1. Toggle inactive | Store hidden from normal users | P2 |
| TC-09-33 | Store appears in POS header | Store created | 1. Open POS as user assigned to that store | Correct store name shown | P1 |

---

## 4. Organization Settings

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-40 | View organization settings | Super Admin | 1. Settings → Organization | Org details shown (name, address, GST, logo) | P2 |
| TC-09-41 | Edit organization name | — | 1. Change org name 2. Save | Name updated in reports and receipts | P2 |
| TC-09-42 | Upload organization logo | — | 1. Upload logo image 2. Save | Logo shown in receipts and header | P3 |
| TC-09-43 | Edit GST number | — | 1. Update GST 2. Save | GST appears on sales receipts | P2 |

---

## 5. Notifications

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-50 | View in-app notifications | Notifications exist | 1. Click bell icon | Notifications dropdown shows | P2 |
| TC-09-51 | Mark notification as read | Unread notifications | 1. Click on notification | Marked as read. Removed from unread count | P2 |
| TC-09-52 | Low stock notification | Variant below threshold | 1. When stock falls below alert level | Notification appears for warehouse/admin | P2 |

---

## 6. Alert Rules

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-60 | Create alert rule — low stock | — | 1. Settings → Alert Rules → New 2. Select "Low Stock", product, threshold = 10 3. Select notification type (email/in-app) 4. Save | Rule created | P2 |
| TC-09-61 | Alert triggers when stock drops | Rule configured, stock above threshold | 1. Adjust stock to below threshold | Alert triggered. Notification sent | P2 |
| TC-09-62 | Edit alert threshold | Rule exists | 1. Edit rule → change threshold 2. Save | Threshold updated | P3 |

---

## 7. Global Notification Duration

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-70 | Success notifications persist 4 seconds | Any save action | 1. Save any record | Green notification appears and stays visible for ~4 seconds before auto-dismissing | P3 |
| TC-09-71 | Error notifications visible long enough | Trigger a validation error | 1. Save with missing required field | Error notification visible for sufficient time to read | P2 |

---

## 8. Automation Dashboard

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-09-80 | Access Automation Dashboard | Admin | 1. Navigate to Automation section | Dashboard loads | P2 |
| TC-09-81 | Manual trigger available | — | 1. View automation controls | Manual run buttons for automation tasks | P3 |
