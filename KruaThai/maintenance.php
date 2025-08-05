<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Under Maintenance</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <style>
        @import url('https://ydpschool.com/fonts/');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'BaticaSans', Arial, sans-serif;
            background: linear-gradient(135deg, #ece8e1 0%, #adb89d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(189, 147, 121, 0.2);
            border: 3px solid #bd9379;
        }
        
        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: #cf723a;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(207, 114, 58, 0.1);
        }
        
        .subtitle {
            color: #bd9379;
            font-size: 1.2rem;
            margin-bottom: 40px;
            font-style: italic;
        }
        
        .maintenance-icon {
            font-size: 5rem;
            color: #adb89d;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .maintenance-title {
            font-size: 2.5rem;
            color: #cf723a;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .maintenance-message {
            font-size: 1.2rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .estimated-time {
            background: #ece8e1;
            border-left: 4px solid #bd9379;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
        }
        
        .estimated-time h3 {
            color: #cf723a;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }
        
        .estimated-time p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .contact-info {
            background: #adb89d;
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .contact-info h3 {
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .contact-details {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .loading-dots {
            display: inline-block;
            margin-left: 10px;
        }
        
        .loading-dots span {
            animation: blink 1.4s infinite both;
            font-size: 1.5rem;
            color: #bd9379;
        }
        
        .loading-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .loading-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes blink {
            0%, 80%, 100% {
                opacity: 0;
            }
            40% {
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 40px 20px;
            }
            
            .logo {
                font-size: 2.5rem;
            }
            
            .maintenance-title {
                font-size: 2rem;
            }
            
            .maintenance-icon {
                font-size: 4rem;
            }
            
            .contact-details {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo">Somdul Table</div>
        <div class="subtitle">Authentic Thai Cuisine</div>
        
        <div class="maintenance-icon">🔧</div>
        
        <h1 class="maintenance-title">Under Maintenance</h1>
        
        <div class="maintenance-message">
            We're currently performing scheduled maintenance to improve your dining experience. 
            Our website will be back online shortly with enhanced features for easier ordering and better service.
            <div class="loading-dots">
                <span>.</span>
                <span>.</span>
                <span>.</span>
            </div>
        </div>
        
        <div class="estimated-time">
            <h3>⏰ Estimated Return Time</h3>
            <p>We expect to be back online within <strong>2-4 hours</strong>.<br>
            Thank you for your patience!</p>
        </div>
        
        <div class="contact-info">
            <h3>Need Immediate Assistance?</h3>
            <div class="contact-details">
                <div class="contact-item">
                    <span>📞</span>
                    <span>(555) 123-THAI</span>
                </div>
                <div class="contact-item">
                    <span>✉️</span>
                    <span>hello@somdultable.com</span>
                </div>
                <div class="contact-item">
                    <span>📍</span>
                    <span>Visit us in-store</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>