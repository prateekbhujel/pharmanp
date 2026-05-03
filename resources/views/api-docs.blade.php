<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PharmaNP API Docs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        :root {
            color-scheme: dark;
            --docs-bg: #171d1e;
            --docs-panel: #202728;
            --docs-line: #3a4648;
            --docs-muted: #aab7ba;
            --docs-text: #eef5f3;
            --docs-green: #5fd37c;
            --docs-teal: #2f7d59;
        }

        body {
            margin: 0;
            background: var(--docs-bg);
        }

        .swagger-ui {
            color: var(--docs-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        }

        .swagger-ui .topbar {
            background: #252b2d;
            border-bottom: 1px solid #31393b;
            padding: 12px 0;
        }

        .swagger-ui .topbar .download-url-wrapper input[type=text] {
            min-height: 44px;
            border-color: var(--docs-teal);
            background: #151a1b;
            color: var(--docs-text);
            font-size: 14px;
        }

        .swagger-ui .topbar .download-url-wrapper .download-url-button {
            min-height: 44px;
            border-color: var(--docs-teal);
            background: var(--docs-teal);
            color: #eafff1;
            font-weight: 800;
        }

        .swagger-ui .info {
            margin: 48px 0 56px;
        }

        .swagger-ui .info .title,
        .swagger-ui .info h1,
        .swagger-ui .info h2,
        .swagger-ui .info h3,
        .swagger-ui .info h4,
        .swagger-ui .opblock-tag,
        .swagger-ui label,
        .swagger-ui table thead tr td,
        .swagger-ui table thead tr th,
        .swagger-ui .parameter__name,
        .swagger-ui .parameter__type,
        .swagger-ui .response-col_status,
        .swagger-ui .response-col_description,
        .swagger-ui .model-title {
            color: var(--docs-text);
        }

        .swagger-ui .info .title {
            font-size: clamp(30px, 4vw, 46px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .swagger-ui .info .description,
        .swagger-ui .info .base-url,
        .swagger-ui .info p,
        .swagger-ui .opblock-tag small,
        .swagger-ui .parameter__deprecated,
        .swagger-ui .parameter__in,
        .swagger-ui .prop-format,
        .swagger-ui .renderedMarkdown p {
            color: var(--docs-muted);
        }

        .swagger-ui .info a {
            color: #69aaff;
        }

        .swagger-ui .scheme-container {
            margin: 0 0 28px;
            padding: 24px 0;
            background: var(--docs-bg);
            border-top: 1px solid #253033;
            border-bottom: 1px solid var(--docs-line);
            box-shadow: none;
        }

        .swagger-ui .servers > label {
            color: var(--docs-text);
        }

        .swagger-ui select,
        .swagger-ui input[type=text],
        .swagger-ui textarea {
            border-color: #758084;
            background: #151a1b;
            color: var(--docs-text);
        }

        .swagger-ui .btn.authorize {
            border-color: #64d68b;
            color: #75e69b;
        }

        .swagger-ui .btn.authorize svg {
            fill: #75e69b;
        }

        .swagger-ui .filter .operation-filter-input {
            border: 1px solid #b9c2c5;
            background: transparent;
            color: var(--docs-text);
        }

        .swagger-ui .opblock-tag {
            border-bottom-color: #4b5558;
            padding: 20px 12px;
            font-size: 26px;
        }

        .swagger-ui .opblock-tag:hover {
            background: rgba(95, 211, 124, 0.06);
        }

        .swagger-ui .opblock {
            overflow: hidden;
            border-color: var(--docs-line);
            background: var(--docs-panel);
            box-shadow: none;
        }

        .swagger-ui .opblock .opblock-summary {
            border-color: var(--docs-line);
        }

        .swagger-ui .opblock .opblock-section-header {
            background: #22292b;
            box-shadow: none;
        }

        .swagger-ui .opblock-description-wrapper,
        .swagger-ui .opblock-external-docs-wrapper,
        .swagger-ui .opblock-title_normal,
        .swagger-ui .responses-inner,
        .swagger-ui .parameters-container {
            color: var(--docs-text);
        }

        .swagger-ui section.models {
            border-color: var(--docs-line);
            background: var(--docs-panel);
        }

        .swagger-ui section.models h4 {
            color: var(--docs-text);
        }

        .swagger-ui .modal-ux {
            background: #22292b;
            color: var(--docs-text);
        }

        .swagger-ui .dialog-ux .modal-ux-header,
        .swagger-ui .dialog-ux .modal-ux-content {
            border-color: var(--docs-line);
        }

        .swagger-ui .dialog-ux .modal-ux-header h3,
        .swagger-ui .auth-container h4,
        .swagger-ui .auth-container h5,
        .swagger-ui .auth-container label {
            color: var(--docs-text);
        }

        .swagger-ui .auth-container input[type=text],
        .swagger-ui .auth-container input[type=password] {
            border-color: #667276;
            background: #151a1b;
            color: var(--docs-text);
        }

        @media (max-width: 720px) {
            .swagger-ui .info .title {
                font-size: 28px;
            }

            .swagger-ui .topbar .download-url-wrapper {
                flex-direction: column;
                gap: 8px;
            }

            .swagger-ui .topbar .download-url-wrapper input[type=text],
            .swagger-ui .topbar .download-url-wrapper .download-url-button {
                width: 100%;
                margin: 0;
            }
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: @json(url('/docs/api-docs.json')),
        dom_id: '#swagger-ui',
        deepLinking: true,
        filter: true,
        persistAuthorization: true,
        displayRequestDuration: true,
        docExpansion: 'none',
        tryItOutEnabled: true,
        defaultModelsExpandDepth: 1,
        layout: 'StandaloneLayout',
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset,
        ],
        syntaxHighlight: {
            theme: 'obsidian',
        },
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
