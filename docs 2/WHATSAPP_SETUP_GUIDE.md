# WhatsApp Integration Setup Guide
## RishiPath POS - Complete Configuration

---

## 🎯 Overview

This guide covers **complete WhatsApp setup** for RishiPath POS, from sandbox testing to production deployment with business-initiated template messages.

### What's Working Now
- ✅ Twilio account configured
- ✅ Sandbox mode working (+14155238886)
- ✅ Plain text receipts
- ✅ Template message support in code
- ✅ Error handling and logging

### What Requires Manual Setup
- ⚠️ WhatsApp Business sender registration
- ⚠️ Template creation and approval
- ⚠️ Production number setup

---

## 📋 Prerequisites

1. **Twilio Account** (Active)
   - Account SID: `[REDACTED_TWILIO_SID]`
   - Auth Token: Stored in `.env`
   - Sandbox Number: `+14155238886`

2. **Facebook Business Manager Account**
   - Required for production WhatsApp sender
   - Must be verified business
   - Link: https://business.facebook.com

3. **Phone Number for Registration**
   - Must not be currently on WhatsApp
   - Will become your business WhatsApp number
   - Can be VoIP number or mobile

---

## 🧪 Phase 1: Sandbox Testing (Current)

### Step 1.1: Recipient Opt-In (CRITICAL)

Before sending messages, recipients MUST opt into your sandbox:

```
1. Recipient opens WhatsApp
2. Start new chat with: +1 415 523 8886
3. Send message: join <your-code>
   (Your code shown in Twilio Console → Messaging → Try it out → Send a WhatsApp message)
```

**Your sandbox code:** Check at https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn

### Step 1.2: Test Plain Text Receipt

```bash
php test_whatsapp_comprehensive.php
```

Enter test phone number (e.g., `+9779843268325`) and verify:
- ✅ Configuration valid
- ✅ Phone formatting works
- ✅ Message sent (check SID)
- ✅ Recipient receives message

### Step 1.3: Check Twilio Logs

View sent messages:
```bash
https://console.twilio.com/us1/monitor/logs/messages
```

Common errors:
- **63007**: Recipient not opted into sandbox → Send "join" message
- **21211**: Invalid 'To' phone number → Check format (+country code)
- **21408**: Permission to send disabled → Check account status

---

## 🏢 Phase 2: Production WhatsApp Sender

### Step 2.1: Register WhatsApp Sender

**In Twilio Console:**

1. Go to: https://console.twilio.com/us1/develop/sms/senders/whatsapp-senders
2. Click **"Register a WhatsApp Sender"**
3. Select **"Use your existing Facebook Business Account"**
4. Complete Facebook Business verification:
   - Business name
   - Business website
   - Business address
   - Tax ID (if required)

5. Link phone number:
   - Enter phone number (not currently on WhatsApp)
   - Verify via SMS code
   - Complete 2-factor authentication

6. Submit for review (1-3 business days)

**Cost:** $0 registration, pay-per-message pricing applies

### Step 2.2: Display Name & Profile

Once approved:
1. Set business display name (shown to customers)
2. Upload profile photo (square, 640x640px minimum)
3. Add business description
4. Set business category

### Step 2.3: Update Configuration

After approval, update `.env`:

```env
TWILIO_WHATSAPP_FROM=+1234567890  # Your registered number
```

Remove sandbox number, restart services:
```bash
php artisan config:cache
php artisan queue:restart
```

---

## 📝 Phase 3: Message Templates

### Step 3.1: Understanding Templates

**Why Templates?**
- WhatsApp requires pre-approved templates for business-initiated messages
- Customer replies = open 24-hour conversation window (no template needed)
- Templates prevent spam and enforce quality

**Template Categories:**
1. **UTILITY** (transactional):
   - Order confirmations
   - Receipts
   - Delivery updates
   - Account alerts
   
2. **MARKETING** (promotional):
   - Product launches
   - Sales announcements
   - Special offers
   
3. **AUTHENTICATION** (OTP):
   - Login codes
   - Verification codes

### Step 3.2: Create Templates in Twilio

**Navigate to:**
```
https://console.twilio.com/us1/develop/sms/content-editor
```

**Click "Create new Content"**

#### Example 1: Receipt Template

```
Name: pos_receipt
Category: UTILITY
Language: English

Content:
---
🧾 *RishiPath POS Receipt*

Order #{{1}}
Date: {{2}}
---
Subtotal: ₹{{3}}
Tax: ₹{{4}}
*Total: ₹{{5}}*
---
Thank you for your purchase!
```

**Variables:**
- `{{1}}` = order_number
- `{{2}}` = date
- `{{3}}` = subtotal
- `{{4}}` = tax
- `{{5}}` = total

