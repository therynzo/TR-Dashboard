<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LinkvertiseController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedeemController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\EnsureAdmin;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// OAuth routes always exist, but the controller refuses to start flows until an admin explicitly enables them.
Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->whereIn('provider', ['discord', 'google'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->whereIn('provider', ['discord', 'google'])->name('oauth.callback');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/create', [ServerController::class, 'createForm'])->name('servers.create');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('/pterodactyl/discovery/{resource}', PterodactylDiscoveryController::class)->whereIn('resource', ['locations', 'nodes', 'nests', 'eggs', 'allocations'])->name('pterodactyl.discovery');
    Route::get('/servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::post('/servers/{server}/renew', [ServerController::class, 'renew'])->name('servers.renew');

    Route::get('/credits', function () {
        return view('dashboard.credits', ['transactions' => CreditTransaction::where('user_id', auth()->id())->latest()->paginate(10)]);
    })->name('credits');
    Route::post('/redeem', [RedeemController::class, 'redeem'])->name('redeem');
    Route::get('/linkvertise/go', [LinkvertiseController::class, 'start'])->name('linkvertise.start');
    Route::get('/linkvertise/complete', [LinkvertiseController::class, 'complete'])->name('linkvertise.complete');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [AdminController::class, 'overview'])->name('overview');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/servers', [AdminController::class, 'servers'])->name('servers');
        Route::post('/servers/{server}/suspend', [AdminController::class, 'suspendServer'])->name('servers.suspend');
        Route::delete('/servers/{server}', [AdminController::class, 'deleteServer'])->name('servers.delete');
        Route::get('/tickets', [AdminController::class, 'tickets'])->name('tickets');
        Route::post('/tickets/{ticket}/status', [AdminController::class, 'updateTicketStatus'])->name('tickets.status');
        Route::get('/redeem-codes', [AdminController::class, 'redeemCodes'])->name('redeem-codes');
        Route::post('/redeem-codes', [AdminController::class, 'createRedeemCodes'])->name('redeem-codes.create');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');

        // Old single settings URL redirects to the new, separate category pages.
        Route::get('/settings', fn () => redirect()->route('admin.settings.branding'))->name('settings');
        Route::get('/settings/branding', [AdminSettingsController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/branding', [AdminSettingsController::class, 'saveBranding'])->name('settings.branding.save');
        Route::get('/settings/pterodactyl', [AdminSettingsController::class, 'pterodactyl'])->name('settings.pterodactyl');
        Route::post('/settings/pterodactyl', [AdminSettingsController::class, 'savePterodactyl'])->name('settings.pterodactyl.save');
        Route::post('/settings/pterodactyl/test', [AdminSettingsController::class, 'testPterodactyl'])->name('settings.pterodactyl.test');
        Route::post('/settings/pterodactyl/defaults', [AdminSettingsController::class, 'savePterodactylDefaults'])->name('settings.pterodactyl.defaults');
        Route::get('/settings/defaults', [AdminSettingsController::class, 'defaults'])->name('settings.defaults');
        Route::post('/settings/defaults', [AdminSettingsController::class, 'saveDefaults'])->name('settings.defaults.save');
        Route::get('/settings/authentication', [AdminSettingsController::class, 'authentication'])->name('settings.authentication');
        Route::post('/settings/authentication', [AdminSettingsController::class, 'saveAuthentication'])->name('settings.authentication.save');
        Route::get('/settings/linkvertise', [AdminSettingsController::class, 'linkvertise'])->name('settings.linkvertise');
        Route::post('/settings/linkvertise', [AdminSettingsController::class, 'saveLinkvertise'])->name('settings.linkvertise.save');
    });
});
