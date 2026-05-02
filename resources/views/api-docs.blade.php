<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    </style>
</head>
<body>
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
    });
</script>
</body>
</html>
