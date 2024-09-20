<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class ProfileController extends Controller
{
    public function showCompleteProfileForm()
    {
        $user = Auth::user();
        return view('auth.complete', compact('user'));
    }

    public function saveCompleteProfile(Request $request)
    {
        // Validation สำหรับฟิลด์เพิ่มเติม
        $request->validate([
            'prefix' => 'nullable|string|max:256',
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'student_id' => 'nullable|string|max:11',
            'card_id' => 'nullable|string|max:13',
            'phone' => 'nullable|string|max:11',
            'password' => 'nullable|string|min:8|confirmed', // กำหนด validation สำหรับรหัสผ่าน
        ]);

        // ดึงข้อมูล user ที่ล็อกอิน
        $user = Auth::user();

        // อัปเดตข้อมูล
        $user->update([
            'prefix' => $request->input('prefix'),
            'fname' => $request->input('fname'),
            'lname' => $request->input('lname'),
            'student_id' => $request->input('student_id'),
            'card_id' => $request->input('card_id'),
            'phone' => $request->input('phone'),
        ]);

        // ตรวจสอบว่ามีการกรอกรหัสผ่านใหม่หรือไม่ และอัปเดตรหัสผ่าน
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password')); // เข้ารหัสรหัสผ่านก่อนบันทึก
            $user->save(); // ต้องเรียก save เพื่อบันทึกการเปลี่ยนแปลงรหัสผ่าน
        }

        // ส่งกลับหน้า home พร้อมกับข้อความสำเร็จ
        return redirect()->intended('home')->with('success', 'Profile updated successfully.');
    }
}
