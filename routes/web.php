<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/signin', fn () => redirect()->route('login'))->name('signin');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// dashboard pages
Route::get('/', fn () => redirect()->route('dashboard'))->middleware('auth');

Route::get('/dashboard', function () {
    return view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']);
})->middleware('auth')->name('dashboard');

// main navigation pages
Route::get('/kanal', function () {
    return view('pages.kanal.index', ['title' => 'Kanal']);
})->middleware('auth')->name('kanal.index');

Route::get('/program', function () {
    return view('pages.program.index', ['title' => 'Program']);
})->middleware('auth')->name('program.index');

Route::get('/campaign', function () {
    return view('pages.campaign.index', ['title' => 'Campaign']);
})->middleware('auth')->name('campaign.index');

Route::get('/donatur', function () {
    return view('pages.donatur.index', ['title' => 'Donatur']);
})->middleware('auth')->name('donatur.index');

Route::get('/laporan', function () {
    return view('pages.laporan.index', ['title' => 'Laporan']);
})->middleware('auth')->name('laporan.index');

// favorit pages
Route::get('/favorit/dashboard-eksekutif', function () {
    return view('pages.favorit.dashboard-eksekutif', ['title' => 'Dashboard Eksekutif']);
})->middleware('auth')->name('favorit.dashboard-eksekutif');

Route::get('/favorit/performa-kanal', function () {
    return view('pages.favorit.performa-kanal', ['title' => 'Performa Kanal']);
})->middleware('auth')->name('favorit.performa-kanal');

Route::get('/favorit/realtime-monitor', function () {
    return view('pages.favorit.realtime-monitor', ['title' => 'Real-Time Monitor']);
})->middleware('auth')->name('favorit.realtime-monitor');

Route::get('/favorit/analisis-donatur', function () {
    return view('pages.favorit.analisis-donatur', ['title' => 'Analisis Donatur']);
})->middleware('auth')->name('favorit.analisis-donatur');

// calender pages
Route::get('/calendar', function () {
    return view('pages.calender', ['title' => 'Calendar']);
})->name('calendar');

// profile pages
Route::get('/profile', function () {
    return view('pages.profile', ['title' => 'Profile']);
})->middleware('auth')->name('profile');

// form pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

// tables pages
Route::get('/basic-tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->middleware('auth')->name('basic-tables');

Route::get('/tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->middleware('auth')->name('tables');

// pages

Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

// error pages
Route::get('/error-404', function () {
    return view('pages.errors.error-404', ['title' => 'Error 404']);
})->name('error-404');

// chart pages
Route::get('/line-chart', function () {
    return view('pages.chart.line-chart', ['title' => 'Line Chart']);
})->name('line-chart');

Route::get('/bar-chart', function () {
    return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
})->name('bar-chart');


Route::get('/signup', function () {
    return view('pages.auth.signup', ['title' => 'Sign Up']);
})->middleware('guest')->name('signup');

// ui elements pages
Route::get('/alerts', function () {
    return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
})->name('alerts');

Route::get('/avatars', function () {
    return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
})->name('avatars');

Route::get('/badge', function () {
    return view('pages.ui-elements.badges', ['title' => 'Badges']);
})->name('badges');

Route::get('/buttons', function () {
    return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
})->name('buttons');

Route::get('/image', function () {
    return view('pages.ui-elements.images', ['title' => 'Images']);
})->name('images');

Route::get('/videos', function () {
    return view('pages.ui-elements.videos', ['title' => 'Videos']);
})->name('videos');





















