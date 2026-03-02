<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('ecommerce');
});

Route::get('/crm', function () {
    return view('crm');
});

Route::get('/project-management', function () {
    return view('project-management');
});

Route::get('/lms', function () {
    return view('lms');
});

Route::get('/help-desk', function () {
    return view('help-desk');
});

Route::get('/analytics', function () {
    return view('analytics');
});

Route::get('/crypto', function () {
    return view('crypto');
});

Route::get('/sales', function () {
    return view('sales');
});

Route::get('/hospital', function () {
    return view('hospital');
});

Route::get('/marketing', function () {
    return view('marketing');
});

Route::get('/nft', function () {
    return view('nft');
});

Route::get('/saas', function () {
    return view('saas');
});

Route::get('/real-estate', function () {
    return view('real-estate');
});

Route::get('/shipment', function () {
    return view('shipment');
});

Route::get('/finance', function () {
    return view('finance');
});

Route::get('/hrm', function () {
    return view('hrm');
});

Route::get('/school', function () {
    return view('school');
});

Route::get('/call-center', function () {
    return view('call-center');
});

Route::get('/pos-system', function () {
    return view('pos-system');
});

Route::get('/podcast', function () {
    return view('podcast');
});

Route::get('/landing-page', function () {
    return view('landing-page');
});

Route::get('/login', function () {
    return view('login');
});

Route::get('/register', function () {
    return view('register');
});

Route::get('/pricing-plan', function () {
    return view('pricing-plan');
});

Route::get('/faq', function () {
    return view('faq');
});

Route::get('/timeline', function () {
    return view('timeline');
});

Route::get('/calender', function () {
    return view('calender');
});

Route::get('/to-do-list', function () {
    return view('to-do-list');
});

Route::get('/contacts', function () {
    return view('contacts');
});

Route::get('/chat', function () {
    return view('chat');
});