**Buttons (optional):**
- Quick Reply: "Track Order"
- URL: "View Invoice" → https://yoursite.com/invoice/{{1}}

#### Example 2: Delivery Notification

```
Name: order_shipped
Category: UTILITY
Language: English

Content:
---
📦 *Your order is on the way!*

Order #{{1}} has been shipped.
Expected delivery: {{2}}

Track: {{3}}
---
```

**Variables:**
- `{{1}}` = order_number
- `{{2}}` = delivery_date
- `{{3}}` = tracking_url

#### Example 3: Low Stock Alert (Staff)

```
Name: low_stock_alert
Category: UTILITY
Language: English

Content:
---
⚠️ *Low Stock Alert*

Product: {{1}}
SKU: {{2}}
Current Stock: {{3}} units
Reorder Level: {{4}} units

Action required.
---
```

### Step 3.3: Submit Templates for Approval

**After creating template:**
1. Click **"Submit for Approval"**
2. Provide sample values for all variables
3. Explain use case clearly
4. Wait for approval (1-24 hours typically)

**Approval Criteria:**
- Clear purpose (transactional vs promotional)
- No misleading content
- Proper grammar and formatting
- Valid variable placeholders
- Complies with WhatsApp Commerce Policy

**Track status:**
```
https://console.twilio.com/us1/develop/sms/content-editor
```

Statuses:
- 🟡 **PENDING**: Under review
- 🟢 **APPROVED**: Ready to use
- 🔴 **REJECTED**: Needs modification

### Step 3.4: Get Template Content SID

Once approved, click template to view:
```
Content SID: HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Copy this SID for configuration.

---

## ⚙️ Phase 4: Code Configuration

### Step 4.1: Update `.env`

```env
# Twilio WhatsApp Configuration
TWILIO_ACCOUNT_SID=[REDACTED_TWILIO_SID]
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_WHATSAPP_FROM=+1234567890  # Production number

# Template Content SIDs (after approval)
TWILIO_TEMPLATE_RECEIPT_SID=HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TEMPLATE_DELIVERY_SID=HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TEMPLATE_LOW_STOCK_SID=HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Step 4.2: Update `config/services.php`

```php
'twilio' => [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    
    // Template Content SIDs
    'templates' => [
        'receipt' => env('TWILIO_TEMPLATE_RECEIPT_SID'),
        'delivery' => env('TWILIO_TEMPLATE_DELIVERY_SID'),
        'low_stock' => env('TWILIO_TEMPLATE_LOW_STOCK_SID'),
    ],
],
```

### Step 4.3: Using Templates in Code

**Send receipt with template:**

```php
use App\Services\WhatsAppService;

$whatsapp = new WhatsAppService();

// Using template (pre-approved)
$result = $whatsapp->sendTemplate(
    phoneNumber: '+9779843268325',
    contentSid: config('services.twilio.templates.receipt'),
    variables: [
        '1' => $sale->order_number,
        '2' => $sale->created_at->format('M d, Y h:i A'),
        '3' => number_format($sale->subtotal, 2),
        '4' => number_format($sale->tax_amount, 2),
        '5' => number_format($sale->total, 2),
    ],
    fallbackBody: 'Your RishiPath POS receipt'
);

if ($result['success']) {
    Log::info("Receipt sent", ['sid' => $result['sid']]);
} else {
    Log::error("Failed to send receipt", ['error' => $result['error']]);
}
```

**Plain text (24-hour window):**

```php
// If customer replied within 24 hours, no template needed
$receipt = $receiptService->generateReceipt($sale);
$result = $whatsapp->sendReceipt($sale, $customer->phone, $receipt);
```

---

## 🧪 Phase 5: Testing

### Test 1: Sandbox Plain Text

```bash
php test_whatsapp_comprehensive.php
```

### Test 2: Production Template

```bash
php artisan tinker
```

```php
$service = new \App\Services\WhatsAppService();
$result = $service->sendTemplate(
    '+9779843268325',
    config('services.twilio.templates.receipt'),
    ['1' => 'TEST123', '2' => 'Jan 20, 2026 11:30 PM', '3' => '100.00', '4' => '12.00', '5' => '112.00'],
    'Test receipt'
);
dd($result);
```

### Test 3: POS Integration

1. Create sale in POS
2. Enter customer phone
3. Click "Send Receipt"
4. Check logs: `tail -f storage/logs/laravel.log | grep WhatsApp`

---

## 📊 Phase 6: Monitoring & Analytics

### Twilio Dashboard

**Message logs:**
```
https://console.twilio.com/us1/monitor/logs/messages
```

**Filter by:**
- Status (queued, sent, delivered, failed, undelivered)
- Date range
- Error code

**Key metrics:**
- Delivery rate
- Response rate
- Cost per message
- Template performance

### Laravel Logging

