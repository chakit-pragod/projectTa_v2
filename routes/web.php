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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/', [TaController::class, 'showAnnounces'])->name('home');

// login with google
Route::get('/auth/google/', [GoogleAuthController::class, 'redirect'])->name('google-auth');
Route::get('/auth/google/call-back', [GoogleAuthController::class, 'callbackGoogle']);

// update profile
Route::get('/complete-profile', [ProfileController::class, 'showCompleteProfileForm'])->name('complete.profile');
Route::post('/complete-profile', [ProfileController::class, 'saveCompleteProfile'])->name('save.profile');

//Ta Routes List
Route::middleware(['auth', 'user-access:user'])->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home', [TaController::class, 'showAnnounces'])->name('home');

    // Route to display the request form
    Route::get('/request', [TaController::class, 'request'])->name('layout.ta.request');
    Route::put('/requests/{studentId}', [RequestsController::class, 'update'])->name('requests.update');
    Route::delete('/requests/{studentId}', [RequestsController::class, 'destroy'])->name('requests.destroy');
    Route::post('/request', [TaController::class, 'apply'])->name('ta.apply');

    // Route to display the request status
    Route::get('/statusrequest', [RequestsController::class, 'showTARequests'])->name('layouts.ta.statusRequest');

    // Route to display the disbursement form
    // Route::get('/disbursements', [TaController::class, 'disbursements'])->name('layout.ta.disbursements');
    Route::get('/disbursements', [DisbursementsController::class, 'disbursements'])->name('layo ut.ta.disbursements');
    Route::post('/disbursements', [DisbursementsController::class, 'uploads'])->name('layout.ta.disbursements');
    Route::get('/ta/documents/download/{id}', [DisbursementsController::class, 'downloadDocument'])->name('layout.ta.download-document');

    Route::get('/taSubject', [TaController::class, 'showCourseTas'])->name('ta.showCourseTas');
    Route::get('/course_ta/{id}/class/{classId?}', [TaController::class, 'showSubjectDetail'])->name('course_ta.show');

    Route::get('/attendances', [TaController::class, 'attendances'])->name('layout.ta.attendances');

    // Route to display the teaching data
    Route::get('/teaching/{id?}', action: [TaController::class, 'showTeachingData'])->name('layout.ta.teaching');

    // Route to display the attendance form for the selected teaching session
    Route::get('/attendances/{teaching_id}', [TaController::class, 'showAttendanceForm'])->name('attendances.form');
    // Route to handle attendance form submission
    Route::post('/attendances/{teaching_id}', [TaController::class, 'submitAttendance'])->name('attendances.submit');

    // Route to display profile page and update profile
    Route::get('/ta/profile', [TaController::class, 'edit'])->name('ta.profile');
    Route::put('/ta/profile/update', [TaController::class, 'update'])->name('ta.profile.update');
    
    // For regular attendance
    Route::get('/attendances/{teaching_id}/edit', [TaController::class, 'editAttendance'])->name('attendances.edit');
    Route::put('/attendances/{teaching_id}', [TaController::class, 'updateAttendance'])->name('attendances.update');
    Route::delete('/attendances/{teaching_id}', [TaController::class, 'deleteAttendance'])->name('attendances.delete');
    
    // For extra attendance
    Route::post('/extra-attendance', [TaController::class, 'storeExtraAttendance'])->name('extra-attendance.store');
    Route::get('/extra-attendance/{id}/edit', [TaController::class, 'editExtraAttendance'])->name('extra-attendance.edit');
    Route::put('/extra-attendance/{id}', [TaController::class, 'updateExtraAttendance'])->name('extra-attendance.update');
    Route::delete('/extra-attendance/{id}', [TaController::class, 'deleteExtraAttendance'])->name('extra-attendance.delete');
});

//Admin Routes List
Route::middleware(['auth', 'user-access:admin'])->group(function () {

    Route::get('/admin', [HomeController::class, 'adminHome'])->name('admin.home');
    // Route::get('/admin', [RequestsController::class, 'showCourseTas'])->name('admin.home');
    // Route::get('/statusrequest', [RequestsController::class, 'showCourseTas'])->name('layout.ta.statusRequest');

    Route::resource('announces', AdminController::class);
    // Route::get('/admin/announce', [AdminController::class, 'announce'])->name('layout.admin.announce');
    Route::get('/admin/tausers', [AdminController::class, 'taUsers'])->name('layout.admin.taUsers');
    Route::get('/admin/detailsta', [AdminController::class, 'detailsTa'])->name('layout.admin.detailsTa');
    Route::get('/admin/detailsta/id', [AdminController::class, 'detailsByid'])->name('layout.admin.detailsByid');
    Route::get('/fetchdata', [ApiController::class, 'fetchData']);
    Route::get('/admin/detailsta/{course_id}', [AdminController::class, 'showTaDetails'])->name('layout.admin.detailsTa');
    Route::get('/admin/detailsta/profile/{student_id}', [AdminController::class, 'taDetail'])->name('admin.ta.profile');
    Route::get('/layout/ta/download-document/{id}', [AdminController::class, 'downloadDocument'])
        ->name('layout.ta.download-document');

    Route::get('/ta/export-pdf/{id}', [AdminController::class, 'exportTaDetailPDF'])->name('layout.exports.pdf');

    Route::prefix('admin-ta-requests')->name('admin.ta-requests.')->group(function () {
        Route::get('/', [AdminController::class, 'taRequests'])->name('index');
        Route::get('/{id}', [AdminController::class, 'showTARequest'])->name('show');
        Route::put('/{id}/process', [AdminController::class, 'processTARequest'])->name('process');
    });
});

