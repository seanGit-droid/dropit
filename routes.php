<?php
/**
 * Drop IT routes
 */

// ============ Public ============
$router->get('/', 'app/views/landing');

$router->get('/track',            'app/views/public_track');
$router->get('/track/{tracking}', 'app/views/public_track');

$router->get('/login', 'app/views/login');
$router->post('/login', 'app/views/actions/login_action');

$router->post('/logout', 'app/views/actions/logout', ['auth']);

// ============ Forgot password ============
$router->get('/forgot',         'app/views/forgot');
$router->post('/forgot/send',   'app/views/actions/forgot_password');
$router->post('/forgot/verify', 'app/views/actions/forgot_password');
$router->post('/forgot/reset',  'app/views/actions/forgot_password');

// ============ Signup ============
$router->get('/signup', 'app/views/signup/choose');
$router->get('/signup/driver',   function(){ $role='driver';   include APP_ROOT . '/app/views/signup/form.php'; });
$router->get('/signup/customer', function(){ $role='customer'; include APP_ROOT . '/app/views/signup/form.php'; });
$router->post('/signup/driver',   function(){ $role='driver';   include APP_ROOT . '/app/views/actions/signup_submit.php'; });
$router->post('/signup/customer', function(){ $role='customer'; include APP_ROOT . '/app/views/actions/signup_submit.php'; });
$router->post('/signup/otp/send',   'app/views/actions/otp_send');
$router->post('/signup/otp/verify', 'app/views/actions/otp_verify');

// ============ Customer ============
$router->group([
    'prefix'     => 'customer',
    'middleware' => ['customer'],
], function ($router) {
    $router->get('/dashboard',     'app/views/customer/dashboard');
    $router->get('/track',         'app/views/customer/track');
    $router->get('/notifications', 'app/views/customer/notifications');
    $router->get('/history',       'app/views/customer/history');
    $router->get('/profile',       'app/views/customer/profile');
    $router->post('/profile/update', 'app/views/actions/profile_update');
    $router->get('/report',        'app/views/customer/report');
    $router->post('/report',       'app/views/actions/report_submit');
    $router->get('/order',         'app/views/customer/order');
    $router->post('/order',        'app/views/actions/customer_order');
    $router->get('/messages',      'app/views/customer/messages');
    $router->get('/return/{id}',   'app/views/customer/return_request');
    $router->post('/return/{id}',  'app/views/actions/return_submit');
    $router->get('/support',       'app/views/customer/support');
    $router->get('/favorites',     'app/views/customer/favorites');
    $router->post('/favorites',    'app/views/actions/favorites');
    $router->post('/favorites/delete/{id}', 'app/views/actions/favorites');
    $router->get('/rate/{id}',     'app/views/customer/rate');
    $router->post('/rate/{id}',    'app/views/actions/rate_submit');
    $router->get('/receipt/{tracking}', 'app/views/customer/receipt');
    $router->get('/cash-in',            'app/views/customer/cash_in');
    $router->post('/cash-in',           'app/views/actions/customer_cash_in');
    $router->get('/cash-in/return',     'app/views/actions/customer_cash_in_return');
});

// ============ Driver ============
$router->group([
    'prefix'     => 'driver',
    'middleware' => ['driver'],
], function ($router) {
    $router->get('/dashboard',      'app/views/driver/dashboard');
    $router->get('/notifications',  'app/views/customer/notifications');
    $router->get('/profile',        'app/views/driver/profile');
    $router->post('/profile/update','app/views/actions/profile_update');
    $router->get('/messages',       'app/views/driver/messages');
    $router->get('/analytics',      'app/views/driver/analytics');
    $router->get('/analytics/export','app/views/driver/analytics_export');
    $router->get('/parcel/{id}',    'app/views/driver/parcel_detail');
    $router->post('/accept/{id}',   'app/views/actions/driver_accept');
    $router->post('/update/{id}',   'app/views/actions/driver_update_status');
    $router->get('/pod/{id}',       'app/views/driver/pod');
    $router->post('/pod/{id}',      'app/views/actions/pod_submit');
    $router->post('/transit-photo/{id}', 'app/views/actions/transit_photo');
    $router->get('/quick-accept/{id}',  'app/views/actions/driver_quick_accept');
    $router->get('/quick-decline/{id}', 'app/views/actions/driver_quick_accept');
    $router->post('/cod/{id}',      'app/views/actions/pay_cod_collect');
});

