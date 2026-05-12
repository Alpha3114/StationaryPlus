<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --accent: #F1EDE8;       /* Warm Beige */
            --background: #FAFAFA;   /* Very Light Grey */
            --text-primary: #2E2E2E; /* Charcoal */
            --text-secondary: #707070; /* Grey */
            --border: #E0E0E0;       /* Soft Grey */
            --white: #FFFFFF;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .registration-container {
            width: 100%;
            max-width: 1100px;
            display: flex;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--white);
        }
        
        /* Information Panel */
        .info-panel {
            flex: 1;
            background: linear-gradient(145deg, var(--primary) 0%, #8b2a2a 100%);
            color: var(--white);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
        }
        
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 50px;
        }
        
        .logo-icon {
            background-color: rgba(255, 255, 255, 0.15);
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }
        
        .logo-text {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .system-title {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 25px;
            line-height: 1.2;
        }
        
        .system-description {
            font-size: 17px;
            line-height: 1.6;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 90%;
        }
        
        .benefits-container {
            margin-top: 20px;
        }
        
        .benefit {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        
        .benefit-icon {
            background-color: rgba(255, 255, 255, 0.15);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            font-size: 18px;
        }
        
        .benefit-text {
            font-size: 16px;
            line-height: 1.5;
        }
        
        /* Form Panel */
        .form-panel {
            flex: 1;
            padding: 50px 40px;
            background-color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            margin-bottom: 40px;
        }
        
        .form-title {
            font-size: 30px;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-subtitle {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .registration-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 28px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
            background-color: var(--accent);
            color: var(--text-primary);
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 53, 53, 0.1);
            background-color: var(--white);
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 18px;
        }
        
        .input-hint {
            display: block;
            margin-top: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .button-container {
            margin-top: 10px;
            margin-bottom: 30px;
        }
        
        .register-button {
            width: 100%;
            padding: 16px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .register-button:hover {
            background-color: #8b2a2a;
        }
        
        .secondary-action {
            text-align: center;
            color: var(--text-secondary);
            font-size: 16px;
            padding-top: 25px;
            border-top: 1px solid var(--border);
        }
        
        .secondary-action a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        
        .secondary-action a:hover {
            text-decoration: underline;
        }
        
        .form-footer {
            margin-top: 25px;
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .error-message {
            color: #D32F2F;
            font-size: 14px;
            margin-top: 6px;
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 900px) {
            .registration-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .info-panel, .form-panel {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <!-- Information Panel -->
        <div class="info-panel">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-pen-nib"></i>
                </div>
                <div class="logo-text">StationaryPlus</div>
            </div>
            
            <h2 class="system-title">Stationery & Printing Management System</h2>
            <p class="system-description">
                A comprehensive solution for managing stationery orders, printing services, and pre-order tracking for educational institutions and businesses.
            </p>
            
            <div class="benefits-container">
                <div class="benefit">
                    <div class="benefit-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Make pre-orders</strong> for stationery items and printing services in advance.
                    </div>
                </div>
                
                <div class="benefit">
                    <div class="benefit-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Upload printing files</strong> directly to the system for quick processing and printing.
                    </div>
                </div>
                
                <div class="benefit">
                    <div class="benefit-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Check order or pre-order status</strong> in real-time with detailed tracking information.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Registration Form Panel -->
        <div class="form-panel">
            <div class="form-header">
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Register to access StationaryPlus services</p>
            </div>
            
            <form class="registration-form" id="registrationForm">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="name" placeholder="Enter your full name" required>
                    </div>
                    <span class="error-message" id="name-error">Please enter a valid name (minimum 2 characters)</span>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" placeholder="example@domain.com" required>
                    </div>
                    <span class="input-hint">We'll use this for order notifications and account recovery</span>
                    <span class="error-message" id="email-error">Please enter a valid email address</span>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" placeholder="Create a secure password" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                    <span class="input-hint">Minimum 8 characters with letters and numbers</span>
                    <span class="error-message" id="password-error">Password must be at least 8 characters long</span>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" id="phone" placeholder="Enter your phone number" required>
                    </div>
                    <span class="input-hint">For delivery updates and account verification</span>
                    <span class="error-message" id="phone-error">Please enter a valid phone number</span>
                </div>
                
                <div class="button-container">
                    <button type="submit" class="register-button">Register Account</button>
                </div>
                
                <div class="secondary-action">
                    Already have an account? <a href="#">Login here</a>
                </div>
                
                <div class="form-footer">
                    By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form validation
        const registrationForm = document.getElementById('registrationForm');
        const nameError = document.getElementById('name-error');
        const emailError = document.getElementById('email-error');
        const passwordError = document.getElementById('password-error');
        const phoneError = document.getElementById('phone-error');
        
        registrationForm.addEventListener('submit', function(event) {
            event.preventDefault();
            let isValid = true;
            
            // Clear previous error messages
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });
            
            // Validate name
            const name = document.getElementById('name').value.trim();
            if (name.length < 2) {
                nameError.style.display = 'block';
                isValid = false;
            }
            
            // Validate email
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailError.style.display = 'block';
                isValid = false;
            }
            
            // Validate password
            const password = document.getElementById('password').value;
            if (password.length < 8) {
                passwordError.style.display = 'block';
                isValid = false;
            }
            
            // Validate phone (simplified validation)
            const phone = document.getElementById('phone').value.trim();
            const phoneDigits = phone.replace(/\D/g, '');
            if (phoneDigits.length < 10) {
                phoneError.style.display = 'block';
                isValid = false;
            }
            
            // If form is valid, show success message
            if (isValid) {
                // For academic/demo purposes, we'll just show an alert
                alert('Registration successful! You can now log in to make pre-orders, upload files, and check order status.');
                // In a real application, you would submit the form to a server here
                // registrationForm.submit();
            }
        });
        
        // Real-time validation on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                const errorId = this.id + '-error';
                const errorElement = document.getElementById(errorId);
                
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
