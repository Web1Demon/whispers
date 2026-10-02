<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Request;
use App\Core\Response;

class DocsController
{
    private string $openApiJsonPath;

    public function __construct(?string $openApiJsonPath = null)
    {
        $this->openApiJsonPath = $openApiJsonPath ?? __DIR__ . '/../../../openapi.json';
    }

    public function openapi(Request $request): Response
    {
        if (!file_exists($this->openApiJsonPath)) {
            return Response::error("OpenAPI specification file not found", 404);
        }

        $json = file_get_contents($this->openApiJsonPath);
        $data = json_decode($json, true);

        return Response::json($data);
    }

    public function ui(Request $request): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Whisper API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z'/></svg>">
    <style>
        body { margin: 0; padding: 0; background: #0f172a; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui { filter: invert(88%) hue-rotate(180deg); }
        .swagger-ui .wrapper { max-width: 1200px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/api/docs/openapi.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
HTML;

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
