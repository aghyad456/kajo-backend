<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JoinRequestController;
use App\Http\Controllers\Api\AdminJoinRequestController;
use App\Http\Controllers\Api\AdminUsersController;
use App\Http\Controllers\Api\AdminStatsController;
use App\Http\Controllers\Api\FeedPostController;
use App\Http\Controllers\Api\PostCommentController;
use App\Http\Controllers\Api\AdminModerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\Api\DoctorSlotsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MyActivitiesController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ShelterController;
use App\Http\Controllers\Api\SavedPostController;

Route::get('/health', function () {
    return response()->json(['ok' => true]);
});

// -------------------- AUTH (Public) --------------------
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// -------------------- PUBLIC CLINICS --------------------
Route::get('/clinics', [ClinicController::class, 'index']);
Route::get('/clinics/{clinic}', [ClinicController::class, 'show']);
Route::get('/clinics/{clinic}/slots', [BookingController::class, 'clinicSlotsPublic']);

// -------------------- PUBLIC STORES --------------------
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{store}', [StoreController::class, 'show']);
Route::get('/stores/{store}/products', [StoreController::class, 'publicProducts']);

// -------------------- PUBLIC SHELTERS --------------------
Route::get('/shelters', [ShelterController::class, 'index']);
Route::get('/shelters/{shelter}', [ShelterController::class, 'show']);
Route::get('/shelters/{shelter}/animals', [ShelterController::class, 'publicAnimals']);
Route::get('/shelters/{shelter}/donation-requests', [ShelterController::class, 'publicDonationRequests']);

// ==================== AUTHENTICATED ====================
Route::middleware('auth:sanctum')->group(function () {

    // -------- Auth --------
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // -------- Join Requests --------
    Route::get('/join-request/me', [JoinRequestController::class, 'me']);
    Route::get('/join-requests', [JoinRequestController::class, 'index']);

    // -------- Profile --------
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // -------- My Activities --------
    Route::get('/my/activities', [MyActivitiesController::class, 'index']);

    // -------- Clinic Setup --------
    Route::post('/clinic/setup', [ClinicController::class, 'setup']);

    // -------- Store Setup / Dashboard / Products / Orders --------
    Route::post('/store/setup', [StoreController::class, 'setup']);
    Route::get('/store/dashboard', [StoreController::class, 'dashboard']);
    Route::get('/store/products', [StoreController::class, 'products']);
    Route::post('/store/products', [StoreController::class, 'storeProduct']);
    Route::delete('/store/products/{id}', [StoreController::class, 'deleteProduct']);
    Route::post('/store/products/{product}/order', [StoreController::class, 'createOrder']);
    Route::get('/store/orders', [StoreController::class, 'orders']);
    Route::patch('/store/orders/{id}', [StoreController::class, 'updateOrderStatus']);

   // -------- Shelter Setup / Dashboard / Animals / Donations / Adoptions --------
    Route::post('/shelter/setup', [ShelterController::class, 'setup']);
    Route::get('/shelter/dashboard', [ShelterController::class, 'dashboard']);

    Route::get('/shelter/animals', [ShelterController::class, 'animals']);
    Route::post('/shelter/animals', [ShelterController::class, 'storeAnimal']);

    Route::get('/shelter/donation-requests', [ShelterController::class, 'donationRequests']);
    Route::post('/shelter/donation-requests', [ShelterController::class, 'storeDonationRequest']);
    Route::delete('/shelter/donation-requests/{id}', [ShelterController::class, 'deleteDonationRequest']);

    Route::post('/shelter/donation-requests/{id}/donate', [ShelterController::class, 'createDonationSubmission']);
    Route::get('/shelter/donation-submissions', [ShelterController::class, 'donationSubmissions']);
    Route::patch('/shelter/donation-submissions/{id}', [ShelterController::class, 'updateDonationSubmissionStatus']);

    Route::post('/shelter/animals/{animal}/adopt', [ShelterController::class, 'createAdoptionRequest']);
    Route::get('/shelter/adoption-requests', [ShelterController::class, 'adoptionRequests']);
    Route::patch('/shelter/adoption-requests/{id}', [ShelterController::class, 'updateAdoptionRequestStatus']);

    // -------- Feed --------
    Route::get('/feed/posts', [FeedPostController::class, 'index']);
    Route::post('/feed/posts', [FeedPostController::class, 'store']);
    Route::get('/feed/posts/{post}', [FeedPostController::class, 'show']);
    Route::post('/feed/posts/{post}/like', [FeedPostController::class, 'toggleLike']);

    Route::get('/feed/posts/{post}/comments', [PostCommentController::class, 'index']);
    Route::post('/feed/posts/{post}/comments', [PostCommentController::class, 'store']);

    // -------- Doctor Dashboard --------
    Route::get('/doctor/dashboard', [DoctorController::class, 'dashboard']);

    // -------- Doctor Slots --------
    Route::get('/doctor/slots', [DoctorSlotsController::class, 'index']);
    Route::post('/doctor/slots', [DoctorSlotsController::class, 'store']);
    Route::delete('/doctor/slots/{id}', [DoctorSlotsController::class, 'destroy']);

    // -------- Booking --------
    Route::post('/bookings', [BookingController::class, 'book']);

    // -------- Doctor Appointments --------
    Route::get('/doctor/appointments', [DoctorController::class, 'appointments']);
    Route::patch('/doctor/appointments/{id}', [DoctorController::class, 'updateAppointmentStatus']);

    // ==================== ADMIN ====================
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/ping', function () {
            return response()->json(['ok' => true, 'admin' => true]);
        });

        Route::get('/join-requests', [AdminJoinRequestController::class, 'index']);
        Route::post('/join-requests/{joinRequest}/approve', [AdminJoinRequestController::class, 'approve']);
        Route::post('/join-requests/{joinRequest}/reject', [AdminJoinRequestController::class, 'reject']);

        Route::get('/users', [AdminUsersController::class, 'index']);
        Route::delete('/users/{user}', [AdminUsersController::class, 'destroy']);
        Route::patch('/users/{user}/role', [AdminUsersController::class, 'updateRole']);

        Route::get('/stats', [AdminStatsController::class, 'index']);

        Route::delete('/posts/{post}', [AdminModerationController::class, 'deletePost']);
        Route::delete('/comments/{comment}', [AdminModerationController::class, 'deleteComment']);
    });

        // -------- Saved Posts --------
    Route::get('/saved-posts', [SavedPostController::class, 'index']);
    Route::post('/feed/posts/{post}/save', [SavedPostController::class, 'toggle']);
});