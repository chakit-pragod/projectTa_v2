<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TaController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\RequestsController;
use App\Http\Controllers\DisbursementsController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\CompensationRateController;
use App\Http\Controllers\CourseBudgetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', [TaController::class, 'showAnnounces'])->name('home');

// login with google
Route::get('/auth/google/', [GoogleAuthController::class, 'redirect'])->name('google-auth');
Route::get('/auth/google/call-back', [GoogleAuthController::class, 'callbackGoogle']);

// Route to display the input form for the account
Route::get('/complete-profile', [ProfileController::class, 'showCompleteProfileForm'])->name('complete.profile');
Route::post('/complete-profile', [ProfileController::class, 'saveCompleteProfile'])->name('save.profile');

//Ta Routes List
Route::middleware(['auth', 'user-access:user'])->group(function () {
    // Route to display the home page
    Route::get('/home', [TaController::class, 'showAnnounces'])->name('home');

    // Route to display the request form
    Route::get('/request', [TaController::class, 'request'])->name('layout.ta.request');
    Route::put('/requests/{studentId}', [RequestsController::class, 'update'])->name('requests.update');
    Route::delete('/requests/{studentId}', [RequestsController::class, 'destroy'])->name('requests.destroy');
    Route::post('/request', [TaController::class, 'apply'])->name('ta.apply');

    // Route to display the request status
    Route::get('/statusrequest', [RequestsController::class, 'showTARequests'])->name('layouts.ta.statusRequest');

    // Route to display the disbursement form
    Route::get('/disbursements', [DisbursementsController::class, 'disbursements'])->name('layout.ta.disbursements');
    Route::post('/disbursements', [DisbursementsController::class, 'uploads'])->name('layout.ta.disbursements');
    Route::get('/ta/documents/download/{id}', [DisbursementsController::class, 'downloadDocument'])->name('layout.ta.download-document');

    Route::get('/taSubject', [TaController::class, 'showCourseTas'])->name('ta.showCourseTas');
    Route::get('/course_ta/{id}/class/{classId?}', [TaController::class, 'showSubjectDetail'])->name('course_ta.show');

    Route::get('/attendances', [TaController::class, 'attendances'])->name('layout.ta.attendances');

    // Route to display the teaching data
    Route::get('/teaching/{id?}', action: [TaController::class, 'showTeachingData'])->name('layout.ta.teaching');

    // For refresh teaching data
    Route::get('/class/{id}/refresh-teachings', [TaController::class, 'refreshTeachings'])
        ->name('class.refresh-teachings');

    // Route to display the attendance form for the selected teaching session
    Route::get('/attendances/form/{teaching_id}', [TaController::class, 'showAttendanceForm'])
        ->name('attendances.form');
    // Route to handle attendance form submission
    Route::post('/attendances/{teaching_id}', [TaController::class, 'submitAttendance'])->name('attendances.submit');

    // Route to display profile page edit and update profile
    Route::get('/ta/profile', [TaController::class, 'edit'])->name('ta.profile');
    Route::put('/ta/profile/update', [TaController::class, 'update'])->name('ta.profile.update');

    // For regular attendance
    Route::get('/attendances/{teaching_id}/edit', [TaController::class, 'editAttendance'])->name('attendances.edit');
    Route::put('/attendances/{teaching_id}', [TaController::class, 'updateAttendance'])->name('attendances.update');
    Route::delete('/attendances/{teaching_id}', [TaController::class, 'deleteAttendance'])->name('attendances.delete');
    Route::put('/extra-teaching/{teaching_id}/attendance', [TaController::class, 'updateExtraTeachingAttendance'])->name('extra-teaching.attendance.update');
    Route::delete('/attendances/extra/{teaching_id}', [TaController::class, 'deleteExtraTeachingAttendance'])->name('attendances.delete.extra');

    // For extra attendance
    Route::post('/extra-attendance', [TaController::class, 'storeExtraAttendance'])->name('extra-attendance.store');
    Route::get('/extra-attendance/{id}/edit', [TaController::class, 'editExtraAttendance'])->name('extra-attendance.edit');
    Route::put('/extra-attendance/{id}', [TaController::class, 'updateExtraAttendance'])->name('extra-attendance.update');
    Route::delete('/extra-attendance/{id}', [TaController::class, 'deleteExtraAttendance'])->name('extra-attendance.delete');
});

