<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', config('app.name'))</title>
    <style>
        html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }
        * { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; }
        table { border-spacing:0 !important; border-collapse:collapse !important; table-layout:fixed !important; margin:0 auto !important; }
        a { text-decoration:none; color:#0ea5e9; }
        img { -ms-interpolation-mode:bicubic; }
        .bg { background:#f8fafc; }
        .card { max-width:640px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; }
        .header { background:#ffffff; color:#111827; padding:24px; text-align: center; border-bottom: 1px solid #e5e7eb; }
        .brand { font-size:24px; font-weight:700; letter-spacing:.3px; color: #0ea5e9; }
        .content { padding:24px; font-family: Arial, Helvetica, sans-serif; color:#111827; line-height:1.6; font-size:15px; }
        .salam { font-weight:600; }
        .muted { color:#6b7280; font-size:13px; }
        .divider { height:1px; background:#e5e7eb; margin:16px 0; }
        .btn { display:inline-block; background:#0ea5e9; color:#fff !important; padding:10px 16px; border-radius:8px; font-weight:600; text-decoration: none; }
        .footer { padding:16px 24px; text-align:center; font-family: Arial, Helvetica, sans-serif; color:#6b7280; font-size:12px; }
        .note { background:#f1f5f9; border:1px dashed #cbd5e1; padding:12px; border-radius:8px; font-size:14px; color:#334155; }
        .meta { width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
        .meta th, .meta td { padding:10px 12px; text-align:left; font-size:14px; }
        .meta th { width:38%; background:#f8fafc; color:#374151; font-weight:600; }
        .meta td { color:#111827; }
    </style>
</head>
<body class="bg">
    <div class="card">
        <div class="header">
            <a href="{{ config('app.frontend_url') ?? config('app.url') }}" target="_blank" style="display: inline-block;">
                @php($brandSlug = config('brand.slug', 'attaqwa'))
                <img src="{{ config('app.url') . '/images/' . $brandSlug . '-logo.svg' }}" alt="{{ config('app.name') }}" height="50" style="height: 50px; max-height: 50px;">
            </a>
            <div style="margin-top: 10px; font-size: 18px; font-weight: bold; color: #374151;">{{ config('app.name') }}</div>
        </div>
        <div class="content">
            @yield('content')

            <p>Jazākumullāhu khayran,<br>{{ config('app.name') }}</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p class="muted">This is a system notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