**View sent messages:**
```bash
grep "WhatsApp.*sent successfully" storage/logs/laravel.log
```

**View failures:**
```bash
grep "Failed to send WhatsApp" storage/logs/laravel.log
```

**Daily summary:**
```bash
grep "WhatsApp" storage/logs/laravel-$(date +%Y-%m-%d).log | wc -l
```

### Error Code Reference

| Code | Meaning | Solution |
|------|---------|----------|
| 63007 | Not opted in | Recipient must send "join" (sandbox) |
| 21211 | Invalid phone | Check E.164 format (+country code) |
| 21408 | Permission denied | Check account status |
| 21610 | Attempt to send outside 24hr window | Use approved template |
| 63016 | Template not approved | Wait for approval or resubmit |

---

## 💰 Pricing (India)

**WhatsApp Business Platform Pricing:**

| Category | Rate (per message) |
|----------|-------------------|
| User-Initiated (replies) | ₹0.25 - ₹0.30 |
| Business-Initiated (templates) | ₹0.80 - ₹1.20 |
| Authentication (OTP) | ₹0.20 - ₹0.25 |

**First 1,000 conversations per month: FREE**

**Tips to reduce cost:**
1. Only send when customer opts in
2. Batch notifications
3. Use session window (24hr) for follow-ups
4. Prefer user-initiated over business-initiated

---

## 🚨 Common Issues & Solutions

### Issue 1: Messages Not Delivered

**Symptoms:** Status = sent, but not delivered

**Solutions:**
1. Check recipient's phone has WhatsApp installed
2. Check recipient's phone has internet connection
3. Verify phone number format (+country code)
4. Check if recipient blocked your number
5. Verify recipient opted into sandbox (if testing)

### Issue 2: Error 63007 (Not Opted In)

**Solution (Sandbox):**
```
Recipient sends: join [your-code]
To: +1 415 523 8886
```

**Solution (Production):**
This shouldn't occur - production numbers don't need opt-in

### Issue 3: Template Rejected

**Common reasons:**
- Vague variable names (use descriptive names)
- Marketing content in UTILITY category
- Grammar errors
- Missing context
- Too many variables

**Solution:**
1. Review rejection reason in Twilio Console
2. Modify template
3. Add clear sample values
4. Resubmit with explanation

### Issue 4: 24-Hour Window Expired

**Error 21610:** Tried to send plain text after 24hr

**Solution:**
Use pre-approved template instead:
```php
// Before (fails after 24hr)
$whatsapp->sendReceipt($sale, $phone, $text);

// After (always works)
$whatsapp->sendTemplate($phone, $templateSid, $variables);
```

---

## 📚 Additional Resources

**Official Documentation:**
- Twilio WhatsApp API: https://www.twilio.com/docs/whatsapp/api
- Template Best Practices: https://www.twilio.com/docs/whatsapp/tutorial/send-whatsapp-notification-messages-templates
- Error Codes: https://www.twilio.com/docs/api/errors

**Meta (WhatsApp) Policies:**
- Commerce Policy: https://www.whatsapp.com/legal/commerce-policy
- Business Policy: https://www.whatsapp.com/legal/business-policy

**Community:**
- Twilio Community: https://www.twilio.com/community
- Stack Overflow: Tag `twilio-whatsapp`

---

## ✅ Production Checklist

Before going live:

- [ ] WhatsApp sender registered and approved
- [ ] Production phone number configured in `.env`
- [ ] All required templates created and approved
- [ ] Template SIDs added to configuration
- [ ] Tested template sending with real data
- [ ] Error handling and logging in place
- [ ] Customer opt-in flow designed (if needed)
- [ ] Privacy policy updated (mention WhatsApp)
- [ ] Terms of service updated (message frequency)
- [ ] Staff trained on WhatsApp features
- [ ] Monitoring and alerts configured
- [ ] Budget allocated for message costs
- [ ] Fallback method if WhatsApp fails (SMS, email)

---

## 🎓 Next Steps

1. **Complete sandbox testing** (Phase 1)
2. **Register production sender** (Phase 2) - 1-3 days
3. **Create and submit templates** (Phase 3) - 1-24 hours
4. **Update configuration** (Phase 4) - 30 minutes
5. **Run comprehensive tests** (Phase 5) - 1 hour
6. **Monitor first week** (Phase 6) - ongoing

**Estimated total time:** 3-5 business days (mostly waiting for approvals)

---

## 📞 Support

**Twilio Support:**
- Email: help@twilio.com
- Console: https://console.twilio.com/support
- Phone: Check console for regional number

**RishiPath POS Team:**
- Technical issues: Check logs first
- Integration questions: Review this guide
- Feature requests: Submit to development team

---

**Last Updated:** January 20, 2026
**Version:** 1.0.0
