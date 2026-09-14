<?php

declare(strict_types=1);

namespace Indieinabox\Webmention;

/**
 * Class HelpPageView
 *
 * Renders the HTML help and test-form view for the Webmention endpoint.
 */
class HelpPageView
{
    /**
     * Renders the HTML help page for GET requests to the Webmention endpoint.
     *
     * @param string $defaultTarget Default FQDN or target URL to pre-fill in the form.
     * @return string
     */
    public static function render(string $defaultTarget = ''): string
    {
        $escapedTarget = htmlspecialchars($defaultTarget);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webmention Endpoint</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            --card-bg: rgba(30, 41, 59, 0.7);
            --accent: #eccb00;
            --accent-glow: rgba(236, 203, 0, 0.4);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-focus: rgba(236, 203, 0, 0.15);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 1.5rem;
            box-sizing: border-box;
        }

        .container {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 640px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 40px rgba(236, 203, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .container:hover {
            box-shadow: 0 30px 60px -10px rgba(0, 0, 0, 0.6),
                        0 0 50px rgba(236, 203, 0, 0.1);
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #eccb00, #ff8a00);
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 0.75rem;
            background: linear-gradient(90deg, #ffffff, #eccb00);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.025em;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .instruction-box {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .instruction-box p {
            margin: 0 0 1rem 0;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #e2e8f0;
        }

        .instruction-box p:last-child {
            margin-bottom: 0;
        }

        .instruction-box ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }

        .instruction-box li {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .instruction-box li strong {
            color: var(--text-primary);
        }

        code {
            font-family: 'JetBrains Mono', monospace;
            background: rgba(236, 203, 0, 0.1);
            color: var(--accent);
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            font-size: 0.9em;
            border: 1px solid rgba(236, 203, 0, 0.2);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        label {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
        }

        input[type="url"] {
            font-family: inherit;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        input[type="url"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--input-focus);
            background: rgba(15, 23, 42, 0.8);
        }

        input[type="url"]::placeholder {
            color: #475569;
        }

        button {
            font-family: inherit;
            background: linear-gradient(135deg, #eccb00 0%, #d8b600 100%);
            color: #0f172a;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px var(--accent-glow);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--accent-glow);
            background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
        }

        button:active {
            transform: translateY(0);
        }

        .footer {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 0.85rem;
            color: #475569;
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        .footer a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Webmention Endpoint</h1>
        <p class="subtitle">This endpoint allows external websites to notify this site when they link to its content.</p>
        
        <div class="instruction-box">
            <p>To send a webmention programmatically, make an HTTP <code>POST</code> request with the following form-encoded parameters:</p>
            <ul>
                <li><code>source</code>: The absolute URL of your page referencing this site.</li>
                <li><code>target</code>: The absolute URL of the page on this site being referenced.</li>
            </ul>
        </div>

        <form action="" method="POST">
            <div class="form-group">
                <label for="source">Source URL</label>
                <input type="url" name="source" id="source" required placeholder="https://yourdomain.com/posts/my-awesome-post">
            </div>
            <div class="form-group">
                <label for="target">Target URL</label>
                <input type="url" name="target" id="target" required value="{$escapedTarget}" placeholder="https://example.com/post-slug">
            </div>
            <button type="submit">
                Send Webmention
            </button>
        </form>
        
        <div class="footer">
            Powered by <a href="https://indieinabox.org" target="_blank" rel="noopener noreferrer">IndieInABox</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
