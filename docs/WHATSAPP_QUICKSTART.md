# Quick Start: WhatsApp Receipts

## Using the Feature (Cashier Guide)

### 1. During Checkout
When a customer is selected and has a phone number:

![WhatsApp Checkbox](screenshot-placeholder.png)

✅ A checkbox will appear: **"Send receipt via WhatsApp"**
- Below it shows the customer's phone number
- Check the box if customer wants receipt on WhatsApp
- Complete the sale as normal

### 2. After Sale Completion
- Receipt is sent immediately to customer's WhatsApp
- Notification shows: "Sale Completed - Invoice: INV-123 (WhatsApp sent)"
- Customer receives formatted receipt within seconds

### 3. No Phone Number?
If customer doesn't have a phone number:
- The WhatsApp checkbox won't appear
- Complete sale normally (no WhatsApp option)
- Add phone to customer profile for future purchases

---

## Setup for Store Owner

### Quick Setup (15 minutes)

#### Step 1: Create Twilio Account
```
1. Go to: https://www.twilio.com/try-twilio
2. Sign up (free $15 credit)
3. Verify your email and phone
4. Complete the welcome wizard
```

#### Step 2: Get Your Credentials
```
1. Go to: https://console.twilio.com/
2. Note your Account SID (starts with AC...)
3. Click "Show" to reveal Auth Token
4. Copy both values
```

#### Step 3: Enable WhatsApp (Sandbox for Testing)
```
1. In Console, go to: Messaging → Try it out → WhatsApp
2. You'll see a number like: +1 415 523 8886
3. Send this message from YOUR phone:
   "join [your-unique-code]"
   (Example: "join kitchen-tiger")
4. You'll receive a confirmation on WhatsApp
```

#### Step 4: Add to Your POS
```bash
# Edit .env file
nano .env

# Add these lines:
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_WHATSAPP_FROM=+14155238886

# Save and exit (Ctrl+X, then Y, then Enter)

# Clear cache
php artisan config:clear
```

#### Step 5: Test It
```
1. Open POS in browser
2. Create a test sale
3. Select customer with phone number (or add phone to a customer)
4. Check "Send receipt via WhatsApp"
5. Complete sale
6. Check your WhatsApp - receipt should arrive!
```

---

## Production Setup (For Live Business)

### Before Going Live

⚠️ **Important:** Twilio Sandbox is for TESTING only. For live business:

1. **Apply for WhatsApp Business API**
   - In Twilio Console: Messaging → WhatsApp → Request Access
   - Provide business details, website, use case
   - Meta review takes 1-2 weeks

2. **Get Business Verified**
   - Business registration documents
   - Website with proper contact info
   - Privacy policy and terms of service

3. **Customer Opt-In Required**
   - Customers must initiate conversation first
   - They send any message to your number
   - Then you can send receipts
   - Required by WhatsApp policy

### Customer Opt-In Methods

**Option 1: In-Store Sign**
```
Want receipts on WhatsApp?
Send "Hi" to: +91-XXXXX-XXXXX
```

**Option 2: First Purchase**
```
"Would you like receipts on WhatsApp? 
 Just send 'Hi' to this number: +91-XXXXX-XXXXX"
```

**Option 3: QR Code**
- Generate WhatsApp QR code
- Customer scans → Opens WhatsApp
- They click Send
- They're opted in

### Cost Planning

**Example Usage:**
- 100 customers/day × 30 days = 3,000 receipts/month
- At ₹0.50/message = ₹1,500/month
- Twilio $15 credit = ~1,875 messages (first month free)

**Tips to Save:**
- Make it opt-in only (checkbox, not default)
- Offer email as alternative
- Use for high-value customers only
- Monitor usage in Twilio Console

---

## Troubleshooting

### "WhatsApp Not Configured" Message
**Cause:** Environment variables not set or incorrect

**Fix:**
```bash
# Check if variables exist
cat .env | grep TWILIO

# Should show:
# TWILIO_ACCOUNT_SID=ACxxxx...
# TWILIO_AUTH_TOKEN=xxxxx...
# TWILIO_WHATSAPP_FROM=+14155238886

# If empty or missing, add them
nano .env

# Then clear cache
php artisan config:clear
```

### Receipt Not Received
**Cause 1:** Customer hasn't joined Sandbox (or opted in for production)

**Fix:**
- Testing: Customer must send "join [code]" to Twilio number first
- Production: Customer must send any message to your WhatsApp number first

**Cause 2:** Wrong phone number format

**Fix:**
- Check customer phone in database
- Should be 10 digits: `9876543210`
- Or with +91: `+919876543210`
- No spaces, dashes, or extra characters

**Cause 3:** Invalid Twilio credentials

**Fix:**
```bash
# Test credentials
php artisan tinker --execute="
\$response = Http::withBasicAuth(
    config('services.twilio.account_sid'),
    config('services.twilio.auth_token')
)->get('https://api.twilio.com/2010-04-01/Accounts/' . config('services.twilio.account_sid') . '.json');
echo \$response->successful() ? 'Credentials valid' : 'Credentials invalid';
"
```

### Check Delivery Status
```
1. Go to: https://console.twilio.com/
2. Click: Monitor → Logs → Messaging
3. Find your message by time/phone number
4. Check status and error details
```

---

## FAQs

**Q: Do I need to pay for Twilio?**
A: You get $15 free credit (enough for ~2,000 messages). After that, pay-as-you-go.

**Q: Can I use my own WhatsApp Business number?**
A: Not directly. You need to go through Twilio or another WhatsApp Business API provider.

**Q: What if customer doesn't have WhatsApp?**
A: The checkbox won't appear. Offer email receipt instead (future feature).

**Q: Can I send promotional messages?**
A: Only after customer opts in. Use pre-approved templates. Different pricing.

**Q: Is customer data secure?**
A: Yes. Receipt sent directly from Twilio to customer. Not stored in Twilio logs long-term.

**Q: Can customer reply?**
A: They can, but POS doesn't handle replies yet. Future enhancement.

**Q: Works for international customers?**
A: Yes. Phone number must include country code (e.g., +1 for US, +44 for UK).

**Q: Can I customize the receipt format?**
A: Yes! Edit `app/Services/ReceiptService.php` to change layout/text.

---

## Support

**Twilio Issues:**
- Help: https://www.twilio.com/help/
- Docs: https://www.twilio.com/docs/whatsapp
- Status: https://status.twilio.com/

**POS Issues:**
- Check logs: `storage/logs/laravel.log`
- Test service: `php -f tmp/test_whatsapp.php`
- Read docs: `docs/WHATSAPP_SETUP.md`

**Emergency Disable:**
```bash
# To temporarily disable WhatsApp
# Remove or comment these in .env:
# TWILIO_ACCOUNT_SID=
# TWILIO_AUTH_TOKEN=
# TWILIO_WHATSAPP_FROM=

php artisan config:clear

# Feature will fallback to logging only
```
