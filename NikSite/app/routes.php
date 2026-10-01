<?php
/** @var Router $router */

// ---------------- Public ----------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/services', [HomeController::class, 'services']);
$router->get('/services/{category}', [HomeController::class, 'services']);
$router->get('/service/{slug}', [HomeController::class, 'service']);
$router->get('/search', [HomeController::class, 'search']);
$router->get('/blog', [HomeController::class, 'blog']);
$router->get('/blog/{slug}', [HomeController::class, 'post']);
$router->get('/page/{slug}', [HomeController::class, 'page']);
$router->get('/faq', [HomeController::class, 'faq']);
$router->get('/contact', [HomeController::class, 'contact']);
$router->get('/b/{id}', [HomeController::class, 'bannerClick']);
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);
$router->get('/robots.txt', [HomeController::class, 'robots']);

// ---------------- Cart & checkout ----------------
$router->get('/cart', [CartController::class, 'index']);
$router->post('/cart/add', [CartController::class, 'add']);
$router->post('/cart/update', [CartController::class, 'update']);
$router->post('/cart/remove', [CartController::class, 'remove']);
$router->post('/cart/coupon', [CartController::class, 'coupon']);
$router->post('/cart/checkout', [CartController::class, 'checkout'], ['auth']);

// ---------------- Auth ----------------
$router->get('/login', [AuthController::class, 'loginForm'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/register', [AuthController::class, 'registerForm'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->get('/forgot', [AuthController::class, 'forgotForm'], ['guest']);
$router->post('/forgot', [AuthController::class, 'forgot'], ['guest']);
$router->get('/reset/{token}', [AuthController::class, 'resetForm'], ['guest']);
$router->post('/reset/{token}', [AuthController::class, 'reset'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/google', [AuthController::class, 'google']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);
$router->get('/auth/telegram', [AuthController::class, 'telegram']);
$router->post('/impersonate/stop', [AuthController::class, 'stopImpersonating'], ['auth']);

// ---------------- Payments ----------------
$router->post('/wallet/charge', [PaymentController::class, 'charge'], ['auth']);
$router->post('/wallet/card', [PaymentController::class, 'card'], ['auth']);
$router->get('/payment/callback/{id}', [PaymentController::class, 'callback']);
$router->get('/payment/test/{id}', [PaymentController::class, 'testGateway'], ['auth']);
$router->post('/payment/test/{id}', [PaymentController::class, 'testGatewaySubmit'], ['auth']);

// ---------------- User panel ----------------
$router->post('/favorite/{id}', [UserController::class, 'toggleFavorite'], ['auth']);
$router->group('dashboard', ['auth'], function (Router $r) {
    $r->get('/', [UserController::class, 'dashboard']);
    $r->get('/new-order', [UserController::class, 'newOrder']);
    $r->post('/new-order', [UserController::class, 'placeOrder']);
    $r->get('/orders', [UserController::class, 'orders']);
    $r->get('/orders/{id}', [UserController::class, 'order']);
    $r->post('/orders/{id}/pay', [UserController::class, 'payOrder']);
    $r->post('/orders/{id}/cancel', [UserController::class, 'cancelOrder']);
    $r->get('/wallet', [UserController::class, 'wallet']);
    $r->get('/favorites', [UserController::class, 'favorites']);
    $r->get('/tickets', [UserController::class, 'tickets']);
    $r->get('/tickets/new', [UserController::class, 'ticketCreate']);
    $r->post('/tickets/new', [UserController::class, 'ticketStore']);
    $r->get('/tickets/{id}', [UserController::class, 'ticket']);
    $r->post('/tickets/{id}/reply', [UserController::class, 'ticketReply']);
    $r->post('/tickets/{id}/close', [UserController::class, 'ticketClose']);
    $r->get('/profile', [UserController::class, 'profile']);
    $r->post('/profile', [UserController::class, 'profileUpdate']);
    $r->post('/password', [UserController::class, 'passwordUpdate']);
    $r->get('/api', [UserController::class, 'api']);
    $r->post('/api/regenerate', [UserController::class, 'apiRegenerate']);
    $r->get('/notifications', [UserController::class, 'notifications']);
    $r->post('/notifications/read', [UserController::class, 'notificationsRead']);
});

// ---------------- Reseller API & cron ----------------
$router->any('/api/v2', [ApiController::class, 'handle'], ['nocsrf']);
$router->get('/cron/{key}', [ApiController::class, 'cron']);

// ---------------- Admin ----------------
$router->group('admin', ['admin'], function (Router $r) {
    $r->get('/', [AdminDashboardController::class, 'index']);
    $r->get('/search', [AdminDashboardController::class, 'search']);
    $r->get('/reports', [AdminDashboardController::class, 'reports']);
    $r->post('/notifications/read', [AdminDashboardController::class, 'notificationsRead']);
    $r->post('/cron/run', [AdminDashboardController::class, 'runCron']);

    $r->get('/services', [AdminCatalogController::class, 'services']);
    $r->get('/services/create', [AdminCatalogController::class, 'serviceForm']);
    $r->get('/services/{id}/edit', [AdminCatalogController::class, 'serviceForm']);
    $r->post('/services/save', [AdminCatalogController::class, 'serviceSave']);
    $r->post('/services/{id}/delete', [AdminCatalogController::class, 'serviceDelete']);
    $r->post('/services/{id}/toggle', [AdminCatalogController::class, 'serviceToggle']);
    $r->post('/services/bulk', [AdminCatalogController::class, 'serviceBulk']);
    $r->get('/categories', [AdminCatalogController::class, 'categories']);
    $r->post('/categories/save', [AdminCatalogController::class, 'categorySave']);
    $r->post('/categories/{id}/delete', [AdminCatalogController::class, 'categoryDelete']);
    $r->get('/stock', [AdminCatalogController::class, 'stock']);
    $r->post('/stock', [AdminCatalogController::class, 'stockAdd']);
    $r->get('/providers', [AdminCatalogController::class, 'providers']);
    $r->post('/providers/save', [AdminCatalogController::class, 'providerSave']);
    $r->post('/providers/{id}/delete', [AdminCatalogController::class, 'providerDelete']);
    $r->post('/providers/{id}/check', [AdminCatalogController::class, 'providerCheck']);
    $r->get('/providers/{id}/import', [AdminCatalogController::class, 'providerImport']);
    $r->post('/providers/{id}/import', [AdminCatalogController::class, 'providerImportSave']);

    $r->get('/orders', [AdminOrderController::class, 'index']);
    $r->get('/orders/export', [AdminOrderController::class, 'export']);
    $r->get('/orders/create', [AdminOrderController::class, 'create']);
    $r->post('/orders/create', [AdminOrderController::class, 'store']);
    $r->post('/orders/bulk', [AdminOrderController::class, 'bulk']);
    $r->get('/orders/{id}', [AdminOrderController::class, 'show']);
    $r->post('/orders/{id}/update', [AdminOrderController::class, 'update']);
    $r->post('/orders/{id}/resend', [AdminOrderController::class, 'resend']);
    $r->post('/orders/{id}/sync', [AdminOrderController::class, 'sync']);

    $r->get('/users', [AdminUserController::class, 'index']);
    $r->post('/users/create', [AdminUserController::class, 'store']);
    $r->get('/users/{id}', [AdminUserController::class, 'show']);
    $r->post('/users/{id}/update', [AdminUserController::class, 'update']);
    $r->post('/users/{id}/balance', [AdminUserController::class, 'balance']);
    $r->post('/users/{id}/impersonate', [AdminUserController::class, 'impersonate']);

    $r->get('/tickets', [AdminSupportController::class, 'index']);
    $r->get('/tickets/{id}', [AdminSupportController::class, 'show']);
    $r->post('/tickets/{id}/reply', [AdminSupportController::class, 'reply']);
    $r->post('/tickets/{id}/status', [AdminSupportController::class, 'status']);

    $r->get('/payments', [AdminFinanceController::class, 'payments']);
    $r->post('/payments/{id}/approve', [AdminFinanceController::class, 'approve']);
    $r->post('/payments/{id}/reject', [AdminFinanceController::class, 'reject']);
    $r->get('/transactions', [AdminFinanceController::class, 'transactions']);
    $r->get('/coupons', [AdminFinanceController::class, 'coupons']);
    $r->post('/coupons/save', [AdminFinanceController::class, 'couponSave']);
    $r->post('/coupons/{id}/delete', [AdminFinanceController::class, 'couponDelete']);

    $r->get('/banners', [AdminContentController::class, 'banners']);
    $r->post('/banners/save', [AdminContentController::class, 'bannerSave']);
    $r->post('/banners/{id}/delete', [AdminContentController::class, 'bannerDelete']);
    $r->get('/content', [AdminContentController::class, 'index']);
    $r->get('/content/create', [AdminContentController::class, 'form']);
    $r->get('/content/{id}/edit', [AdminContentController::class, 'form']);
    $r->post('/content/save', [AdminContentController::class, 'save']);
    $r->post('/content/{id}/delete', [AdminContentController::class, 'delete']);
    $r->post('/faqs/save', [AdminContentController::class, 'faqSave']);
    $r->post('/faqs/{id}/delete', [AdminContentController::class, 'faqDelete']);

    $r->get('/settings', [AdminSettingsController::class, 'index']);
    $r->post('/settings', [AdminSettingsController::class, 'save']);
});
