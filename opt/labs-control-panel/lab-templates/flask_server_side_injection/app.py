from flask import Flask, request, render_template_string

app = Flask(__name__)

# ── Digital Elysium Theme Page ────────────────────────────────────
PAGE_TEMPLATE = '''<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Elysium - Flaskhaven Gateway</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;600&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            min-height: 100vh;
            background: #0a0a1a;
            color: #c8d6e5;
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated background grid */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                linear-gradient(rgba(10, 10, 26, 0.9), rgba(10, 10, 26, 0.9)),
                repeating-linear-gradient(0deg, transparent, transparent 50px, rgba(0, 255, 136, 0.03) 50px, rgba(0, 255, 136, 0.03) 51px),
                repeating-linear-gradient(90deg, transparent, transparent 50px, rgba(0, 255, 136, 0.03) 50px, rgba(0, 255, 136, 0.03) 51px);
            z-index: -1;
        }
        
        .container {
            text-align: center;
            max-width: 640px;
            padding: 2.5rem;
        }
        
        .logo {
            font-family: 'Orbitron', monospace;
            font-size: 2.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #00ff88, #00d4ff, #7b68ee);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 3px;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 40px rgba(0, 255, 136, 0.3);
        }
        
        .subtitle {
            font-size: 0.85rem;
            color: #576574;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 2.5rem;
        }
        
        .greeting-card {
            background: rgba(20, 20, 40, 0.8);
            border: 1px solid rgba(0, 255, 136, 0.15);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.05);
            margin-bottom: 2rem;
        }
        
        .greeting-card h2 {
            font-family: 'Orbitron', monospace;
            font-size: 1.1rem;
            color: #00ff88;
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        
        .greeting-text {
            font-size: 1.3rem;
            color: #e8e8e8;
            line-height: 1.6;
            padding: 1rem;
            background: rgba(0, 255, 136, 0.05);
            border-left: 3px solid #00ff88;
            border-radius: 0 8px 8px 0;
            word-break: break-word;
        }
        
        .search-box {
            display: flex;
            gap: 0.75rem;
            margin-top: 2rem;
        }
        
        .search-box input {
            flex: 1;
            padding: 0.9rem 1.2rem;
            background: rgba(20, 20, 40, 0.9);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: 12px;
            color: #e8e8e8;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: #00ff88;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.15);
        }
        
        .search-box input::placeholder {
            color: #576574;
        }
        
        .search-box button {
            padding: 0.9rem 1.8rem;
            background: linear-gradient(135deg, #00ff88, #00d4ff);
            border: none;
            border-radius: 12px;
            color: #0a0a1a;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
        }
        
        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 255, 136, 0.3);
        }
        
        .footer-note {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #3d3d5c;
            letter-spacing: 1px;
        }
        
        /* Floating particles */
        .particle {
            position: fixed;
            width: 2px;
            height: 2px;
            background: #00ff88;
            border-radius: 50%;
            opacity: 0.3;
            animation: float 8s infinite ease-in-out;
        }
        .particle:nth-child(1) { top: 10%; left: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { top: 30%; left: 80%; animation-delay: 2s; }
        .particle:nth-child(3) { top: 70%; left: 15%; animation-delay: 4s; }
        .particle:nth-child(4) { top: 85%; left: 70%; animation-delay: 1s; }
        .particle:nth-child(5) { top: 50%; left: 50%; animation-delay: 3s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.3; }
            50% { transform: translateY(-20px) scale(1.5); opacity: 0.6; }
        }
    </style>
</head>
<body>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    
    <div class="container">
        <div class="logo">DIGITAL ELYSIUM</div>
        <div class="subtitle">Flaskhaven Gateway Terminal</div>
        
        <div class="greeting-card">
            <h2>// WELCOME TRANSMISSION</h2>
            <div class="greeting-text">
                Hello, GREETING_PLACEHOLDER
            </div>
        </div>
        
        <form class="search-box" method="GET" action="/">
            <input type="text" name="name" placeholder="Enter your codename, Sentinel..." value="">
            <button type="submit">TRANSMIT</button>
        </form>
        
        <div class="footer-note">
            &#9733; JINJA SENTINELS NETWORK &bull; CIPHER STONE PROTOCOL v2.1 &#9733;
        </div>
    </div>
</body>
</html>'''


@app.route('/')
def index():
    name = request.args.get('name', 'Wanderer')

    # ──────────────────────────────────────────────────────────────
    # VULNERABILITY: Server-Side Template Injection (SSTI)
    # The user-supplied 'name' parameter is directly interpolated
    # into a Jinja2 template string WITHOUT sanitization.
    # This allows attackers to inject {{ ... }} expressions
    # that execute arbitrary Python code on the server.
    # ──────────────────────────────────────────────────────────────
    rendered_page = PAGE_TEMPLATE.replace('GREETING_PLACEHOLDER', name)
    return render_template_string(rendered_page)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=80, debug=False)
