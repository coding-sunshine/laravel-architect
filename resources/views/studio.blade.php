<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Architect Studio</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.5rem; }
        p { color: #666; }
        ul { margin: 1rem 0; padding-left: 1.5rem; }
        .message { padding: 0.75rem; margin: 1rem 0; border-radius: 0.25rem; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }
        form { margin: 1rem 0; }
        button { padding: 0.5rem 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Architect Studio</h1>
    <p>Visual Schema Designer for Laravel Architect. This view is shown when the UI driver is <code>blade</code> or when other drivers are not available.</p>

    @if(session('architect.message'))
        <div class="message {{ session('architect.error') ? 'error' : 'success' }}">{{ session('architect.message') }}</div>
    @endif

    <p><strong>Validate draft (UI parity with <code>architect:validate</code>):</strong></p>
    <form method="post" action="{{ route('architect.studio.validate') }}">
        @csrf
        <button type="submit">Validate draft</button>
    </form>

    <p>CLI commands you can run:</p>
    <ul>
        <li><code>architect:draft</code> – Generate draft from description</li>
        <li><code>architect:validate</code> – Validate draft</li>
        <li><code>architect:plan</code> – Preview what will be generated</li>
        <li><code>architect:build</code> – Generate code</li>
        <li><code>architect:status</code> – Show generated files</li>
        <li><code>architect:packages</code> – List detected packages</li>
        <li><code>architect:import</code> – Import from codebase</li>
    </ul>
    <p>See <a href="https://github.com/coding-sunshine/laravel-architect#readme">documentation</a> for full command–UI parity and driver options.</p>
</body>
</html>