Route::get('/inbox', function () {
    return view('inbox');
});
Route::get('/compose', function () {
    return view('compose');
});
Route::get('/read-email', function () {
    return view('read-email');
});
Route::get('/snoozed', function () {
    return view('snoozed');
});
Route::get('/draft', function () {
    return view('draft');
});
Route::get('/sent-mail', function () {
    return view('sent-mail');
});
Route::get('/trash-email', function () {
    return view('trash-email');
});
Route::get('/spam', function () {
    return view('spam');
});
Route::get('/starred', function () {
    return view('starred');
});
Route::get('/important', function () {
    return view('important');
});
Route::get('/kanban-board', function () {
    return view('kanban-board');
});
Route::get('/file-manager', function () {
    return view('file-manager');
});
Route::get('/assets-manager', function () {
    return view('assets-manager');
});
Route::get('/projects', function () {
    return view('projects');
});
Route::get('/personal', function () {
    return view('personal');
});
Route::get('/applications', function () {
    return view('applications');
});
Route::get('/documents', function () {
    return view('documents');
});
Route::get('/media', function () {
    return view('media');
});
Route::get('/recents', function () {
    return view('recents');
});
Route::get('/drive-important', function () {
    return view('drive-important');
});
Route::get('/drive-spam', function () {
    return view('drive-spam');
});
Route::get('/drive-trash', function () {
    return view('drive-trash');
});
Route::get('/products-grid', function () {
    return view('products-grid');
});
Route::get('/products-list', function () {
    return view('products-list');
});
Route::get('/product-details', function () {
    return view('product-details');
});
Route::get('/create-product', function () {
    return view('create-product');
});
Route::get('/edit-product', function () {
    return view('edit-product');
});
Route::get('/cart', function () {
    return view('cart');
});
Route::get('/checkout', function () {
    return view('checkout');
});
Route::get('/orders', function () {
    return view('orders');
});
Route::get('/order-details', function () {
    return view('order-details');
});
Route::get('/create-order', function () {
    return view('create-order');
});
Route::get('/order-tracking', function () {
    return view('order-tracking');
});
Route::get('/customers', function () {
    return view('customers');
});
Route::get('/customer-details', function () {
    return view('customer-details');
});
Route::get('/categories', function () {
    return view('categories');
});
Route::get('/sellers', function () {
    return view('sellers');
});
Route::get('/seller-details', function () {
    return view('seller-details');
});
Route::get('/create-seller', function () {
    return view('create-seller');
});
Route::get('/reviews', function () {
    return view('reviews');
});
Route::get('/refunds', function () {
    return view('refunds');
});
Route::get('/contacts-2', function () {
    return view('contacts-2');
});
Route::get('/customers-2', function () {
    return view('customers-2');
});
Route::get('/leads', function () {
    return view('leads');
});
Route::get('/deals', function () {
    return view('deals');
});
Route::get('/project-overview', function () {
    return view('project-overview');
});
Route::get('/project-list', function () {
    return view('project-list');
});
Route::get('/create-project', function () {
    return view('create-project');
});
Route::get('/clients', function () {
    return view('clients');
});
Route::get('/teams', function () {
    return view('teams');
});
Route::get('/kanban-board-2', function () {
    return view('kanban-board-2');
});
Route::get('/user', function () {
    return view('user');
});
Route::get('/courses-list', function () {
    return view('courses-list');
});
Route::get('/course-details', function () {
    return view('course-details');
});
Route::get('/lesson-preview', function () {
    return view('lesson-preview');
});
Route::get('/create-course', function () {
    return view('create-course');
});
Route::get('/edit-course', function () {
    return view('edit-course');
});
Route::get('/instructors', function () {
    return view('instructors');
});
Route::get('/tickets', function () {
    return view('tickets');
});
Route::get('/ticket-details', function () {
    return view('ticket-details');
});
Route::get('/agents', function () {
    return view('agents');
});
Route::get('/reports', function () {
    return view('reports');
});
Route::get('/events', function () {
    return view('events');
});
Route::get('/events-list', function () {
    return view('events-list');
});
Route::get('/event-details', function () {
    return view('event-details');
});
Route::get('/create-an-event', function () {
    return view('create-an-event');
});
Route::get('/edit-an-event', function () {
    return view('edit-an-event');
});
Route::get('/profile', function () {
    return view('profile');
});
Route::get('/settings', function () {
    return view('settings');
});
Route::get('/invoice-list', function () {
    return view('invoice-list');
});
Route::get('/invoice-details', function () {
    return view('invoice-details');
});
Route::get('/create-invoice', function () {
    return view('create-invoice');
});
Route::get('/edit-invoice', function () {
    return view('edit-invoice');
});
Route::get('/team-members', function () {
    return view('team-members');
});
Route::get('/users-list', function () {
    return view('users-list');
});
Route::get('/add-user', function () {
    return view('add-user');
});
Route::get('/user-profile', function () {
    return view('user-profile');
});
Route::get('/teams-2', function () {
    return view('teams-2');
});
Route::get('/my-projects', function () {
    return view('my-projects');
});
Route::get('/starter', function () {
    return view('starter');
});
Route::get('/material-icons', function () {
    return view('material-icons');
});
Route::get('/feathericon', function () {
    return view('feathericon');
});
Route::get('/remixicon', function () {
    return view('remixicon');
});
Route::get('/alerts', function () {
    return view('alerts');
});
Route::get('/avatar', function () {
    return view('avatar');
});
Route::get('/buttons', function () {
    return view('buttons');
});
Route::get('/badges', function () {
    return view('badges');
});
Route::get('/cards', function () {
    return view('cards');
});
Route::get('/carousels', function () {
    return view('carousels');
});
Route::get('/dropdowns', function () {
    return view('dropdowns');
});
Route::get('/grids', function () {
    return view('grids');
});
Route::get('/images', function () {
    return view('images');
});
Route::get('/list', function () {
    return view('list');
});
Route::get('/modals', function () {
    return view('modals');
});
Route::get('/navs', function () {
    return view('navs');
});
Route::get('/paginations', function () {
    return view('paginations');
});
Route::get('/popover-tooltips', function () {
    return view('popover-tooltips');
});
Route::get('/progess', function () {
    return view('progess');
});
Route::get('/spinners', function () {
    return view('spinners');
});
Route::get('/tabs', function () {
    return view('tabs');
});
Route::get('/accoridions', function () {
    return view('accoridions');
});
Route::get('/date-time-picker', function () {
    return view('date-time-picker');
});
Route::get('/videos', function () {
    return view('videos');
});
Route::get('/basic-table', function () {
    return view('basic-table');
});
Route::get('/data-tables', function () {
    return view('data-tables');
});
Route::get('/basic-elements', function () {
    return view('basic-elements');
});
Route::get('/advanced-elements', function () {
    return view('advanced-elements');
});
Route::get('/validation', function () {
    return view('validation');
});
Route::get('/wizard', function () {
    return view('wizard');
});
Route::get('/editors', function () {
    return view('editors');
});
Route::get('/file-upload', function () {
    return view('file-upload');
});
Route::get('/apex-charts', function () {
    return view('apex-charts');
});
Route::get('/animation', function () {
    return view('animation');
});
Route::get('/clip-board', function () {
    return view('clip-board');
});
Route::get('/drag-drop', function () {
    return view('drag-drop');
});
Route::get('/range-slider', function () {
    return view('range-slider');
});
Route::get('/ratings', function () {
    return view('ratings');
});
Route::get('/toasts', function () {
    return view('toasts');
});
Route::get('/check-radio', function () {
    return view('check-radio');
});
Route::get('/sweet-alarts', function () {
    return view('sweet-alarts');
});
Route::get('/select', function () {
    return view('select');
});
Route::get('/scrollbar', function () {
    return view('scrollbar');
});
Route::get('/typography', function () {
    return view('typography');
});
Route::get('/gallery', function () {
    return view('gallery');
});
Route::get('/search', function () {
    return view('search');
});
Route::get('/my-profile', function () {
    return view('my-profile');
});



Route::get('/404-error-page', function () {
    return view('404-error-page');
});
Route::get('/internal-error', function () {
    return view('internal-error');
});
Route::get('/widgets', function () {
    return view('widgets');
});
Route::get('/google-map', function () {
    return view('google-map');
});
Route::get('/notification', function () {
    return view('notification');
});
Route::get('/account-settings', function () {
    return view('account-settings');
});
Route::get('/change-password', function () {
    return view('change-password');
});
Route::get('/connections', function () {
    return view('connections');
});
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});
Route::get('/terms-conditions', function () {
    return view('terms-conditions');
});
Route::get('/members', function () {
    return view('members');
});
