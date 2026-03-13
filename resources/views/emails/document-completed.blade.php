<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Completed</title>
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
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
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
        .success-card {
            background-color: #F0FDF4;
            border: 1px solid #22C55E;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
            text-align: center;
        }
        .success-icon {
            color: #22C55E;
            font-size: 48px;
            margin-bottom: 12px;
        }
        .success-title {
            font-size: 18px;
            font-weight: 600;
            color: #166534;
        }
        .document-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
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
            color: #4F46E5;
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
                <div class="greeting">Document Completed!</div>
                
                <p class="message-text">
                    All recipients have signed <span class="highlight">{{ $documentTitle }}</span>.
                </p>
                
                <div class="success-card">
                    <div class="success-icon">✓</div>
                    <div class="success-title">Document is now complete</div>
                </div>
                
                <p class="message-text" style="font-size: 14px; color: #6B7280;">
                    The document has been successfully signed by all recipients. The signed files are attached to this email for your records.
                </p>
                
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
