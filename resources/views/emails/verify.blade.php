
<!DOCTYPE html>
<html lang="ar">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد الإلكتروني</title>
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
      }
      .container {
        max-width: 600px;
        margin: 40px auto;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      }
      .button {
        display: inline-block;
        background-color: #4caf50;
        color: #ffffff;
        text-decoration: none;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 16px;
        margin: 20px 0;
      }
      .footer {
        font-size: 12px;
        color: #888888;
        text-align: center;
        margin-top: 30px;
      }
      .logo {
        max-width: 140px;
        margin-bottom: 20px;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <center>
        <img src="https://lokta.sy/assets/lokta-logo-SfBK6bR9.svg" alt="شعار لوكتا" class="logo" />
      </center>

      <!-- Arabic Section -->
      <div dir="rtl" style="text-align: right;">
        <h2>مرحباً </h2>
        <p>شكرًا لانضمامك إلى <strong>لوكتا</strong> — نحن سعداء بوجودك معنا.</p>
        <p>يرجى تأكيد بريدك الإلكتروني بالنقر على الزر أدناه:</p>

        <center>
        <a href="{{ $verificationLink }}" target="_blank" class="button">تأكيد البريد الإلكتروني</a>
        </center>

        <p>إذا لم يعمل الزر أعلاه، يمكنك نسخ الرابط التالي ولصقه في متصفحك:</p>
        <p><a href="{{ $verificationLink }}" target="_blank">{{ $verificationLink }}</a></p>

        <p><strong>لماذا يجب تأكيد بريدك الإلكتروني؟</strong></p>
        <ul>
          <li>الوصول إلى جميع ميزات المنصة</li>
          <li>نشر الإعلانات والتفاعل مع الآخرين</li>
          <li>الحفاظ على أمان حسابك</li>
        </ul>

        <p>إذا لم تقم بإنشاء هذا الحساب، يمكنك تجاهل هذه الرسالة بأمان.</p>
      </div>

      <hr style="margin: 40px 0;" />

      <!-- English Section -->
      <div dir="ltr" style="text-align: left;">
        <h2>Hello</h2>
        <p>Thank you for joining <strong>Lokta</strong> — we’re excited to have you!</p>
        <p>Please verify your email address by clicking the button below:</p>

        <center>
         <a href="{{ $verificationLink }}" target="_blank" class="button">Verify My Email</a>
        </center>

        <p>If the button above doesn’t work, copy and paste the link below into your browser:</p>
        <p><a href="{{ $verificationLink }}" target="_blank">{{ $verificationLink }}</a></p>

        <p><strong>Why verify your email?</strong></p>
        <ul>
          <li>Access all marketplace features</li>
          <li>Post listings and interact with others</li>
          <li>Keep your account secure</li>
        </ul>

        <p>If you didn’t sign up for this account, you can safely ignore this email.</p>
      </div>

      <div class="footer">
        <p>بحاجة إلى مساعدة؟ راسلنا على <a href="mailto:looktaa.sy@gmail.com">looktaa.sy@gmail.com</a></p>
        <p>&copy; 2025 لقطة — <a href="https://lokta.sy">lokta.sy</a></p>
      </div>
    </div>
  </body>
</html>