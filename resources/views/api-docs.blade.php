<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PharmaNP API Docs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body {
            margin: 0;
            background: #f4f8f7;
        }

        .topbar {
            display: none;
        }

        .swagger-ui .info {
            margin: 32px 0;
        }

        .swagger-ui .scheme-container {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(21, 94, 92, 0.08);
        }

        .api-docs-note {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 12px 24px;
            border-bottom: 1px solid #dce8e5;
            background: #ffffff;
            color: #334155;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
        }

        .api-docs-note strong {
            color: #0f766e;
        }

        .api-docs-note code {
            border-radius: 6px;
            padding: 2px 6px;
            background: #edf7f4;
            color: #155e58;
        }
    </style>
</head>
<body>
<div class="api-docs-note">
    <strong>PharmaNP API Docs</strong>
    <span>Use the Authorize button with a bearer token from <code>php artisan pharmanp:api-token user@example.com</code>, or log in normally for session testing.</span>
</div>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: @json(url('/api/v1/openapi.json')),
        dom_id: '#swagger-ui',
        deepLinking: true,
        persistAuthorization: true,
        displayRequestDuration: true,
        tryItOutEnabled: true,
        requestInterceptor: (request) => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            if (csrf && !request.headers['Authorization']) {
                request.headers['X-CSRF-TOKEN'] = csrf;
            }

            request.headers['Accept'] = 'application/json';

            return request;
        },
    });
</script>
</body>
</html>
