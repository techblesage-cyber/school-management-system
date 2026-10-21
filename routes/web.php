<?php

use App\Http\Controllers\CircularAttachmentController;
use App\Http\Controllers\FeeDocumentController;
use App\Http\Controllers\LoginSlipController;
use App\Http\Controllers\ParentDocumentController;
use App\Http\Controllers\ReportCardController;
use App\Support\SchoolAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Home page: links to the parents' portal and the staff portal. On a
// school's own subdomain it shows that school's name and logo.
Route::get('/', fn (Request $request) => view('home', [
    'school' => SchoolAddress::fromRequest($request),
]))->name('home');

// Printable fees documents (opened from the school portal in a new tab)
Route::get('/fees/receipts/{payment}', [FeeDocumentController::class, 'receipt'])->name('fees.receipt');
Route::get('/fees/statements/{student}', [FeeDocumentController::class, 'statement'])->name('fees.statement');

// Printable report cards for staff (opened from Academics → Report cards)
Route::get('/reports/terms/{term}/classes/{class}', [ReportCardController::class, 'print'])
    ->whereNumber(['term', 'class'])
    ->name('reports.print');

// Circular attachments, for the staff and parents they were sent to
Route::get('/circulars/{circular}/attachment', CircularAttachmentController::class)->name('circulars.attachment');

// Printable documents in the parents' portal (their own children only)
Route::get('/parents/report-cards/{term}/{student}', [ParentDocumentController::class, 'reportCard'])
    ->whereNumber(['term', 'student'])
    ->name('parents.report-card');
Route::get('/parents/statements/{student}', [ParentDocumentController::class, 'statement'])
    ->whereNumber('student')
    ->name('parents.statement');

// A printed first login for a parent without email or SMS (just made by the person printing it)
Route::get('/parents/login-slips/{guardian}', LoginSlipController::class)->whereNumber('guardian')->name('parents.login-slip');
