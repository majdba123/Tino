<!DOCTYPE html>
<html>

<head>
    <title>Pill Details - #{{ $pill->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .details-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 3px;
        }

        .value {
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .section-title {
            font-size: 18px;
            color: #333;
            margin: 20px 0 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
            grid-column: span 2;
        }

        .full-width {
            grid-column: span 2;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Pill Receipt #{{ $pill->id }}</h1>
        <p>Issued: {{ \Carbon\Carbon::parse($pill->issued_at)->format('F j, Y \a\t h:i A') }}</p>
    </div>

    <div class="details-container">
        <h3 class="section-title">Order Information</h3>

        <div class="detail-item">
            <span class="label">Order Clinic ID:</span>
            <div class="value">{{ $pill->order_clinic_id }}</div>
        </div>

        <div class="detail-item">
            <span class="label">Issued At:</span>
            <div class="value">{{ \Carbon\Carbon::parse($pill->issued_at)->format('F j, Y \a\t h:i A') }}</div>
        </div>

        <h3 class="section-title">Medical Details</h3>

        <div class="detail-item full-width">
            <span class="label">Medical Details:</span>
            <div class="value">{{ $pill->medical_details }}</div>
        </div>

        <div class="detail-item full-width">
            <span class="label">Clinic Note:</span>
            <div class="value">{{ $pill->clinic_note }}</div>
        </div>

        <h3 class="section-title">Pricing Information</h3>

        <div class="detail-item">
            <span class="label">Base Price:</span>
            <div class="value">${{ number_format($pill->price_order, 2) }}</div>
        </div>

        <div class="detail-item">
            <span class="label">Discount Applied:</span>
            <div class="value">{{ $pill->have_discount ? 'Yes' : 'No' }}</div>
        </div>

        @if ($pill->have_discount)
            <div class="detail-item">
                <span class="label">Discount Percentage:</span>
                <div class="value">{{ $pill->discount_percent }}%</div>
            </div>

            <div class="detail-item">
                <span class="label">Discount Amount:</span>
                <div class="value">${{ number_format($pill->discount_amount, 2) }}</div>
            </div>
        @endif

        <div class="detail-item">
            <span class="label">Final Price:</span>
            <div class="value" style="font-weight: bold; color: #2a6496;">${{ number_format($pill->final_price, 2) }}
            </div>
        </div>
    </div>
</body>

</html>
