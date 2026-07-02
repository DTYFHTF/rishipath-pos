# WhatsApp Receipt Integration Setup

## Overview

The POS system can now send receipts directly to customers via WhatsApp using the Twilio WhatsApp Business API. When a customer has a phone number on file, a checkbox appears in the payment section to send the receipt after completing the sale.

## Features

- ✅ Automatic receipt generation with full sale details
- ✅ One-click WhatsApp sending from POS
- ✅ Phone number formatting (supports Indian numbers with auto +91 prefix)
- ✅ Graceful fallback if WhatsApp not configured (logs receipt instead)
- ✅ Works in development without Twilio credentials (logs only)

## Setup Instructions

### 1. Create Twilio Account

1. Go to [Twilio](https://www.twilio.com/) and create an account
2. Navigate to the [Console Dashboard](https://console.twilio.com/)
3. Note your **Account SID** and **Auth Token**

### 2. Enable WhatsApp on Twilio

1. In Twilio Console, go to **Messaging** → **Try it out** → **Send a WhatsApp message**
2. Follow the steps to activate the WhatsApp Sandbox
3. Send "join [your-sandbox-keyword]" to the Twilio WhatsApp number from your phone
4. Note the WhatsApp-enabled phone number (format: +14155238886)

**For Production:**
- Apply for a Twilio WhatsApp Business Profile
- Get your business number approved (typically takes 1-2 weeks)
- Customers must opt-in by sending a message to your business number first

### 3. Configure Environment Variables

Add these to your `.env` file:

```env
TWILIO_ACCOUNT_SID=your_account_sid_here
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_WHATSAPP_FROM=+14155238886
```

**Note:** The `TWILIO_WHATSAPP_FROM` should be your Twilio WhatsApp-enabled number (in sandbox or production).

### 4. Test in Development

Without configuring Twilio credentials, the system will:
- Still show the WhatsApp checkbox
- Log receipt content to `storage/logs/laravel.log` 
- Show "WhatsApp not configured" notification

This allows you to test the UI and receipt formatting without Twilio.

### 5. Verify Setup

1. Open POS in browser
2. Add items to cart
3. Select a customer with a phone number
4. Check the "Send receipt via WhatsApp" box
5. Complete the sale
6. Customer receives receipt via WhatsApp

## Phone Number Format

The system automatically formats Indian phone numbers:

| Input Format | Output Format | Notes |
|--------------|---------------|-------|
| 9876543210 | +919876543210 | Adds +91 prefix |
| 09876543210 | +919876543210 | Removes leading 0, adds +91 |
| 919876543210 | +919876543210 | Adds + prefix |
| +919876543210 | +919876543210 | Already correct |

For international numbers, ensure they include the country code.

## Receipt Format

The WhatsApp receipt includes:

- Store name and address
- Receipt number and date/time
- Cashier name
- Customer name and phone
- Itemized list with quantities and prices
- Subtotal, tax, and total
- Payment method
- Thank you message

## Troubleshooting

### "WhatsApp Not Configured" notification
- Check that all 3 environment variables are set in `.env`
- Run `php artisan config:clear` to refresh config cache

### Receipt not received
- Verify customer phone number is correct
- Check Twilio Console logs for delivery status
- Ensure customer has joined your WhatsApp Sandbox (dev) or opted-in (production)
- Check `storage/logs/laravel.log` for errors

### "Invalid phone number" in logs
- Phone number must be 10 digits (India) or include country code
- Update customer record with valid phone number

### Twilio API errors
- Verify Account SID and Auth Token are correct
- Check Twilio account balance
- Review Twilio error code in logs

## Cost Considerations

**Twilio Pricing (as of 2024):**
- WhatsApp messages: ~$0.005-0.01 per message (varies by country)
- Free credits available for new accounts

**Recommended:**
- Make WhatsApp optional (checkbox, not default)
- Monitor message volume in Twilio Console
- Set up billing alerts in Twilio

## Security Notes

- Never commit `.env` file to version control
- Rotate Auth Token if accidentally exposed
- Use environment-specific Twilio projects (dev/staging/prod)
- Validate phone numbers before sending

## Alternative: WhatsApp Business API

For high-volume businesses, consider:
- [WhatsApp Business Platform](https://business.whatsapp.com/products/business-platform) (direct from Meta)
- Providers like MessageBird, InfoBip, or Gupshup
- Requires verified business profile and approval process

To switch providers, modify `app/Services/WhatsAppService.php` to use their API.

## Future Enhancements

Potential additions:
- [ ] Support for WhatsApp templates (pre-approved messages)
- [ ] Send promotional messages to opted-in customers
- [ ] Order status notifications via WhatsApp
- [ ] Two-way communication (customer replies)
- [ ] Attachment support (PDF receipts with logo)