//Teacher Routes List
Route::middleware(['auth', 'user-access:teacher'])->group(function () {

    Route::get('/teacherreq', [HomeController::class, 'teacherHome'])->name('teacher.home');
    Route::get('/teacherreq', [TeacherController::class, 'showTARequests'])->name('teacher.home');
    Route::post('/teacherreq', [TeacherController::class, 'updateTARequestStatus'])->name('teacher.home');
    Route::get('/subject', [TeacherController::class, 'subjectTeacher'])->name('layout.teacher.subject');
    Route::get('/subject/subjectDetail', [TeacherController::class, 'subjectDetail'])->name('subjectDetail');
    Route::get('/teacher/subjectDetail/{course_id}', [TeacherController::class, 'subjectDetail']);
    Route::get('/subject/subjectDetail/taDetail/{student_id}', [TeacherController::class, 'taDetail'])->name('teacher.taDetail');
    Route::post('/teacher/approve-month/{ta_id}', [TeacherController::class, 'approveMonthlyAttendance'])
        ->name('teacher.approve-month');

    Route::prefix('ta-requests')->name('teacher.ta-requests.')->group(function () {
        // แสดงรายการคำร้องทั้งหมด
        Route::get('/', [TeacherController::class, 'indexTARequests'])
            ->name('index');

        // หน้าสร้างคำร้องใหม่
        Route::get('/create/{course_id}', [TeacherController::class, 'createTARequest'])
            ->name('create');

        // บันทึกคำร้อง
        Route::post('/', [TeacherController::class, 'storeTARequest'])
            ->name('store');

        // แก้ไขคำร้อง
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])
            ->name('edit');

        // อัพเดตคำร้อง
        Route::put('/{id}', [TeacherController::class, 'update'])
            ->name('update');

        // แสดงรายละเอียดคำร้อง - ต้องอยู่ท้ายสุดเพราะใช้ parameter
        Route::get('/{request}', [TeacherController::class, 'showTARequest'])
            ->name('show');
    });
    Route::prefix('ta-requests')->name('teacher.ta-requests.')->group(function () {
        // แสดงรายการคำร้องทั้งหมด
        Route::get('/', [TeacherController::class, 'indexTARequests'])
            ->name('index');

        // หน้าสร้างคำร้องใหม่
        Route::get('/create/{course_id}', [TeacherController::class, 'createTARequest'])
            ->name('create');

        // บันทึกคำร้อง
        Route::post('/', [TeacherController::class, 'storeTARequest'])
            ->name('store');

        // แก้ไขคำร้อง
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])
            ->name('edit');

        // อัพเดตคำร้อง
        Route::put('/{id}', [TeacherController::class, 'update'])
            ->name('update');

        // แสดงรายละเอียดคำร้อง - ต้องอยู่ท้ายสุดเพราะใช้ parameter
        Route::get('/{request}', [TeacherController::class, 'showTARequest'])
            ->name('show');
    });
    Route::prefix('ta-requests')->name('teacher.ta-requests.')->group(function () {
        // แสดงรายการคำร้องทั้งหมด
        Route::get('/', [TeacherController::class, 'indexTARequests'])
            ->name('index');

        // หน้าสร้างคำร้องใหม่
        Route::get('/create/{course_id}', [TeacherController::class, 'createTARequest'])
            ->name('create');

        // บันทึกคำร้อง
        Route::post('/', [TeacherController::class, 'storeTARequest'])
            ->name('store');

        // แก้ไขคำร้อง
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])
            ->name('edit');

        // อัพเดตคำร้อง
        Route::put('/{id}', [TeacherController::class, 'update'])
            ->name('update');

        // แสดงรายละเอียดคำร้อง - ต้องอยู่ท้ายสุดเพราะใช้ parameter
        Route::get('/{request}', [TeacherController::class, 'showTARequest'])
            ->name('show');
    });
});

Route::fallback(function () {
    return view('error\404');
});
