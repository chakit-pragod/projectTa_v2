<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Announce;

class AnnouncesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $announces = [
            [
                'title' => 'กำหนดการส่งหลักฐานการปฏิบัติงาน',
                'description' => 'ขอแจ้งแก้ไขกำหนดการ ส่งหลักฐานการปฏิบัติงาน ภาคต้น ปีการศึกษา 2567 ดังนี้ค่ะ 1) เดือนมิถุนายน ส่งภายใน 8 กรกฎาคม 2567 2) เดือนกรกฎาคม - สิงหาคม ส่งภายใน 31 กรกฎาคม 2567 3) เดือนกันยายน ส่งภายใน 5 ตุลาคม 2567 4) เดือนตุลาคม ส่งภายใน 5 พฤศจิกายน 2567 ขอความอนุเคราะห์ให้นักศึกษส่งหลักฐานการปฏิบัติงานก่อนล่วงหน้า 1 เดือน (เดือนสิงหาคม) โดยลงข้อมูลของเดือนสิงหาคมมาล่วงหน้าเลยค่ะ ว่าอาจารย์สอนวันไหน สอนชดเชยวันไหนบ้าง และตรวจงานวันไหน เพราะจนท. จะได้มีเวลาดึงข้อมูล ตรวจเช็คความถูกต้องและส่งเอกสารเบิกจ่าย TA ของทั้ง 3 เดือน ให้เจ้าหน้าที่การเงิน ภายในวันที่ 31 สิงหาคมค่ะ',
            ],
            [
                'title' => 'ไดรฟ์สำหรับอัพโหลดไฟล์หลักฐานการปฏิบัติงาน',
                'description' => 'https://drive.google.com/drive/folders/1s0aPVrxpSTaG1aCSEF0Vcioi23E8y61q',
            ],
            [
                'title' => 'ส่งหลักฐานการปฏิบัติงาน',
                'description' => 'ประกาศแจ้ง นักศึกษาช่วยปฏิบัติงาน (TA) ขอให้ส่งหลักฐานการปฏิบัติงานเดือน ก.ค. และเดือน ส.ค. ภายในวันที่ 31 ก.ค. 2567 ด้วยนะคะ ***ขอความอนุเคราะห์ให้นักศึกษาส่งหลักฐานการปฏิบัติงานก่อนล่วงหน้า 1 เดือน (เดือนสิงหาคม) โดยลงข้อมูลของเดือนสิงหาคมมาล่วงหน้าเลยค่ะ ว่าอาจารย์สอนวันไหน สอนชดเชยวันไหนบ้าง และตรวจงานวันไหน เพราะจนท. จะได้มีเวลาดึงข้อมูล ตรวจเช็คความถูกต้องและส่งเอกสารเบิกจ่าย TA ของทั้ง 3 เดือน ให้เจ้าหน้าที่การเงิน ภายในวันที่ 31 สิงหาคมค่ะ',
            ],
        ];

        foreach ($announces as $key => $value) {
            Announce::create($value);
        }
    }
}
