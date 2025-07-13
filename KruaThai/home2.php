<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thai Delights - Fresh Thai Meal Prep Delivered</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #ffffff;
            color: #333;
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #e74c3c;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #e74c3c;
        }

        .cta-button {
            background: #e74c3c;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            transition: transform 0.3s;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        /* Hero Vertical Slider */
        .hero-vertical-slider {
            height: 100vh;
            position: relative;
            overflow: hidden;
            margin-top: 80px;
            display: flex;
            align-items: center;
        }

        .hero-vertical-slider__image-columns-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero-vertical-slider__image-columns-container .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
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
            font-weight: bold;
            color: #333;
            line-height: 1.1;
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            color: #666;
            line-height: 1.6;
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
            background: white;
            transition: all 0.3s;
            outline: none;
        }

        .zip-input:focus {
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .zip-input::placeholder {
            color: #999;
        }

        .order-now-button {
            background: #e74c3c;
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 200px;
        }

        .order-now-button:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

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
            animation-duration: 20s;
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
            background: #f8f8f8;
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
            background-color: #f8f9fa;
        }

        .menu-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .menu-nav {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            gap: 2rem;
        }

        .menu-nav button {
            background: none;
            border: 2px solid #e74c3c;
            color: #e74c3c;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }

        .menu-nav button.active,
        .menu-nav button:hover {
            background: #e74c3c;
            color: white;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .menu-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            color: #333;
        }

        .menu-item p {
            color: #666;
            margin-bottom: 1rem;
        }

        .menu-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #e74c3c;
        }

        /* Steps Section */
        .steps-section {
            padding: 5rem 2rem;
            background: white;
        }

        .steps-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .steps-title {
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #333;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .step {
            text-align: center;
            padding: 2rem;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        .step h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .step p {
            color: #666;
        }

        /* Help Section */
        .help-section {
            padding: 5rem 2rem;
            background: #f8f9fa;
        }

        .help-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .help-title {
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #333;
        }

        .help-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .help-item {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .help-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .help-item h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .meal-prep-info {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: left;
        }

        .meal-prep-info h3 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: #e74c3c;
            text-align: center;
        }

        .meal-prep-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-check {
            color: #27ae60;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-vertical-slider__image-columns-container {
                flex-direction: column;
                padding: 1rem;
            }

            .hero-content {
                order: 1;
                max-width: 100%;
                padding: 2rem 1rem;
                text-align: center;
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
            }

            .zip-input-container {
                max-width: 100%;
            }

            .order-now-button {
                max-width: 100%;
            }

            .menu-grid,
            .steps-grid,
            .help-grid {
                grid-template-columns: 1fr;
            }

            .meal-prep-features {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-videos {
                height: 250px;
            }
            
            .video-container {
                min-height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">🍜 Thai Delights</div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#menu">Menu</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <a href="#" class="cta-button">Get Started</a>
        </div>
    </nav>

    <!-- Hero Vertical Slider -->
    <section class="hero-vertical-slider" id="home" data-testid="hero-vertical-slider">
        <div class="hero-vertical-slider__image-columns-container" data-testid="hero-vertical-slider-image-columns-container">
            <div class="background"></div>
            
            <!-- Left Side - Content -->
            <div class="hero-content">
                <h1>Authentic Thai Flavors Delivered Fresh</h1>
                <p>Experience the bold, vibrant tastes of Thailand with our chef-prepared meal prep service. Fresh ingredients, traditional recipes, delivered to your door.</p>
                
                <div class="hero-form">
                    <div class="zip-input-container">
                        <input type="text" class="zip-input" placeholder="Enter your ZIP code" maxlength="5">
                    </div>
                    <a href="#" class="order-now-button">Order Now</a>
                </div>
                
                <p style="font-size: 0.9rem; color: #999; margin: 0;">Free delivery on orders over $50</p>
            </div>

            <!-- Right Side - Video Columns -->
            <div class="hero-videos">
                <!-- Left Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Sliding Down -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider">
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-container">
                            <video autoplay loop playsinline muted preload="none" aria-hidden="true" tabindex="-1">
                                <source src="data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAACKBtZGF0AAAC" type="video/mp4">
                            </video>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="menu-section" id="menu">
        <div class="menu-container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #333;">Our Thai Menu</h2>
            
            <div class="menu-nav">
                <button class="active">Main Dishes</button>
                <button>Soups</button>
                <button>Appetizers</button>
                <button>Desserts</button>
            </div>

            <div class="menu-grid">
                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23ff6b35' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Pad Thai</text></svg>" alt="Pad Thai">
                    <div class="menu-item-content">
                        <h3>Classic Pad Thai</h3>
                        <p>Traditional stir-fried rice noodles with shrimp, tofu, bean sprouts, and our signature tamarind sauce</p>
                        <div class="menu-price">$14.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23e74c3c' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Green Curry</text></svg>" alt="Green Curry">
                    <div class="menu-item-content">
                        <h3>Thai Green Curry</h3>
                        <p>Aromatic green curry with chicken, Thai eggplant, bamboo shoots, and fresh basil leaves</p>
                        <div class="menu-price">$16.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%23f39c12' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Massaman Curry</text></svg>" alt="Massaman Curry">
                    <div class="menu-item-content">
                        <h3>Massaman Beef Curry</h3>
                        <p>Rich and mild curry with tender beef, potatoes, and roasted peanuts in coconut milk</p>
                        <div class="menu-price">$18.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%2327ae60' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Som Tam</text></svg>" alt="Som Tam">
                    <div class="menu-item-content">
                        <h3>Papaya Salad (Som Tam)</h3>
                        <p>Fresh green papaya salad with tomatoes, green beans, and lime dressing</p>
                        <div class="menu-price">$12.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%239b59b6' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Tom Yum</text></svg>" alt="Tom Yum">
                    <div class="menu-item-content">
                        <h3>Tom Yum Goong</h3>
                        <p>Spicy and sour soup with shrimp, mushrooms, lemongrass, and lime leaves</p>
                        <div class="menu-price">$15.99</div>
                    </div>
                </div>

                <div class="menu-item">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'><rect fill='%2334495e' width='400' height='250'/><text x='200' y='130' text-anchor='middle' fill='white' font-size='18' font-family='Arial'>Mango Sticky Rice</text></svg>" alt="Mango Sticky Rice">
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
                    <h3>Delivery & Pay</h3>
                    <p>Choose your delivery date and pay securely. Your fresh Thai meals arrive chilled and ready to heat</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Help Section -->
    <section class="help-section">
        <div class="help-container">
            <h2 class="help-title">What Are You Looking For?</h2>
            <div class="help-grid">
                <div class="help-item">
                    <div class="help-icon">🍽️</div>
                    <h3>Meal Plans</h3>
                    <p>Flexible weekly plans that adapt to your schedule and appetite</p>
                </div>
                <div class="help-item">
                    <div class="help-icon">🌶️</div>
                    <h3>Spice Levels</h3>
                    <p>Customize heat levels from mild to authentic Thai spicy</p>
                </div>
                <div class="help-item">
                    <div class="help-icon">🚚</div>
                    <h3>Delivery Info</h3>
                    <p>Free delivery on orders over $50, with flexible scheduling options</p>
                </div>
            </div>

            <div class="meal-prep-info">
                <h3>About Our Thai Meal Prep Service</h3>
                <p>Thai Delights brings the authentic flavors of Thailand to your table with our premium meal prep service. Our experienced Thai chefs prepare each dish using traditional recipes and the finest imported ingredients, ensuring every bite delivers the perfect balance of sweet, sour, salty, and spicy flavors that Thai cuisine is famous for.</p>
                
                <div class="meal-prep-features">
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Authentic Thai recipes from Bangkok chefs</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Fresh ingredients delivered weekly</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>No artificial preservatives or MSG</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Customizable spice levels</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Gluten-free and vegan options available</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Ready in 3 minutes - just heat and eat</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Environmentally friendly packaging</span>
                    </div>
                    <div class="feature">
                        <span class="feature-check">✓</span>
                        <span>Cancel or pause anytime</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Menu Navigation
        const menuButtons = document.querySelectorAll('.menu-nav button');
        
        menuButtons.forEach(button => {
            button.addEventListener('click', function() {
                menuButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Here you could add logic to filter menu items based on category
                // For this demo, we'll just show the category change
                console.log('Category selected:', this.textContent);
            });
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
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

        // Create placeholder video content for demo
        document.addEventListener('DOMContentLoaded', function() {
            const videos = document.querySelectorAll('.video-container');
            
            videos.forEach((container, index) => {
                // Create colored placeholder backgrounds to simulate Thai food videos
                const colors = ['#ff6b35', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6', '#34495e'];
                const foodItems = ['Pad Thai', 'Green Curry', 'Tom Yum', 'Som Tam', 'Massaman', 'Mango Rice'];
                
                const colorIndex = index % colors.length;
                const foodIndex = index % foodItems.length;
                
                container.style.background = `linear-gradient(45deg, ${colors[colorIndex]}, ${colors[(colorIndex + 1) % colors.length]})`;
                container.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 1.2rem; font-weight: bold; text-align: center; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                        ${foodItems[foodIndex]}<br>
                        <span style="font-size: 0.9rem; opacity: 0.8;">Thai Cuisine</span>
                    </div>
                `;
            });

            // ZIP code input functionality
            const zipInput = document.querySelector('.zip-input');
            const orderButton = document.querySelector('.order-now-button');

            zipInput.addEventListener('input', function(e) {
                // Only allow numbers and limit to 5 digits
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 5) {
                    value = value.slice(0, 5);
                }
                e.target.value = value;

                // Update button state based on ZIP code validity
                if (value.length === 5) {
                    orderButton.style.opacity = '1';
                    orderButton.style.pointerEvents = 'auto';
                } else {
                    orderButton.style.opacity = '0.7';
                    orderButton.style.pointerEvents = 'auto';
                }
            });

            // Order Now button click handler
            orderButton.addEventListener('click', function(e) {
                e.preventDefault();
                const zipCode = zipInput.value;
                
                if (zipCode.length === 5) {
                    alert(`Great! We deliver to ${zipCode}. Redirecting to our menu...`);
                    // Here you would normally redirect to the menu/ordering page
                } else {
                    alert('Please enter a valid 5-digit ZIP code to check delivery availability.');
                    zipInput.focus();
                }
            });
        });
    </script>
</body>
</html>