<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Declined</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .email-wrapper {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .email-header {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            padding: 32px;
            text-align: center;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        .email-body {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 24px;
        }
        .message-text {
            font-size: 16px;
            color: #374151;
            margin-bottom: 20px;
        }
        .declined-card {
            background-color: #FEF2F2;
            border: 1px solid #EF4444;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        .declined-icon {
            color: #EF4444;
            font-size: 48px;
            margin-bottom: 12px;
            text-align: center;
        }
        .declined-title {
            font-size: 18px;
            font-weight: 600;
            color: #991B1B;
            text-align: center;
        }
        .document-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        .reason-box {
            background-color: #FEF3C7;
            border: 1px solid #F59E0B;
            border-radius: 6px;
            padding: 12px 16px;
            margin: 20px 0;
        }
        .reason-label {
            font-size: 12px;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .reason-text {
            font-size: 14px;
            color: #B45309;
        }
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.3);
        }
        .footer {
            text-align: center;
            padding: 24px 32px;
            border-top: 1px solid #E5E7EB;
        }
        .footer-text {
            font-size: 13px;
            color: #9CA3AF;
        }
        .footer-link {
            color: #6B7280;
            text-decoration: none;
        }
        .highlight {
            font-weight: 600;
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="email-header">
                <span class="logo-text">dockusign</span>
            </div>
            
            <div class="email-body">
                <div class="greeting">Document Declined</div>
                
                <p class="message-text">
                    <span class="highlight">{{ $recipientEmail }}</span> has declined to sign <span class="highlight">{{ $documentTitle }}</span>.
                </p>
                
                <div class="declined-card">
                    <div class="declined-icon">✕</div>
                    <div class="declined-title">Signing Declined</div>
                </div>
                
                @if($declineReason)
                <div class="reason-box">
                    <div class="reason-label">Reason</div>
                    <div class="reason-text">{{ $declineReason }}</div>
                </div>
                @endif
                
                <div class="button-container">
                    <a href="{{ $documentUrl }}" class="button">
                        View Document
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p class="footer-text">
                    Powered by <a href="{{ config('app.url') }}" class="footer-link">Docusign</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
