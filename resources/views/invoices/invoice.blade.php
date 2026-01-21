<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        
        .container {
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #1a56db;
        }
        
        .company-name {
            font-size: 24pt;
            font-weight: bold;
            color: #1a56db;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        
        .invoice-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #1a56db;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        .info-box {
            background: #f9fafb;
            padding: 12px;
            border-radius: 5px;
            margin-right: 10px;
        }
        
        .info-box.right {
            margin-right: 0;
            margin-left: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #6b7280;
            font-size: 9pt;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background: #1a56db;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .item-name {
            font-weight: 600;
            color: #111827;
        }
        
        .item-sku {
            font-size: 8pt;
            color: #9ca3af;
        }
        
        .totals-section {
            margin-top: 30px;
            float: right;
            width: 300px;
        }
        
        .total-row {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .total-row.grand {
            border-top: 2px solid #1a56db;
            border-bottom: 2px solid #1a56db;
            font-size: 12pt;
            font-weight: bold;
            padding: 12px 0;
            margin-top: 10px;
            background: #eff6ff;
        }
        
        .total-label {
            display: table-cell;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        
        .total-value {
            display: table-cell;
            text-align: right;
            color: #111827;
        }
        
        .footer {
            clear: both;
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 9pt;
            color: #6b7280;
        }
        
        .gst-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 10px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .gst-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 5px;
        }
        
        .gst-content {
            font-size: 8pt;
            color: #78350f;
        }
        
        .payment-info {
            background: #dcfce7;
            border: 1px solid #86efac;
            padding: 10px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .payment-title {
            font-weight: bold;
            color: #14532d;
            margin-bottom: 5px;
        }
        
        .payment-content {
            font-size: 9pt;
            color: #15803d;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100pt;
            color: rgba(26, 86, 219, 0.05);
            font-weight: bold;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="watermark">{{ $organization->name ?? 'INVOICE' }}</div>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ $organization->name ?? 'RishiPath POS' }}</div>
            <div class="company-info">
                @if($store->address)
                    {{ $store->address }}, {{ $store->city }}, {{ $store->state }} {{ $store->postal_code }}<br>
                @endif
                Phone: {{ $store->phone ?? 'N/A' }}
                @if($store->email)
                    | Email: {{ $store->email }}
                @endif
                @if($store->tax_number)
                    <br>GSTIN: {{ $store->tax_number }}
                @endif
            </div>
        </div>
        
        <!-- Invoice Title -->
        <div class="invoice-title">TAX INVOICE</div>
        
        <!-- Invoice & Customer Info -->
        <div class="info-row">
            <div class="info-col">
                <div class="info-box">
                    <div class="info-label">Invoice Details</div>
                    <div class="info-value">
                        <strong>Invoice #:</strong> {{ $sale->invoice_number }}<br>
                        <strong>Receipt #:</strong> {{ $sale->receipt_number }}<br>
                        <strong>Date:</strong> {{ $sale->date->format('d-M-Y') }}<br>
                        <strong>Time:</strong> {{ date('h:i A', strtotime($sale->time)) }}<br>
                        <strong>Cashier:</strong> {{ $sale->cashier->name ?? 'N/A' }}
                    </div>
                </div>
            </div>
            
            <div class="info-col">
                <div class="info-box right">
                    <div class="info-label">Bill To</div>
                    <div class="info-value">
                        @if($sale->customer_name)
                            <strong>{{ $sale->customer_name }}</strong><br>
                        @else
                            <strong>Walk-in Customer</strong><br>
                        @endif
                        @if($sale->customer_phone)
                            Phone: {{ $sale->customer_phone }}<br>
                        @endif
                        @if($sale->customer_email)
                            Email: {{ $sale->customer_email }}<br>
                        @endif
                        @if($customer && $customer->address)
                            {{ $customer->address }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 40%;">Item Description</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 12%;" class="text-right">Rate</th>
                    <th style="width: 10%;" class="text-center">GST</th>
                    <th style="width: 12%;" class="text-right">Tax Amt</th>
                    <th style="width: 15%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <span class="item-name">{{ $item->product_name }}</span><br>
                        @if($item->product_sku)
                            <span class="item-sku">SKU: {{ $item->product_sku }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">₹{{ number_format($item->price_per_unit, 2) }}</td>
                    <td class="text-center">{{ number_format($item->tax_rate, 1) }}%</td>
                    <td class="text-right">₹{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="text-right">₹{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span class="total-label">Subtotal:</span>
                <span class="total-value">₹{{ number_format($sale->subtotal, 2) }}</span>
            </div>
            
            @if($sale->discount_amount > 0)
            <div class="total-row">
                <span class="total-label">Discount:</span>
                <span class="total-value">-₹{{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif
            
            <div class="total-row">
                <span class="total-label">GST/Tax:</span>
                <span class="total-value">₹{{ number_format($sale->tax_amount, 2) }}</span>
            </div>
            
            <div class="total-row grand">
                <span class="total-label">GRAND TOTAL:</span>
                <span class="total-value">₹{{ number_format($sale->total_amount, 2) }}</span>
            </div>
        </div>
        
        <div style="clear: both;"></div>
        
        <!-- Payment Info -->
        <div class="payment-info">
            <div class="payment-title">Payment Details</div>
            <div class="payment-content">
                <strong>Payment Method:</strong> {{ strtoupper($sale->payment_method) }}<br>
                @if($sale->payment_method === 'cash')
                    <strong>Amount Paid:</strong> ₹{{ number_format($sale->amount_paid, 2) }}<br>
                    <strong>Change:</strong> ₹{{ number_format($sale->change_amount, 2) }}
                @elseif($sale->payment_reference)
                    <strong>Reference:</strong> {{ $sale->payment_reference }}
                @endif
            </div>
        </div>
        
        <!-- GST Info -->
        @if($store->tax_number)
        <div class="gst-box">
            <div class="gst-title">GST Information</div>
            <div class="gst-content">
                This is a computer-generated GST-compliant invoice. GSTIN: {{ $store->tax_number }}<br>
                Total Taxable Amount: ₹{{ number_format($sale->subtotal - $sale->discount_amount, 2) }} | 
                Total GST: ₹{{ number_format($sale->tax_amount, 2) }}
            </div>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
            <p style="margin-top: 10px; font-size: 8pt;">
                Generated on {{ now()->format('d-M-Y h:i A') }} | Powered by RishiPath POS
            </p>
        </div>
    </div>
</body>
</html>
