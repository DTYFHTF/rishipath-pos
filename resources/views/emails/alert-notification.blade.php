<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
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
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header.info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .header.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .header.error { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; }
        .header.critical { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .alert-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        .alert-box.info { border-left-color: #3b82f6; }
        .alert-box.warning { border-left-color: #f59e0b; }
        .alert-box.error { border-left-color: #f97316; }
        .alert-box.critical { border-left-color: #ef4444; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .data-table th {
            background: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        .data-table td {
            padding: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header {{ $notification->severity }}">
        <h1>{{ $notification->title }}</h1>
        <p>{{ $notification->created_at->format('M d, Y \a\t h:i A') }}</p>
    </div>
    
    <div class="content">
        <div class="alert-box {{ $notification->severity }}">
            <p style="margin: 0; font-size: 16px;">{{ $notification->message }}</p>
        </div>
        
        @if(!empty($notification->data))
            <h3>Details:</h3>
            
            @if(isset($notification->data['items']) && is_array($notification->data['items']))
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach(array_keys($notification->data['items'][0] ?? []) as $key)
                                <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notification->data['items'] as $item)
                            <tr>
                                @foreach($item as $value)
                                    <td>{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table class="data-table">
                    <tbody>
                        @foreach($notification->data as $key => $value)
                            @if(!is_array($value))
                                <tr>
                                    <th style="width: 40%;">{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                                    <td>
                                        @if(is_numeric($value) && (str_contains($key, 'amount') || str_contains($key, 'value') || str_contains($key, 'price')))
                                            ₹{{ number_format($value, 2) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
        
        <p style="margin-top: 30px; color: #6b7280;">
            <strong>Alert Type:</strong> {{ $notification->type_name ?? ucwords(str_replace('_', ' ', $notification->type)) }}<br>
            <strong>Severity:</strong> {{ ucfirst($notification->severity) }}
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated alert from Rishipath POS System.<br>
        If you believe this alert was sent in error, please contact your system administrator.</p>
    </div>
</body>
</html>
