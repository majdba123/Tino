<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطأ في التحقق</title>
    <!-- Load Tajawal font from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Load Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --error-color: #ef4444;
            --primary-color: #3b82f6;
            --dark-color: #1e293b;
            --gray-color: #64748b;
            --light-bg: #f8fafc;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--light-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .verification-box {
            background: white;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 450px;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
        }
        
        .verification-icon {
            color: var(--error-color);
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }
        
        .verification-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        
        .verification-message {
            color: var(--gray-color);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .verification-button {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 1.05rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.25);
        }
        
        .verification-button:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(59, 130, 246, 0.3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .verification-box {
                padding: 1.5rem;
            }
            
            .verification-icon {
                font-size: 4rem;
            }
            
            .verification-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="verification-box">
        <div class="verification-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="verification-title">رابط التحقق غير صالح</h1>
        <p class="verification-message">
            الرابط الذي استخدمته إما منتهي الصلاحية أو غير صحيح.<br>
            يرجى طلب رابط تحقق جديد أو التأكد من صحة الرابط.
        </p>

    </div>
</body>
</html>