// ============ Admin ============
$router->group([
    'prefix'     => 'admin',
    'middleware' => ['admin'],
], function ($router) {
    $router->get('/dashboard', 'app/views/admin/dashboard');

    $router->get('/accounts',              'app/views/admin/accounts');
    $router->get('/accounts/new',          'app/views/admin/account_form');
    $router->post('/accounts/create',      'app/views/actions/account_save');
    $router->get('/accounts/edit/{id}',    'app/views/admin/account_form');
    $router->post('/accounts/update/{id}', 'app/views/actions/account_save');
    $router->post('/accounts/delete/{id}', 'app/views/actions/account_delete');

    $router->get('/shipments/walkin',     'app/views/admin/walkin');
    $router->post('/shipments/walkin',    'app/views/actions/walkin_create');
    $router->get('/shipments/assign',     'app/views/admin/assign_driver');
    $router->post('/shipments/assign/{id}','app/views/actions/parcel_assign');
    $router->post('/shipments/cancel/{id}','app/views/actions/parcel_cancel');

    $router->get('/parcel/{id}',          'app/views/admin/parcel_detail');
    $router->post('/notify-driver/{id}',  'app/views/actions/admin_notify_driver');

    $router->get('/drivers-map',          'app/views/admin/drivers_map');
    $router->get('/messages',             'app/views/admin/messages');
    $router->get('/analytics',            'app/views/admin/analytics');
    $router->get('/analytics/export',     'app/views/admin/analytics_export');
    $router->get('/analytics/report',     'app/views/admin/report_print');
    $router->get('/analytics/report/pdf', 'app/views/admin/report_pdf');

    $router->get('/history', 'app/views/admin/history');

    $router->get('/reports', 'app/views/admin/reports');
    $router->post('/reports/status/{id}', 'app/views/actions/report_status');

    $router->get('/settings',       'app/views/admin/settings');
    $router->post('/settings',      'app/views/actions/settings_save');
    $router->get('/notifications',  'app/views/admin/notifications_log');
    $router->get('/inbox',          'app/views/admin/inbox');
});

// ============ Payment ============
$router->post('/pay/gcash/create/{id}', 'app/views/actions/pay_gcash_create', ['customer']);
$router->get('/pay/gcash/return/{id}',  'app/views/actions/pay_gcash_return', ['customer']);

// ============ API ============
$router->post('/api/driver/location',     'app/views/api/driver_location', ['driver']);
$router->post('/api/driver/availability', 'app/views/api/driver_availability', ['driver']);
$router->post('/api/driver/sos',          'app/views/api/driver_sos', ['driver']);
$router->get('/api/parcel/{tracking}/track', 'app/views/api/parcel_tracking', ['auth']);
$router->get('/api/drivers/locations',    'app/views/api/drivers_locations', ['auth']);
$router->get('/api/parcel/{id}/messages', 'app/views/api/messages', ['auth']);
$router->post('/api/parcel/{id}/messages','app/views/api/messages', ['auth']);
$router->get('/api/notifications/unread', 'app/views/api/notifications_unread', ['auth']);
$router->get('/api/messages/unread',      'app/views/api/messages_unread', ['auth']);
$router->post('/api/theme',               'app/views/api/theme_toggle', ['auth']);
$router->post('/api/parcel/{id}/price-adjust',  'app/views/api/parcel_price_adjust',  ['admin']);
$router->post('/api/parcel/{id}/price-respond', 'app/views/api/parcel_price_respond', ['customer']);
$router->get('/api/customer/price-reviews',     'app/views/api/customer_price_reviews', ['customer']);
