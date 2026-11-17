<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - Audit withGufran</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        
        <!-- Logo and Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-4">
                <i class="bi bi-clipboard-check text-indigo-600 text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Audit withGufran</h1>
            <p class="text-indigo-100">Sign in to your account</p>
        </div>
        
        <!-- Login Form -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            
            <!-- Flash Messages -->
            <?php if ($error = flash('error')): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="bi bi-exclamation-circle text-red-600 text-xl mr-3"></i>
                    <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success = flash('success')): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="bi bi-check-circle text-green-600 text-xl mr-3"></i>
                    <p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="/login.php" id="loginForm">
                
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <!-- Email -->
                <div class="mb-5">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        Email Address
                    </label>
                    <div class="relative">
                        <input 
                            type="email" 
                            id="email"
                            name="email" 
                            value="<?= htmlspecialchars(old('email')) ?>"
                            placeholder="admin@auditwithgufran.com"
                            required
                            class="w-full pl-11 pr-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                        <i class="bi bi-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="mb-5">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            placeholder="Enter your password"
                            required
                            class="w-full pl-11 pr-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                        <i class="bi bi-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center">
                        <input 
                            type="checkbox" 
                            name="remember"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        >
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Forgot password?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="w-full py-3 px-4 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity shadow-lg"
                >
                    <i class="bi bi-box-arrow-in-right mr-2"></i>
                    Sign In
                </button>
                
            </form>
            
            <!-- Default Credentials Info (Remove in production) -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-xs text-amber-800 font-semibold mb-2">
                        <i class="bi bi-info-circle mr-1"></i>
                        Default Credentials
                    </p>
                    <p class="text-xs text-amber-700 mb-1">
                        Email: <strong>admin@auditwithgufran.com</strong>
                    </p>
                    <p class="text-xs text-amber-700">
                        Password: <strong>admin123</strong>
                    </p>
                    <p class="text-xs text-amber-600 mt-2">
                        Please change these credentials after first login
                    </p>
                </div>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-sm text-indigo-100">
                © 2026 Audit withGufran. All rights reserved.
            </p>
        </div>
        
    </div>
    
    <script>
        // Clear old input on successful load
        <?php clearOld(); ?>
    </script>
    
</body>
</html>
