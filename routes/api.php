<?php

use App\Http\Controllers\Api\AdminCatalogController;
use App\Http\Controllers\Api\AdminOperationsController;
use App\Http\Controllers\Api\AdminSummaryController;
use App\Http\Controllers\Api\AgentEventController;
use App\Http\Controllers\Api\AgentOrderController;
use App\Http\Controllers\Api\AgentSummaryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CatalogMasterDataController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\EoAccountController;
use App\Http\Controllers\Api\EoAgentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventTalentController;
use App\Http\Controllers\Api\MeSummaryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganizerAuthController;
use App\Http\Controllers\Api\OrganizerMemberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlesConnectAuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\TalentCategoryController;
use App\Http\Controllers\Api\TalentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Webhook\XenditWebhookController;
use App\Http\Middleware\EnsureCatalogAccess;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Platform auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Passwordless identity for PlesConnect. Organizer/artist capabilities are
// granted separately from authentication and can coexist on one account.
Route::prefix('plesconnect-auth')->group(function () {
    Route::post('/otp/request', [PlesConnectAuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('/otp/verify', [PlesConnectAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::get('/google/redirect', [PlesConnectAuthController::class, 'googleRedirect'])->middleware('throttle:20,1');
    Route::get('/google/callback', [PlesConnectAuthController::class, 'googleCallback'])->middleware('throttle:20,1');
    Route::post('/google/exchange', [PlesConnectAuthController::class, 'googleExchange'])->middleware('throttle:10,1');
});

// Signed-in creator workspace counts
Route::middleware('auth:api')->get('/me/summary', MeSummaryController::class);

// Authenticated user profile
Route::middleware('auth:api')->prefix('profile')->group(function () {
    Route::post('/', [ProfileController::class, 'update']);
    Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
});

// Becoming an event organizer. Only auth:api — the caller is by definition not
// an EO yet. Returns a replacement token carrying the updated claim.
Route::middleware('auth:api')->post('/eo/activate', [EoAccountController::class, 'activate']);

// Organizer member auth (EO_STAFF, GATE_OFFICER, etc.)
Route::prefix('organizer-auth')->group(function () {
    Route::post('/login', [OrganizerAuthController::class, 'login']);

    Route::middleware('auth:organizer')->group(function () {
        Route::get('/me', [OrganizerAuthController::class, 'me']);
        Route::post('/logout', [OrganizerAuthController::class, 'logout']);
    });
});

Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/cities', [CityController::class, 'index']);
Route::get('/banks', [BankController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/payment-methods', [PaymentController::class, 'methods']);

// Categories — Super Admin CRUD
Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN'])->prefix('admin')->group(function () {
    Route::get('/summary', AdminSummaryController::class);
    Route::get('/operations/refunds', [AdminOperationsController::class, 'refunds']);
    Route::get('/operations/webhooks', [AdminOperationsController::class, 'webhooks']);
    Route::get('/categories', [CategoryController::class, 'adminIndex']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// Reading the directory is open to both staff roles, but an ADMIN only ever sees members:
// the controller pins its role filter to REGISTERED_USER and refuses to show a staff account.
Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{uid}', [UserController::class, 'show']);
});

// Every write stays Super Admin only. This is where roles are edited, so an ADMIN with access
// could promote itself and the two roles would stop being distinguishable.
Route::middleware(['auth:api', 'role:SUPER_ADMIN'])->prefix('users')->group(function () {
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{uid}', [UserController::class, 'update']);
    Route::delete('/{uid}', [UserController::class, 'destroy']);
});

// Events — public
Route::get('/events', [EventController::class, 'index']);

// Events — organizer only (must be before /{slug} to avoid conflict)
Route::middleware(['auth:api', 'eo'])->group(function () {
    Route::get('/events/my', [EventController::class, 'myEvents']);
    Route::post('/events', [EventController::class, 'store']);
    Route::post('/events/{id}', [EventController::class, 'update']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::post('/events/{id}/banner', [EventController::class, 'uploadBanner']);
    Route::patch('/events/{id}/toggle', [EventController::class, 'toggleActive']);
});

// Public event by slug (after /my to avoid swallowing it)
Route::get('/events/{slug}', [EventController::class, 'showBySlug']);

// Events — Super Admin
Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN'])->prefix('admin')->group(function () {
    Route::get('/events', [EventController::class, 'adminIndex']);
    Route::get('/events/{id}', [EventController::class, 'adminShow']);
    Route::post('/events/{id}/verify', [EventController::class, 'verify']);
    Route::post('/events/{id}/reject', [EventController::class, 'reject']);
    Route::post('/events/{id}/suspend', [EventController::class, 'suspend']);
});

// Organizer member management — scoped per event, owner only
Route::middleware(['auth:api', 'eo'])->prefix('events/{eventId}/members')->group(function () {
    Route::get('/', [OrganizerMemberController::class, 'index']);
    Route::post('/', [OrganizerMemberController::class, 'store']);
    Route::put('/{memberId}', [OrganizerMemberController::class, 'update']);
    Route::delete('/{memberId}', [OrganizerMemberController::class, 'destroy']);
});

// EO agent sales tracking — scoped per event, owner only
Route::middleware(['auth:api', 'eo'])->prefix('events/{eventId}/agents')->group(function () {
    Route::get('/orders', [EoAgentController::class, 'orders']);
    Route::get('/summary', [EoAgentController::class, 'summary']);
    Route::get('/{agentId}/orders', [EoAgentController::class, 'agentOrders']);
    Route::get('/{agentId}/summary', [EoAgentController::class, 'agentSummary']);
});

// Orders — authenticated buyer
Route::middleware('auth:api')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{orderNumber}', [OrderController::class, 'show']);
    Route::post('/{orderNumber}/pay', [OrderController::class, 'pay']);
    Route::post('/{orderNumber}/cancel', [OrderController::class, 'cancel']);

    // Gateway checkout: create the charge, then poll it while the buyer pays.
    Route::post('/{orderNumber}/payments', [PaymentController::class, 'store']);
    Route::get('/{orderNumber}/payments', [PaymentController::class, 'show']);
});

// Payment gateway callbacks — no auth: verified by provider callback token.
Route::post('/webhooks/xendit', XenditWebhookController::class);

// Talent categories — public master data backing the talent category field
Route::get('/talent-categories', [TalentCategoryController::class, 'index']);

// Talents — public directory
Route::get('/talents', [TalentController::class, 'index']);

// Must be declared before /talents/{id} — Laravel matches in registration
// order, so otherwise "mine" is swallowed as an id and TalentController::show()
// throws a TypeError on its int $id parameter.
Route::get('/talents/mine', [TalentController::class, 'mine'])
    ->middleware(['auth:api', 'eo']);

Route::get('/talents/{id}', [TalentController::class, 'show']);

// Talents — authenticated EO can submit/update/delete own
Route::middleware(['auth:api', 'eo'])->group(function () {
    Route::post('/talents', [TalentController::class, 'store']);
    Route::put('/talents/{id}', [TalentController::class, 'update']);
    Route::delete('/talents/{id}', [TalentController::class, 'destroy']);
});

// Talents — Super Admin management
Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN'])->prefix('admin')->group(function () {
    Route::get('/talents', [TalentController::class, 'adminIndex']);
    Route::post('/talents/{id}/verify', [TalentController::class, 'verify']);
    Route::get('/talent-categories', [TalentCategoryController::class, 'adminIndex']);
    Route::post('/talent-categories', [TalentCategoryController::class, 'store']);
    Route::patch('/talent-categories/{code}', [TalentCategoryController::class, 'update'])->where('code', '[a-z0-9_-]+');
});

