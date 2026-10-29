<?php

declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Account\AccountController;
use App\Controllers\Admin;
use App\Controllers\Auth\AuthController;
use App\Controllers\Seller\SellerController;
use App\Controllers\Seller\SellerProductController;
use App\Controllers\Store;

// ───────────── Storefront ─────────────
$router->get('/', [Store\HomeController::class, 'index']);
$router->get('/shop', [Store\ShopController::class, 'shop']);
$router->get('/new-arrivals', [Store\ShopController::class, 'newArrivals']);
$router->get('/sale', [Store\ShopController::class, 'sale']);
$router->get('/search', [Store\ShopController::class, 'search']);
$router->get('/collections', [Store\ShopController::class, 'collections']);
$router->get('/collections/{slug:[a-z0-9-]+}', [Store\ShopController::class, 'collection']);
$router->get('/category/{slug:[a-z0-9-]+}', [Store\ShopController::class, 'category']);
$router->get('/product/{slug:[a-z0-9-]+}', [Store\ProductController::class, 'show']);
$router->get('/sellers/{slug:[a-z0-9-]+}', [Store\ShopController::class, 'seller']);

$router->get('/cart', [Store\CartController::class, 'show']);
$router->post('/cart/add', [Store\CartController::class, 'add']);
$router->post('/cart/update', [Store\CartController::class, 'update']);
$router->post('/cart/remove', [Store\CartController::class, 'remove']);
$router->post('/cart/coupon', [Store\CartController::class, 'applyCoupon']);
$router->post('/cart/coupon/remove', [Store\CartController::class, 'removeCoupon']);
$router->get('/cart/count', [Store\CartController::class, 'count']);

$router->post('/wishlist/toggle', [Store\WishlistController::class, 'toggle']);
$router->get('/wishlist', [Store\WishlistController::class, 'redirect']);

$router->get('/checkout', [Store\CheckoutController::class, 'show']);
$router->post('/checkout', [Store\CheckoutController::class, 'place']);
$router->get('/checkout/complete/{number:UVX-[0-9]+}', [Store\CheckoutController::class, 'complete']);
$router->post('/orders/{number:UVX-[0-9]+}/bank-reference', [Store\CheckoutController::class, 'bankReference']);
$router->get('/track-order', [Store\TrackController::class, 'form']);
$router->post('/track-order', [Store\TrackController::class, 'lookup']);

$router->get('/payments/sandbox/{ref:sbx_[a-f0-9]+}', [Store\SandboxController::class, 'show']);
$router->post('/payments/sandbox/{ref:sbx_[a-f0-9]+}', [Store\SandboxController::class, 'complete']);
$router->post('/webhooks/{provider:[a-z_]+}', [Store\WebhookController::class, 'handle'], ['nocsrf']);

$router->get('/about', [Store\PageController::class, 'about']);
$router->get('/contact', [Store\PageController::class, 'contact']);
$router->post('/contact', [Store\PageController::class, 'sendContact']);
$router->get('/faq', [Store\PageController::class, 'faq']);
$router->get('/pages/{slug:[a-z0-9-]+}', [Store\PageController::class, 'show']);
$router->post('/newsletter', [Store\PageController::class, 'subscribe']);
$router->get('/currency', [Store\PageController::class, 'currency']);
$router->get('/newsletter/unsubscribe/{token:[a-f0-9]+}', [Store\PageController::class, 'unsubscribe']);
$router->get('/sitemap.xml', [Store\SeoController::class, 'sitemap']);
$router->get('/robots.txt', [Store\SeoController::class, 'robots']);
$router->get('/site.webmanifest', [Store\SeoController::class, 'manifest']);
$router->post('/cron/run', [Store\CronController::class, 'run'], ['nocsrf']);

