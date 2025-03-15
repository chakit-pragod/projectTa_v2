<?php

namespace App\Http\Controllers;

use App\Models\CompensationRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompensationRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $rates = CompensationRate::orderBy('teaching_type')
            ->orderBy('class_type')
            ->get();

        return view('layouts.admin.compensation-rates.index', compact('rates'));
    }

    public function create()
    {
        return view('layouts.admin.compensation-rates.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'teaching_type' => 'required|in:regular,special',
                'class_type' => 'required|in:LECTURE,LAB',
                'degree_level' => 'required|in:undergraduate,graduate',
                'rate_per_hour' => 'nullable|numeric|min:0',
                'is_fixed_payment' => 'sometimes|boolean',
                'fixed_amount' => 'required_if:is_fixed_payment,1|nullable|numeric|min:0',
                'status' => 'required|in:active,inactive'
            ]);

            $data = $request->all();

            // ตรวจสอบว่าเป็นการเหมาจ่ายหรือไม่
            $data['is_fixed_payment'] = $request->has('is_fixed_payment');

            if ($request->has('is_fixed_payment')) {
                $data['is_fixed_payment'] = true;
                $data['fixed_amount'] = $request->fixed_amount;
                $data['rate_per_hour'] = null; 
            } else {
                $data['is_fixed_payment'] = false;
                $data['fixed_amount'] = null;
                $data['rate_per_hour'] = $request->rate_per_hour;
            }
            CompensationRate::create($data);

            return redirect()->route('admin.compensation-rates.index')
                ->with('success', 'อัตราค่าตอบแทนถูกเพิ่มเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            Log::error('Error creating compensation rate: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการเพิ่มอัตราค่าตอบแทน: ' . $e->getMessage());
        }
    }
    public function edit(CompensationRate $rate)
    {
        return view('layouts.admin.compensation-rates.edit', compact('rate'));
    }

    public function update(Request $request, CompensationRate $rate)
    {
        try {
            $request->validate([
                'rate_per_hour' => 'required_without:is_fixed_payment|nullable|numeric|min:0',
                'is_fixed_payment' => 'sometimes|boolean',
                'fixed_amount' => 'required_if:is_fixed_payment,1|nullable|numeric|min:0',
                'status' => 'required|in:active,inactive'
            ]);

            $data = [
                'status' => $request->status,
                'is_fixed_payment' => $request->has('is_fixed_payment')
            ];

            if ($request->has('is_fixed_payment')) {
                $data['fixed_amount'] = $request->fixed_amount;
                $data['rate_per_hour'] = 0;
            } else {
                $data['rate_per_hour'] = $request->rate_per_hour;
                $data['fixed_amount'] = null;
            }

            $rate->update($data);

            return redirect()->route('admin.compensation-rates.index')
                ->with('success', 'อัตราค่าตอบแทนถูกอัปเดตเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            Log::error('Error updating compensation rate: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการอัปเดตอัตราค่าตอบแทน: ' . $e->getMessage());
        }
    }

    
}