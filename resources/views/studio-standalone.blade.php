<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Architect Studio</title>
    <link rel="stylesheet" href="{{ route('architect.assets.studio.css') }}?v={{ time() }}">
</head>
<body class="bg-background text-foreground">
    <div id="architect-studio-root"></div>
    <script>
        window.__ARCHITECT_PROPS__ = @json($architectProps);
    </script>
    <script type="module" src="{{ route('architect.assets.studio.js') }}?v={{ time() }}"></script>
</body>
</html>
