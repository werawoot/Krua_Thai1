<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Authentic Thai Restaurant Management</title>
    <meta name="description" content="Experience authentic Thai cuisine with Somdul Table - Your premier Thai restaurant management system in the US">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    <style>
        /* CSS Custom Properties for Somdul Table Design System */
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--white);
            font-weight: 400;
        }

        /* Typography using BaticaSans */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        .navbar {
            position: fixed;
            top: 38px;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Hero Section */
        .hero-section {
            padding-top: 120px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 2rem 2rem;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .hero-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero-container .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
            z-index: -1;
        }

        .hero-content {
            flex: 1;
            max-width: 600px;
            padding: 3rem 2rem;
            z-index: 10;
        }

        .hero-content h1 {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .zip-input-container {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        .zip-input {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            transition: all 0.3s;
            outline: none;
        }

        .zip-input:focus {
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .zip-input::placeholder {
            color: #999;
            font-family: 'BaticaSans', sans-serif;
        }

        .order-now-button {
            background: var(--curry);
            color: var(--white);
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.2rem;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 200px;
        }

        .order-now-button:hover {
            background: var(--brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Hero Videos - Vertical Sliding Animation */
        .hero-videos {
            flex: 1;
            height: 80vh;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            justify-content: center;
        }

        .image-column {
            flex: 1;
            height: 100%;
            max-width: 300px;
            position: relative;
            overflow: hidden;
        }

        .image-slider, .image-slider-reverse {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            animation-duration: 40s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .image-slider {
            animation-name: slideDown;
        }

        .image-slider-reverse {
            animation-name: slideUp;
        }

        @keyframes slideDown {
            0% { transform: translateY(-50%); }
            100% { transform: translateY(0%); }
        }

        @keyframes slideUp {
            0% { transform: translateY(0%); }
            100% { transform: translateY(-50%); }
        }

        .video-container {
            position: relative;
            min-height: 300px;
            overflow: hidden;
            border-radius: 12px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1.2rem;
            clip-path: inset(0);
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        /* Menu Section */
        .menu-section {
            padding: 5rem 2rem;
            background: var(--cream);
        }

        .menu-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .menu-nav {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .menu-nav button {
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--curry);
            background: transparent;
            color: var(--curry);
            border-radius: 50px;
            cursor: pointer;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            transition: all 0.3s;
        }

        .menu-nav button.active,
        .menu-nav button:hover {
            background: var(--curry);
            color: var(--white);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .menu-item {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s;
        }

        .menu-item:hover {
            transform: translateY(-5px);
        }

        .menu-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .menu-item-content {
            padding: 1.5rem;
        }

        .menu-item h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .menu-item p {
            color: var(--text-gray);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .menu-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Steps Section */
        .steps-section {
            padding: 5rem 2rem;
            background: var(--white);
        }

        .steps-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .steps-title {
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .step {
            text-align: center;
            padding: 2rem;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            margin: 0 auto 1rem;
        }

        .step h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .step p {
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
        .promo-banner {
            font-size: 12px;
            padding: 6px 15px;
        }
        
        .navbar {
            top: 32px; /* UPDATE THIS */
        }
        
        .hero-section {
            padding-top: 100px; /* UPDATE THIS */
        }
        
        .promo-banner-content {
            flex-direction: column;
            gap: 5px;
        }
        
        .promo-close {
            right: 10px;
        }
            .hero-container {
                flex-direction: column;
                text-align: center;
            }

            .hero-content {
                order: 1;
                max-width: 100%;
                padding: 2rem 1rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-videos {
                order: 2;
                height: 300px;
                flex-direction: row;
                gap: 1rem;
            }

            .image-column {
                height: 100%;
            }

            .video-container {
                min-height: 120px;
                font-size: 0.9rem;
            }

            .zip-input-container {
                max-width: 100%;
            }

            .order-now-button {
                max-width: 100%;
            }

            .nav-links {
                display: none;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .steps-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-videos {
                height: 250px;
            }
            
            .video-container {
                min-height: 100px;
                font-size: 0.8rem;
            }
        }
.menu-nav-container {
    margin-bottom: 32px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
}

.menu-nav-wrapper {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.menu-nav-wrapper::-webkit-scrollbar {
    display: none;
}

.menu-nav-list {
    display: flex;
    gap: 0;
    min-width: max-content;
    align-items: center;
}

.menu-nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 54px;
    padding: 0 16px;
    border: none;
    border-bottom: 2px solid #ece8e1;
    background: transparent;
    cursor: pointer;
    font-family: 'BaticaSans', Arial, sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #707070;
    transition: all 0.3s ease;
    white-space: nowrap;
    text-decoration: none;
}

.menu-nav-item:hover {
    color: #bd9379;
    border-bottom-color: #bd9379;
}

.menu-nav-item.active {
    color: #cf723a;
    border-bottom-color: #cf723a;
}

.menu-nav-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-nav-icon svg {
    width: 100%;
    height: 100%;
    fill: #707070;
    transition: fill 0.3s ease;
}

.menu-nav-item:hover .menu-nav-icon svg {
    fill: #bd9379;
}

.menu-nav-item.active .menu-nav-icon svg {
    fill: #cf723a;
}

.menu-nav-text {
    font-size: 14px;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 768px) {
    .menu-nav-container {
        margin-bottom: 24px;
    }
    
    .menu-nav-item {
        padding: 0 12px;
        font-size: 13px;
    }
    
    .menu-nav-icon {
        width: 20px;
        height: 20px;
    }
}
/* Promotional Banner Styles - ADD THIS */
.promo-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--curry) 0%, #e67e22 100%);
    color: var(--white);
    text-align: center;
    padding: 8px 20px;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
    font-size: 14px;
    z-index: 1001;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    animation: glow 2s ease-in-out infinite alternate;
}

.promo-banner-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.promo-icon {
    font-size: 16px;
    animation: bounce 1.5s ease-in-out infinite;
}

.promo-text {
    letter-spacing: 0.5px;
}

.promo-close {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--white);
    font-size: 18px;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.promo-close:hover {
    opacity: 1;
}

@keyframes glow {
    from {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    to {
        box-shadow: 0 2px 20px rgba(207, 114, 58, 0.3);
    }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-3px);
    }
    60% {
        transform: translateY(-2px);
    }
}
    </style>
</head>

<body>
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🍪</span>
            <span class="promo-text">50% OFF First Week + Free Cookies for Life</span>
            <span class="promo-icon">🎉</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Your existing navigation code starts here -->
    <nav class="navbar">
        <!-- existing nav content -->
    </nav>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
            <a href="#" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto;">
            </a>
            <a href="#" class="logo">
                <span class="logo-text">Somdul Table</span>
            </a>

            
            <ul class="nav-links">
                <li><a href="#menu">Menu</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="#" class="btn btn-secondary">Sign In</a>
                <a href="#" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Vertical Slider -->
    <section class="hero-section" id="home" data-testid="hero-vertical-slider">
        <div class="hero-container" data-testid="hero-vertical-slider-image-columns-container">
            <div class="background"></div>
            
            <div class="hero-content">
                <h1>Fresh Thai Meals Delivered Daily</h1>
                <p>Experience authentic Thai flavors crafted by expert chefs and delivered fresh to your door. Healthy, delicious, and perfectly spiced to your preference.</p>
                
                <div class="hero-form">
                    <div class="zip-input-container">
                        <input type="text" class="zip-input" placeholder="Enter your ZIP code">
                    </div>
                    <a href="#menu" class="order-now-button">View Menu</a>
                </div>
            </div>
            
            <div class="hero-videos">
                <!-- Left Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <span>Pad Thai</span>
                        </div>
                        <div class="video-container">
                            <span>Green Curry</span>
                        </div>
                        <div class="video-container">
                            <span>Tom Yum</span>
                        </div>
                        <div class="video-container">
                            <span>Massaman</span>
                        </div>
                        <div class="video-container">
                            <span>Som Tam</span>
                        </div>
                        <div class="video-container">
                            <span>Mango Rice</span>
                        </div>
                    </div>
                </div>

                <!-- Middle Column - Sliding Down -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider">
                        <div class="video-container">
                            <span>Larb</span>
                        </div>
                        <div class="video-container">
                            <span>Satay</span>
                        </div>
                        <div class="video-container">
                            <span>Panang</span>
                        </div>
                        <div class="video-container">
                            <span>Khao Pad</span>
                        </div>
                        <div class="video-container">
                            <span>Sticky Rice</span>
                        </div>
                        <div class="video-container">
                            <span>Thai Basil</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <span>Red Curry</span>
                        </div>
                        <div class="video-container">
                            <span>Drunken Noodles</span>
                        </div>
                        <div class="video-container">
                            <span>Thai Fried Rice</span>
                        </div>
                        <div class="video-container">
                            <span>Coconut Soup</span>
                        </div>
                        <div class="video-container">
                            <span>Spring Rolls</span>
                        </div>
                        <div class="video-container">
                            <span>Thai Tea</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="menu-section" id="menu">
        <div class="menu-container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: var(--text-dark); font-family: 'BaticaSans', sans-serif; font-weight: 700;">Our Thai Menu</h2>
            
            <div class="menu-nav-container">
                <div class="menu-nav-wrapper">
                    <div class="menu-nav-list">
                        <button class="menu-nav-item active" data-category="appetizers">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Appetizers</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="curries">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Curries</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="stir-fry">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12.5 3.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5S10.17 2 11 2s1.5.67 1.5 1.5zM20 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm0 10H4v-8h16v8zm-8-6c1.38 0 2.5 1.12 2.5 2.5S13.38 17 12 17s-2.5-1.12-2.5-2.5S10.62 12 12 12z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Stir Fry</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="noodles">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M22 2v20H2V2h20zm-2 2H4v16h16V4zM6 8h12v2H6V8zm0 4h12v2H6v-2zm0 4h8v2H6v-2z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Noodles</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="rice">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 3H6c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H6V5h12v14zM8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h6v2H8v-2z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Rice Dishes</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="soups">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2zm0-10h16v8H4V8zm8-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Soups</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="salads">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 10c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm8 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.12.23-2.18.65-3.15C6.53 8.51 8 8 9.64 8c.93 0 1.83.22 2.64.61.81-.39 1.71-.61 2.64-.61 1.64 0 3.11.51 4.35.85.42.97.65 2.03.65 3.15 0 4.41-3.59 8-8 8z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Salads</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="desserts">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 3L8 6.5h8L12 3zm0 18c4.97 0 9-4.03 9-9H3c0 4.97 4.03 9 9 9zm0-16L8.5 8h7L12 5z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Desserts</span>
                        </button>
                        
                        <button class="menu-nav-item" data-category="beverages">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 4v3h5.5v12h3V7H19V4H5z"/>
                                </svg>
                            </span>
                            <span class="menu-nav-text">Beverages</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="menu-grid">
                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23ff6b35' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Pad Thai</text></svg>" alt="Pad Thai">
                    <div class="menu-item-content">
                        <h3>Classic Pad Thai</h3>
                        <p>Traditional stir-fried rice noodles with shrimp, tofu, bean sprouts, and our signature tamarind sauce</p>
                        <div class="menu-price">$14.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23e74c3c' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Green Curry</text></svg>" alt="Green Curry">
                    <div class="menu-item-content">
                        <h3>Thai Green Curry</h3>
                        <p>Aromatic green curry with chicken, Thai eggplant, bamboo shoots, and fresh basil leaves</p>
                        <div class="menu-price">$16.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23f39c12' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Massaman Curry</text></svg>" alt="Massaman Curry">
                    <div class="menu-item-content">
                        <h3>Massaman Beef Curry</h3>
                        <p>Rich and mild curry with tender beef, potatoes, and roasted peanuts in coconut milk</p>
                        <div class="menu-price">$18.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%2327ae60' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Som Tam</text></svg>" alt="Som Tam">
                    <div class="menu-item-content">
                        <h3>Papaya Salad (Som Tam)</h3>
                        <p>Fresh green papaya salad with tomatoes, green beans, and lime dressing</p>
                        <div class="menu-price">$12.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%239b59b6' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Tom Yum</text></svg>" alt="Tom Yum">
                    <div class="menu-item-content">
                        <h3>Tom Yum Goong</h3>
                        <p>Spicy and sour soup with shrimp, mushrooms, lemongrass, and lime leaves</p>
                        <div class="menu-price">$15.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%2334495e' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='BaticaSans, Arial'>Mango Sticky Rice</text></svg>" alt="Mango Sticky Rice">
                    <div class="menu-item-content">
                        <h3>Mango Sticky Rice</h3>
                        <p>Sweet sticky rice topped with fresh mango slices and coconut cream</p>
                        <div class="menu-price">$8.99</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Steps Section -->
    <section class="steps-section" id="how-it-works">
        <div class="steps-container">
            <h2 class="steps-title">How It Works</h2>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Register</h3>
                    <p>Create your account and tell us about your dietary preferences and spice level tolerance</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Choose Plan</h3>
                    <p>Select from our flexible meal plans - 4, 8, 12, or 16 meals per week to fit your lifestyle</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Choose Meals</h3>
                    <p>Browse our weekly menu and pick your favorite Thai dishes from our chef-crafted selection</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Delivery & Enjoy</h3>
                    <p>Choose your delivery date and pay securely. Fresh meals delivered right to your door!</p>
                </div>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuItems = document.querySelectorAll('.menu-nav-item');
        
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all items
                menuItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Get category for filtering
                const category = this.getAttribute('data-category');
                console.log('Selected category:', category);
                
                // Add your PHP/AJAX filtering logic here
                // Example: filterMenuItems(category);
            });
        });
    });
    function closePromoBanner() {
        const promoBanner = document.getElementById('promoBanner');
        const navbar = document.querySelector('.navbar');
        const heroSection = document.querySelector('.hero-section');
        
        promoBanner.style.transform = 'translateY(-100%)';
        promoBanner.style.opacity = '0';
        
        setTimeout(() => {
            promoBanner.style.display = 'none';
            navbar.style.top = '0';
            heroSection.style.paddingTop = '80px';
        }, 300);
    }

// Your existing JavaScript continues here...
        // Menu navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuButtons = document.querySelectorAll('.menu-nav button');
            
            menuButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    menuButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                });
            });
        });

        // Smooth scrolling for navigation links
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('a[href^="#"]');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        targetSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });
    </script>
</body>
</html>