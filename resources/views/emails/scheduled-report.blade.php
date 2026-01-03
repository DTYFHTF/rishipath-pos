<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #6b7280;
        }
        .value {
            font-weight: 700;
            color: #111827;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $schedule->name }}</h1>
        <p>{{ $reportData['period'] ?? now()->format('M d, Y') }}</p>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>Your scheduled report "{{ $schedule->name }}" has been generated successfully. Please find the report attached to this email.</p>
        
        @if(isset($reportData['summary']))
        <div class="summary">
            <h3 style="margin-top: 0;">Report Summary</h3>
            
            @foreach($reportData['summary'] as $key => $value)
            <div class="summary-item">
                <span class="label">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                <span class="value">
                    @if(is_numeric($value))
                        @if(str_contains($key, 'amount') || str_contains($key, 'value') || str_contains($key, 'sales'))
                            ₹{{ number_format($value, 2) }}
                        @else
                            {{ number_format($value) }}
                        @endif
                    @else
                        {{ $value }}
                    @endif
                </span>
            </div>
            @endforeach
        </div>
        @endif
        
        <p>This report was automatically generated based on your schedule settings. The report is attached in {{ strtoupper($schedule->format) }} format.</p>
        
        <p style="margin-top: 30px;">
            <strong>Schedule Information:</strong><br>
            Frequency: {{ ucfirst($schedule->frequency) }}<br>
            Next Run: {{ $schedule->next_run_at?->format('M d, Y h:i A') ?? 'Not scheduled' }}
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated email from Rishipath POS System.<br>
        Generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
    </div>
</body>
</html>
