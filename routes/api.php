<?php

use App\Http\Controllers\Api\AgentEventController;
use App\Http\Controllers\Api\AgentOrderController;
use App\Http\Controllers\Api\AgentSummaryController;
use App\Http\Controllers\Api\EoAgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventTalentController;
use App\Http\Controllers\Api\TalentController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Webhook\XenditWebhookController;
use App\Http\Controllers\Api\OrganizerAuthController;
use App\Http\Controllers\Api\OrganizerMemberController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['status' => 'ok']));

// Platform auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Authenticated user profile
Route::middleware('auth:api')->prefix('profile')->group(function () {
    Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
});

// Organizer member auth (EO_STAFF, GATE_OFFICER, etc.)
Route::prefix('organizer-auth')->group(function () {
    Route::post('/login', [OrganizerAuthController::class, 'login']);

    Route::middleware('auth:organizer')->group(function () {
        Route::get('/me',      [OrganizerAuthController::class, 'me']);
        Route::post('/logout', [OrganizerAuthController::class, 'logout']);
    });
});

Route::get('/provinces',  [ProvinceController::class, 'index']);
Route::get('/cities',     [CityController::class, 'index']);
Route::get('/banks',      [BankController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/payment-methods', [PaymentController::class, 'methods']);

// Categories — Super Admin CRUD
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->prefix('admin')->group(function () {
    Route::get('/categories',       [CategoryController::class, 'adminIndex']);
    Route::post('/categories',      [CategoryController::class, 'store']);
    Route::put('/categories/{id}',  [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});


// User management — Super Admin only
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->prefix('users')->group(function () {
    Route::get('/',         [UserController::class, 'index']);
    Route::get('/{uid}',    [UserController::class, 'show']);
    Route::put('/{uid}',    [UserController::class, 'update']);
    Route::delete('/{uid}', [UserController::class, 'destroy']);
});

// Events — public
Route::get('/events', [EventController::class, 'index']);

// Events — authenticated user (must be before /{slug} to avoid conflict)
Route::middleware('auth:api')->group(function () {
    Route::get('/events/my',       [EventController::class, 'myEvents']);
    Route::post('/events',         [EventController::class, 'store']);
    Route::post('/events/{id}',          [EventController::class, 'update']);
    Route::put('/events/{id}',           [EventController::class, 'update']);
    Route::post('/events/{id}/banner',   [EventController::class, 'uploadBanner']);
    Route::patch('/events/{id}/toggle',  [EventController::class, 'toggleActive']);
});

// Public event by slug (after /my to avoid swallowing it)
Route::get('/events/{slug}', [EventController::class, 'showBySlug']);

// Events — Super Admin
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->prefix('admin')->group(function () {
    Route::get('/events',                    [EventController::class, 'adminIndex']);
    Route::get('/events/{id}',               [EventController::class, 'adminShow']);
    Route::post('/events/{id}/verify',       [EventController::class, 'verify']);
    Route::post('/events/{id}/reject',       [EventController::class, 'reject']);
    Route::post('/events/{id}/suspend',      [EventController::class, 'suspend']);
});

// Organizer member management — scoped per event, owner only
Route::middleware('auth:api')->prefix('events/{eventId}/members')->group(function () {
    Route::get('/',              [OrganizerMemberController::class, 'index']);
    Route::post('/',             [OrganizerMemberController::class, 'store']);
    Route::put('/{memberId}',    [OrganizerMemberController::class, 'update']);
    Route::delete('/{memberId}', [OrganizerMemberController::class, 'destroy']);
});

// EO agent sales tracking — scoped per event, owner only
Route::middleware('auth:api')->prefix('events/{eventId}/agents')->group(function () {
    Route::get('/orders',                    [EoAgentController::class, 'orders']);
    Route::get('/summary',                   [EoAgentController::class, 'summary']);
    Route::get('/{agentId}/orders',          [EoAgentController::class, 'agentOrders']);
    Route::get('/{agentId}/summary',         [EoAgentController::class, 'agentSummary']);
});

// Orders — authenticated buyer
Route::middleware('auth:api')->prefix('orders')->group(function () {
    Route::get('/',                       [OrderController::class, 'index']);
    Route::post('/',                      [OrderController::class, 'store']);
    Route::get('/{orderNumber}',          [OrderController::class, 'show']);
    Route::post('/{orderNumber}/pay',     [OrderController::class, 'pay']);
    Route::post('/{orderNumber}/cancel',  [OrderController::class, 'cancel']);

    // Gateway checkout: create the charge, then poll it while the buyer pays.
    Route::post('/{orderNumber}/payments', [PaymentController::class, 'store']);
    Route::get('/{orderNumber}/payments',  [PaymentController::class, 'show']);
});

// Payment gateway callbacks — no auth: verified by provider callback token.
Route::post('/webhooks/xendit', XenditWebhookController::class);

// Talents — public directory
Route::get('/talents', [TalentController::class, 'index']);
Route::get('/talents/{id}', [TalentController::class, 'show']);

// Talents — authenticated EO can submit/update/delete own
Route::middleware('auth:api')->group(function () {
    Route::get('/talents/mine', [TalentController::class, 'mine']);
    Route::post('/talents', [TalentController::class, 'store']);
    Route::put('/talents/{id}', [TalentController::class, 'update']);
    Route::delete('/talents/{id}', [TalentController::class, 'destroy']);
});

// Talents — Super Admin management
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->prefix('admin')->group(function () {
    Route::get('/talents', [TalentController::class, 'adminIndex']);
    Route::post('/talents/{id}/verify', [TalentController::class, 'verify']);
});

// Event lineup (talents per event) — authenticated EO manages, public can read
Route::get('/events/{eventId}/talents', [EventTalentController::class, 'index']);
Route::middleware('auth:api')->prefix('events/{eventId}/talents')->group(function () {
    Route::post('/', [EventTalentController::class, 'store']);
    Route::put('/{id}', [EventTalentController::class, 'update']);
    Route::delete('/{id}', [EventTalentController::class, 'destroy']);
});

// Tickets — lookup open to any auth (buyer or organizer), scan organizer only
Route::get('/tickets/{code}',       [TicketController::class, 'show'])->middleware('auth:api');
Route::post('/tickets/{code}/scan', [TicketController::class, 'scan'])->middleware('auth:organizer');

// Agent (Mitra Ticket Box) portal
Route::middleware(['auth:organizer', 'role:MITRA_TICKET_BOX'])->prefix('agent')->group(function () {
    Route::get('/event',              [AgentEventController::class, 'show']);
    Route::get('/event/ticket-types', [AgentEventController::class, 'ticketTypes']);
    Route::get('/orders',             [AgentOrderController::class, 'index']);
    Route::post('/orders',            [AgentOrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [AgentOrderController::class, 'show']);
    Route::get('/summary',            AgentSummaryController::class);
});
