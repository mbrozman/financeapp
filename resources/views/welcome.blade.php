<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vaulty') }} | Moderná správa financií</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-yellow: #fbcc01;
            --brand-green: #22c55e;
            --brand-green-dark: #1A3E10;
            --bg-dark: #050505;
            --text-main: #ffffff;
            --text-secondary: #94a3b8;
            --glass-white: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Yellow Top Bar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            width: 100%;
            position: fixed;
            top: 0;
            z-index: 1000;
            background-color: var(--brand-yellow);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .logo-icon {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .logo-text {
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--brand-green-dark);
        }

        .nav-links a {
            color: var(--brand-green-dark);
            text-decoration: none;
            margin-left: 2rem;
            font-size: 0.95rem;
            font-weight: 700;
            transition: opacity 0.3s ease;
        }

        .nav-links a:hover {
            opacity: 0.7;
        }

        /* Ambient Glows */
        .glow {
            position: absolute;
            width: 40vw;
            height: 40vw;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.15;
            pointer-events: none;
        }

        .glow-yellow { top: -10%; left: -5%; background: var(--brand-yellow); }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 5%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4rem;
            position: relative;
            z-index: 10;
        }

        .hero-content {
            flex: 1;
            max-width: 600px;
        }

        h1 {
            font-size: clamp(3rem, 6vw, 4.5rem);
            line-height: 1.05;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .gradient-text {
            color: var(--brand-yellow);
        }

        .slogan {
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
            font-weight: 300;
        }

        .features {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 3.5rem;
        }

        .feature-item {
            background: var(--glass-white);
            border: 1px solid var(--glass-border);
            padding: 0.8rem 1.4rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--brand-yellow);
        }

        .cta-btn {
            display: inline-block;
            background: var(--brand-yellow);
            color: var(--brand-green-dark);
            padding: 1.4rem 3.5rem;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(254, 219, 0, 0.2);
            border: none;
        }

        .cta-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(254, 219, 0, 0.4);
            filter: brightness(1.1);
        }

        .hero-image {
            flex: 1;
            position: relative;
            z-index: 1;
            animation: float 6s ease-in-out infinite;
            display: flex;
            justify-content: center;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-25px); }
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 30px;
            box-shadow: 0 0 50px rgba(0,0,0,0.8);
            filter: drop-shadow(0 0 30px rgba(254, 219, 0, 0.1));
        }

        @media (max-width: 968px) {
            .container {
                flex-direction: column;
                text-align: center;
                padding-top: 8rem;
                height: auto;
            }
            .hero-image {
                margin-top: 2rem;
                padding-bottom: 4rem;
            }
            .features {
                justify-content: center;
            }
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="glow glow-yellow"></div>

    <nav>
        <div class="logo-container">
            <img src="{{ asset('images/logo.svg') }}" alt="Vaulty Logo" class="logo-icon">
            <span class="logo-text">VAULTY</span>
        </div>
        <div class="nav-links">
            @auth
                <a href="{{ url('/admin') }}">Dashboard</a>
            @else
                <a href="{{ route('filament.admin.auth.login') }}">Prihlásiť sa</a>
            @endauth
        </div>
    </nav>

    <main class="container">
        <section class="hero-content">
            <h1>Posuňte vaše financie na <span class="gradient-text">novú úroveň.</span></h1>
            <p class="slogan">Prémiová správa majetku, precízny budgeting a detailný prehľad o vašom cashflow. Všetko v jednej elegantnej aplikácii.</p>
            
            <div class="features">
                <div class="feature-item">Investície</div>
                <div class="feature-item">Budgeting</div>
                <div class="feature-item">Cashflow</div>
            </div>

            <a href="{{ route('filament.admin.auth.login') }}" class="cta-btn">
                Vstúpiť do aplikácie
            </a>
        </section>

        <section class="hero-image">
            <img src="{{ asset('images/hero.png') }}" alt="Vaulty Platform Concept">
        </section>
    </main>
</body>
</html>