// ───────────── Authentication ─────────────
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/seller/login', [AuthController::class, 'sellerLoginForm']);
$router->post('/seller/login', [AuthController::class, 'sellerLogin']);
$router->get('/admin/login', [AuthController::class, 'adminLoginForm']);
$router->post('/admin/login', [AuthController::class, 'adminLogin']);
$router->get('/register', [AuthController::class, 'registerForm'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->get('/forgot-password', [AuthController::class, 'forgotForm']);
$router->post('/forgot-password', [AuthController::class, 'forgot']);
$router->get('/reset-password', [AuthController::class, 'resetForm']);
$router->post('/reset-password', [AuthController::class, 'reset']);
$router->get('/verify-email', [AuthController::class, 'verify']);
$router->post('/verify-email/resend', [AuthController::class, 'resendVerification'], ['auth']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// ───────────── Customer account ─────────────
$router->group('/account', ['auth'], static function ($r): void {
    $r->get('', [AccountController::class, 'dashboard']);
    $r->get('/profile', [AccountController::class, 'profile']);
    $r->post('/profile', [AccountController::class, 'updateProfile']);
    $r->post('/password', [AccountController::class, 'updatePassword']);
    $r->get('/addresses', [AccountController::class, 'addresses']);
    $r->post('/addresses', [AccountController::class, 'saveAddress']);
    $r->post('/addresses/{id:\d+}/delete', [AccountController::class, 'deleteAddress']);
    $r->post('/addresses/{id:\d+}/default', [AccountController::class, 'defaultAddress']);
    $r->get('/orders', [AccountController::class, 'orders']);
    $r->get('/orders/{number:UVX-[0-9]+}', [AccountController::class, 'order']);
    $r->get('/wishlist', [AccountController::class, 'wishlist']);
});

// ───────────── Marketplace sellers ─────────────
$router->get('/sell', [SellerController::class, 'applyForm']);
$router->get('/seller/register', [SellerController::class, 'applyForm']);
$router->post('/seller/register', [AuthController::class, 'sellerRegister']);
$router->post('/sell', [SellerController::class, 'apply'], ['auth']);
$router->group('/seller', ['auth'], static function ($r): void {
    $r->get('', [SellerController::class, 'dashboard']);
    $r->get('/orders', [SellerController::class, 'orders']);
    $r->get('/orders/{number:UVX-[0-9]+}', [SellerController::class, 'order']);
    $r->post('/orders/{number:UVX-[0-9]+}/tracking', [SellerController::class, 'tracking']);
    $r->get('/wallet', [SellerController::class, 'wallet']);
    $r->post('/wallet/withdraw', [SellerController::class, 'withdraw']);
    $r->get('/settings', [SellerController::class, 'settings']);
    $r->post('/settings', [SellerController::class, 'saveSettings']);
    $r->get('/products', [SellerProductController::class, 'index']);
    $r->get('/products/new', [SellerProductController::class, 'create']);
    $r->post('/products', [SellerProductController::class, 'store']);
    $r->get('/products/{id:\d+}', [SellerProductController::class, 'edit']);
    $r->post('/products/{id:\d+}', [SellerProductController::class, 'update']);
    $r->post('/products/{id:\d+}/variants', [SellerProductController::class, 'variants']);
    $r->post('/products/{id:\d+}/images', [SellerProductController::class, 'uploadImages']);
    $r->post('/products/{id:\d+}/images/{image:\d+}/delete', [SellerProductController::class, 'deleteImage']);
    $r->post('/products/{id:\d+}/submit', [SellerProductController::class, 'submit']);
    $r->post('/products/{id:\d+}/unpublish', [SellerProductController::class, 'unpublish']);
});

// ───────────── Admin ─────────────
$router->group('/admin', ['staff'], static function ($r): void {
    $r->get('', [Admin\DashboardController::class, 'index'], ['perm:dashboard.view']);

    $r->get('/products', [Admin\ProductController::class, 'index'], ['perm:products.view']);
    $r->get('/products/new', [Admin\ProductController::class, 'create'], ['perm:products.manage']);
    $r->post('/products', [Admin\ProductController::class, 'store'], ['perm:products.manage']);
    $r->get('/products/import', [Admin\ImportController::class, 'form'], ['perm:products.import']);
    $r->get('/products/import/template', [Admin\ImportController::class, 'template'], ['perm:products.import']);
    $r->post('/products/import', [Admin\ImportController::class, 'preview'], ['perm:products.import']);
    $r->post('/products/import/commit', [Admin\ImportController::class, 'commit'], ['perm:products.import']);
    $r->get('/products/export', [Admin\ProductController::class, 'export'], ['perm:products.manage']);
    $r->get('/products/{id:\d+}/edit', [Admin\ProductController::class, 'edit'], ['perm:products.view']);
    $r->get('/products/{id:\d+}/preview', [Admin\ProductController::class, 'preview'], ['perm:products.view']);
    $r->post('/products/{id:\d+}', [Admin\ProductController::class, 'update'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/status', [Admin\ProductController::class, 'status'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/feature', [Admin\ProductController::class, 'feature'], ['perm:products.feature']);
    $r->post('/products/{id:\d+}/delete', [Admin\ProductController::class, 'destroy'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/variants', [Admin\ProductController::class, 'saveVariants'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/images', [Admin\ProductController::class, 'uploadImages'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/images/order', [Admin\ProductController::class, 'orderImages'], ['perm:products.manage']);
    $r->post('/products/{id:\d+}/images/{image:\d+}/delete', [Admin\ProductController::class, 'deleteImage'], ['perm:products.manage']);

    $r->get('/categories', [Admin\CategoryController::class, 'index'], ['perm:catalog.manage']);
    $r->post('/categories', [Admin\CategoryController::class, 'save'], ['perm:catalog.manage']);
    $r->post('/categories/{id:\d+}/archive', [Admin\CategoryController::class, 'archive'], ['perm:catalog.manage']);
    $r->post('/categories/{id:\d+}/delete', [Admin\CategoryController::class, 'destroy'], ['perm:catalog.manage']);
    $r->get('/collections', [Admin\CollectionController::class, 'index'], ['perm:catalog.manage']);
    $r->post('/collections', [Admin\CollectionController::class, 'save'], ['perm:catalog.manage']);
    $r->get('/collections/{id:\d+}', [Admin\CollectionController::class, 'edit'], ['perm:catalog.manage']);
    $r->post('/collections/{id:\d+}/products', [Admin\CollectionController::class, 'products'], ['perm:catalog.manage']);
    $r->post('/collections/{id:\d+}/archive', [Admin\CollectionController::class, 'archive'], ['perm:catalog.manage']);
    $r->post('/collections/{id:\d+}/delete', [Admin\CollectionController::class, 'destroy'], ['perm:catalog.manage']);

    $r->get('/inventory', [Admin\InventoryController::class, 'index'], ['perm:inventory.view']);
    $r->post('/inventory/adjust', [Admin\InventoryController::class, 'adjust'], ['perm:inventory.adjust']);
    $r->get('/inventory/movements', [Admin\InventoryController::class, 'movements'], ['perm:inventory.view']);

    $r->get('/orders', [Admin\OrderController::class, 'index'], ['perm:orders.view']);
    $r->get('/orders/export', [Admin\OrderController::class, 'export'], ['perm:reports.export']);
    $r->get('/orders/{id:\d+}', [Admin\OrderController::class, 'show'], ['perm:orders.view']);
    $r->get('/orders/{id:\d+}/invoice', [Admin\OrderController::class, 'invoice'], ['perm:orders.view']);
    $r->get('/orders/{id:\d+}/packing-slip', [Admin\OrderController::class, 'packingSlip'], ['perm:orders.view']);
    $r->get('/orders/{id:\d+}/receipt/{payment:\d+}', [Admin\OrderController::class, 'receipt'], ['perm:orders.payments']);
    $r->post('/orders/{id:\d+}/status', [Admin\OrderController::class, 'status'], ['perm:orders.fulfil']);
    $r->post('/orders/{id:\d+}/cancel', [Admin\OrderController::class, 'cancel'], ['perm:orders.cancel']);
    $r->post('/orders/{id:\d+}/ship', [Admin\OrderController::class, 'ship'], ['perm:orders.fulfil']);
    $r->post('/orders/{id:\d+}/cod-collected', [Admin\OrderController::class, 'codCollected'], ['perm:orders.payments']);
    $r->post('/orders/{id:\d+}/bank-verify', [Admin\OrderController::class, 'bankVerify'], ['perm:orders.payments']);
    $r->post('/orders/{id:\d+}/refund', [Admin\OrderController::class, 'refund'], ['perm:orders.refund']);
    $r->post('/orders/{id:\d+}/note', [Admin\OrderController::class, 'note'], ['perm:orders.fulfil']);
    $r->post('/orders/{id:\d+}/address', [Admin\OrderController::class, 'address'], ['perm:orders.fulfil']);

    $r->get('/sellers', [Admin\SellerController::class, 'index'], ['perm:sellers.manage']);
    $r->get('/sellers/reviews', [Admin\SellerController::class, 'reviews'], ['perm:sellers.manage']);
    $r->get('/sellers/{id:\d+}', [Admin\SellerController::class, 'show'], ['perm:sellers.manage']);
    $r->post('/sellers/{id:\d+}/decide', [Admin\SellerController::class, 'decide'], ['perm:sellers.manage']);
    $r->post('/sellers/{id:\d+}/commission', [Admin\SellerController::class, 'commission'], ['perm:settings.manage']);
    $r->post('/sellers/products/{id:\d+}/review', [Admin\SellerController::class, 'reviewProduct'], ['perm:sellers.manage']);
    $r->get('/withdrawals', [Admin\SellerController::class, 'withdrawals'], ['perm:payouts.manage']);
    $r->post('/withdrawals/{id:\d+}', [Admin\SellerController::class, 'processWithdrawal'], ['perm:payouts.manage']);

    $r->get('/customers', [Admin\CustomerController::class, 'index'], ['perm:customers.view']);
    $r->get('/customers/export', [Admin\CustomerController::class, 'export'], ['perm:customers.export']);
    $r->get('/customers/{id:\d+}', [Admin\CustomerController::class, 'show'], ['perm:customers.view']);
    $r->post('/customers/{id:\d+}', [Admin\CustomerController::class, 'update'], ['perm:customers.manage']);

    $r->get('/coupons', [Admin\CouponController::class, 'index'], ['perm:coupons.manage']);
    $r->get('/coupons/new', [Admin\CouponController::class, 'edit'], ['perm:coupons.manage']);
    $r->get('/coupons/{id:\d+}', [Admin\CouponController::class, 'edit'], ['perm:coupons.manage']);
    $r->post('/coupons', [Admin\CouponController::class, 'save'], ['perm:coupons.manage']);
    $r->post('/coupons/{id:\d+}/delete', [Admin\CouponController::class, 'destroy'], ['perm:coupons.manage']);

    $r->get('/content', [Admin\ContentController::class, 'index'], ['perm:content.manage']);
    $r->post('/content/settings/{group:[a-z_]+}', [Admin\ContentController::class, 'saveSettings'], ['perm:content.manage']);
    $r->get('/content/banners', [Admin\ContentController::class, 'banners'], ['perm:content.manage']);
    $r->post('/content/banners', [Admin\ContentController::class, 'saveBanner'], ['perm:content.manage']);
    $r->post('/content/banners/{id:\d+}/delete', [Admin\ContentController::class, 'deleteBanner'], ['perm:content.manage']);
    $r->get('/content/pages', [Admin\ContentController::class, 'pages'], ['perm:content.manage']);
    $r->get('/content/pages/{id:\d+}', [Admin\ContentController::class, 'editPage'], ['perm:content.manage']);
    $r->get('/content/pages/new', [Admin\ContentController::class, 'editPage'], ['perm:content.manage']);
    $r->post('/content/pages', [Admin\ContentController::class, 'savePage'], ['perm:content.manage']);
    $r->get('/content/faqs', [Admin\ContentController::class, 'faqs'], ['perm:content.manage']);
    $r->post('/content/faqs', [Admin\ContentController::class, 'saveFaq'], ['perm:content.manage']);
    $r->post('/content/faqs/{id:\d+}/delete', [Admin\ContentController::class, 'deleteFaq'], ['perm:content.manage']);
    $r->get('/content/home', [Admin\ContentController::class, 'home'], ['perm:content.manage']);
    $r->get('/content/navigation', [Admin\ContentController::class, 'navigation'], ['perm:content.manage']);
    $r->get('/content/branding', [Admin\ContentController::class, 'branding'], ['perm:content.manage']);
    $r->post('/content/pages/{id:\d+}/delete', [Admin\ContentController::class, 'deletePage'], ['perm:content.manage']);
    $r->post('/content/home/featured', [Admin\ContentController::class, 'saveFeatured'], ['perm:content.manage']);

    $r->get('/settings', [Admin\SettingsController::class, 'index'], ['perm:settings.manage']);
    $r->post('/settings/{group:[a-z_]+}', [Admin\SettingsController::class, 'save'], ['perm:settings.manage']);
    $r->get('/settings/shipping', [Admin\SettingsController::class, 'shipping'], ['perm:settings.manage']);
    $r->post('/settings/shipping/zone', [Admin\SettingsController::class, 'saveZone'], ['perm:settings.manage']);
    $r->post('/settings/shipping/rate', [Admin\SettingsController::class, 'saveRate'], ['perm:settings.manage']);
    $r->post('/settings/shipping/courier', [Admin\SettingsController::class, 'saveCourier'], ['perm:settings.manage']);
    $r->get('/settings/payments', [Admin\SettingsController::class, 'payments'], ['perm:payments.configure']);
    $r->get('/settings/currency', [Admin\SettingsController::class, 'currency'], ['perm:settings.manage']);

    $r->get('/staff', [Admin\StaffController::class, 'index'], ['perm:staff.manage']);
    $r->post('/staff', [Admin\StaffController::class, 'save'], ['perm:staff.manage']);

    $r->get('/reports', [Admin\ReportController::class, 'index'], ['perm:reports.view']);
    $r->get('/reports/export/{type:[a-z_]+}', [Admin\ReportController::class, 'export'], ['perm:reports.export']);
    $r->get('/audit', [Admin\AuditController::class, 'index'], ['perm:audit.view']);
    $r->get('/messages', [Admin\MessageController::class, 'index'], ['perm:messages.view']);
    $r->post('/messages/{id:\d+}/handled', [Admin\MessageController::class, 'handled'], ['perm:messages.view']);
    $r->get('/newsletter/export', [Admin\MessageController::class, 'exportNewsletter'], ['perm:customers.export']);
});
