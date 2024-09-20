<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subjects;
use App\Models\Curriculums;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SubjectsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $curriculums = Curriculums::first();

        // if ($curriculums) {
            $subjects = [
                // CS
                [
                    'subject_id' => 'CP352001',
                    'name_th' => 'โครงสร้างข้อมูล',
                    'name_en' => 'Data Structure',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'พื้นฐานของขั้นตอนวิธี โครงสร้างข้อมูลแบบเชิงเส้น แถวลำดับ สแต๊ค คิว ลิงก์ลิสต์ทางเดียว ลิงก์ลิสต์วงกลม ลิงก์ลิสต์สองทาง โครงสร้างข้อมูลไม่เชิงเส้น โครงสร้างรูปต้นไม้และกราฟ เทคนิคการเรียงลำดับและการค้นหาข้อมูล',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP352002',
                    'name_th' => 'การออกแบบประสบการณ์ผู้ใช้',
                    'name_en' => 'User Experience Design',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'หลักการและแนวคิดการโต้ตอบระหว่างคอมพิวเตอร์กับผู้ใช้งานขั้นพื้นฐาน ความสาคัญของการออกแบบประสบการณ์ผู้ใช้ ทฤษฎีพื้นฐานของการออกแบบระบบการติดต่อโต้ตอบ วงจรการออกแบบประสบการณ์ผู้ใช้ ปัจจัยที่ส่งผลต่อประสบการณ์ผู้ใช้ ประเด็นที่เกี่ยวกับการออกแบบประสบการณ์ผู้ใช้ในปัจจุบัน การวิเคราะห์พฤติกรรมผู้ใช้ การยศาสตร์ การฝึกทักษะในการใช้เทคโนโลยีเพื่อการออกแบบประสบการณ์ผู้ใช้',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP351203',
                    'name_th' => 'การเขียนโปรแกรมเว็บและการประยุกต์ใช้',
                    'name_en' => 'Web Programming and Application',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การสร้างเว็บเพจด้วยภาษาแอชทีเอ็มแอลและภาษาจาวาสคริปต์ การจัดรูปแบบด้วยซีเอสเอส (CSS) การ เขียนโปรแกรมบนเว็บเซิร์ฟเวอร์ด้วยภาษาพี เอ็ช พี',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353001',
                    'name_th' => 'ระบบปฏิบัติการและการเขียนโปรแกรมซีสเต็มคอล',
                    'name_en' => 'Operating Systems and System Calls Programming',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ระบบปฏิบัติการขั้นแนะนา กระบวนการ การจัดกาหนดการซีพียู การประสานเวลากระบวนการ การติดตาม การจัดการหน่วยเก็บข้อมูล การจัดการหน่วยความจา หน่วยความจาเสมือน การจัดการหน่วยเก็บรอง ระบบแฟ้มและการอารักขา บทนาเครื่องจักรเสมือน แนวความคิดของเครื่องจักรเสมือน กรณีศึกษาของเครื่องจักรเสมือน การปฏิบัติเกี่ยวกับระบบปฏิบัติการ การเขียนโปรแกรมเชลล์ การเขียนโปรแกรมซีสเต็มคอล',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP351001',
                    'name_th' => 'วิทยาการคอมพิวเตอร์หลักมูล',
                    'name_en' => 'Fundamental Computer Science',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'องค์ประกอบระบบคอมพิวเตอร์พื้นฐานเบื้องต้น รหัสและการแทนข้อมูลในคอมพิวเตอร์ ระบบปฏิบัติการ ส่วนประกอบของระบบคอมพิวเตอร์ การประมวลผลข้อมูลระบบแฟ้มข้อมูลและระบบ ฐานข้อมูล หลักการเครือข่ายคอมพิวเตอร์และอินเทอร์เน็ต ความมั่นคงทางคอมพิวเตอร์ วิทยาการข้อมูลและ ปัญญาประดิษฐ์ เทคโนโลยีสารสนเทศและ กฎหมายคอมพิวเตอร์ เส้นทางอาชีพคอมพิวเตอร์และ คุณลักษณะบัณฑิต',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353201',
                    'name_th' => 'การประกันคุณภาพซอฟต์แวร์',
                    'name_en' => 'Software Quality Assurance',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'คุณภาพของซอฟต์แวร์ ตัวแบบคุณภาพของซอฟต์แวร์ การวัดคุณภาพซอฟต์แวร์และตัวชี้วัด การจัดการคุณภาพซอฟต์แวร์ กระบวนการประกันคุณภาพซอฟต์แวร์ การควบคุมคุณภาพซอฟต์แวร์ การทดสอบซอฟต์แวร์ การออกแบบการทดสอบ กลยุทธ์ในการทดสอบซอฟต์แวร์ การทดสอบซอฟต์แวร์แบบอัตโนมัติ การทดสอบซอฟต์แวร์แบบอไจล์ การพัฒนาซอฟต์แวร์ที่มีการทดสอบเป็นตัวนา การพัฒนาซอฟต์แวร์แบบบูรณาการและการส่งมอบซอฟต์แวร์อย่างต่อเนื่อง (CI/CD) การปรับปรุงกระบวนการพัฒนาซอฟต์แวร์ มาตรฐานและนโยบายที่เกี่ยวข้อง',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353108',
                    'name_th' => 'โครงข่ายประสาทและการเรียนรู้เชิงลึก',
                    'name_en' => 'Neural Network and Deep Learning',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ทฤษฎีและการประยุกต์ของการคำนวณแบบประสาทขั้นแนะนำ แบบการคำนวณแบบประสาท การจำแนกรูปแบบ การประมาณค่าของฟังก์ชัน ขั้นตอนวิธีการเรียนรู้ การเรียนรู้เชิงลึก',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353003',
                    'name_th' => 'ปัญญาประดิษฐ์',
                    'name_en' => 'Artificial Intelligence',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ภาษาการเขียนโปรแกรมลิสพ์ การแก้ปัญหาโดยการค้นหาคาตอบในปริภูมิสถานะโดยใช้ภาษาลิสพ์ เทคนิคปัญญาประดิษฐ์ในเกมส์ องค์ความรู้แบบฟัซซี เทคนิคการเรียนรู้ เทคนิควิธีการจาแนกและการจัดกลุ่ม การเรียนรู้เชิงลึก',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353102',
                    'name_th' => 'เครื่องจักรเรียนรู้ขั้นนำ',
                    'name_en' => 'Introduction to Machine Learning',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'แนวคิดและกลไกพื้นฐานของวิธีการเรียนรู้สาหรับเครื่องจักรเรียนรู้ที่รู้มีชื่อเสียงและประยุกต์ใช้แพร่หลาย วิธี เค-มีนส์ วิธีฟัซซี่-มีนส์ การจัดระเบียบตนเอง เครื่องจักรเรียนรู้เอ็กซ์ทรีม โครงข่ายประสาทเทียมแบบคอนโวลูชัน ตัวเข้ารหัสอัตโนมัติเชิงลึก เครื่องจักรบ็อลทซ์มันเชิงลึก เครื่องจักรเรียนรู้แบบต้นไม้ การส่งเสริม ป่าแบบสุ่ม เทคนิคการหาคาตอบที่หมาะสม การประยุกต์',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353103',
                    'name_th' => 'การวิเคราะห์และการประยุกต์ข้อมูล',
                    'name_en' => 'Data Analytics and Application',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ข้อมูล การเตรียมข้อมูล กระบวนการค้นพบความรู้ในการวิเคราะห์ข้อมูล การทาเหมืองข้อมูล การวิเคราะห์ข้อมูล เครื่องมือและภาษาที่ใช้ในการวิเคราะห์ข้อมูล การทาเหมืองกฎความสัมพันธ์ การจาแนกประเภทและการพยากรณ์ข้อมูล การจัดกลุ่มข้อมูล การสร้างภาพนามธรรม ความท้าทายสาหรับการวิเคราะห์ข้อมูลขนาดใหญ่ และการประยุกต์ใช้',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP352201',
                    'name_th' => 'เทคโนโลยีการออกแบบเว็บ',
                    'name_en' => 'Web Design Technologies',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'หลักการการออกแบบเว็บไซต์ การวางแผนการทาเว็บไซต์ การออกแบบเว็บไซต์ สภาวะแวดล้อมที่ควรคานึงถึงในการออกแบบเว็บ การเลือกใช้ตัวอักษร การออกแบบการจัดวางหน้าเว็บ การใช้กราฟิกและการใช้สี การออกแบบเนื้อหา การวางแผนระบบนาทางมัลติมีเดีย การพัฒนาเว็บไซต์ให้ทุกคนเข้าถึงได้ การออกแบบเว็บที่รองรับทุกอุปกรณ์ ภาษาเอชทีเอ็มแอลและแคสเคดดิ้งสไตล์ชีท',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP351002',
                    'name_th' => 'การเขียนโปรแกรมเชิงโครงสร้างสำหรับวิทยาการคอมพิวเตอร์',
                    'name_en' => 'Structure Programming Languages for Computer Science',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'แนะนำการเขียนโปรแกรมภาษาซี แนวคิดการเขียนโปรแกรมเชิงกระบวนคำสั่ง การสร้างผังงาน โครงสร้างโปรแกรม การคิดเชิงตรรกะ ชนิดข้อมูล การกำหนดตัวแปร ประโยคเงื่อนไข คำสั่งควบคุม การใช้ แถวลำดับและสายอักขระ ฟังก์ชัน ตัวชี้ โครงสร้าง การอ่านและเขียนไฟล์ คุณลักษณะบัณฑิต',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353761',
                    'name_th' => 'สัมมนาทางวิทยาการคอมพิวเตอร์',
                    'name_en' => 'Seminar in Computer Science',
                    'credits' => 1,
                    'weight' => '1-0-2',
                    'detail' => 'การทบทวนวรรณกรรม การอภิปราย การเขียนรายงานและการนาเสนอรายงานเกี่ยวกับเทคโนโลยีและความก้าวหน้าในสาขาวิชาวิทยาการคอมพิวเตอร์',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP353002',
                    'name_th' => 'หลักการออกแบบพัฒนาซอฟต์แวร์',
                    'name_en' => 'Principles of Software Design and Development',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การมองถึงการพัฒนาระบบเชิงวัตถุ แนวคิดเชิงวัตถุ ลักษณะประจา พฤติกรรมและระเบียบวิธี การห่อหุ้มและการซ่อนสารสนเทศ คลาส ภาวะรูปร่างหลายแบบ ความสัมพันธ์และความเกี่ยวข้องเชิงวัตถุ วงจรชีวิตการพัฒนาระบบเชิงวัตถุ ระเบียบวิธีการเชิงวัตถุ ภาษาการทาแบบจาลองให้เป็นหนึ่งเดียว การวิเคราะห์เชิงวัตถุ การออกแบบเชิงวัตถุ การออกแบบลวดลาย',
                    'cur_id' => 1,
                    'status' => 'A'
                ],
                // IT
                [
                    'subject_id' => 'SC361002',
                    'name_th' => 'การเขียนโปรแกรมเชิงโครงสร้างสำหรับเทคโนโลยีสารสนเทศ',
                    'name_en' => 'Structured Programming for Information Technology',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การเขียนโปรแกรมคอมพิวเตอร์ขั้นแนะนำ ผังงาน ซูโดโค้ด การแก้ปัญหา การคิดเชิงตรรกะ ชนิดข้อมูล ตัวแปร โครงสร้างควบคุม อาร์เรย์ ฟังก์ชัน การนำเข้า/การส่งออก ไฟล์',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362004',
                    'name_th' => 'การเขียนโปรแกรมประยุกต์บนเว็บ',
                    'name_en' => 'Web Application Programming',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การสร้างเว็บเพจด้วยภาษาแอชทีเอ็มแอลและภาษาจาวาสคริปต์ การจัดรูปแบบด้วยซีเอสเอส (CSS) เจคิวรี่ การเขียนโปรแกรมบนเว็บเซิร์ฟเวอร์ด้วยภาษาพี เอ็ช พี',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363762',
                    'name_th' => 'ระเบียบวิธีวิจัย',
                    'name_en' => 'Research Methodology',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'การศึกษาความหมายความสำคัญ วัตถุประสงค์ การตั้งคำถาม และประเภทของงานวิจัยกระบวนการวิจัยทางเทคโนโลยีสารสนเทศ ทบทวนวรรณกรรมและงานวิจัยที่เกี่ยวข้อง การเขียนข้อเสนอโครงการวิจัยสถิติเบื้องต้น การทดลอง การสรุปผลงานวิจัย การเขียนรายงาน การเขียนบทความวิจัย การนำเสนอ การอ้างอิงและมารยาทและจริยธรรมในการทำวิจัย',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => '362003',
                    'name_th' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ',
                    'name_en' => 'Introduction to Computer Networking',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ การคำนวณเลขฐาน เทคโนโลยีเครือข่ายคอมพิวเตอร์ แนะนำอินเทอร์เน็ต ระบบระดับชั้น ระดับชั้นกายภาพ สื่อตัวนำสัญญาณ ระดับชั้นเชื่อมโยงข้อมูล อีเทอร์เน็ต เครือข่ายเฉพาะที่ ระดับชั้นเครือข่าย การค้นหาเส้นทางเครือข่ายระดับชั้นทรานส์พอร์ต ตัวอย่างชั้นโปรแกรมประยุกต์',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => '342222',
                    'name_th' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ',
                    'name_en' => 'Introduction to Computer Networking',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ การคำนวณเลขฐาน เทคโนโลยีเครือข่ายคอมพิวเตอร์ แนะนำอินเทอร์เน็ต ระบบระดับชั้น ระดับชั้นกายภาพ สื่อตัวนำสัญญาณ ระดับชั้นเชื่อมโยงข้อมูล อีเทอร์เน็ต เครือข่ายเฉพาะที่ ระดับชั้นเครือข่าย การค้นหาเส้นทางเครือข่ายระดับชั้นทรานส์พอร์ต ตัวอย่างชั้นโปรแกรมประยุกต์',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362005',
                    'name_th' => 'การวิเคราะห์และออกแบบฐานข้อมูล',
                    'name_en' => 'Database Analysis and Design',
                    'credits' => 3,
                    'weight' => '2-2-3',
                    'detail' => 'แนวคิดระบบจัดการฐานข้อมูล รูปแบบข้อมูลและภาษา ทฤษฎีฐานข้อมูลเชิงสัมพันธ์ การออกแบบฐานข้อมูล การจัดระเบียบแฟ้ม การประมวลผลข้อคำถาม การควบคุมภาวะพร้อมกันการย้อนกลับและการกู้คืน บูรณภาพและความต้องกัน ทัศนะการทำให้เกิดผล ความมั่นคงของฐานข้อมูล เทคนิคการทำให้เกิดผลสำหรับระบบฐานข้อมูล',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363101',
                    'name_th' => 'การทดสอบซอฟต์แวร์และการประกันคุณภาพ',
                    'name_en' => 'Software Testing and Quality Assurance',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'แนะนำการทดสอบซอฟต์แวร์เบื้องต้นตัวอย่างปัญหาและการทดสอบซอฟต์แวร์โดยวิธีการทดสอบค่าขอบเขตวิธีการทดสอบแบบแบ่งกลุ่มคลาสเท่ากัน วิธีการทดสอบโดยใช้พื้นฐานตารางการตัดสินใจ ทบทวนบทบาทและหน้าที่ของการทดสอบซอฟต์แวร์ วิธีการทดสอบแบบมีเส้นทางวิธีการทดสอบกระแสงานของข้อมูล วิธีการทดสอบโครงสร้าง วิธีการทดสอบแบบบูรณาการหรือการรวมเข้าด้วยกัน วิธีการทดสอบแบบยอมรับและการสร้างข้อผิดพลาดให้ระบบ เครื่องมือในการทดสอบและการวางแผนการทดสอบในองค์กร',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363002',
                    'name_th' => 'การจัดการเชิงกลยุทธ์เทคโนโลยีสารสนเทศ',
                    'name_en' => 'Strategic Management of Information Technology',
                    'credits' => 3,
                    'weight' => '3-0-6	',
                    'detail' => 'ภาพรวมของการจัดการเทคโนโลยีสารสนเทศ การใช้เทคโนโลยีสารสนเทศและระบบสารสนเทศเชิงกลยุทธ์ในองค์กรบทบาทในเชิงกลยุทธ์ของระบบสารสนเทศและเทคโนโลยีสารสนเทศในองค์กร ความสัมพันธ์ระหว่างแผนกลยุทธ์ทางธุรกิจและกลยุทธ์เทคโนโลยีสารสนเทศการปรับกลยุทธ์เทคโนโลยีสารสนเทศให้เข้ากับกลยุทธ์ทางธุรกิจและองค์กรการวางแผนกลยุทธ์เทคโนโลยีสารสนเทศธรรมาภิบาลด้านเทคโนโลยีสารสนเทศ',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363102',
                    'name_th' => 'ระบบธุรกิจอัจฉริยะ',
                    'name_en' => 'Business Intelligence System',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'แนวคิดพื้นฐานเกี่ยวกับระบบธุรกิจอัจฉริยะ สถาปัตยกรรมของระบบธุรกิจอัจฉริยะ แนวคิดพื้นฐานเกี่ยวกับระบบสนับสนุนการตัดสินใจ คลังข้อมูลขั้นแนะนำ การออกแบบคลังข้อมูลการวิเคราะห์ข้อมูลทางธุรกิจเบื้องต้น รูปแบบของการวิเคราะห์ข้อมูล รายงานทางธุรกิจการนำเสนอข้อมูลโดยภาพ การประยุกต์ใช้ระบบธุรกิจอัจฉริยะ กรณีศึกษาต่าง ๆ ในการนำระบบธุรกิจอัจฉริยะมาใช้ในองค์กร',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362102',
                    'name_th' => 'วิศวกรรมซอฟต์แวร์',
                    'name_en' => 'Software Engineering',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ภาพรวมวิศวกรรมซอฟต์แวร์ แบบจำลองกระบวนการผลิตซอฟต์แวร์ วิศวกรรมระบบ การบริหารจัดการโครงการผลิตซอฟต์แวร์ วิศวกรรมความต้องการ แบบจำลองการวิเคราะห์แบบจำลองเชิงวัตถุ วิศวกรรมการออกแบบ การออกแบบส่วนประสานงานผู้ใช้ การทดสอบและบำรุงรักษาซอฟต์แวร์ การประมาณค่าใช้จ่ายซอฟต์แวร์ การจัดการความเสี่ยง',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363001',
                    'name_th' => 'การวิเคราะห์และออกแบบระบบ',
                    'name_en' => 'Systems Analysis and Design',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'พื้นฐานเกี่ยวกับเทคโนโลยีวัตถุและการพัฒนาระบบเชิงวัตถุ การพัฒนาข้อเสนอโครงการและการจัดการโครงการ การเก็บรวบรวมและการวิเคราะห์ความต้องการแบบจำลอง Use Case แบบจำลองลำดับเหตุการณ์ของระบบแบบจำลองโดเมน ทางเลือกสำหรับการพัฒนาระบบใหม่การออกแบบสถาปัตยกรรมแบบจำลองโต้ตอบ แบบจำลองคลาส การออกแบบคลาสโดยใช้แบบแผน GRASP การออกแบบฐานข้อมูลการพัฒนาซอฟต์แวร์ การติดตั้งและการบำรุงรักษาระบบ กรณีศึกษา: การพัฒนาระบบลงทะเบียนออนไลน์',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC361001',
                    'name_th' => 'ความบันดาลใจในวิชาชีพเทคโนโลยีสารสนเทศ',
                    'name_en' => 'Inspiration in IT Career',
                    'credits' => 1,
                    'weight' => '0-2-1',
                    'detail' => 'เกณฑ์มาตรฐานวิชาชีพคอมพิวเตอร์และจรรยาบรรณวิชาชีพ เส้นทางอาชีพ ความก้าวหน้าของนักไอที การร่วมอภิปรายด้วยกรณีศึกษาการประยุกต์ไอทีในองค์กร ก่อนที่จะอธิบายโครงสร้างหลักสูตรและโปรแกรมการศึกษา',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362001',
                    'name_th' => 'ระบบปฏิบัติการ',
                    'name_en' => 'Operating Systems',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => ' ระบบปฏิบัติการขั้นแนะนำ การจัดการกระบวนการ กระบวนการ การจัดกำหนดการซีพียู การประสานเวลากระบวนการ การติดตาย การจัดการหน่วยเก็บข้อมูล การจัดการหน่วยความจำ หน่วยความจำเสมือน การจัดการหน่วยเก็บรองระบบแฟ้มและการอารักขา ระบบแบบกระจายขั้นแนะนำ และการปฏิบัติบนระบบปฏิบัติการ',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP363205',
                    'name_th' => 'ภาษาอนุกรมข้อมูลและการประยุกต์',
                    'name_en' => 'Data Serialization Languages and Applications',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'แนะนำภาษาอนุกรมข้อมูล (XML JSON และ YAML) การทำความเข้าใจโครงสร้างของภาษาอนุกรมข้อมูล การออกแบบและการใช้ภาษาอนุกรมข้อมูล การใช้ภาษาอนุกรมข้อมูลใน NoSQL หรือฐานข้อมูลที่ไม่ใช่เชิงสัมพันธ์ การจัดการภาษาอนุกรมข้อมูลด้วยภาษาทางเว็บ',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC320001',
                    'name_th' => 'การเขียนโปรแกรมประยุกต์บนเว็บ',
                    'name_en' => 'Introduction to information and Communication Technology',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'ระบบคอมพิวเตอร์ ฮาร์ดแวร์และซอฟต์แวร์ การเก็บรวบรวมข้อมูล การวิเคราะห์และการประมวลผลข้อมูล โครงสร้างของระบบประมวลผลข้อมูล การจัดระเบียบแฟ้มข้อมูลแบบต่างๆ หลักการเกี่ยวกับการเครือข่ายคอมพิวเตอร์ วิธีการเขียนโปรแกรมเบื้องต้น การเขียนผังงาน',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362201',
                    'name_th' => 'เทคโนโลยีการออกแบบเว็บ',
                    'name_en' => 'Web Design Technologies',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'หลักการการออกแบบเว็บไซต์ การวางแผนการทำเว็บไซต์ การออกแบบเว็บไซต์ สภาวะแวดล้อมที่ควรคำนึงถึงในการออกแบบเว็บ การเลือกใช้ตัวอักษร การออกแบบการจัดวางหน้าเว็บ การใช้กราฟิกและการใช้สีการออกแบบเนื้อหา การวางแผนระบบนำทาง มัลติมีเดีย การพัฒนาเว็บไซต์ให้ทุกคนเข้าถึงได้ การออกแบบเว็บที่รองรับทุกอุปกรณ์ ภาษาเอชทีเอ็มแอล และแคสเคดดิ้งสไตล์ชีท',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC362301',
                    'name_th' => 'ตรรกะดิจิทัลและระบบฝังตัว',
                    'name_en' => 'Digital Logic and Embedded Systems',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'วงจรดิจิทัลอิเล็กทรอนิกส์ ประตูตรรกะและพีชคณิตแบบบูลีน การลดรูปฟังก์ชันตรรกะให้ง่าย การเข้ารหัสและการถอดรหัส ฟลิปฟลอป วงจรตรรกะเชิงลำดับ วงจรคำนวณทางคณิตศาสตร์การอินเตอร์เฟสแบบอนุกรมและแบบขนาน การแปลงอนาล็อกไปเป็นดิจิทัลและดิจิทัลไปเป็นอนาล็อก สถาปัตยกรรมระบบฝังตัว การเขียนโปรแกรมระบบฝังตัว',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'SC363003',
                    'name_th' => 'วิชาชีพเทคโนโลยีสารสนเทศ',
                    'name_en' => 'Information Technology Profession',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'กิจการองค์กรและกฎหมายกลยุทธ์ทางธุรกิจกลยุทธ์ระบบการพัฒนาเทคโนโลยีการจัดการโครงการการจัดการบริการทฤษฎีพื้นฐานของเทคโนโลยีระบบคอมพิวเตอร์องค์ประกอบทางด้านเทคนิค',
                    'cur_id' => 2,
                    'status' => 'A'
                ],
                
                // GIS
                [
                    'subject_id' => 'CP350002',
                    'name_th' => 'โครงสร้างข้อมูล',
                    'name_en' => 'Data Structure',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'หลักมูลของขั้นตอนวิธี โครงสร้างข้อมูลแบบเชิงเส้น แถวลาดับ เรียงทับซ้อน แถวคอย แถวคอยสองด้าน รายการโยง รายการวง รายการโยงคู่ รายการหลายตัวโยง โครงสร้างข้อมูลไม่เชิงเส้น โครงสร้างรูปต้นไม้และกราฟ เทคนิคการเรียงลาดับและการค้นหาข้อมูล',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP371031',
                    'name_th' => 'เทคโนโลยีสารสนเทศขั้นพื้นฐาน',
                    'name_en' => 'Basics of Information Technology',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ระบบคอมพิวเตอร์เบื้องต้นรวมไปถึงการท างานของคอมพิวเตอร์ฮาร์ดแวร์และซอฟต์แวร์แต่ละ ประเภท การจัดเก็บ วิเคราะห์ และการประมวลผลข้อมูล แนวคิดของข้อมูลเชิงพื้นที่ หลักการของเลขฐาน และการแทนค่าภาษาเครื่อง แนวคิดของเครือข่ายคอมพิวเตอร์ วิธีการเขียนโปรแกรมคอมพิวเตอร์เบื้องต้น และการเขียนผังงาน หลักการของฐานข้อมูล ระบบปฏิบัติการและฝึกหัดโปรแกรมประยุกต์ที่จ าเป็นและ อรรถประโยชน์อื่นๆ',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP372002',
                    'name_th' => 'ระบบสารสนเทศภูมิศาสตร์ขั้นแนะนำ',
                    'name_en' => 'Introduction to Geographic Information System',
                    'credits' => 3,
                    'weight' => '2-3-6',
                    'detail' => 'นิยามและองค์ประกอบของระบบสารสนเทศภูมิศาสตร์ พื้นฐานทางแผนที่ เส้นโครงแผนที่และระบบพิกัดอ้างอิง ข้อมูลเวกเตอร์และแรสเตอร์ การปรับแก้ความคลาดเคลื่อนทางเรขาคณิต การนาเข้าและจัดการข้อมูลเชิงพื้นที่และข้อมูลคุณลักษณะ การแสดงข้อมูล การจัดทาแผนที่ การวิเคราะห์ข้อมูลเวกเตอร์และแรสเตอร์ การประมาณค่าเชิงพื้นที่และแบบจาลองความสูงเชิงเลข แบบจาลองและการประกอบแบบจาลองทางระบบสารสนเทศทางภูมิศาสตร์ การวิเคราะห์ข้อมูลเชิงพื้นที่และการตรวจสอบความถูกต้อง การใช้งานซอฟต์แวร์ด้านระบบสารสนเทศภูมิศาสตร์ พจนานุกรมข้อมูลและข้อมูลอภิพันธุ์',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP372011',
                    'name_th' => 'หลักมูลการรับรู้จากระยะไกล',
                    'name_en' => 'Fundamentals of Remote Sensing',
                    'credits' => 3,
                    'weight' => '2-3-6',
                    'detail' => 'นิยามและแนวคิดของการรับรู้จากระยะไกล แหล่งพลังงาน การสะท้อนคลื่นแม่เหล็กไฟฟ้า การสะท้อนของวัตถุ วิวัฒนาการของการรับรู้จากระยะไกล ดาวเทียมสารวจทรัพยากรธรรมชาติ เครื่องรับรู้หลายช่วงคลื่น หลักการและองค์ประกอบการแปลตีความภาพด้วยสายตาและกระบวนการประมวลผลภาพเชิงเลข การประยุกต์การรับรู้จากระยะไกลในการจัดการทรัพยากรธรรมชาติและสิ่งแวดล้อม สังคมและเศรษฐกิจ',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP373232',
                    'name_th' => 'การจัดการฐานข้อมูลเชิงพื้นที่',
                    'name_en' => 'Spatial Database Management',
                    'credits' => 3,
                    'weight' => '2-3-6',
                    'detail' => 'ฐานข้อมูลเชิงพื้นที่ขั้นแนะนา พื้นฐานของฐานข้อมูลเชิงสัมพันธ์และ NoSQL สาหรับข้อมูลเชิงพื้นที่ เงื่อนไขบังคับและความคงสภาพของข้อมูล มาตรฐานและประเภทของข้อมูลเชิงพื้นที่และการวิเคราะห์เชิงพื้นที่ แบบจาลองเชิงตรรกะและภาษาในการสืบค้นข้อมูลเชิงพื้นที่ กระบวนการสร้าง สืบค้น และ แก้ไขข้อมูลเชิงพื้นที่ การออกแบบฐานข้อมูลเชิงพื้นที่ การประยุกต์ใช้ฐานข้อมูลเชิงพื้นที่กับแผนที่บนเว็บและโปรแกรมประยุกต์ ข้อมูลภูมิสารสนเทศพื้นฐานของประเทศ',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP373112',
                    'name_th' => 'การทำแผนที่ด้วยอากาศยานไร้คนขับ',
                    'name_en' => 'Unmanned Aerial Vehicle Mapping',
                    'credits' => 3,
                    'weight' => '2-3-6',
                    'detail' => 'การวางแผนและควบคุมการบินอากาศยานไร้คนขับเพื่อการทาแผนที่ การประมวลผลข้อมูลภาพถ่ายเพื่อจัดทาแผนที่หรือแบบจาลอง 3 มิติ จุดควบคุม โฟโตแกรมเมตรี การวิเคราะห์ข้อมูลภาพถ่ายทางอากาศรวมไปถึงการสกัดและจาแนกวัตถุหรือข้อมูลเชิงพื้นที่อัตโนมัติ การประยุกต์ใช้อากาศยานไร้คนขับในงานสารวจประเภทต่าง ๆ ประเด็นเรื่องกฎหมายและข้อบังคับในการใช้อากาศยานไร้คนขับ',
                    'cur_id' => 3,
                    'status' => 'A'
                ],
                // CY
                
                [
                    'subject_id' => 'CP422011',
                    'name_th' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ',
                    'name_en' => 'Introduction to Computer Networking',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'เครือข่ายคอมพิวเตอร์ขั้นแนะนำ การคำนวณเลขฐาน เทคโนโลยีเครือข่ายคอมพิวเตอร์ แนะนำอินเทอร์เน็ต ระบบระดับชั้น ระดับชั้นกายภาพ สื่อตัวนำสัญญาณ ระดับชั้นเชื่อมโยงข้อมูล อีเทอร์เน็ต เครือข่ายเฉพาะที่ ระดับชั้นเครือข่าย การค้นหาเส้นทางเครือข่าย ระดับชั้นทรานส์พอร์ต ตัวอย่างชั้นโปรแกรมประยุกต์ การฝึกปฏิบัติการเครือข่ายคอมพิวเตอร์ขั้นแนะนำ',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP421025',
                    'name_th' => 'การวิเคราะห์และออกแบบซอฟต์แวร์',
                    'name_en' => 'Software Design and Analysis',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'พื้นฐานเกี่ยวกับเทคโนโลยีวัตถุและการพัฒนาระบบเชิงวัตถุ การพัฒนาข้อเสนอโครงการและการจัดการโครงการ การเก็บรวบรวมและการวิเคราะห์ความต้องการแบบจำลอง Use Case แบบจำลองลำดับเหตุการณ์ของระบบแบบจำลองโดเมน ทางเลือกสำหรับการพัฒนาระบบใหม่ การออกแบบสถาปัตยกรรมแบบจำลองโต้ตอบ แบบจำลองคลาส การออกแบบคลาสโดยใช้แบบแผน GRASP การออกแบบฐานข้อมูลการพัฒนาซอฟต์แวร์ การติดตั้งและการบำรุงรักษาระบบ กรณีศึกษา: การพัฒนาระบบลงทะเบียนออนไลน์ การฝึกปฏิบัติการการวิเคราะห์และออกแบบซอฟต์แวร์',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP422022',
                    'name_th' => 'การวิเคราะห์และออกแบบสถาปัตยกรรมฐานข้อมูล',
                    'name_en' => 'Database Architecture Analysis and Design',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => ' แนวคิดระบบจัดการฐานข้อมูล รูปแบบข้อมูลและภาษา ทฤษฎีฐานข้อมูลเชิงสัมพันธ์ การออกแบบฐานข้อมูล การจัดระเบียบแฟ้ม การประมวลผลข้อคำถาม การควบคุมภาวะพร้อมกัน การย้อนกลับและการกู้คืน บูรณภาพและความต้องกัน ทัศนะการทำให้เกิดผล ความมั่นคงของฐานข้อมูล เทคนิคการทำให้เกิดผลสำหรับระบบฐานข้อมูล การฝึกปฏิบัติการการวิเคราะห์และออกแบบสถาปัตยกรรมฐานข้อมูล',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP422001',
                    'name_th' => 'กฏหมายและจริยธรรมด้านความมั่นคงปลอดภัยไซเบอร์',
                    'name_en' => 'Cybersecurity Law and Ethic',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'ความรู้เบื้องต้นเกี่ยวกับกฎหมายอาญา ตามประมวลกฎหมายอาญาว่าด้วยหลักการทั่วไป เช่น ตัวการ ผู้ใช้ ผู้สนับสนุน ภาคความผิดที่เกี่ยวข้องกับความรับผิดที่เกี่ยวกับกฎหมายคอมพิวเตอร์ พระราชบัญญัติว่าด้วยการกระทำความผิดเกี่ยวกับคอมพิวเตอร์ พระราชบัญญัติธุรกรรมทางอิเล็กทรอนิกส์ รวมถึงประกาศของหน่วยงานรัฐบาลที่เกี่ยวข้อง รวมทั้งจริยธรรมในการปฏิบัติการทางไซเบอร์',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP422052',
                    'name_th' => 'ระบบปฏิบัติการขั้นแนะนำ',
                    'name_en' => 'Introduction to Operating System',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ระบบปฏิบัติการขั้นแนะนำ การจัดการกระบวนการ กระบวนการ การจัดกำหนดการซีพียู การประสานเวลากระบวนการ การติดตาย การจัดการหน่วยเก็บข้อมูล การจัดการหน่วยความจำ หน่วยความจำเสมือน การจัดการหน่วยเก็บรอง ระบบแฟ้มและการอารักขา ระบบแบบกระจายขั้นแนะนำ และการปฏิบัติบนระบบปฏิบัติการ การฝึกปฏิบัติการระบบปฏิบัติการขั้นแนะนำ',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP421011',
                    'name_th' => 'ความบันดาลใจในวิชาชีพความมั่นคงปลอดภัยไซเบอร์',
                    'name_en' => 'Inspiration in Cybersecurity Career',
                    'credits' => 1,
                    'weight' => '0-2-1',
                    'detail' => 'การสร้างแรงบันดาลใจในวิชาชีพ เส้นทางอาชีพ ความก้าวหน้าของนักไอที การร่วมอภิปรายด้วยกรณีศึกษาการประยุกต์ไอทีในองค์กร ก่อนที่จะอธิบายโครงสร้างหลักสูตรและโปรแกรมการศึกษา',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP422021',
                    'name_th' => 'สถาปัตยกรรมเว็บและโมบายแอปพลิเคชัน',
                    'name_en' => 'Web and Mobile Application Architecture',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การพัฒนาโปรแกรมประยุกต์สำหรับอุปกรณ์โทรศัพท์ แทปเล็ต และ อุปกรณ์เคลื่อนที่อื่นๆ ที่ใช้การออกแบบส่วนต่อประสานกับผู้ใช้งาน โดยใช้เว็บเทคโนโลยีและโปรแกรมภาษาคอมพิวเตอร์ และส่วนต่อประสานโปรแกรมประยุกต์ เพื่อเข้าถึงการวางแนวอุปกรณ์ การจัดการการสัมผัสหน้าจอ แหล่งเก็บข้อมูลท้องถิ่น การเข้าถึงกล้องและการระบุตำแหน่ง',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP421021',
                    'name_th' => 'การเขียนโปรแกรมเชิงโครงสร้าง',
                    'name_en' => 'Structured Programming',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'การเขียนโปรแกรมคอมพิวเตอร์ขั้นแนะนำ ผังงาน ซูโดโค้ด การแก้ปัญหา การคิดเชิงตรรกะ ชนิดข้อมูล ตัวแปร โครงสร้างควบคุม อาร์เรย์ ฟังก์ชัน การนำเข้า/การส่งออก ไฟล์ การฝึกปฏิบัติการเขียนโปรแกรมเชิงโครงสร้าง',
                    'cur_id' => 5,
                    'status' => 'A'
                ],
                
                // AI
                
                [
                    'subject_id' => 'CP411106',
                    'name_th' => 'การเขียนโปรแกรมสำหรับการเรียนรู้ของเครื่อง',
                    'name_en' => 'Programming for Machine Learning',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'หลักการการเขียนโปรแกรมคอมพิวเตอร์ขั้นพื้นฐาน ผังงาน อัลกอริทึม การคิดเชิงตรรก ชนิดข้อมูล ตัวดำเนินการและนิพจน์ คำสั่งการควบคุมสายงาน อาเรย์ ฟังก์ชัน การนำเข้าและแสดงผล การจัดการไฟล์ การแก้จุดบกพร่อง การจัดการความผิดปรกติ แนวคิดการเขียนโปรแกรมเชิงวัตถุ การเขียนโปรแกรมเชิงเหตุการณ์ แนวคิดการเขียนโปรแกรมเชิงฟังก์ชัน การฝึกเขียนโปรแกรมสำหรับการเรียนรู้ของเครื่อง',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP411701',
                    'name_th' => 'แรงบันดาลใจของปัญญาประดิษฐ์',
                    'name_en' => 'Artificial Intelligence Inspiration',
                    'credits' => 2,
                    'weight' => '2-0-4',
                    'detail' => 'ประวัติศาสตร์ปัญญาประดิษฐ์ มาตรฐานปัญญาประดิษฐ์ ปัญญาประดิษฐ์และวิทยาการข้อมูล การประยุกต์ปัญญาประดิษฐ์ จรรยาบรรณปัญญาประดิษฐ์ ความท้าทายในปัญญาประดิษฐ์ กรณีศึกษาปัญญาประดิษฐ์ เส้นทางอาชีพปัญญาประดิษฐ์',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP412703',
                    'name_th' => 'การฝึกปฏิบัติงานปัญญาประดิษฐ์ 1',
                    'name_en' => 'Artificial Intelligence Workshop I',
                    'credits' => 1,
                    'weight' => '0-2-1',
                    'detail' => 'การให้ประสบการณ์ในการออกแบบและใช้งานปัญญาประดิษฐ์เพื่อแก้ปัญหาในโลกแห่งความเป็นจริง ครอบคลุมแนวคิดในปัญญาประดิษฐ์ เช่น การค้นหาในปริภูมิสถานะ การวางแผน ปัญหาความพึงพอใจจากข้อจำกัด การเขียนโปรแกรมเชิงตรรกะ การแทนองค์ความรู้',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP412002',
                    'name_th' => 'การเรียนรู้ของเครื่อง',
                    'name_en' => 'Machine Learning',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'แนวคิดและกลไกพื้นฐานของวิธีการเรียนรู้ของเครื่องจักรที่มีชื่อเสียงและมีการประยุกต์ใช้อย่างแพร่หลาย เทคนิคการหาคำตอบที่หมาะสม การเรียนรู้ของเครื่องจักรแบบไม่มีผู้สอน วิธี เค-มีนส์ วิธีฟัซซี่-มีนส์ การจัดระเบียบตนเอง ดัชนีความสมเหตุสมผลของการจัดกลุ่ม การเรียนรู้ของเครื่องจักรแบบมีผู้สอน ฟังก์ชันสูญเสีย ขั้นตอนวิธีการเรียนรู้ ตัวแบบการถดถอย การถดถอยโลจิสติค เครื่องจักรเรียนรู้แบบต้นไม้ การเรียนรู้ของเครื่องแบบเอ็กซ์ทรีม ซัพพอร์ตเวกเตอร์แมชชิน โครงข่ายประสาทเทียม การส่งเสริม ป่าแบบสุ่ม การเลือกและประเมินผลตัวแบบ การเลือกคุณลักษณะ การเรียนรู้ของเครื่องจักรแบบเสริมกำลัง การเรียนรู้แบบคิว กระบวนการตัดสินใจมาร์คอฟ',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP412003',
                    'name_th' => 'วิทยาศาสตร์ข้อมูล',
                    'name_en' => 'Data Science',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ความรู้เบื้องต้นเกี่ยวกับวิทยาศาสตร์ข้อมูล การจัดเก็บข้อมูลในขนาดต่าง ๆ การวิเคราะห์ข้อมูล เหมืองข้อมูล การสื่อสารผลลัพธ์ การตรวจสอบความผิดพลาดในการกรอกข้อมูล ค่าสูญหาย ค่าสุดโต่ง การตรวจสอบแจกแจงของ ข้อมูลและการแปลงไม่เชิงเส้น การตรวจสอบความแปรปรวนของข้อมูล ภาพนิทัศน์สำหรับข้อมูล การประยุกต์ใช้การเรียนรู้เครื่องในงานวิทยาศาสตร์ข้อมูล การสื่อสารและอธิบายผลลัพธ์ของงานวิทยาศาสตร์ข้อมูล',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP412004',
                    'name_th' => 'เครือข่ายประสาทเทียมและการเรียนรู้เชิงลึก',
                    'name_en' => 'Neural Network and Deep Learning	',
                    'credits' => 3,
                    'weight' => '2-2-5',
                    'detail' => 'ทฤษฎีและการประยุกต์ของการคำนวณแบบประสาท สถาปัตยกรรมโครงข่ายประสาทเทียม ขั้นตอนวิธีการเรียนรู้ การฝึกแบบกระจาย ฟังก์ชันกระตุ้นและฟังก์ชันความสูญเสีย ความเอนเอียงและความแปรปรวน การทำให้เป็นมาตรฐาน การเรียนรู้เชิงลึก โครงข่ายประสาทแบบคอนโวลูชัน โครงข่ายประสาทแบบวนซ้ำ โครงข่ายประสาทก่อกำเนิดแบบมีคู่ปรปักษ์ ตัวเข้ารหัสอัตโนมัติ ตัวแปลง ตัวแบบเรียนรู้แบบเสริมกำลัง การถ่ายโอนองค์ความรู้',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                [
                    'subject_id' => 'CP411107',
                    'name_th' => 'โครงสร้างข้อมูลและขั้นตอนวิธี',
                    'name_en' => 'Data Structures and Algorithms',
                    'credits' => 3,
                    'weight' => '3-0-6',
                    'detail' => 'แนะนำการวิเคราะห์และออกแบบขั้นตอนวิธี การวิเคราะห์ความซับซ้อนของขั้นตอน ขั้นตอนวิธีที่มีประสิทธิภาพสำหรับการเรียง การค้นหา การเลือก โครงข้อมูลสำหรับการค้นหาแบบต้นไม้แบ่งครึ่ง แบบฮีฟ แบบตารางแฮช เทคนิคการออกแบบขั้นตอนวิธีแบบแตกเพื่อชนะ แบบไดนามิคโปรแกรมมิ่ง แบบละโมบ แบบวิเคราะห์ถั่วเฉลี่ย แบบการสุ่ม ขั้นตอนวิธีต้นไม้แบบขยายต้นทุนต่ำสุด แบบวิธีชิ้นส่วนเชื่อมต่อ แบบการเรียงเชิงภูมิลักษณะ การหาเส้นทางที่สั้นที่สุด การแก้ปัญหาเชิงคำนวณด้วยใช้ขั้นตอนวิธี แนะนำโครงสร้างข้อมูล ตัววัดสมรรถนะขั้นตอนวิธี ลิสต์ อาร์เรย์ รายการโยง สแต็ค คิว การเรียง การค้นหา โครงสร้างต้นไม้ และกราฟ การค้นหาแบบไบนารี แบบฮีฟ การแทนกราฟ การท่องและการหาเส้นทาง',
                    'cur_id' => 4,
                    'status' => 'A'
                ],
                
                // KKBS
                // [
                //     'subject_id' => 'CP020002',
                //     'name_th' => 'การจัดการกระบวนการอย่างอัจฉริยะ',
                //     'name_en' => 'Smart Process Management',
                //     'credits' => 3,
                //     'weight' => '2-2-5',
                //     'detail' => 'แนวคิดระบบจัดการฐานข้อมูล รูปแบบข้อมูลและภาษา การออกแบบฐานข้อมูล ภาษา เอสคิวแอล ภาษาโนเอสคิวแอล การปรับใช้โมเดล พาวเวอร์ บีไอ การจัดการโครงการ บริบทของการ จัดการโครงการและเทคโนโลยีสารสนเทศ ศึกษาแนวความคิดและวิธีการจัดการกระบวนการทางธุรกิจ การทำโมเดลกระบวนการ การใช้เทคโนโลยีสารสนเทศกับการจัดการปรับปรุงกระบวนการ กรณีศึกษา ของการบริหารกระบวนการธุรกิจสาธิตโดยใช้ระบบ ERP แนวคิดพื้นฐานเกี่ยวกับระบบธุรกิจอัจฉริยะ สถาปัตยกรรมของระบบธุรกิจอัจฉริยะ แนวคิดพื้นฐานเกี่ยวกับระบบสนับสนุนการตัดสินใจ การประยุกต์ ใช้ระบบธุรกิจอัจฉริยะ กรณีศึกษาต่าง ๆ ในการนำระบบธุรกิจอัจฉริยะมาใช้ในองค์กร',
                //     'cur_id' => 1,
                //     'status' => 'A'
                // ],
                // เพิ่มข้อมูลอื่น ๆ ที่คุณต้องการ
            ];
            foreach ($subjects as $subject) {
                Subjects::create($subject);
            }
        // } else {
        //     $this->command->info('No curriculum found. Please run CurriculumSeeder first.');
        // }
    }
}