// Event lineup (talents per event) — authenticated EO manages, public can read
Route::get('/events/{eventId}/talents', [EventTalentController::class, 'index']);
Route::middleware(['auth:api', 'eo'])->prefix('events/{eventId}/talents')->group(function () {
    Route::post('/', [EventTalentController::class, 'store']);
    Route::put('/{id}', [EventTalentController::class, 'update']);
    Route::delete('/{id}', [EventTalentController::class, 'destroy']);
});

// Tickets — lookup open to any auth (buyer or organizer), scan organizer only
Route::get('/tickets/{code}', [TicketController::class, 'show'])->middleware('auth:api');
Route::post('/tickets/{code}/scan', [TicketController::class, 'scan'])->middleware('auth:organizer');

// Agent (Mitra Ticket Box) portal
Route::middleware(['auth:organizer', 'role:MITRA_TICKET_BOX'])->prefix('agent')->group(function () {
    Route::get('/event', [AgentEventController::class, 'show']);
    Route::get('/event/ticket-types', [AgentEventController::class, 'ticketTypes']);
    Route::get('/orders', [AgentOrderController::class, 'index']);
    Route::post('/orders', [AgentOrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [AgentOrderController::class, 'show']);
    Route::get('/summary', AgentSummaryController::class);
});

// Catalog identity is independent of event organizer capabilities.
Route::middleware(['auth:api', EnsureCatalogAccess::class])->prefix('catalog')->group(function () {
    Route::get('/options', [CatalogController::class, 'options']);
    Route::get('/{masterType}', [CatalogMasterDataController::class, 'index'])->where('masterType', 'genres|languages|territories|timezones|dsps');
    Route::get('/releases', [CatalogController::class, 'index']);
    Route::post('/releases', [CatalogController::class, 'store'])->middleware('throttle:catalog-create');
    Route::prefix('releases/{release}')->whereUuid('release')->group(function () {
        Route::get('/', [CatalogController::class, 'show']);
        Route::patch('/', [CatalogController::class, 'update']);
        Route::delete('/', [CatalogController::class, 'destroy']);
        Route::post('/assets', [CatalogController::class, 'upload'])->middleware('throttle:catalog-upload');
        Route::get('/assets/{asset}', [CatalogController::class, 'download'])->whereUuid('asset');
        Route::delete('/assets/{asset}', [CatalogController::class, 'removeAsset'])->whereUuid('asset');
        Route::post('/submit', [CatalogController::class, 'submit'])->middleware('throttle:catalog-submit');
        Route::post('/change-requests', [CatalogController::class, 'requestChange'])->middleware('throttle:catalog-change-request');
        Route::get('/submissions/{version}', [CatalogController::class, 'submission'])->whereNumber('version');
    });
});

Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN', EnsureCatalogAccess::class.':admin'])->prefix('admin/catalog/releases')->group(function () {
    Route::get('/', [AdminCatalogController::class, 'index']);
    Route::prefix('{release}')->whereUuid('release')->group(function () {
        Route::get('/', [AdminCatalogController::class, 'show']);
        Route::post('/status', [AdminCatalogController::class, 'transition']);
        Route::patch('/assignment', [AdminCatalogController::class, 'assign']);
        Route::post('/notes', [AdminCatalogController::class, 'note']);
        Route::put('/distribution', [AdminCatalogController::class, 'distribution']);
        Route::put('/stores/{store}', [AdminCatalogController::class, 'delivery']);
        Route::get('/assets/{asset}', [AdminCatalogController::class, 'download'])->whereUuid('asset');
        Route::get('/submissions/{version}', [AdminCatalogController::class, 'submission'])->whereNumber('version');
        Route::get('/submissions/{version}/export', [AdminCatalogController::class, 'export'])->whereNumber('version')->middleware('throttle:catalog-export');
    });
});

Route::middleware(['auth:api', 'role:SUPER_ADMIN,ADMIN', EnsureCatalogAccess::class.':admin'])->prefix('admin/catalog')->group(function () {
    Route::get('/{masterType}', [CatalogMasterDataController::class, 'adminIndex'])->where('masterType', 'genres|languages|territories|timezones|dsps');
    Route::post('/dsps/{code}/logo', [CatalogMasterDataController::class, 'uploadDspLogo'])->where('code', '[a-z0-9_-]+');
    Route::post('/{masterType}', [CatalogMasterDataController::class, 'store'])->where('masterType', 'genres|languages|territories|timezones|dsps');
    Route::patch('/{masterType}/{code}', [CatalogMasterDataController::class, 'update'])->where('masterType', 'genres|languages|territories|timezones|dsps')->where('code', '.+');
});
