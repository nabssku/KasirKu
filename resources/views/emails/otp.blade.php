<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Keamanan JagoKasir</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #6366f1;
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.025em;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin-top: 10px;
            font-size: 16px;
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        .content h2 {
            font-size: 20px;
            color: #0f172a;
            margin-bottom: 24px;
        }
        .otp-box {
            background: #f1f5f9;
            padding: 30px;
            border-radius: 16px;
            margin: 30px 0;
            border: 2px dashed #cbd5e1;
        }
        .otp-code {
            font-size: 48px;
            font-weight: 800;
            color: #6366f1;
            letter-spacing: 0.2em;
            margin: 0;
        }
        .footer {
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }
        .warning {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 20px;
            font-style: italic;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background-color: #6366f1;
            color: #ffffff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>JagoKasir</h1>
            <p>Solusi Kasir Pintar Digital</p>
        </div>
        <div class="content">
            <h2>{{ $title }}</h2>
            <p style="color: #475569; line-height: 1.6;">
                {{ $description }}
            </p>
            
            <div class="otp-box">
                <p style="text-transform: uppercase; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 10px; letter-spacing: 0.1em;">Kode Verifikasi Anda</p>
                <h3 class="otp-code">{{ $otp }}</h3>
            </div>

            <p style="font-size: 14px; color: #64748b; margin-top: 20px;">
                Kode ini berlaku selama <strong>{{ $expires }} menit</strong>. 
                <br>Jangan sebarkan kode ini kepada siapa pun, termasuk pihak JagoKasir.
            </p>
            
            <p class="warning">
                Jika Anda tidak merasa melakukan permintaan ini, silakan abaikan email ini.
            </p>
        </div>
        <div class="footer">
            <p>
                &copy; {{ date('Y') }} JagoKasir. Seluruh Hak Cipta Dilindungi.
                <br>Layanan Kasir Terbaik untuk Bisnis Anda.
            </p>
        </div>
    </div>
</body>
</html>
