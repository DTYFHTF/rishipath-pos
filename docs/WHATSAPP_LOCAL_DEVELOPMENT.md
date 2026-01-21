# WhatsApp Integration - Local Development Notes

## The PDF Attachment Problem

When developing locally (e.g., `https://rishipath-pos.test`), Twilio cannot download invoice PDFs because:

1. **Local domains are not publicly accessible** - Your Herd domain only works on your computer
2. **Twilio servers need to download the PDF** - They can't reach `https://rishipath-pos.test` from the internet
3. **Error returned**: `21620 - Invalid media URL(s)`

## Current Solution: Automatic Fallback

The system now **automatically falls back** to sending text-only receipts when PDF attachment fails:

```php
// Tries to send PDF first
$result = $whatsappService->sendInvoicePdf($sale, $phone, $publicUrl);

// If it fails due to "Invalid media URL", automatically sends text receipt instead
if (!$result['success'] && str_contains($result['error'], 'Invalid media URL')) {
    $whatsappService->sendReceipt($sale, $phone);
    // Shows: "Receipt Sent (Text Only)"
}
```

## Production Solutions

### Option 1: Use a Public Domain (Recommended)
Deploy your app to a server with a real domain:
- `https://yourdomain.com` 
- Twilio can access and download PDFs
- No changes needed - will work automatically

### Option 2: ngrok for Testing
Use ngrok to create a temporary public URL:
```bash
ngrok http https://rishipath-pos.test
```
Then update `.env`:
```
APP_URL=https://abc123.ngrok.io
```

### Option 3: Upload PDFs to Cloud Storage
Store invoices on AWS S3 or similar:
- Always accessible via public URL
- More reliable than local file storage
- Recommended for production

## Testing WhatsApp Locally

1. **Text receipts work fine** - No URL needed
2. **PDF attachments auto-fallback** - System handles it gracefully
3. **No errors shown to user** - Just shows "Text Only" message

## Twilio Sandbox Limitation

Remember: Recipients must join your Twilio Sandbox first:
```
Send: "join <your-code>"
To: +14155238886
```

## Files Changed
- `app/Filament/Pages/EnhancedPOS.php` - Auto-fallback to text receipt
- `app/Filament/Resources/SaleResource/Pages/ViewSale.php` - Auto-fallback to text receipt
- `app/Services/WhatsAppService.php` - Already had proper error handling
