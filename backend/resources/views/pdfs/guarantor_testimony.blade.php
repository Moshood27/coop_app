<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Guarantor Testimony</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #047857; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #047857; text-transform: uppercase; font-size: 24px; }
        .header p { margin: 5px 0; font-weight: bold; font-size: 16px; }
        .section { margin-bottom: 30px; }
        .section-title { background: #f3f4f6; padding: 10px; font-weight: bold; color: #047857; text-transform: uppercase; margin-bottom: 20px; border-left: 5px solid #047857; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table td { padding: 10px; vertical-align: top; border-bottom: 1px solid #eee; }
        .label { font-weight: bold; width: 220px; color: #666; }
        .value { font-weight: bold; color: #000; }
        .signature-box { margin-top: 40px; text-align: center; border-top: 1px solid #ccc; padding-top: 20px; }
        .signature-image { max-width: 250px; max-height: 100px; display: block; margin: 0 auto 10px; }
        .footer { margin-top: 50px; font-size: 12px; text-align: center; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        .stamp-box { float: left; width: 120px; height: 120px; border: 2px dashed #ccc; border-radius: 50%; text-align: center; line-height: 120px; font-size: 10px; color: #ccc; margin-top: 20px; }
    </style>
</head>
<body>
    @php
        $getPath = function($path) {
            if (!$path) return null;

            $absolutePath = null;

            // 1. If it's already a full path and exists
            if (file_exists($path)) {
                $absolutePath = $path;
            }

            // 2. Check public/
            if (!$absolutePath) {
                $publicPath = public_path($path);
                if (file_exists($publicPath)) {
                    $absolutePath = $publicPath;
                }
            }

            // 3. Check storage/app/public/
            if (!$absolutePath) {
                $storagePath = storage_path('app/public/' . $path);
                if (file_exists($storagePath)) {
                    $absolutePath = $storagePath;
                }
            }

            // 4. Try to resolve if it's a "storage/..." URL path (common for Filament)
            if (!$absolutePath && str_starts_with($path, 'storage/')) {
                $trimmedPath = substr($path, 8);
                $storagePath2 = storage_path('app/public/' . $trimmedPath);
                if (file_exists($storagePath2)) {
                    $absolutePath = $storagePath2;
                }
            }

            if ($absolutePath) {
                try {
                    $imageData = base64_encode(file_get_contents($absolutePath));
                    $mimeType = 'image/jpeg';
                    if (function_exists('mime_content_type')) {
                        $mimeType = @mime_content_type($absolutePath) ?: 'image/jpeg';
                    } else {
                        $ext = pathinfo($absolutePath, PATHINFO_EXTENSION);
                        $mimeType = match(strtolower($ext)) {
                            'png' => 'image/png',
                            'webp' => 'image/webp',
                            'gif' => 'image/gif',
                            default => 'image/jpeg',
                        };
                    }
                    return 'data:' . $mimeType . ';base64,' . $imageData;
                } catch (\Exception $e) {
                    return null;
                }
            }

            // 5. If it's a URL, try to fetch it if isRemoteEnabled is on (optional, but robust)
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                try {
                    $imageData = base64_encode(file_get_contents($path));
                    // Simple mime detection by extension for URLs
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $mimeType = match(strtolower($ext)) {
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif',
                        default => 'image/jpeg',
                    };
                    return 'data:' . $mimeType . ';base64,' . $imageData;
                } catch (\Exception $e) {
                    return null;
                }
            }

            return null;
        };

        $fullName = trim("{$application->surname} {$application->name} {$application->other_names}");
    @endphp
    <div class="header">
        <h1>{{ config('app.name') }} COOPERATIVE SOCIETY</h1>
        <p>GUARANTOR TESTIMONY</p>
    </div>

    <div class="section">
        <div class="section-title">Applicant Information</div>
        <table>
            <tr>
                <td class="label">Full Name:</td>
                <td class="value">{{ $fullName }}</td>
            </tr>
            <tr>
                <td class="label">Phone Number:</td>
                <td class="value">{{ $application->phone }}</td>
            </tr>
            <tr>
                <td class="label">Residential Address:</td>
                <td class="value">{{ $application->residential_address }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Guarantor Information</div>
        <table>
            <tr>
                <td class="label">Guarantor Name:</td>
                <td class="value">{{ $application->guarantor_name }}</td>
            </tr>
            <tr>
                <td class="label">Occupation:</td>
                <td class="value">{{ $application->guarantor_occupation }}</td>
            </tr>
            <tr>
                <td class="label">Phone Number:</td>
                <td class="value">{{ $application->guarantor_phone }}</td>
            </tr>
            <tr>
                <td class="label">Address:</td>
                <td class="value">{{ $application->guarantor_address }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; font-size: 16px;">
        <p>
            I hereby testify before Allah (SWT) that the applicant, <strong>{{ $fullName }}</strong>, is known to me to be a person of good character and Islamic integrity. I vouch for (his/her) trustworthiness and believe them to be capable of fulfilling (his/her) obligations to the Cooperative. I understand that by acting as a guarantor, I am affirming my belief in (his/her) honesty and reliability in accordance with Islamic principles of mutual support and trust.
        </p>
    </div>

    <div style="margin-top: 40px; position: relative; height: 150px;">
        <div style="float: right; width: 300px; text-align: center;">
            @php
                $sigPath = $application->guarantor_signature_path;
            @endphp
            @if($sig = $getPath($sigPath))
                <img src="{{ $sig }}" class="signature-image">
            @else
                <div style="height: 100px;"></div>
            @endif
            <div style="border-top: 1px solid #000; padding-top: 5px; font-weight: bold;">
                Signature of Guarantor
            </div>
            <p>Date: {{ $application->guarantor_responded_at ? $application->guarantor_responded_at->format('d/m/Y') : date('d/m/Y') }}</p>
            @if(isset($application->guarantor_status))
                <p>Status: <span style="text-transform: uppercase; color: {{ $application->guarantor_status === 'accepted' ? 'green' : 'orange' }}">{{ $application->guarantor_status }}</span></p>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>This document is part of the official Membership Application of {{ config('app.name') }} Cooperative Society.</p>
        <p>Generated on {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
