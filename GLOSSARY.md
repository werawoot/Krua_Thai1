# Krua Thai

บริการ meal subscription อาหารไทยใน Orange County, CA: ลูกค้าสมัครรับอาหารรายสัปดาห์ ตัดเงินอัตโนมัติ และมีไรเดอร์ส่งถึงบ้าน

## Language

### ผลิตภัณฑ์

**Plan**:
แพ็กที่กำหนดจำนวน Meal ต่อสัปดาห์และราคาต่อสัปดาห์ ต่ออายุไปเรื่อยๆ จนกว่าจะ Cancel ไม่มีระยะเวลาสิ้นสุด
_Avoid_: Package, subscription plan

**Meal**:
อาหาร 1 จานในแคตตาล็อกของร้าน
_Avoid_: Menu, dish, item

**Weekly Menu**:
ชุด Meal ที่เปิดให้ Customer เลือกในสัปดาห์หนึ่ง
_Avoid_: Menu (เมื่อหมายถึงจานเดียว)

### คนในระบบ

**Customer**:
คนที่มี Subscription
_Avoid_: User, member

**Rider**:
คนส่ง Order ถึงบ้าน Customer
_Avoid_: Driver, courier

**Kitchen Staff**:
คนทำ Meal ตาม Order ของแต่ละ Delivery Day
_Avoid_: Chef, cook

**Admin**:
ผู้ดูแลร้าน จัดการ Plan, Weekly Menu, Subscription และมอบงานให้ Rider
_Avoid_: Support, staff

### การส่ง

**Delivery Day**:
วันที่ร้านไปส่ง (พุธ, เสาร์) Customer เลือกได้หนึ่งหรือสองวันต่อสัปดาห์ และ Meal ใน Plan จะแบ่งตามวันที่เลือก
_Avoid_: Delivery date, ship day

**Delivery Zone**:
ชุดรหัส ZIP ใน Orange County ที่ร้านส่งถึง ZIP ที่อยู่นอก Zone สมัคร Subscription ไม่ได้
_Avoid_: Area, region

### เงิน

**Weekly Charge**:
การตัดเงินผ่าน Stripe สัปดาห์ละครั้งที่เวลา Cutoff จ่ายค่า Order ทุกใบของสัปดาห์ถัดไป ถ้าตัดเงินไม่ผ่านจะไม่สร้าง Order
_Avoid_: Payment, bill

### วงจรของ Order

**Order status**:
Scheduled → Preparing → Ready (Kitchen Staff เป็นคนกด) → Out for Delivery → Delivered หรือ Failed (Rider เป็นคนกด)
_Avoid_: In kitchen, completed

**Proof of Delivery**:
รูปถ่ายที่ Rider ต้องแนบทุกครั้งตอนเปลี่ยน Order เป็น Delivered
_Avoid_: Delivery photo

### วงจรของ Subscription

**Subscription**:
สัญญาระหว่างลูกค้ากับร้าน ระบุ Plan, วันส่ง และการตัดเงินอัตโนมัติรายสัปดาห์ จะ active, paused หรือ cancelled
_Avoid_: Order, membership

**Order**:
การส่ง 1 ครั้งใน 1 วันส่ง ที่สร้างจาก Subscription มีเมนูของรอบนั้น, status, ไรเดอร์ และรูปหลักฐานการส่งเป็นของตัวเอง
_Avoid_: Subscription, delivery

**Cutoff**:
เส้นตายของแต่ละสัปดาห์ที่ลูกค้าต้องเลือกเมนูหรือ Skip ให้เสร็จก่อน หลังจากนั้นระบบจะเลือกเมนูให้และตัดเงิน

**Skip**:
การที่ลูกค้าข้ามสัปดาห์หนึ่งก่อน Cutoff ทำให้ไม่มี Order และไม่ถูกตัดเงินในสัปดาห์นั้น โดย Subscription ยัง active
_Avoid_: Pause

**Pause**:
การหยุด Subscription ชั่วคราวแบบไม่มีกำหนด ไม่มี Order และไม่ถูกตัดเงินจนกว่าจะกลับมาใช้
_Avoid_: Skip, hold

**Cancel**:
การจบ Subscription ถาวร ถ้าจะกลับมาต้องสมัครใหม่
_Avoid_: Renew (การทำให้ Subscription ที่ Cancel แล้วกลับมาใช้ได้ไม่มีอยู่ในธุรกิจนี้)