//Admin Routes List
Route::middleware(['auth', 'user-access:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'adminHome'])->name('admin.home');
    Route::resource('announces', AdminController::class);
    Route::get('/admin/tausers', [AdminController::class, 'taUsers'])->name('layout.admin.taUsers');
    Route::get('/admin/detailsta', [AdminController::class, 'detailsTa'])->name('layout.admin.detailsTa');
    Route::get('/admin/detailsta/id', [AdminController::class, 'detailsByid'])->name('layout.admin.detailsByid');
    Route::get('/admin/detailsta/{course_id}', [AdminController::class, 'showTaDetails'])->name('layout.admin.detailsTa');
    Route::get('/admin/detailsta/profile/{student_id}', action: [AdminController::class, 'taDetail'])->name('admin.ta.profile');
    Route::get('/layout/ta/download-document/{id}', [AdminController::class, 'downloadDocument'])->name('layout.ta.download-document');
    Route::get('/ta/export-pdf/{id}', [AdminController::class, 'exportTaDetailPDF'])->name('layout.exports.pdf');
    Route::get('/exports/result-pdf/{id}', [AdminController::class, 'exportResultPDF'])->name('layout.exports.result-pdf');
    Route::get('/admin/ta/export-template/{id}', [App\Http\Controllers\AdminController::class, 'exportFromTemplate'])->name('admin.export.template');

    Route::prefix('admin-ta-requests')->name('admin.ta-requests.')->group(function () {
        Route::get('/', [AdminController::class, 'taRequests'])->name('index');
        Route::get('/{id}', [AdminController::class, 'showTARequest'])->name('show');
        Route::put('/{id}/process', [AdminController::class, 'processTARequest'])->name('process');
    });

    Route::prefix('admin/compensation-rates')->name('admin.compensation-rates.')->group(function () {
        Route::get('/', [CompensationRateController::class, 'index'])->name('index');
        Route::get('/create', [CompensationRateController::class, 'create'])->name('create');
        Route::post('/', [CompensationRateController::class, 'store'])->name('store');
        Route::get('/{rate}/edit', [CompensationRateController::class, 'edit'])->name('edit');
        Route::put('/{rate}', [CompensationRateController::class, 'update'])->name('update');
    });

    // check list
    Route::post('/admin/course-budgets/compensation/save', [CourseBudgetController::class, 'saveCompensation'])
        ->name('admin.course-budgets.compensation.save');

    Route::post('/admin/update-user-semester', [AdminController::class, 'updateUserSemester'])->name('layout.admin.updateUserSemester');

    // Route for sync all data from api into database
    Route::get('/admin/sync-all-data', [App\Http\Controllers\AdminController::class, 'syncAllData'])->name('admin.sync-all-data');

    // Route for change password
    Route::get('/admin/change-password', [AdminController::class, 'showChangePasswordForm'])->name('admin.change-password');
    Route::post('/admin/change-password', [AdminController::class, 'changePassword'])->name('admin.update-password');
});

//Teacher Routes List
Route::middleware(['auth', 'user-access:teacher'])->group(function () {

    Route::get('/teacherreq', [TeacherController::class, 'showTARequests'])->name('teacher.home');
    Route::post('/teacherreq', [TeacherController::class, 'updateTARequestStatus'])->name('teacher.home');
    Route::get('/subject', [TeacherController::class, 'subjectTeacher'])->name('layout.teacher.subject');
    Route::get('/subject/subjectDetail', [TeacherController::class, 'subjectDetail'])->name('subjectDetail');
    Route::get('/teacher/subjectDetail/{course_id}', [TeacherController::class, 'subjectDetail']);
    Route::get('/subject/subjectDetail/taDetail/{student_id}', [TeacherController::class, 'taDetail'])->name('teacher.taDetail');
    Route::post('/teacher/approve-month/{ta_id}', [TeacherController::class, 'approveMonthlyAttendance'])->name('teacher.approve-month');

    Route::prefix('ta-requests')->name('teacher.ta-requests.')->group(function () {
        Route::get('/', [TeacherController::class, 'indexTARequests'])->name('index');
        Route::get('/create/{course_id}', [TeacherController::class, 'createTARequest'])->name('create');
        Route::post('/', [TeacherController::class, 'storeTARequest'])->name('store');
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])->name('edit');
        Route::put('/{id}', [TeacherController::class, 'update'])->name('update');
        Route::get('/{request}', [TeacherController::class, 'showTARequest'])->name('show');
    });

    // Route for chacge password
    Route::get('/teacher/change-password', [TeacherController::class, 'showChangePasswordForm'])->name('teacher.change-password');
    Route::post('/teacher/change-password', [TeacherController::class, 'changePassword'])->name('teacher.update-password');
});

Route::fallback(function () {
    return view('error\404');
});
