<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم التحقق بنجاح</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <!-- تضمين خط Tajawal من Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .verification-box {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .verification-icon {
            color: #10b981;
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .verification-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #1e293b;
        }
        .verification-message {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .verification-button {
            background-color: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .verification-button:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>
    <div class="verification-box">
        <div class="verification-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h1 class="verification-title">تم التحقق من البريد الإلكتروني بنجاح</h1>
        <p class="verification-message">شكراً لتسجيلك معنا. يمكنك الآن استخدام جميع ميزات الموقع.</p>
    </div>
</body>
</html>