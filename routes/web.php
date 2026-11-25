<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PublicationEditorController;
use App\Http\Controllers\ResearchLineController;
use App\Http\Controllers\ResearchProjectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogueController::class, 'home'])->name('home');
Route::get('/publicaciones', [CatalogueController::class, 'index'])->middleware('throttle:120,1')->name('publications.index');
Route::get('/publicaciones/{publication}', [CatalogueController::class, 'show'])->name('publications.show');
Route::get('/investigadores', [CatalogueController::class, 'researchers'])->name('researchers.index');
Route::get('/investigadores/{researcher}', [CatalogueController::class, 'researcher'])->name('researchers.show');
Route::get('/investigadores/{researcher}/foto', [AccountController::class, 'avatar'])->name('researchers.avatar');
Route::get('/estadisticas', [CatalogueController::class, 'statistics'])->name('statistics');
Route::get('/proyectos', [ResearchProjectController::class, 'index'])->name('projects.index');
Route::get('/proyectos/{project}', [ResearchProjectController::class, 'show'])->name('projects.show');
Route::get('/lineas-de-investigacion', [ResearchLineController::class, 'index'])->name('lines.index');
Route::get('/lineas-de-investigacion/{line}', [ResearchLineController::class, 'show'])->name('lines.show');
Route::view('/privacidad', 'legal.privacy')->name('privacy');
Route::view('/cookies', 'legal.cookies')->name('cookies');
Route::post('/cookies', function (Request $r) {
    $d = $r->validate(['choice' => 'required|in:essential,all']);

    return response()->json(['saved' => true])->cookie('cookie_preferences', json_encode(['choice' => $d['choice'], 'version' => 1, 'date' => now()->toIso8601String()]), 60 * 24 * 180, '/', null, $r->isSecure(), true, false, 'lax');
})->middleware('throttle:30,1')->name('cookies.store');
Route::post('/exportar', [ExportController::class, 'export'])->middleware(['throttle:6,1', 'turnstile:export'])->name('exports');
Route::post('/publicaciones/{publication}/ficha', [ExportController::class, 'pdf'])->middleware(['throttle:6,1', 'turnstile:export'])->name('publications.pdf');
Route::get('/documentos/{document}', [DocumentController::class, 'show'])->name('documents.show');
Route::get('/documentos/{document}/archivo', [DocumentController::class, 'stream'])->middleware('throttle:60,1')->name('documents.stream');
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'login'])->name('login');
    Route::post('/entrar', [AuthController::class, 'authenticate'])->middleware(['throttle:5,1', 'turnstile:login']);
    Route::get('/auth/google', [AuthController::class, 'google'])->middleware('throttle:10,1')->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('auth.google.callback');
    Route::get('/olvide-contrasena', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/olvide-contrasena', [AuthController::class, 'sendReset'])->middleware(['throttle:3,1', 'turnstile:reset'])->name('password.email');
    Route::get('/restablecer/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/restablecer', [AuthController::class, 'storeReset'])->middleware(['throttle:5,1', 'turnstile:reset'])->name('password.store');
});
Route::middleware(['auth', 'account.ready'])->group(function () {
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cuenta', [AccountController::class, 'index'])->name('account');
    Route::get('/cuenta/contrasena', [AuthController::class, 'change'])->name('password.change');
    Route::post('/cuenta/contrasena', [AuthController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::post('/investigadores/{researcher}/reclamar', [AccountController::class, 'claim'])->middleware('throttle:5,1')->name('researchers.claim');
    Route::get('/cuenta/perfil', [AccountController::class, 'edit'])->name('profile.edit');
    Route::post('/cuenta/perfil', [AccountController::class, 'update'])->name('profile.update');
    Route::get('/cuenta/publicaciones/nueva', [PublicationEditorController::class, 'create'])->name('editor.create');
    Route::get('/cuenta/autores', [CatalogueController::class, 'authorSuggestions'])->middleware('throttle:120,1')->name('authors.suggestions');
    Route::post('/cuenta/publicaciones', [PublicationEditorController::class, 'save'])->middleware('throttle:20,1')->name('editor.store');
    Route::get('/cuenta/publicaciones/{publication}/editar', [PublicationEditorController::class, 'edit'])->name('editor.edit');
    Route::post('/cuenta/publicaciones/{publication}', [PublicationEditorController::class, 'save'])->middleware('throttle:20,1')->name('editor.update');
    Route::post('/cuenta/publicaciones/{publication}/confirmar', [AccountController::class, 'confirm'])->name('editor.confirm');
    Route::get('/cuenta/publicaciones/{publication}/eliminar', [AccountController::class, 'remove'])->name('account.remove');
    Route::delete('/cuenta/publicaciones/{publication}/bandeja', [AccountController::class, 'dismiss'])->middleware('throttle:20,1')->name('editor.dismiss');
    Route::post('/cuenta/documentos', [DocumentController::class, 'store'])->middleware(['throttle:10,1', 'turnstile:upload'])->name('documents.store');
    Route::post('/cuenta/documentos/{document}/ocultar', [DocumentController::class, 'hide'])->name('documents.hide');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::post('/admin/revision/lote', [AdminController::class, 'batch'])->middleware('throttle:15,1')->name('admin.batch');
    Route::post('/admin/publicaciones/{publication}', [AdminController::class, 'review'])->name('admin.review');
    Route::get('/admin/usuarios', [AdminController::class, 'users'])->name('admin.users.index');
    Route::get('/admin/proyectos', [ResearchProjectController::class, 'index'])->name('admin.projects');
    Route::get('/admin/proyectos/nuevo', [ResearchProjectController::class, 'create'])->name('admin.projects.create');
    Route::post('/admin/proyectos', [ResearchProjectController::class, 'save'])->name('admin.projects.store');
    Route::get('/admin/proyectos/{project}/editar', [ResearchProjectController::class, 'edit'])->name('admin.projects.edit');
    Route::post('/admin/proyectos/{project}', [ResearchProjectController::class, 'save'])->name('admin.projects.update');
    Route::delete('/admin/proyectos/{project}', [ResearchProjectController::class, 'destroy'])->name('admin.projects.destroy');
    Route::get('/cuenta/proyectos/{project}/editar', [ResearchProjectController::class, 'edit'])->name('projects.edit');
    Route::post('/cuenta/proyectos/{project}/editar', [ResearchProjectController::class, 'save'])->name('projects.update');
    Route::get('/admin/lineas', [ResearchLineController::class, 'index'])->name('admin.lines');
    Route::get('/admin/lineas/nueva', [ResearchLineController::class, 'create'])->name('admin.lines.create');
    Route::post('/admin/lineas', [ResearchLineController::class, 'save'])->name('admin.lines.store');
    Route::get('/admin/lineas/{line}/editar', [ResearchLineController::class, 'edit'])->name('admin.lines.edit');
    Route::post('/admin/lineas/{line}', [ResearchLineController::class, 'save'])->name('admin.lines.update');
    Route::delete('/admin/lineas/{line}', [ResearchLineController::class, 'destroy'])->name('admin.lines.destroy');
    Route::get('/admin/investigadores/{researcher}/editar', [AccountController::class, 'editResearcher'])->name('admin.researchers.edit');
    Route::post('/admin/investigadores/{researcher}/editar', [AccountController::class, 'updateResearcher'])->middleware('throttle:20,1')->name('admin.researchers.update');
    Route::get('/admin/investigadores/{researcher}/correos', [AccountController::class, 'editPublicEmails'])->name('admin.researchers.emails');
    Route::post('/admin/investigadores/{researcher}/correos', [AccountController::class, 'updatePublicEmails'])->middleware('throttle:20,1')->name('admin.researchers.emails.update');
    Route::get('/cuenta/publicaciones/{publication}/revisar', [AccountController::class, 'review'])->name('account.review');
    Route::post('/admin/usuarios', [AdminController::class, 'createUser'])->name('admin.users');
    Route::post('/admin/usuarios/{user}/acceso', [AdminController::class, 'toggleUser'])->name('admin.user.toggle');
    Route::get('/admin/historial', [AdminController::class, 'history'])->name('admin.history');
});

Route::get('/branding/{kind}', [SettingsController::class, 'logo'])->name('branding.logo');
Route::middleware(['auth', 'account.ready'])->group(function () {
    Route::get('/admin/configuracion', [SettingsController::class, 'edit'])->name('admin.settings');
    Route::post('/admin/configuracion', [SettingsController::class, 'update'])->name('admin.settings.update');
});

Route::post('/admin/publicaciones/{publication}/autoria', [AdminController::class, 'attribution'])->middleware(['auth', 'account.ready'])->name('admin.attribution');

Route::middleware(['auth', 'account.ready'])->group(function () {
    Route::get('/admin/sistema', [SystemSettingsController::class, 'edit'])->name('admin.system');
    Route::post('/admin/sistema', [SystemSettingsController::class, 'update'])->middleware('throttle:10,1')->name('admin.system.update');
    Route::get('/admin/instalacion', [SystemSettingsController::class, 'setup'])->name('admin.setup');
    Route::post('/admin/sistema/probar-correo', [SystemSettingsController::class, 'testMail'])->middleware('throttle:3,1')->name('admin.system.mail');
    Route::post('/admin/sistema/probar-almacenamiento', [SystemSettingsController::class, 'testStorage'])->middleware('throttle:3,1')->name('admin.system.storage');
});
